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
 * and in <unit>.radio:
 *   srSquadChannel    [squadName, acreChannel]
 *   tfarNets          [squadName, shortRange, longRange]
 *   lrPlatoonChannel  [platoonId, lrChannel]     - see CHANGES.md 2026-09-09
 *   vonTeamNets       [squadName, teamNetName]   - base game VoIP, 2026-10-20
 *   vonNets           [netName, "c2"|"a2g", [squadName, ...], [roleId, ...], [skillId, ...]]
 *   vonSide, vonCommand, vonGlobalAdminsOnly     - plain booleans
 *
 * Everything here is used by the ORBAT tabs AND by the squad and platoon pages,
 * which is the point: two editors that disagree about what a squad is would be
 * two different squads.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/templates.php';

/**
 * The sides a unit can be on, by the engine's own name.
 *
 * NOT FREE TEXT. A side the engine does not have is a side nothing can be
 * created on, and the failure is at mission start with no message. These four
 * are all there are; the labels are what a player calls them.
 */
const GHOSTD_SIDES = [
    'WEST' => 'BLUFOR - WEST',
    'EAST' => 'OPFOR - EAST',
    'GUER' => 'INDEPENDENT - GUER',
    'CIV'  => 'CIVILIAN - CIV',
];

/** Which parts of a squad are edited together - one section, one page. */
// ONE ARSENAL AND ONE MOTORPOOL EACH (user, 2026-09-09: "on squad and platoos
// no create they get a single arnonal config single motor pool config"). Not a
// version to be created - the squad simply has one, and opening it is editing
// it.
const GHOSTD_SQUAD_SECTIONS = [
    'identity'  => 'Identity',
    'slots'     => 'Slots',
    'radio'     => 'Radio',
    'arsenal'   => 'Arsenal',
    'motorpool' => 'Motorpool',
    'copy'      => 'Copy',
    'remove'    => 'Remove',
];

/** The same for a platoon. */
const GHOSTD_PLATOON_SECTIONS = [
    'identity'  => 'Identity',
    'squads'    => 'Squads',
    'radio'     => 'Radio',
    'arsenal'   => 'Arsenal',
    'motorpool' => 'Motorpool',
    'remove'    => 'Remove',
];

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

/**
 * The radio plan's document - the common one, or a named version.
 *
 * A COMMS PLAN IS A TEMPLATE LIKE ANY OTHER (user, 2026-09-09: "messaging acre
 * and tfar need to be a list of templates that are assigned at the orbat
 * level"). <unit>.radio is the default; <unit>.radio.<id> is a version, and an
 * order of battle names which one it runs.
 */
function ghostd_radio_doc_id(string $variant = ''): string
{
    $id = ghostd_config()['unit'] . '.radio';
    return $variant === '' ? $id : $id . '.' . $variant;
}

/**
 * Every named version of ANY document, by listing the "<unit>.<doc>." prefix.
 *
 * The radio plan is not a template in the registry - its document is a map of
 * ghostFR_radio_* globals rather than the {id: {fields}} shape the generic
 * editor takes - but it still has versions, and the order of battle still has
 * to offer them. This is the same prefix listing the mod does.
 */
