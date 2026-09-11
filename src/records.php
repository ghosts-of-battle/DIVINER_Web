<?php
/**
 * The unit's own records - the seven documents the PAC could edit and the site
 * could not.
 *
 * NOT CONFIG TEMPLATES. A unit has ONE rank ladder, one skill list, one set of
 * awards; they do not have versions and a mission does not choose between them.
 * That is why they are not in GHOSTD_TEMPLATES: a version picker on the rank
 * ladder would be a question nobody can answer.
 *
 * THE FIELDS ARE THE MOD'S. Every one below is a field
 * ghostD_pac_fnc_structFields declares for the same section, by the same name,
 * so the in-game editor and this one write the same document (user,
 * 2026-09-09: "make sure web us and the in game pac ui sync up"). A field added
 * there gets added here; anything either editor does not know about rides along
 * untouched on save.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/branding.php';    // GHOSTD_ASSET_TYPES, the same list

const GHOSTD_RECORDS = [
    'ranks' => [
        'label'  => 'Ranks',
        'blurb'  => 'The ladder. A rank maps to an Arma rank, which is what the engine uses for who leads a group.',
        'idHelp' => 'lower case, no spaces - private, sergeant',
        'fields' => [
            'name'     => ['label' => 'Name', 'kind' => 'text'],
            'abbrev'   => ['label' => 'Abbrev', 'kind' => 'text', 'help' => 'SGT'],
            'payGrade' => ['label' => 'Pay grade', 'kind' => 'text', 'help' => 'E-4'],
            'armaRank' => ['label' => 'Arma rank', 'kind' => 'text',
                           'help' => 'PRIVATE CORPORAL SERGEANT LIEUTENANT CAPTAIN MAJOR COLONEL'],
            'insignia' => ['label' => 'Insignia', 'kind' => 'image',
                           'help' => 'the game path - data\ranks\sgt.paa'],
        ],
    ],
    'skills' => [
        'label'  => 'Skills',
        'blurb'  => 'What a man is qualified to do. A role can require one, and the squad panel draws its letters.',
        'idHelp' => 'lower case, no spaces - cls, engineer',
        'fields' => [
            'name'    => ['label' => 'Name', 'kind' => 'text'],
            'abbrev'  => ['label' => 'Abbrev', 'kind' => 'text', 'help' => '2-4 letters for the squad panel'],
            'effects' => ['label' => 'Effects', 'kind' => 'list',
                          'datalist' => 'traitEffects',
                          'help' => 'comma separated: medic:2, engineer:1, eod:1, trait:isJFO, var:name=value - the trait and var names come from Custom traits'],
            'color'   => ['label' => 'Color', 'kind' => 'text', 'help' => 'R,G,B 0-255 - empty is the ink color'],
        ],
    ],
    'awards' => [
        'label'  => 'Awards',
        'blurb'  => 'Badges, ribbons and medals a man can hold. Shown on the operator file.',
        'idHelp' => 'lower case, no spaces - cib',
        'fields' => [
            'name'     => ['label' => 'Name', 'kind' => 'text'],
            'type'     => ['label' => 'Type', 'kind' => 'text', 'help' => 'badge / ribbon / medal'],
            'image'    => ['label' => 'Image', 'kind' => 'image',
                           'help' => 'the game path, may be empty'],
            'campaign' => ['label' => 'Campaign', 'kind' => 'text'],
        ],
    ],
    'statuses' => [
        'label'  => 'Statuses',
        'blurb'  => 'Where a man stands with the unit - active, leave, retired.',
        'idHelp' => 'lower case, no spaces',
        'fields' => [
            'name' => ['label' => 'Name', 'kind' => 'text'],
        ],
    ],
    'promotion' => [
        'label'  => 'Promotion',
        'blurb'  => 'The formula, as data. An id names a weight - hour, op, serviceMonth, gradeMonth, training, award - or a rung, rank_&lt;rankId&gt;, and the value is points per unit or points required.',
        'idHelp' => 'a weight name, or rank_&lt;rankId&gt;',
        'fields' => [
            'name'  => ['label' => 'What it is', 'kind' => 'text'],
            'value' => ['label' => 'Value', 'kind' => 'number'],
        ],
    ],
    'trainings' => [
        'label'  => 'Training',
        'blurb'  => 'The courses the unit runs. A course held is logged against its id, so a rename follows.',
        'idHelp' => 'lower case, no spaces - cls_course',
        'fields' => [
            'name'        => ['label' => 'Name', 'kind' => 'text'],
            'category'    => ['label' => 'Category', 'kind' => 'text', 'help' => 'Medical, Leadership, Fires...'],
            'description' => ['label' => 'Description', 'kind' => 'text'],
        ],
    ],
    // THE UNIT'S OWN TRAIT NAMES. One set for the whole unit, no versions, and
    // a role picks names off it - which is a config, not part of an order of
    // battle (user, 2026-09-09: "ar not traits part of the config").
    // THIS UNIT'S OWN VARIABLES - setVariable, the third of the three groups.
    // The game's four traits and ACE3's three variables are fixed lists in
    // src/roles.php and are not typed here. There is NO "custom trait": a
    // trait is only ever one of the engine's four, so the "Set as: trait or
    // variable" field this record used to carry is gone (2026-09-09 - on the
    // reference unit all twelve entries said "variable" and none ever said
    // "trait").
    //
    // EDITED ON THE ORBAT, not on Configs (2026-09-09: "this should be part of
    // the orbat"). The entry stays here because it is the registry every
    // editor reads - src/pages/records.php is what leaves it off the Configs
    // list, and src/pages/orbat_variables.php is what draws it.
    'traits' => [
        'label'     => 'Custom variables',
        'blurb'     => 'The setVariable names THIS UNIT invented - isLeader, draWhitelisted. The four game traits and ACE3&rsquo;s three variables are built in and are not listed here. Kept in one place so every role ticks the same spelling.',
        'idHelp'    => 'the name the mod reads - draWhitelisted, isRTO. No spaces',
        'idPattern' => '/^[A-Za-z_][A-Za-z0-9_]{0,63}$/',
        'fields'    => [
            'label' => ['label' => 'Shown as', 'kind' => 'text', 'help' => 'what the role editor calls it - DRA whitelisted'],
            'kind'  => ['label' => 'Kind', 'kind' => 'choice',
                        'options' => ['bool' => 'yes / no', 'number' => 'a number']],
            'help'  => ['label' => 'What it does', 'kind' => 'text', 'help' => 'one line, read by whoever decides whether a role should have it'],
        ],
    ],
    // HOW THE UNIT BEHAVES, as opposed to what it is made of. The same three
    // the in-game editor offers under EDIT STRUCTURE > SETTINGS, writing the
    // same <unit>.settings document - so there is one place per setting and
    // the two ends cannot disagree (2026-09-09: "ok where do you change auto
    // slot" - the answer was config_pac.hpp or raw JSON, which is no answer).
    //
    // NOT unitId, serverId OR sync: they say where the database is and who
    // this server is, and a database cannot tell a server where it lives.
    // NOT currentOrbat or currentOpord either - those are ticked LIVE where
    // the thing itself is edited, and two places to choose is one too many.
    'settings' => [
        'label'  => 'Settings',
        'blurb'  => 'How the unit behaves. Read at the NEXT mission start - a mission already running keeps what it booted with.',
        'idHelp' => 'one of autoSlot, slotMatch, savedLoadouts, autoPromote, newPlayers',
        'idPattern' => '/^(autoSlot|slotMatch|savedLoadouts|autoPromote|newPlayers)$/',
        'fields' => [
            'value' => ['label' => 'Value', 'kind' => 'text',
                        'help' => 'autoSlot 1 or 0 - slotMatch role or slot - savedLoadouts a count, 0 for off - autoPromote 1 or 0 - newPlayers auto or apply'],
        ],
    ],
    'admins' => [
        'label'     => 'Admins',
        'blurb'     => 'Who may open TAC//ADMIN and edit any of this. The id is a Steam id; the engine reads the mission\'s own list for the debug console.',
        'idHelp'    => 'a Steam id - 17 digits',
        'idPattern' => '/^\d{5,20}$/',
        'fields'    => [
            'name' => ['label' => 'Name', 'kind' => 'text'],
        ],
    ],
];

function ghostd_record_doc_id(string $section): string
{
    return ghostd_config()['unit'] . '.' . $section;
}

/** The items, keyed by id, exactly as stored. */
function ghostd_record_items(string $section): array
{
    try {
        $doc = ghostd_get(ghostd_record_doc_id($section));
    } catch (Throwable $e) {
        return [];
    }
    $items = is_array($doc['items'] ?? null) ? $doc['items'] : [];

    // THE SETTINGS DOCUMENT IS FLAT - autoSlot: 1, slotMatch: "role" - not a
    // map of records like every other section. The editor wants id => {value},
    // so it is turned into that here and turned back in ghostd_record_save.
    // Only the three that have no other home are offered; unitId, serverId and
    // sync are the server's, and currentOrbat / currentOpord are ticked LIVE
    // where the thing itself is edited.
    if ($section === 'settings') {
        $help = [
            'autoSlot'      => '1 puts a man with a role into its slot on spawn; 0 leaves him where he is',
            'slotMatch'     => 'role: any free slot with his role | slot: only the group his record names',
            'savedLoadouts' => 'kept loadouts per player per role; 0 turns them off',
            'autoPromote'   => '1 promotes a man the moment he is over the points for the next rank; 0 lists him on the dashboard for a human to do it',
            // NEW PLAYERS (2026-09-10): auto seeds a record on first connect;
            // apply makes them fill in an application (website or the PAC
            // tile) and an admin accepts them under Applications.
            'newPlayers'    => 'auto: a record is seeded the moment somebody first connects | apply: they apply (here or on the PAC tile) and an admin accepts them',
        ];
        $out = [];
        foreach ($help as $k => $h) {
            $v = $items[$k] ?? '';
            $out[$k] = ['name' => $h, 'value' => is_scalar($v) ? (string) $v : ''];
        }
        return $out;
    }

    $out = [];
    foreach ($items as $id => $it) {
        $out[(string) $id] = is_array($it) ? $it : ['name' => (string) $it];
    }
    return $out;
}

