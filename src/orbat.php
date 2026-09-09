<?php
/**
 * The order of battle, and the radio plan that goes with it.
 *
 * TWO DOCUMENTS, ONE SUBJECT. Platoons, squads and their slots live in
 * <unit>.orbat; the channel a squad or platoon sits on lives in <unit>.radio,
 * because that is where the mod reads it. Nobody thinks of them separately - a
 * squad without a channel is half a squad - so saving one saves the other.
 *
 * THE SHAPES ARE THE MOD'S, positional and unchanged:
 *   groups     [name, [roleId, ...], showWhen]
 *   platoons   [id, name, callsign, net, [squadName, ...]]
 *   radioNets  [id, name, [squadName, ...]]
 * and in <unit>.radio:
 *   srSquadChannel    [squadName, acreChannel]
 *   tfarNets          [squadName, shortRange, longRange]
 *   lrPlatoonChannel  [platoonId, lrChannel]     - see CHANGES.md 2026-09-09
 *
 * Everything here is used by the ORBAT tabs AND by the squad and platoon pages,
 * which is the point: two editors that disagree about what a squad is would be
 * two different squads.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/templates.php';

function ghostd_orbat_doc_id(string $variant = ''): string
{
    $id = ghostd_config()['unit'] . '.orbat';
    if ($variant !== '') {
        if (!ghostd_variant_ok($variant)) {
            throw new RuntimeException('A version id is letters, digits and underscore.');
        }
        $id .= '.' . $variant;
    }
    return $id;
}

function ghostd_radio_doc_id(): string
{
    return ghostd_config()['unit'] . '.radio';
}

/** Every named version of the ORBAT, sorted. '' - the common one - is not listed. */
function ghostd_orbat_variants(): array
{
    $prefix = ghostd_config()['unit'] . '.orbat.';
    $out = [];
    try {
        foreach (ghostd_keys() as $k) {
            if (str_starts_with($k, $prefix)) {
                $out[] = substr($k, strlen($prefix));
            }
        }
    } catch (Throwable $e) {
        return [];
    }
    sort($out);
    return $out;
}

/** One version of the ORBAT, every list present. */
function ghostd_orbat(string $variant = ''): array
{
    $doc = null;
    try {
        $doc = ghostd_get(ghostd_orbat_doc_id($variant));
    } catch (Throwable $e) {
        $doc = null;
    }
    $arr = static fn($v) => is_array($v) ? array_values(array_filter($v, 'is_array')) : [];
    return [
        'faction'   => (string) ($doc['faction'] ?? ''),
        'platoons'  => $arr($doc['platoons'] ?? null),
        'groups'    => $arr($doc['groups'] ?? null),
        'radioNets' => $arr($doc['radioNets'] ?? null),
        'exists'    => is_array($doc),
    ];
}

/** Read the ORBAT, hand it to $fn to change, write it back. */
function ghostd_orbat_edit(string $variant, callable $fn): void
{
    $docId = ghostd_orbat_doc_id($variant);
    $doc = ghostd_get($docId);
    $doc = is_array($doc) ? $doc : [];
    unset($doc['_id']);
    $fn($doc);
    $doc['section']   = 'orbat';
    $doc['id']        = $variant;
    $doc['from']      = 'DIVINER_Web';
    $doc['updatedAt'] = gmdate('Y-m-d H:i:s');
    ghostd_put($docId, $doc);
}

/** The radio plan's items - the shape the mod reads, {section, items}. */
function ghostd_radio_items(): array
{
    try {
        $doc = ghostd_get(ghostd_radio_doc_id());
    } catch (Throwable $e) {
        return [];
    }
    return is_array($doc['items'] ?? null) ? $doc['items'] : [];
}

/** The same for the radio plan. */
function ghostd_radio_edit(callable $fn): void
{
    $docId = ghostd_radio_doc_id();
    $doc = ghostd_get($docId);
    $doc = is_array($doc) ? $doc : [];
    unset($doc['_id']);
    $items = is_array($doc['items'] ?? null) ? $doc['items'] : [];
    $fn($items);
    $doc['section']   = 'radio';
    $doc['items']     = $items;
    $doc['from']      = 'DIVINER_Web';
    $doc['updatedAt'] = gmdate('Y-m-d H:i:s');
    ghostd_put($docId, $doc);
}

// ---------------------------------------------------------------------------
// Channels, by the thing they belong to.
// ---------------------------------------------------------------------------

/** squad name => ACRE short-range channel. */
function ghostd_acre_of(array $radio): array
{
    $out = [];
    foreach ((array) ($radio['srSquadChannel'] ?? []) as $r) {
        if (is_array($r) && isset($r[0])) {
            $out[(string) $r[0]] = (int) ($r[1] ?? 0);
        }
    }
    return $out;
}

