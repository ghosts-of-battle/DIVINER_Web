<?php
/**
 * A role, as a record - the thing behind every slot in the ORBAT.
 *
 * ONE DOCUMENT PER ROLE, <unit>.role.<id>, holding {section, id, role:{...}}.
 * That is the mod's arrangement, not a choice here: adding a role is writing a
 * new document, so it never rewrites the others and two people editing two
 * roles never collide.
 *
 * THE FIELD NAMES ARE THE MISSION'S, verbatim. ghostD_groups_fnc_roleFields is
 * the contract - name, description, icon, nets, tiles, traits, customVariables,
 * defaultLoadout and the four arsenal lists - plus what TAC//PAC
 * adds on top (minRank, requiredSkills, uids, arsenalWhitelist, defaultSkills,
 * slotTag). A role written in a config file and a role kept here are the same
 * record with the same keys, which is the only reason the mod can read either.
 *
 * ANYTHING NOT LISTED HERE RIDES ALONG UNTOUCHED. A save merges over the stored
 * record rather than replacing it, so a field the mod grows before this page
 * knows about it is not deleted by the next edit.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/templates.php';

/**
 * The live tiles a role may be given, from ghostD_tacpad_apps_fnc_tileData.
 *
 * A LIST, NOT FREE TEXT. A tile id that matches nothing is a permission that
 * grants nothing, and the band simply comes up short with nobody able to say
 * why. Kept here because the mod has no registry to read - the ids are written
 * into tileData itself.
 */
const GHOSTD_TILES = [
    'drones'  => 'Drone picture - contacts, bearing, range',
    'jam'     => 'Jamming state',
    'hack'    => 'Hacking suite',
    'weather' => 'Weather and light',
    'timer'   => 'Mission timer',
    'radio'   => 'Radio state',
    'intel'   => 'Intel take',
    'support' => 'Supports available',
    'pac'     => 'The PAC tile',
];

/**
 * The traits the ENGINE itself knows, from getAllUnitTraits.
 *
 * WHY THE DISTINCTION MATTERS. setUnitTrait takes a third argument saying
 * whether the name is a custom one; pass false for a name the engine does not
 * have and the trait is silently dropped. These seven are the only names that
 * take false. Anything else - a unit's own flag - needs custom set.
 *
 * Two of them are NUMBERS (audibleCoef, camouflageCoef, loadCoef take a
 * coefficient); the rest are true/false.
 */
// FOUR, AND THEY ARE ALL BOOLEANS (user, 2026-09-09). These are the traits
// that describe the JOB, so they belong to a role. The engine's numeric
// coefficients - audibleCoef, camouflageCoef, loadCoef, staminaDrainCoef -
// describe the UNIT and are set once for everybody on the order of battle,
// not per role.
const GHOSTD_ENGINE_TRAITS = [
    'medic'               => 'treat self and others with a medikit - attendant = 1',
    'engineer'            => 'partially repair vehicles with a toolkit - engineer = 1',
    'explosiveSpecialist' => 'defuse mines with a toolkit - canDeactivateMines = 1',
    'UAVHacker'           => 'hack enemy and friendly drones - uavHacker = 1',
];

/**
 * The engine's numeric coefficients, set on the ORDER OF BATTLE for everybody.
 *
 * A lower audibleCoef is harder to hear, a lower camouflageCoef is harder to
 * spot, loadCoef multiplies equipment weight against stamina, and
 * staminaDrainCoef (Arma 2.22) is how fast stamina goes - never negative.
 */
const GHOSTD_TRAIT_COEFS = [
    'audibleCoef'      => 'lower is harder to hear',
    'camouflageCoef'   => 'lower is harder to spot',
    'loadCoef'         => 'equipment weight against stamina',
    'staminaDrainCoef' => 'how fast stamina drains - never negative',
];

/**
 * ACE3's OWN VARIABLES - setVariable, names ACE defines and reads.
 *
 * THE SECOND OF THREE GROUPS, and the reason they are listed here rather than
 * typed into the unit's list: ACE spells them, not this unit, so a unit cannot
 * invent one and cannot get one wrong. Every role on the reference unit sets
 * all three.
 */
