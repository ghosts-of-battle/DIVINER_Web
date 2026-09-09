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

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Every section, in the order they are written and read.
 * kind: t = text line, x = paragraph, a = list (one per line)
 */
const GHOSTD_OPORD_SECTIONS = [
    'header' => [
        'title' => 'Header',
        'hint'  => 'What this order is and who may read it.',
        'fields' => [
            'title'        => ['label' => 'Title', 'kind' => 't', 'help' => 'OPERATION IRON VEIL'],
            'date'         => ['label' => 'Date-time group', 'kind' => 't', 'help' => 'When it is set, in whatever form the unit uses.'],
            'campaign'     => ['label' => 'Campaign', 'kind' => 't', 'help' => 'The larger series this belongs to, if any.'],
            'release'      => ['label' => 'Release', 'kind' => 't', 'help' => 'Draft, final, or a version.'],
            'distribution' => ['label' => 'Distribution', 'kind' => 't', 'help' => 'Who this goes to.'],
            'mapImage'     => ['label' => 'Map image', 'kind' => 't', 'help' => 'A path INSIDE the game - a mission file like "media\\\\opord_map.paa", or a mod path like "\\\\z\\\\ghostD\\\\addons\\\\...". Arma cannot load a web address, and it wants .paa or .jpg. Upload the file to the mission or mod yourself; this only records where it is.'],
            'markers'      => ['label' => 'Map markers', 'kind' => 'a', 'help' => 'Marker names to show, one per line.'],
        ],
    ],
    'situation' => [
        'title' => 'Situation',
        'hint'  => 'What is going on before anybody moves.',
        'fields' => [
            'overview'      => ['label' => 'Overview', 'kind' => 'x', 'help' => 'The picture in a paragraph.'],
            'enemy'         => ['label' => 'Enemy forces', 'kind' => 'x', 'help' => 'Composition, disposition, strength, and what they are likely to do.'],
            'enemyFactions' => ['label' => 'Enemy factions', 'kind' => 'a', 'help' => 'One per line.'],
            'friendly'      => ['label' => 'Friendly forces', 'kind' => 'x', 'help' => 'Who else is out there and what they are doing.'],
            'civilTerrain'  => ['label' => 'Civilians and terrain', 'kind' => 'x', 'help' => 'Ground, weather, population - anything that shapes the plan.'],
        ],
    ],
    'mission' => [
        'title' => 'Mission',
        'hint'  => 'The one sentence everybody has to be able to repeat, and how it is done.',
        'fields' => [
            'mission'   => ['label' => 'Mission', 'kind' => 'x', 'help' => 'Who, what, when, where and why - in one statement.'],
            'execution' => ['label' => 'Execution', 'kind' => 'x', 'help' => 'Intent, scheme of manoeuvre, and tasks by element.'],
        ],
    ],
    'adminLogistics' => [
        'title' => 'Admin and logistics',
        'hint'  => 'What the plan needs to keep running.',
        'fields' => [
            'admin'              => ['label' => 'Administration', 'kind' => 'x', 'help' => 'Casualties, prisoners, timings.'],
            'logistics'          => ['label' => 'Logistics', 'kind' => 'x', 'help' => 'Ammunition, fuel, transport, resupply.'],
            'special'            => ['label' => 'Special instructions', 'kind' => 'x', 'help' => ''],
            'armaConsiderations' => ['label' => 'Arma considerations', 'kind' => 'x', 'help' => 'Mods, respawn rules, Zeus, anything about the game rather than the fiction.'],
        ],
    ],
    'commandSignal' => [
        'title' => 'Command and signal',
        'hint'  => 'Who is in charge, and how everyone talks.',
        'fields' => [
            'command' => ['label' => 'Command', 'kind' => 'x', 'help' => 'Chain of command, succession.'],
            'signal'  => ['label' => 'Signal', 'kind' => 'x', 'help' => 'Nets, frequencies, callsigns, code words.'],
        ],
    ],
    'roe' => [
        'title' => 'Rules of engagement',
        'hint'  => 'What may be engaged, and what may not.',
        'fields' => [
            'roeText'        => ['label' => 'Rules of engagement', 'kind' => 'x', 'help' => ''],
            'clarifications' => ['label' => 'Clarifications', 'kind' => 'a', 'help' => 'One per line.'],
        ],
    ],
];

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
    foreach (GHOSTD_OPORD_SECTIONS as $sec => $meta) {
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
    foreach (GHOSTD_OPORD_SECTIONS as $sec => $meta) {
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
