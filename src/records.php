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
            'insignia' => ['label' => 'Insignia', 'kind' => 'text', 'help' => 'texture path'],
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
                          'help' => 'comma separated: medic:2, engineer:1, eod:1, trait:isJFO, var:name=value'],
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
            'image'    => ['label' => 'Image', 'kind' => 'text', 'help' => 'texture path, may be empty'],
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