function ghostd_doc_variants(string $doc): array
{
    $prefix = ghostd_config()['unit'] . '.' . $doc . '.';
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

/**
 * THE DEFAULT ORBAT - the version missions actually run.
 *
 * A unit builds several orders of battle and one of them is the live one. The
 * "currentOrbat" setting names it and ghostD_pac_fnc_svcStructure reads that at
 * boot, so it is the same answer the game gets. Empty means the common one.
 *
 * Everything that asks "what squads are there" - the roster's group dropdown,
 * its role dropdown - asks this, not whichever version somebody happens to be
 * editing. A roster full of squads from a draft ORBAT is a roster nobody can
 * slot from.
 */
function ghostd_default_orbat_id(): string
{
    $v = ghostd_setting('currentOrbat');
    return ($v !== '' && ghostd_variant_ok($v) && in_array($v, ghostd_orbat_variants(), true)) ? $v : '';
}

function ghostd_default_orbat(): array
{
    return ghostd_orbat(ghostd_default_orbat_id());
}

/**
 * The roles a squad's slots ask for, in slot order, without repeats.
 *
 * Empty squad name, or a squad with no slots, answers every role there is -
 * because a man in no squad still has to be given a role.
 */
function ghostd_roles_for_squad(string $squad): array
{
    $out = [];
    if ($squad !== '') {
        foreach (ghostd_default_orbat()['groups'] as $g) {
            if (strcasecmp((string) ($g[0] ?? ''), $squad) !== 0) {
                continue;
            }
            foreach ((array) ($g[1] ?? []) as $r) {
                $r = (string) $r;
                if ($r !== '' && !in_array($r, $out, true)) { $out[] = $r; }
            }
        }
    }
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
    $side = strtoupper((string) ($doc['side'] ?? ''));
    return [
        'faction'   => (string) ($doc['faction'] ?? ''),
        // WHICH COMMS THIS ORDER OF BATTLE USES. Empty is the default template.
        // They belong to the ORBAT because a different order of battle is a
        // different set of squads, and a comms plan is written around squads.
        'radio'     => (string) ($doc['radioVersion'] ?? ''),
        'nets'      => (string) ($doc['netsVersion'] ?? ''),
        // AND ITS GEAR. The mod folds these into currentArsenal and
        // currentMotorpool at boot - see svcOrbat.inc.sqf.
        'arsenal'   => (string) ($doc['arsenalVersion'] ?? ''),
        'motorpool' => (string) ($doc['motorpoolVersion'] ?? ''),
        'side'      => isset(GHOSTD_SIDES[$side]) ? $side : 'WEST',
        'platoons'  => $arr($doc['platoons'] ?? null),
        'groups'    => $arr($doc['groups'] ?? null),
        // The engine's numeric coefficients, one set for everybody in this
        // order of battle - see GHOSTD_TRAIT_COEFS.
        'coefs'     => is_array($doc['coefs'] ?? null) ? $doc['coefs'] : [],
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
function ghostd_radio_items(string $variant = ''): array
{
    try {
        $doc = ghostd_get(ghostd_radio_doc_id($variant));
    } catch (Throwable $e) {
        return [];
    }
    return is_array($doc['items'] ?? null) ? $doc['items'] : [];
}

/** The same for the radio plan. */
function ghostd_radio_edit(callable $fn, string $variant = ''): void
{
    $docId = ghostd_radio_doc_id($variant);
    $doc = ghostd_get($docId);
    $doc = is_array($doc) ? $doc : [];
    unset($doc['_id']);
    $items = is_array($doc['items'] ?? null) ? $doc['items'] : [];
    $fn($items);
    $doc['section']   = 'radio';
    $doc['id']        = $variant;
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

// ---------------------------------------------------------------------------
// VON - the game's own voice chat, for a mission that runs neither TFAR nor
// ACRE (user, 2026-10-20: "add a base game voip option to diviner and the
// diviner web").
//
// THE CONTRACT IS SHARED WITH BOTH MODS and was agreed before this was written,
// so these keys and shapes are not ours to change:
//   vonTeamNets          [[squadName, teamNetName], ...]  blank or missing = the squad's name
//   vonNets              [[netName, kind, [squadName, ...], [roleId, ...], [skillId, ...]], ...]
//                        kind "c2" or "a2g". The roles and skills columns came
//                        2026-10-21 (user: C2 to every TL, ATL and the HQ, A2G to
//                        everyone with the JFO skill): a man is on the net when
//                        his squad, his role or one of his skills is listed. A
//                        role is its BASE id - "teamleadGhost_t", the testing
//                        copy, answers to "teamleadGhost". A skill is its PAC
//                        skill id ("jfo"). Old three-column rows read as no
//                        roles and no skills.
//   vonSide              bool, default false - the game's Side channel open
//   vonCommand           bool, default false - the game's Command channel open
//   vonGlobalAdminsOnly  bool, default true  - Global talk for admins alone
//
// The game's Group channel IS each squad's team net; Vehicle is crew intercom
// and Direct is local voice, and neither needs a plan. The custom nets are the
// engine's custom channels, of which there are ten - so ten nets, no more.
// ---------------------------------------------------------------------------

/** The engine has ten custom radio channels; every VON net is one of them. */
const GHOSTD_VON_MAX_NETS = 10;

/** Longest net name, the agreed limit - it is the label a player reads in the channel list. */
const GHOSTD_VON_NAME_MAX = 24;

/** The two kinds of custom net, by the value stored. */
const GHOSTD_VON_KINDS = [
    'c2'  => 'C2 - command net',
    'a2g' => 'A2G - air to ground',
];

/** The testing copy of a role ends in this and answers to its base id. */
const GHOSTD_VON_ROLE_TEST_SUFFIX = '_t';

/** A role id as a VON net lists it: the base id, the testing suffix taken off. */
function ghostd_von_role_base(string $id): string
{
    $n = strlen(GHOSTD_VON_ROLE_TEST_SUFFIX);
    return strlen($id) > $n && substr($id, -$n) === GHOSTD_VON_ROLE_TEST_SUFFIX ? substr($id, 0, -$n) : $id;
}

/** A list column of a VON net row: unique non-empty strings, in order. */
function ghostd_von_list($v): array
{
    $out = [];
    foreach ((array) $v as $x) {
        if (is_scalar($x) && trim((string) $x) !== '' && !in_array(trim((string) $x), $out, true)) {
            $out[] = trim((string) $x);
        }
    }
    return $out;
}

/** The three switches, by key, with their defaults. */
const GHOSTD_VON_FLAGS = [
    'vonSide'             => false,
    'vonCommand'          => false,
    'vonGlobalAdminsOnly' => true,
];

/** squad name => its team net name, for the squads that have one typed in. */
function ghostd_von_team_of(array $radio): array
{
    $out = [];
    foreach ((array) ($radio['vonTeamNets'] ?? []) as $r) {
        if (is_array($r) && isset($r[0]) && is_scalar($r[0])) {
            $n = trim(is_scalar($r[1] ?? null) ? (string) $r[1] : '');
            if ($n !== '') {
                $out[(string) $r[0]] = $n;
            }
        }
    }
    return $out;
}

/**
 * The custom nets, in order, each ['name', 'kind', 'squads', 'roles', 'skills'].
 *
 * Read defensively - a row the mod or a hand edit left malformed is skipped
 * rather than drawn as a net with no name.
 */
function ghostd_von_nets(array $radio): array
{
    $out = [];
    foreach ((array) ($radio['vonNets'] ?? []) as $r) {
        if (!is_array($r) || !isset($r[0]) || !is_scalar($r[0])) {
            continue;
        }
        $name = trim((string) $r[0]);
        if ($name === '') {
            continue;
        }
        $kind = is_scalar($r[1] ?? null) ? strtolower((string) $r[1]) : '';
        $squads = [];
        foreach ((array) ($r[2] ?? []) as $s) {
            if (is_scalar($s) && (string) $s !== '' && !in_array((string) $s, $squads, true)) {
                $squads[] = (string) $s;
            }
        }
        $out[] = [
            'name'   => $name,
            'kind'   => isset(GHOSTD_VON_KINDS[$kind]) ? $kind : 'c2',
            'squads' => $squads,
            'roles'  => array_values(array_unique(array_map('ghostd_von_role_base', ghostd_von_list($r[3] ?? [])))),
            'skills' => ghostd_von_list($r[4] ?? []),
        ];
    }
    return $out;
}

/** squad name => [[netName, kind], ...] - the custom nets it is on. */
function ghostd_von_nets_of(array $radio): array
{
    $out = [];
    foreach (ghostd_von_nets($radio) as $n) {
        foreach ($n['squads'] as $s) {
            $out[$s][] = [$n['name'], $n['kind']];
        }
    }
    return $out;
}

/** The three switches, each a real bool, missing ones at their defaults. */
function ghostd_von_flags(array $radio): array
{
    $out = [];
    foreach (GHOSTD_VON_FLAGS as $k => $def) {
        $out[$k] = is_bool($radio[$k] ?? null) ? $radio[$k] : $def;
    }
    return $out;
}

/**
 * One squad's VON, said in a line: its team net and the custom nets it is on.
 *
 * Plain text - the caller escapes it. Used by the squad, platoon and squads
 * pages so the three say it the same way.
 */
function ghostd_von_line(array $radio, string $squad): string
{
    $team = ghostd_von_team_of($radio)[$squad] ?? $squad;
    $parts = ['team ' . $team];
    foreach (ghostd_von_nets_of($radio)[$squad] ?? [] as $n) {
        $parts[] = $n[0] . ' (' . strtoupper($n[1]) . ')';
    }
    return implode(', ', $parts);
}

/**
 * A name a VON net may have: trimmed, 1..24 characters, no control characters.
 *
 * Throws with a message a person can act on; returns the cleaned name. Counted
 * in characters, not bytes, without needing mbstring: the /u match fails on
 * text that is not UTF-8 as well.
 */
function ghostd_von_name_check(string $name, string $what): string
{
    $name = trim($name);
    if ($name === '') {
        throw new RuntimeException($what . ' needs a name.');
    }
    if (preg_match('/^[^\x00-\x1F\x7F]+$/u', $name) !== 1) {
        throw new RuntimeException('"' . $name . '" has characters a channel name cannot show.');
    }
    if (preg_match('/^.{1,' . GHOSTD_VON_NAME_MAX . '}$/us', $name) !== 1) {
        throw new RuntimeException('"' . $name . '" is longer than ' . GHOSTD_VON_NAME_MAX
            . ' characters - the game cuts it off in the channel list.');
    }
    return $name;
}

/**
 * A squad was renamed ($to) or deleted ($to null): carry its VON with it.
 *
 * The same job the squad page does for srSquadChannel and tfarNets, on the two
 * VON lists - its team net row is renamed or dropped, and it is renamed in or
 * taken out of every custom net's squad list. A net left with no squads stays:
 * it is still a channel somebody set up, and deleting it is a choice for the
 * VON tab.
 */
function ghostd_von_squad_moved(array &$items, string $from, ?string $to): void
{
    if (is_array($items['vonTeamNets'] ?? null)) {
        $keep = [];
        foreach ($items['vonTeamNets'] as $r) {
            if (is_array($r) && is_scalar($r[0] ?? null) && (string) $r[0] === $from) {
                if ($to === null) {
                    continue;
                }
                $r[0] = $to;
            }
            $keep[] = $r;
        }
        $items['vonTeamNets'] = $keep;
    }

    if (!is_array($items['vonNets'] ?? null)) {
        return;
    }
    foreach ($items['vonNets'] as $i => $r) {
        if (!is_array($r) || !is_array($r[2] ?? null)) {
            continue;
        }
        $squads = [];
        foreach ($r[2] as $s) {
            if (!is_scalar($s)) {
                continue;
            }
            $s = (string) $s;
            if ($s === $from) {
                if ($to === null) {
                    continue;
                }
                $s = $to;
            }
            if (!in_array($s, $squads, true)) {
                $squads[] = $s;
            }
        }
        $items['vonNets'][$i][2] = $squads;
    }
}

/**
 * A squad was copied: the copy joins the same custom nets.
 *
 * Its team net NAME is not copied - two squads answering to one name on the
 * Group channel is the mistake, and no name already means the copy's own.
 */
function ghostd_von_squad_copied(array &$items, string $from, string $to): void
{
    if (!is_array($items['vonNets'] ?? null)) {
        return;
    }
    foreach ($items['vonNets'] as $i => $r) {
        if (is_array($r) && is_array($r[2] ?? null)) {
            $on = array_map('strval', array_filter($r[2], 'is_scalar'));
            if (in_array($from, $on, true) && !in_array($to, $on, true)) {
                $items['vonNets'][$i][2][] = $to;
            }
        }
    }
}

/**
 * Every platoon the unit has, across ALL its orders of battle.
 *
 * A PLATOON IS DEFINED ONCE AND SELECTED INTO AN ORBAT. An order of battle is a
 * choice of which platoons are in it, not a second place to write them down -
 * so this is the pool the selection is made from. A platoon that only one ORBAT
 * has is still in the pool; that is how a new one gets into a second.
 *
 * Keyed by id. Where two ORBATs define the same id differently, the one in the
 * version being edited wins - it is the one in front of somebody.
 */
function ghostd_platoon_pool(string $prefer = ''): array
{
    $pool = [];

    // THE POOL DOCUMENT FIRST. A platoon written here survives being unticked
    // from every order of battle (2026-09-09: "no way to remove platoons form
    // an orbat" - unticking DID remove it, and because a platoon only existed
    // inside an ORBAT, removing it was deleting it). The ORBAT holds the
    // SELECTION; this holds the platoons themselves.
    try {
        $doc = ghostd_get(ghostd_config()['unit'] . '.platoons');
        foreach ((array) ($doc['items'] ?? []) as $pid => $p) {
            if (is_array($p) && (string) $pid !== '') {
                $pool[(string) $pid] = array_values($p);
            }
        }
    } catch (Throwable $e) {
        // No pool document yet - the orders of battle below are the pool.
    }

    foreach (array_merge([''], ghostd_orbat_variants()) as $v) {
        foreach (ghostd_orbat((string) $v)['platoons'] as $p) {
            $pid = (string) ($p[0] ?? '');
            if ($pid === '') {
                continue;
            }
            if (!isset($pool[$pid]) || (string) $v === $prefer) {
                $pool[$pid] = $p;
            }
        }
    }
    ksort($pool);
    return $pool;
}

/**
 * Put one platoon in the pool, so it outlives being unticked.
 *
 * Called wherever a platoon is written. The row is the ORBAT's own shape -
 * [id, name, callsign, net, [squads]] - because that is what everything reads.
 */
function ghostd_platoon_pool_put(array $row): void
{
    $pid = (string) ($row[0] ?? '');
    if ($pid === '') {
        return;
    }
    ghostd_set_path(ghostd_config()['unit'] . '.platoons', 'items.' . $pid, array_values($row));
}

/** Take one out of the pool - the platoon itself, not a selection. */
function ghostd_platoon_pool_delete(string $pid): void
{
    if ($pid === '') {
        return;
    }
    ghostd_unset_path(ghostd_config()['unit'] . '.platoons', 'items.' . $pid);
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
                // The kind of element - inf, mech_inf, air. The blue force
                // tracker draws the group with it; empty means infantry.
                'type'  => (string) ($g[3] ?? ''),
            ];
        }
    }
    return null;
}

/** A platoon's row in the ORBAT, or null. */
function ghostd_platoon(string $variant, string $pid): ?array
{
    // THE POOL, NOT THE ORDER OF BATTLE. A platoon exists in <unit>.platoons;
    // an order of battle only says which ones it RUNS. Looking it up inside
    // one meant the list offered all ten and opening any that the current
    // order of battle did not run answered "No platoon called C2Tropical in
    // this ORBAT" - every tropical platoon, because the default is vanilla
    // (2026-09-09). ghostd_platoon_pool() prefers this variant's own copy,
    // so an order of battle that overrides a platoon still wins.
    foreach (ghostd_platoon_pool($variant) as $pid2 => $p) {
        if ((string) $pid2 === $pid) {
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
