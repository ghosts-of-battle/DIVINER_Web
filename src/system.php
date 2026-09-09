<?php
/**
 * The PAC system templates: the shape of an operation order, and the kinds of
 * PAC request a player can raise.
 *
 * NOT CONFIG A MISSION SHIPS - these are the forms the system itself is made
 * of, and until now they were PHP constants, so adding a paragraph to the OPORD
 * or a new kind of request meant editing the site. They are documents now, with
 * the constants as the fallback: a unit that has never touched them gets
 * exactly what it had.
 *
 *   <unit>.system.opord        the sections and fields of an operation order
 *   <unit>.system.ticketKinds  the kinds of PAC request
 *
 * READ THROUGH THESE FUNCTIONS, never off the constants directly, or an edit
 * shows up on one page and not another.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/opords.php';
require_once __DIR__ . '/tickets.php';

function ghostd_system_doc_id(string $what): string
{
    return ghostd_config()['unit'] . '.system.' . $what;
}

/**
 * The OPORD's shape: sections, each with ordered fields.
 *
 * Stored FLAT - one row per field, carrying its section - because that is how
 * it is edited, and turning it back into sections here means the editor never
 * has to build a nested document.
 */
function ghostd_opord_sections(): array
{
    try {
        $doc = ghostd_get(ghostd_system_doc_id('opord'));
    } catch (Throwable $e) {
        $doc = null;
    }
    $rows = is_array($doc['fields'] ?? null) ? $doc['fields'] : [];
    if ($rows === []) {
        return GHOSTD_OPORD_SECTIONS;
    }

    $out = [];
    foreach ($rows as $r) {
        if (!is_array($r)) {
            continue;
        }
        $sec = trim((string) ($r['section'] ?? ''));
        $fid = trim((string) ($r['field'] ?? ''));
        if ($sec === '' || $fid === '') {
            continue;
        }
        if (!isset($out[$sec])) {
            $out[$sec] = [
                'title'  => trim((string) ($r['sectionTitle'] ?? $sec)),
                'hint'   => trim((string) ($r['sectionHint'] ?? '')),
                'fields' => [],
            ];
        }
        // A section's title and hint ride on its first row; a later row may
        // still carry them, and the first non-empty one wins.
        if (($out[$sec]['hint'] === '') && trim((string) ($r['sectionHint'] ?? '')) !== '') {
            $out[$sec]['hint'] = trim((string) $r['sectionHint']);
        }
        $kind = (string) ($r['kind'] ?? 'x');
        $out[$sec]['fields'][$fid] = [
            'label' => trim((string) ($r['label'] ?? $fid)),
            'kind'  => in_array($kind, ['t', 'a', 'x'], true) ? $kind : 'x',
            'help'  => trim((string) ($r['help'] ?? '')),
        ];
    }
    return $out === [] ? GHOSTD_OPORD_SECTIONS : $out;
}

/** The same shape, flattened for the editor. */
function ghostd_opord_rows(): array
{
    $out = [];
    foreach (ghostd_opord_sections() as $sec => $meta) {
        foreach ($meta['fields'] as $fid => $fm) {
            $out[] = [
                'section'      => (string) $sec,
                'sectionTitle' => (string) ($meta['title'] ?? $sec),
                'sectionHint'  => (string) ($meta['hint'] ?? ''),
                'field'        => (string) $fid,
                'label'        => (string) ($fm['label'] ?? $fid),
                'kind'         => (string) ($fm['kind'] ?? 'x'),
                'help'         => (string) ($fm['help'] ?? ''),
            ];
        }
    }
    return $out;
}

function ghostd_opord_rows_save(array $rows): void
{
    ghostd_put(ghostd_system_doc_id('opord'), [
        'section'   => 'system',
        'id'        => 'opord',
        'fields'    => array_values($rows),
        'from'      => 'DIVINER_Web',
        'updatedAt' => gmdate('Y-m-d H:i:s'),
    ]);
}

/** The kinds of PAC request a player may raise. */
function ghostd_ticket_kinds(): array
{
    try {
        $doc = ghostd_get(ghostd_system_doc_id('ticketKinds'));
    } catch (Throwable $e) {
        $doc = null;
    }
    $items = is_array($doc['items'] ?? null) ? $doc['items'] : [];
    if ($items === []) {
        return GHOSTD_TICKET_KINDS;
    }

    $out = [];
    foreach ($items as $id => $it) {
        if (!is_array($it)) {
            continue;
        }
        $out[(string) $id] = [
            'label' => trim((string) ($it['label'] ?? $id)),
            'hint'  => trim((string) ($it['hint'] ?? '')),
        ];
    }
    return $out === [] ? GHOSTD_TICKET_KINDS : $out;
}

function ghostd_ticket_kinds_save(array $items): void
{
    if ($items === []) {
        throw new RuntimeException('With no kinds nobody can raise a request at all. '
            . 'Leave at least one.');
    }
    ghostd_put(ghostd_system_doc_id('ticketKinds'), [
        'section'   => 'system',
        'id'        => 'ticketKinds',
        'items'     => $items,
        'from'      => 'DIVINER_Web',
        'updatedAt' => gmdate('Y-m-d H:i:s'),
    ]);
}

/** Put one back to how it ships, by removing the document. */
function ghostd_system_reset(string $what): void
{
    require_once __DIR__ . '/roles.php';       // ghostd_doc_delete lives there
    ghostd_doc_delete(ghostd_system_doc_id($what));
}