const GHOSTD_ACE_VARS = [
    'ace_medical_medicClass' => ['kind' => 'number',
        'help' => '0 none, 1 combat lifesaver, 2 medic - what ACE medical lets him do'],
    'ace_isEngineer'         => ['kind' => 'number',
        'help' => '0 none, 1 engineer, 2 advanced - repairs and mine work'],
    'ace_isEOD'              => ['kind' => 'bool',
        'help' => 'may defuse ACE explosives'],
];

/**
 * THE UNIT'S OWN VARIABLES, from <unit>.traits - name => [label, kind, help].
 *
 * THE THIRD OF THREE GROUPS. There are exactly three kinds of thing a role can
 * put on a man and they are not mixed (user, 2026-09-09: "there are 4 defaul
 * game s one then there ace3 customvarables then there are cutomer varables we
 * add"):
 *
 *   1. GHOSTD_ENGINE_TRAITS   setUnitTrait  - the game's four, a fixed list
 *   2. GHOSTD_ACE_VARS        setVariable   - ACE3's, a fixed list
 *   3. this                   setVariable   - the ones this unit invented
 *
 * THERE IS NO SUCH THING AS A CUSTOM TRAIT. This function used to return a
 * "where" saying trait or variable, as though a unit could invent a trait; on
 * the reference unit all twelve entries said "variable" and not one ever said
 * "trait", because a trait is only ever one of the four. The field is gone.
 *
 * The engine's and ACE's names are filtered out, so what comes back is only
 * the unit's own even if somebody typed one of the built-ins into the list.
 */
function ghostd_unit_vars(): array
{
    $out = [];
    try {
        require_once __DIR__ . '/records.php';
        foreach (ghostd_record_items('traits') as $id => $t) {
            $id = (string) $id;
            // ACE spells its own three and they get their own group; skip them
            // here so they are not listed twice.
            //
            // AN ENGINE TRAIT NAME IS NOT SKIPPED. UAVHacker is one of the four
            // AND a variable 57 roles set - config_co.hpp carries it in both
            // lists - so filtering engine names out of here dropped it off the
            // variables screen entirely (2026-09-09).
            if (isset(GHOSTD_ACE_VARS[$id])) {
                continue;
            }
            $kind = strtolower(trim((string) ($t['kind'] ?? 'bool')));
            $out[$id] = [
                'label' => trim((string) ($t['label'] ?? '')) ?: $id,
                'kind'  => $kind === 'number' ? 'number' : 'bool',
                'help'  => trim((string) ($t['help'] ?? '')),
            ];
        }
    } catch (Throwable $e) {
        return [];
    }
    return $out;
}

/**
 * The trait and variable names TAC//PAC has taken, which a role cannot set.
 *
 * THE SAME DERIVATION AS ghostD_pac_fnc_managedNames, and it has to stay the
 * same: the mod SKIPS these when it applies a role, so a role that sets one is
 * a role with a setting that does nothing and nothing in game says so. Six are
 * built in; the rest come from whatever the unit's skills declare, because a
 * skill setting "var:isRTO=true" has by that taken isRTO away from the roles.
 */
function ghostd_pac_owned(): array
{
    $names = ['medic', 'engineer', 'explosivespecialist',
              'ace_medical_medicclass', 'ace_isengineer', 'ace_iseod'];
    try {
        $doc = ghostd_get(ghostd_config()['unit'] . '.skills');
        foreach ((array) ($doc['items'] ?? []) as $s) {
            foreach ((array) (is_array($s) ? ($s['effects'] ?? []) : []) as $e) {
                $parts = explode(':', (string) $e, 2);
                if (count($parts) < 2) {
                    continue;
                }
                $kind = strtolower(trim($parts[0]));
                $val  = trim($parts[1]);
                if ($kind === 'trait') {
                    $names[] = strtolower($val);
                } elseif ($kind === 'var') {
                    $names[] = strtolower(trim(explode('=', $val)[0]));
                }
            }
        }
    } catch (Throwable $e) {
        // The built-in six are still right without the document.
    }
    return array_values(array_unique($names));
}