/**
 * Write the section back.
 *
 * The whole document is replaced, so what an editor did not show has to be
 * carried in by the caller - see the merge in src/pages/record.php. The admin
 * list also keeps its flat "ids" array, which is what the mod's own writer
 * produces and what the login check reads.
 */
function ghostd_record_save(string $section, array $items): void
{
    // BACK TO FLAT, and back to numbers. The mod tests autoSlot with
    // `isEqualTo 0`, which a string "0" never matches - so a value that looks
    // like a number is written as one. Everything the editor did not show is
    // carried across untouched: unitId, serverId, sync, currentOrbat and the
    // op windows all live in this document too.
    if ($section === 'settings') {
        $doc = ghostd_get(ghostd_record_doc_id($section));
        $doc = is_array($doc) ? $doc : [];
        unset($doc['_id']);
        $keep = is_array($doc['items'] ?? null) ? $doc['items'] : [];
        foreach ($items as $k => $rec) {
            $v = is_array($rec) ? (string) ($rec['value'] ?? '') : (string) $rec;
            $keep[(string) $k] = is_numeric($v) ? $v + 0 : $v;
        }
        $doc['section']   = 'settings';
        $doc['items']     = $keep;
        $doc['from']      = 'DIVINER_Web';
        $doc['updatedAt'] = gmdate('Y-m-d H:i:s');
        ghostd_put(ghostd_record_doc_id($section), $doc);
        return;
    }

    $doc = [
        'section'   => $section,
        'items'     => $items,
        'from'      => 'DIVINER_Web',
        'updatedAt' => gmdate('Y-m-d H:i:s'),
    ];
    if ($section === 'admins') {
        $ids = array_map('strval', array_keys($items));
        sort($ids);
        $doc['ids'] = $ids;
    }
    ghostd_put(ghostd_record_doc_id($section), $doc);
}

