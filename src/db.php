<?php
/**
 * MongoDB access, on the PECL driver alone.
 *
 * NO COMPOSER, ON PURPOSE. This runs on whatever nginx or Apache box a unit
 * already has: install php-mongodb, drop the folder in the web root, done.
 * The high-level mongodb/mongodb library is nicer to read and is one more
 * thing to install and keep current on a machine nobody logs into.
 *
 * EVERY WRITE BACKS UP FIRST. The PAC store has been lost twice to a write
 * that looked harmless, so ghostd_put() copies the document it is about to
 * change into the backup collection before it changes it. That copy is what
 * makes a mistake on this site survivable.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;

const GHOSTD_TYPEMAP = [
    'root'     => 'array',
    'document' => 'array',
    'array'    => 'array',
];

function ghostd_manager(): Manager
{
    static $m = null;
    if ($m === null) {
        $cfg = ghostd_config();
        if ($cfg['mongo_uri'] === '') {
            throw new RuntimeException(
                'No connection string. Set GHOSTD_MONGO in the web server environment, '
                . 'or copy src/config.local.example.php to src/config.local.php and fill it in.'
            );
        }
        $m = new Manager($cfg['mongo_uri']);
    }
    return $m;
}

function ghostd_ns(?string $collection = null): string
{
    $cfg = ghostd_config();
    return $cfg['database'] . '.' . ($collection ?? $cfg['collection']);
}

/** True when the database answers. Used by the dashboard so a bad string is a message, not a stack trace. */
function ghostd_ping(): array
{
    try {
        $cur = ghostd_manager()->executeCommand(
            ghostd_config()['database'],
            new Command(['ping' => 1])
        );
        $cur->setTypeMap(GHOSTD_TYPEMAP);
        $r = current($cur->toArray());
        return [true, isset($r['ok']) ? 'ok' : 'answered'];
    } catch (Throwable $e) {
        return [false, $e->getMessage()];
    }
}

/** Every document id, sorted. */
function ghostd_keys(): array
{
    $cur = ghostd_manager()->executeQuery(
        ghostd_ns(),
        new Query([], ['projection' => ['_id' => 1], 'sort' => ['_id' => 1]])
    );
    $cur->setTypeMap(GHOSTD_TYPEMAP);
    $out = [];
    foreach ($cur as $doc) {
        $out[] = (string) $doc['_id'];
    }
    return $out;
}

/** One document as a plain array, or null. */
function ghostd_get(string $id): ?array
{
    $cur = ghostd_manager()->executeQuery(ghostd_ns(), new Query(['_id' => $id], ['limit' => 1]));
    $cur->setTypeMap(GHOSTD_TYPEMAP);
    $rows = $cur->toArray();
    return $rows === [] ? null : $rows[0];
}

/**
 * Replace a document's fields, keeping _id, after copying the current version
 * into the backup collection.
 *
 * $fields is the whole document minus _id. Anything not in it is REMOVED - this
 * is a replace, which is what a JSON editor means when it says save.
 */
function ghostd_put(string $id, array $fields): array
{
    unset($fields['_id']);

    $cfg = ghostd_config();
    $current = ghostd_get($id);

    if ($current !== null && $cfg['backup_collection'] !== '') {
        $copy = $current;
        unset($copy['_id']);
        $bulkB = new BulkWrite();
        $bulkB->insert([
            'sourceId'   => $id,
            'backedUpAt' => gmdate('Y-m-d H:i:s'),
            'by'         => 'DIVINER_Web',
            'document'   => $copy,
        ]);
        ghostd_manager()->executeBulkWrite(ghostd_ns($cfg['backup_collection']), $bulkB);
    }

    $fields['updatedAt'] = gmdate('Y-m-d H:i:s');

    $bulk = new BulkWrite();
    $bulk->update(['_id' => $id], ['$set' => $fields], ['upsert' => true]);
    // A replace has to drop what the editor deleted, so keys the new document
    // does not carry are unset explicitly. $set alone would silently keep them.
    if ($current !== null) {
        $gone = array_diff(array_keys($current), array_keys($fields), ['_id']);
        if ($gone !== []) {
            $unset = [];
            foreach ($gone as $k) {
                $unset[$k] = '';
            }
            $bulk->update(['_id' => $id], ['$unset' => $unset]);
        }
    }

    $res = ghostd_manager()->executeBulkWrite(ghostd_ns(), $bulk);
    return [
        'matched'  => $res->getMatchedCount(),
        'modified' => $res->getModifiedCount(),
        'upserted' => count($res->getUpsertedIds()),
    ];
}