/**
 * Every variable a role may set: ACE3's, then the unit's own.
 *
 * ONE LIST FOR THE SCREEN AND THE SAVE, so a name that is offered is a name
 * that is written. Each entry keeps `group` so the editor can head them
 * separately - ACE3 spells its three, the unit spells the rest.
 */
function ghostd_role_vars(): array
{
    $out = [];
    foreach (GHOSTD_ACE_VARS as $n => $m) {
        $out[$n] = ['label' => $n, 'kind' => $m['kind'], 'help' => $m['help'], 'group' => 'ace'];
    }
    foreach (ghostd_unit_vars() as $n => $m) {
        $out[$n] = $m + ['group' => 'unit'];
    }
    return $out;
}

/** Which parts of a role are edited together. Drives the sections on the page. */
const GHOSTD_ROLE_SECTIONS = [
    'identity' => 'Identity',
    'gates'    => 'Who may take it',
    'nets'     => 'Messaging nets',
    'tiles'    => 'TAC//PAD tiles',
    'traits'   => 'Traits',
    'vars'     => 'Variables',
    'loadout'  => 'Default loadout',
    'arsenal'  => 'Arsenal',
    'remove'   => 'Remove',
];

function ghostd_role_doc_id(string $id): string
{
    return ghostd_config()['unit'] . '.role.' . $id;
}

/** A role id is a Dynamic_Roles class name - it has to be one in a config file. */
function ghostd_role_id_ok(string $id): bool
{
    return (bool) preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $id);
}

