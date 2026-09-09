<?php
/**
 * Operation orders: the shape, and reading and writing them.
 *
 * THE SHAPE IS THE MOD'S, NOT THIS SITE'S. fnc_loadStructure reads six named
 * sections with named fields out of the config, and fnc_opordField addresses
 * them as "section.field" - which is what a report template's
 * autoFill = "pac:situation.enemy" resolves against. So the field names here
 * are not a choice: rename one and the compose card stops filling itself in.
 *
 * THE CONTENT NESTS UNDER "order". A stored document is
 * {section, id, order:{...the six sections...}, exportedAt, updatedAt} - the
 * same shape push_config.py writes, so a document written here and one pushed
 * from a mission are indistinguishable to the game.
 *
 * ARRAYS ARE ONE PER LINE in the editor. markers, enemyFactions and
 * clarifications are arrays to the game; a textarea split on newlines is a
 * kinder way to type a list than a comma-separated box.
 */

declare(strict_types=1);

require_once __DIR__ . '/system.php';

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * THE SHAPE OF AN ORDER IS A DOCUMENT, not a list in here.
 *
 * <unit>.system.opord says which sections an order has and what fields each
 * holds; ghostd_opord_sections() reads it and there is no copy to fall back to
 * (2026-09-09 - the same rule the colour schemes follow). It is edited on
 * Templates > System, a section at a time.
 *
 * kind: t = text line, x = paragraph, a = list (one per line)
 */

function ghostd_opord_doc_id(string $id): string
{
    return ghostd_config()['unit'] . '.opord.' . $id;
}

/** Every OPORD document id, without the prefix. */
function ghostd_opord_ids(): array
{
    $prefix = ghostd_config()['unit'] . '.opord.';
    $out = [];
    foreach (ghostd_keys() as $key) {
        if (str_starts_with($key, $prefix)) {
            $out[] = substr($key, strlen($prefix));
        }
    }
    sort($out);
    return $out;
}

/** One order's content - the six sections - or an empty set. */
function ghostd_opord(string $id): array
{
    $doc = ghostd_get(ghostd_opord_doc_id($id));
    $order = (is_array($doc['order'] ?? null)) ? $doc['order'] : [];

    $out = [];
    foreach (ghostd_opord_sections() as $sec => $meta) {
        $have = (is_array($order[$sec] ?? null)) ? $order[$sec] : [];
        foreach ($meta['fields'] as $f => $fm) {
            $v = $have[$f] ?? ($fm['kind'] === 'a' ? [] : '');
            $out[$sec][$f] = $fm['kind'] === 'a'
                ? array_values(array_map('strval', (array) $v))
                : (string) (is_array($v) ? implode(', ', $v) : $v);
        }
    }
    return $out;
}

/** An id the game can use as a config class name. */
function ghostd_opord_valid_id(string $id): bool
{
    return (bool) preg_match('/^[a-z0-9_]{2,40}$/', $id);
}

/**
 * Write one.
 *
 * The whole document is replaced, so ghostd_put copies the previous version
 * into the backup collection first - which is what makes a bad save on a
 * finished order survivable.
 */
function ghostd_opord_save(string $id, array $sections): void
{
    if (!ghostd_opord_valid_id($id)) {
        throw new RuntimeException('An id is lower-case letters, digits and underscore - it becomes a config class name in the game.');
    }

    $order = ['id' => $id];
    foreach (ghostd_opord_sections() as $sec => $meta) {
        $rec = [];
        foreach ($meta['fields'] as $f => $fm) {
            $v = $sections[$sec][$f] ?? ($fm['kind'] === 'a' ? [] : '');
            $rec[$f] = $fm['kind'] === 'a' ? array_values((array) $v) : (string) $v;
        }
        // The header carries the id too - fnc_loadStructure reads it there.
        if ($sec === 'header') {
            $rec['id'] = $id;
        }
        $order[$sec] = $rec;
    }
    // attachments is a collection the game builds from config classes; keep
    // whatever is already stored rather than dropping it on every save.
    $existing = ghostd_get(ghostd_opord_doc_id($id));
    $att = $existing['order']['situation']['attachments'] ?? null;
    if (is_array($att)) {
        $order['situation']['attachments'] = $att;
    }

    ghostd_put(ghostd_opord_doc_id($id), [
        'section'   => 'opord',
        'id'        => $id,
        'order'     => $order,
        'from'      => 'DIVINER_Web',
        'updatedAt' => gmdate('Y-m-d H:i:s'),
    ]);
}

/** A textarea of lines to a list, blank lines dropped. */
function ghostd_lines(string $s): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $s) ?: []), static fn($x) => $x !== ''));
}