/** Set one dotted path inside a document, backing the document up first. */
function ghostd_set_path(string $id, string $path, $value): array
{
    $current = ghostd_get($id);
    $cfg = ghostd_config();

    if ($current !== null && $cfg['backup_collection'] !== '') {
        $copy = $current;
        unset($copy['_id']);
        $bulkB = new BulkWrite();
        $bulkB->insert([
            'sourceId'   => $id,
            'backedUpAt' => gmdate('Y-m-d H:i:s'),
            'by'         => 'DIVINER_Web',
            'document'   => $copy,
        ]);
        ghostd_manager()->executeBulkWrite(ghostd_ns($cfg['backup_collection']), $bulkB);
    }

    $bulk = new BulkWrite();
    $bulk->update(['_id' => $id], ['$set' => [$path => $value, 'updatedAt' => gmdate('Y-m-d H:i:s')]]);
    $res = ghostd_manager()->executeBulkWrite(ghostd_ns(), $bulk);
    return ['matched' => $res->getMatchedCount(), 'modified' => $res->getModifiedCount()];
}

/** Remove one dotted path from a document, backing the document up first. */
function ghostd_unset_path(string $id, string $path): array
{
    $current = ghostd_get($id);
    $cfg = ghostd_config();

    if ($current !== null && $cfg['backup_collection'] !== '') {
        $copy = $current;
        unset($copy['_id']);
        $bulkB = new BulkWrite();
        $bulkB->insert([
            'sourceId'   => $id,
            'backedUpAt' => gmdate('Y-m-d H:i:s'),
            'by'         => 'DIVINER_Web',
            'document'   => $copy,
        ]);
        ghostd_manager()->executeBulkWrite(ghostd_ns($cfg['backup_collection']), $bulkB);
    }

    $bulk = new BulkWrite();
    $bulk->update(['_id' => $id], ['$unset' => [$path => ''], '$set' => ['updatedAt' => gmdate('Y-m-d H:i:s')]]);
    $res = ghostd_manager()->executeBulkWrite(ghostd_ns(), $bulk);
    return ['matched' => $res->getMatchedCount(), 'modified' => $res->getModifiedCount()];
}

/** The unit's document ids, split into the shapes the mod files them under. */
function ghostd_sections(): array
{
    $unit = ghostd_config()['unit'];
    $keys = ghostd_keys();

    $out = ['store' => null, 'sections' => [], 'roles' => [], 'opords' => [], 'other' => []];
    foreach ($keys as $k) {
        if ($k === $unit) {
            $out['store'] = $k;
        } elseif (str_starts_with($k, $unit . '.role.')) {
            $out['roles'][] = $k;
        } elseif (str_starts_with($k, $unit . '.opord.')) {
            $out['opords'][] = $k;
        } elseif (str_starts_with($k, $unit . '.')) {
            $out['sections'][] = $k;
        } else {
            $out['other'][] = $k;
        }
    }
    return $out;
}

/**
 * Every document's id and its few summary fields, in ONE query.
 *
 * The listing page used to call ghostd_get() per key, which is one Atlas
 * round-trip per document - forty of them to draw one table. A projection
 * costs one.
 */
function ghostd_index(): array
{
    $cur = ghostd_manager()->executeQuery(
        ghostd_ns(),
        new Query([], [
            'projection' => ['_id' => 1, 'section' => 1, 'updatedAt' => 1, 'exportedAt' => 1],
            'sort'       => ['_id' => 1],
        ])
    );
    $cur->setTypeMap(GHOSTD_TYPEMAP);
    $out = [];
    foreach ($cur as $doc) {
        $out[(string) $doc['_id']] = [
            'section'    => $doc['section'] ?? null,
            'updatedAt'  => $doc['updatedAt'] ?? null,
            'exportedAt' => $doc['exportedAt'] ?? null,
        ];
    }
    return $out;
}