/** Every role id the unit has, sorted. */
function ghostd_role_ids(): array
{
    $prefix = ghostd_config()['unit'] . '.role.';
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
 * Rows of pairs or triples - nets, tiles, traits, customVariables - normalised.
 *
 * The mod writes them as arrays and reads them positionally, and a row short of
 * its values is read as empty rather than as an error. Padding here means every
 * caller can index without checking.
 */
function ghostd_role_rows($v, int $width): array
{
    $out = [];
    foreach ((array) $v as $row) {
        if (!is_array($row)) {
            continue;
        }
        $row = array_values($row);
        $name = trim((string) ($row[0] ?? ''));
        if ($name === '') {
            continue;
        }
        $r = [$name];
        for ($i = 1; $i < $width; $i++) {
            // A row written short means "the default". The only three-wide
            // field left is customVariables, whose third column is the GLOBAL
            // flag, and its default is true - the same default
            // ghostD_pac_fnc_roleFieldParse uses, so the two ends agree.
            $x = $row[$i] ?? ($i === 2 ? 'true' : '');
            // Numbers stay numbers - ace_medical_medicClass is 1, not "1", and
            // the mod's setVariable would put a string on the man.
            $r[] = is_int($x) || is_float($x) ? $x : (string) $x;
        }
        $out[] = $r;
    }
    return $out;
}

/** One role's record, every field present and the right shape. */
function ghostd_role(string $id): array
{
    $doc = null;
    try {
        $doc = ghostd_get(ghostd_role_doc_id($id));
    } catch (Throwable $e) {
        $doc = null;
    }
    $r = is_array($doc['role'] ?? null) ? $doc['role'] : [];

    $str  = static fn($k, $d = '') => (string) ($r[$k] ?? $d);
    $list = static fn($k) => array_values(array_filter(
        array_map(static fn($x) => trim((string) $x), (array) ($r[$k] ?? [])),
        static fn($x) => $x !== ''
    ));

    return [
        'id'              => $id,
        'name'            => $str('name'),
        'description'     => $str('description'),
        'icon'            => $str('icon'),
        'slotTag'         => $str('slotTag', $id),
        'minRank'         => $str('minRank'),
        'requiredSkills'  => $list('requiredSkills'),
        'defaultSkills'   => $list('defaultSkills'),
        'uids'            => $list('uids'),
        'nets'            => ghostd_role_rows($r['nets'] ?? [], 2),
        'tiles'           => ghostd_role_rows($r['tiles'] ?? [], 2),
        // THREE WIDE, not two. setUnitTrait's third argument says whether the
        // name is a custom one, and dropping it made every custom trait a
        // name the engine throws away without a word. Default "false", which
        // is what the mission files write when they omit it.
        // TWO WIDE - {name, value}, exactly what a config file writes:
        //     traits[] = { {"UAVHacker","true"} };
        // The third column was setUnitTrait's "custom" flag, and since a
        // trait is only ever one of the engine's four it was always false.
        // ghostD_groups_fnc_setupPlayer already defaults it - params
        // ["_trait","_value",["_custom","false"]] - so a pair applies the
        // same and the storage matches the file (user, 2026-09-09).
        'traits'          => ghostd_role_rows($r['traits'] ?? [], 2),
        'customVariables' => ghostd_role_rows($r['customVariables'] ?? [], 3),
        'defaultLoadout'  => is_array($r['defaultLoadout'] ?? null) ? $r['defaultLoadout'] : [],
        'arsenalWeapons'   => $list('arsenalWeapons'),
        'arsenalMagazines' => $list('arsenalMagazines'),
        'arsenalItems'     => $list('arsenalItems'),
        'arsenalBackpacks' => $list('arsenalBackpacks'),
        'arsenalWhitelist' => $list('arsenalWhitelist'),
        'exists'          => is_array($doc),
    ];
}

/**
 * Write some fields of a role, leaving the rest of the record alone.
 *
 * MERGE, NOT REPLACE. Each section of the editor posts only itself, so a save
 * that wrote the whole record would blank every field the form did not carry -
 * and would drop any field the mod has that this page does not know.
 */
function ghostd_role_save(string $id, array $fields): void
{
    if (!ghostd_role_id_ok($id)) {
        throw new RuntimeException('A role id is a config class name: letters, digits and underscore, starting with a letter.');
    }
    $docId = ghostd_role_doc_id($id);

    $doc = ghostd_get($docId);
    $doc = is_array($doc) ? $doc : [];
    unset($doc['_id']);

    $role = is_array($doc['role'] ?? null) ? $doc['role'] : [];
    foreach ($fields as $k => $v) {
        $role[$k] = $v;
    }
    $role['id'] = $id;

    $doc['section']   = 'role';
    $doc['id']        = $id;
    $doc['role']      = $role;
    $doc['from']      = 'DIVINER_Web';
    $doc['updatedAt'] = gmdate('Y-m-d H:i:s');

    ghostd_put($docId, $doc);
}

/** Rename a role: the document moves, and every squad slot naming it follows. */
function ghostd_role_rename(string $from, string $to): int
{
    if ($from === $to) {
        return 0;
    }
    if (!ghostd_role_id_ok($to)) {
        throw new RuntimeException('A role id is a config class name: letters, digits and underscore, starting with a letter.');
    }
    if (in_array($to, ghostd_role_ids(), true)) {
        throw new RuntimeException('A role called "' . $to . '" already exists.');
    }

    $doc = ghostd_get(ghostd_role_doc_id($from));
    if (!is_array($doc)) {
        throw new RuntimeException('No role called "' . $from . '".');
    }
    unset($doc['_id']);
    $doc['id'] = $to;
    if (is_array($doc['role'] ?? null)) {
        $doc['role']['id'] = $to;
    }
    $doc['updatedAt'] = gmdate('Y-m-d H:i:s');
    ghostd_put(ghostd_role_doc_id($to), $doc);

    // The squads that asked for the old id would silently stop filling those
    // slots, so they are moved with it - across every version of the ORBAT.
    // COPIED, MOVED, THEN REMOVED, in that order: a failure part way leaves the
    // old role still there rather than a squad pointing at nothing.
    $moved = ghostd_orbat_role_rename($from, $to);
    ghostd_doc_delete(ghostd_role_doc_id($from));
    return $moved;
}

/**
 * Point every squad slot naming $from at $to, in every version of the ORBAT.
 *
 * Returns how many slots moved.
 */
function ghostd_orbat_role_rename(string $from, string $to): int
{
    $unit = ghostd_config()['unit'];
    $moved = 0;
    foreach (ghostd_keys() as $k) {
        if ($k !== $unit . '.orbat' && !str_starts_with($k, $unit . '.orbat.')) {
            continue;
        }
        $doc = ghostd_get($k);
        if (!is_array($doc) || !is_array($doc['groups'] ?? null)) {
            continue;
        }
        $touched = false;
        foreach ($doc['groups'] as $gi => $g) {
            foreach ((array) ($g[1] ?? []) as $si => $slot) {
                if ((string) $slot === $from) {
                    $doc['groups'][$gi][1][$si] = $to;
                    $touched = true;
                    $moved++;
                }
            }
        }
        if ($touched) {
            unset($doc['_id']);
            $doc['updatedAt'] = gmdate('Y-m-d H:i:s');
            ghostd_put($k, $doc);
        }
    }
    return $moved;
}

/**
 * Remove a document outright.
 *
 * db.php has ghostd_unset_path for a field; a role IS a document, so removing
 * one needs this. It goes through the same write guard as every other write.
 */
function ghostd_doc_delete(string $id): bool
{
    ghostd_guard_write();

    // A DELETE IS BACKED UP LIKE A WRITE. ghostd_put copies the previous
    // version into the backup collection before replacing it; deleting did not,
    // so a document removed by a button was gone with only the nightly file
    // behind it (2026-09-09 - an order of battle went that way). Same shape as
    // ghostd_put's copy, so one restore reads both.
    try {
        $was  = ghostd_get($id);
        $coll = (string) (ghostd_config()['backup_collection'] ?? '');
        if (is_array($was) && $coll !== '') {
            unset($was['_id']);
            $b = new MongoDB\Driver\BulkWrite();
            $b->insert([
                'sourceId'   => $id,
                'backedUpAt' => gmdate('Y-m-d H:i:s'),
                'by'         => 'DIVINER_Web (delete)',
                'document'   => $was,
            ]);
            ghostd_manager()->executeBulkWrite(ghostd_ns($coll), $b);
        }
    } catch (Throwable $e) {
        // A backup that cannot be written must not stop the delete, but it is
        // the one thing worth saying out loud.
        error_log('ghostd_doc_delete: no backup for ' . $id . ' - ' . $e->getMessage());
    }

    $bulk = new MongoDB\Driver\BulkWrite();
    $bulk->delete(['_id' => $id], ['limit' => 1]);
    $res = ghostd_manager()->executeBulkWrite(ghostd_ns(), $bulk);
    return $res->getDeletedCount() > 0;
}

// ---------------------------------------------------------------------------
// The default loadout, in the shape a config file writes it.
//
// WHY THE CONFIG FORM AND NOT JSON. The array is copied out of a mission's
// config_*.hpp and pasted back into one; showing it in braces means copy and
// paste both work without anybody editing brackets. JSON is accepted too, so a
// paste from getUnitLoadout's output is not rejected.
// ---------------------------------------------------------------------------

/** A nested array of strings and numbers, written the way a config file does. */
function ghostd_sqf_encode($v, int $depth = 0, bool $brace = true): string
{
    // BRACES OR BRACKETS - and it is not a matter of taste. A config file
    // writes an array as {...}; an SQF FILE writes it as [...], and <unit>.pylons
    // and <unit>.logistics are SQF files kept in a document. Writing braces into
    // one of those makes the mod compile CODE instead of a table, and the crate
    // or the preset menu is simply empty with nothing said (found 2026-09-09).
    $open  = $brace ? '{' : '[';
    $close = $brace ? '}' : ']';
    if (is_array($v)) {
        $flat = true;
        foreach ($v as $x) {
            if (is_array($x)) { $flat = false; break; }
        }
        $parts = [];
        foreach ($v as $x) {
            $parts[] = ghostd_sqf_encode($x, $depth + 1, $brace);
        }
        // A row of plain values stays on its line; a list that holds lists is
        // broken up, so a loadout reads as one line per slot.
        if ($flat || $depth > 1) {
            return $open . implode(',', $parts) . $close;
        }
        $pad = str_repeat('    ', $depth + 1);
        return $open . "\n" . $pad . implode(",\n" . $pad, $parts) . "\n" . str_repeat('    ', $depth) . $close;
    }
    if (is_bool($v)) {
        return $v ? 'true' : 'false';
    }
    if (is_int($v) || is_float($v)) {
        return (string) $v;
    }
    return '"' . str_replace('"', '""', (string) $v) . '"';
}

/**
 * The same text back into an array.
 *
 * Braces become brackets and the whole thing goes through the JSON parser -
 * which is exact for what a loadout holds (strings, numbers and lists) and
 * refuses anything it is not, rather than half-parsing it. A brace inside a
 * string would break that, so strings are lifted out first.
 */
function ghostd_sqf_decode(string $text): array
{
    $text = trim($text);
    if ($text === '') {
        return [];
    }

    // Config files double a quote to escape it; JSON backslashes it.
    $strings = [];
    $lifted = preg_replace_callback('/"((?:[^"]|"")*)"/', static function ($m) use (&$strings) {
        $strings[] = str_replace('""', '"', $m[1]);
        return "\x01" . (count($strings) - 1) . "\x01";
    }, $text);
    if ($lifted === null) {
        throw new RuntimeException('That loadout has an unclosed quote.');
    }

    $lifted = strtr($lifted, ['{' => '[', '}' => ']']);
    // Comments, and the trailing semicolon a config line carries.
    $lifted = preg_replace('#//[^\n]*#', '', $lifted) ?? $lifted;
    $lifted = preg_replace('#/\*.*?\*/#s', '', $lifted) ?? $lifted;
    $lifted = trim($lifted);
    $lifted = rtrim($lifted, "; \t\n\r");
    // A trailing comma before a closing bracket is legal in a config and not in
    // JSON, and it is the single most common thing to be left behind by a cut.
    $lifted = preg_replace('/,\s*([\]])/', '$1', $lifted) ?? $lifted;

    $lifted = preg_replace_callback('/\x01(\d+)\x01/', static function ($m) use ($strings) {
        return json_encode($strings[(int) $m[1]], JSON_UNESCAPED_SLASHES);
    }, $lifted);

    $out = json_decode((string) $lifted, true);
    if (!is_array($out)) {
        throw new RuntimeException('That is not a loadout array. It should start with { and end with } '
            . '(or [ and ]) and hold only classnames, numbers and nested lists. '
            . (json_last_error() !== JSON_ERROR_NONE ? json_last_error_msg() . '.' : ''));
    }
    return $out;
}

/**
 * What a loadout actually puts on a man, in English.
 *
 * The array is unreadable and always will be; this is the check that the paste
 * went in the right way round. The order is getUnitLoadout's, which is the
 * order the config writes.
 */
function ghostd_loadout_summary(array $l): array
{
    $name = static function ($x) {
        if (is_array($x)) {
            return trim((string) ($x[0] ?? ''));
        }
        return trim((string) $x);
    };
    $slots = [
        0 => 'Primary', 1 => 'Secondary', 2 => 'Handgun', 3 => 'Uniform',
        4 => 'Vest', 5 => 'Backpack', 6 => 'Headgear', 7 => 'Facewear',
        8 => 'Binoculars', 9 => 'Linked items',
    ];
    $out = [];
    foreach ($slots as $i => $label) {
        if (!array_key_exists($i, $l)) {
            continue;
        }
        if ($i === 9) {
            $items = array_values(array_filter(array_map('strval', (array) $l[9]), static fn($x) => $x !== ''));
            if ($items !== []) {
                $out[] = [$label, implode(', ', $items)];
            }
            continue;
        }
        $n = $name($l[$i]);
        if ($n === '') {
            continue;
        }
        // A container slot carries what is in it; say how much rather than list it.
        $extra = '';
        if (in_array($i, [3, 4, 5], true) && is_array($l[$i]) && is_array($l[$i][1] ?? null)) {
            $c = count($l[$i][1]);
            $extra = ' - ' . $c . ' item' . ($c === 1 ? '' : 's') . ' in it';
        }
        $out[] = [$label, $n . $extra];
    }
    return $out;
}