// ---- the pictures ---------------------------------------------------------
// TWO THINGS FOR ONE PICTURE (user, 2026-09-09: "anythigjn with an image needs
// 2 things, 1 a game path and only on thew web a local upload"). The game path
// is a .paa inside a mod and the browser cannot draw it; the upload is a PNG
// the site can. They are not alternatives - the game reads the path, the site
// shows the upload, and neither knows about the other.
//
// Stored the way the branding pictures are: base64 in a document, so there is
// no upload folder to create, no permissions to get wrong and nothing to lose
// when the server is rebuilt from git.

/** How big one record picture may be - an insignia, not a wallpaper. */
const GHOSTD_RECORD_IMAGE_MAX = 524288;

function ghostd_record_images_id(): string
{
    return ghostd_config()['unit'] . '.web.images';
}

/**
 * The key one picture is filed under.
 *
 * NO DOTS: the key is a field name inside the document and ghostd_set_path
 * reads a dot as a step down into it, so "ranks.sergeant.insignia" would make
 * three nested objects instead of one entry.
 */
function ghostd_record_image_key(string $section, string $id, string $field): string
{
    $safe = static fn(string $s): string => preg_replace('/[^A-Za-z0-9_-]+/', '-', $s) ?? '';
    return $safe($section) . '__' . $safe($id) . '__' . $safe($field);
}