/** squad name => [TFAR short range, TFAR long range]. */
function ghostd_tfar_of(array $radio): array
{
    $out = [];
    foreach ((array) ($radio['tfarNets'] ?? []) as $r) {
        if (is_array($r) && isset($r[0])) {
            $out[(string) $r[0]] = [(int) ($r[1] ?? 0), (int) ($r[2] ?? 0)];
        }
    }
    return $out;
}

/**
 * platoon id => the LR channel its people are put on.
 *
 * NEW, 2026-09-09. Long range used to be one plan for the whole task force -
 * every 117F on the same channel - which is right for a detachment net and
 * wrong the moment two platoons want to talk among themselves without the
 * other listening. Read by ghostD_gear_fnc_setupRadios.
 */
function ghostd_lrplt_of(array $radio): array
{
    $out = [];
    foreach ((array) ($radio['lrPlatoonChannel'] ?? []) as $r) {
        if (is_array($r) && isset($r[0])) {
            $out[(string) $r[0]] = (int) ($r[1] ?? 0);
        }
    }
    return $out;
}

/** The LR channels the plan defines, as index => label, for a dropdown. */
function ghostd_lr_channels(array $radio): array
{
    $out = [];
    foreach ((array) ($radio['lrChannels'] ?? []) as $r) {
        if (!is_array($r) || !isset($r[0])) {
            continue;
        }
        $idx   = (int) $r[0];
        $freq  = (string) ($r[1] ?? '');
        $label = trim((string) ($r[2] ?? ''));
        $out[$idx] = $idx . ' - ' . ($label !== '' ? $label : 'unnamed') . ($freq !== '' ? ' (' . $freq . ')' : '');
    }
    ksort($out);
    return $out;
}

/**
 * Set one row in a keyed channel list, or remove it when the value is blank.
 *
 * The rows are [key, ...values]; a key appears once. Written this way because
 * every channel save is the same operation on a different list.
 */
function ghostd_channel_set(array &$items, string $list, string $key, ?array $values): void
{
    $rows = is_array($items[$list] ?? null) ? $items[$list] : [];
    $rows = array_values(array_filter($rows,
        static fn($r) => is_array($r) && (string) ($r[0] ?? '') !== $key));
    if ($values !== null) {
        array_unshift($values, $key);
        $rows[] = $values;
    }
    $items[$list] = $rows;
}

/** A squad's row in the ORBAT, or null. */
function ghostd_squad(string $variant, string $name): ?array
{
    foreach (ghostd_orbat($variant)['groups'] as $g) {
        if ((string) ($g[0] ?? '') === $name) {
            return [
                'name'  => (string) ($g[0] ?? ''),
                'roles' => array_values(array_map('strval', (array) ($g[1] ?? []))),
                'cond'  => (string) ($g[2] ?? 'true'),
            ];
        }
    }
    return null;
}

/** A platoon's row in the ORBAT, or null. */
function ghostd_platoon(string $variant, string $pid): ?array
{
    foreach (ghostd_orbat($variant)['platoons'] as $p) {
        if ((string) ($p[0] ?? '') === $pid) {
            return [
                'id'       => (string) ($p[0] ?? ''),
                'name'     => (string) ($p[1] ?? ''),
                'callsign' => (string) ($p[2] ?? ''),
                'net'      => (string) ($p[3] ?? ''),
                'squads'   => array_values(array_map('strval', (array) ($p[4] ?? []))),
            ];
        }
    }
    return null;
}

/**
 * How much is in a template version, said in a line.
 *
 * The squad and platoon pages show their arsenal and motorpool as a summary and
 * a button into the one editor that already exists, rather than repeating that
 * editor in three places for the three of them to drift apart.
 */
function ghostd_variant_summary(string $key, string $variant): string
{
    try {
        if (ghostd_get(ghostd_template_doc_id($key, $variant)) === null) {
            return '';
        }
        $t = GHOSTD_TEMPLATES[$key];
        if ($t['shape'] === 'lists') {
            $lists = ghostd_template_lists($key, $variant);
            $parts = [];
            foreach ($lists as $name => $vals) {
                if ($vals !== []) {
                    $parts[] = count($vals) . ' ' . $name;
                }
            }
            return $parts === [] ? 'empty' : implode(', ', $parts);
        }
        $n = count(ghostd_template_items($key, $variant));
        return $n === 0 ? 'empty' : $n . ' entries';
    } catch (Throwable $e) {
        return '';
    }
}