/** Every stored picture, keyed. Read once per request. */
function ghostd_record_images(bool $fresh = false): array
{
    static $cache = null;
    if ($fresh) {
        $cache = null;
    }
    if ($cache === null) {
        $cache = [];
        try {
            $doc = ghostd_get(ghostd_record_images_id());
            $cache = is_array($doc['images'] ?? null) ? $doc['images'] : [];
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return $cache;
}

/** ['mime' => ..., 'data' => base64], or null. */
function ghostd_record_image(string $key): ?array
{
    $a = ghostd_record_images()[$key] ?? null;
    return (is_array($a) && ($a['data'] ?? '') !== '') ? $a : null;
}

/** Write one, or remove it when $mime is null. The rest are left alone. */
function ghostd_record_image_put(string $key, ?string $mime, string $bytes = ''): void
{
    $id = ghostd_record_images_id();
    if ($mime === null) {
        ghostd_unset_path($id, 'images.' . $key);
    } else {
        ghostd_set_path($id, 'images.' . $key,
            ['mime' => $mime, 'data' => base64_encode($bytes), 'at' => gmdate('Y-m-d H:i:s')]);
    }
    ghostd_record_images(true);
}

// ---- where the two lists meet ---------------------------------------------
// A SKILL SETS A CUSTOM TRAIT, and the name has to be the same name. The
// effects box offers what Custom traits holds so nobody invents a second
// spelling of isJFO (user, 2026-09-09: "in the config there ar skills those tie
// in to Custom traits so clean it up only one place"), and the traits page says
// which skill owns each name - a name a skill sets is applied by PAC and
// skipped on the role, so the role editor greys it out.

/** The values the skills' EFFECTS box offers. */
function ghostd_effect_options(): array
{
    $out = ['medic:1', 'medic:2', 'engineer:1', 'eod:1'];
    foreach (ghostd_record_items('traits') as $id => $t) {
        $where = strtolower(trim((string) ($t['where'] ?? 'variable')));
        $out[] = ($where === 'trait' ? 'trait:' : 'var:') . $id;
    }
    return $out;
}

/** Custom trait name => the skills that set it. */
function ghostd_trait_owners(): array
{
    $out = [];
    foreach (ghostd_record_items('skills') as $sid => $s) {
        foreach ((array) ($s['effects'] ?? []) as $e) {
            $parts = explode(':', (string) $e, 2);
            if (count($parts) < 2) {
                continue;
            }
            $kind = strtolower(trim($parts[0]));
            if (!in_array($kind, ['trait', 'var'], true)) {
                continue;
            }
            $name = trim(explode('=', trim($parts[1]))[0]);
            $out[strtolower($name)][] = (string) ($s['name'] ?? $sid);
        }
    }
    return $out;
}
