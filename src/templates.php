<?php
/**
 * Config templates: the things a mission used to ship as files.
 *
 * ONE SET PER UNIT, NOT PER MISSION. Every mission that names the same unit id
 * reads the same documents, so Roomba and the framework missions share one set
 * of templates. That is the point of moving this out of the mission folder:
 * edit the net list once and every mission that unit runs has it.
 *
 * A REGISTRY, NOT TWELVE PAGES. Each config type declares its document, the
 * shape of one item and what to call each field; one editor page renders any of
 * them. Adding the next config type is an entry here, not a new page - which is
 * the only way twelve of these get finished rather than two.
 *
 * SHAPES. Not every config is a keyed list:
 *   'items'  <unit>.<doc> = {section, items:{id: {...fields}}} - the shape the
 *            mod already uses for nets, ranks, skills, statuses and the rest.
 *   'lists'  named lists of classnames - the arsenal shape. Not built yet.
 *   'code'   a block of SQF the mod compiles - logistics and pylons.
 *            Both have their own editor now (a crate list, a preset list);
 *            configedit_code.php is the fallback for any other code template.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

const GHOSTD_TEMPLATES = [
    'welcome' => [
        'label'    => 'Welcome screen',
        'doc'      => 'welcome',
        'shape'    => 'welcome',
        'replaces' => 'config_welcome.hpp',
        'blurb'    => "The panel every player sees once, at mission start. A title, a subtitle, and the text - typed the way it reads, with Arma's own tags for a heading, a color or an image.",
    ],
    'arsenal' => [
        'label'    => 'Common arsenal',
        'doc'      => 'arsenal',
        'shape'    => 'lists',
        'replaces' => 'config/arsenal/ (Common_Arsenal)',
        'blurb'    => 'Gear every player can draw, whatever their role. One list per kind. The mod merges every list here with the role\'s own group arsenal - and any list whose name starts with "items" is treated as items, which is why the names matter.',
        // THE NAMES ARE THE MISSION'S, verbatim - camelCase, as the .hpp files
        // spell them. fnc_setupPlayer merges by name and treats anything
        // beginning "items" as items, so a renamed list silently stops working.
        'lists'    => ['weapons', 'magazines', 'backpacks', 'itemsUniforms',
                       'itemsHeadgear', 'itemsVests', 'itemsFacewear', 'itemsNvgs',
                       'itemsOptics', 'itemsMuzzles', 'itemsPointersLights',
                       'itemsBipods', 'itemsBinoculars', 'itemsMedical', 'itemsTools'],
        'openNames' => true,
        'classKind' => 'all',
        // MORE THAN ONE ARSENAL. The common one is <unit>.arsenal; each variant
        // is <unit>.arsenal.<id>, and the id is the class name a role's
        // groupArsenal names - "Arsenal_Banshee" stays "Arsenal_Banshee", so
        // the roles need no editing.
        'variants'  => true,
        'variantOf' => 'groupArsenal',
    ],
    // 'radar' WAS HERE. The radar network is a CBA setting now
    // (ghostD_Settings_radarClasses, 2026-09-09) - a server admin types the
    // vehicle classes in the settings menu rather than editing a document.
    // ghostD_init_fnc_radarNetwork still adds <unit>.radar to whatever is
    // typed, so an old list keeps working; there is just nothing to edit here.

    'motorpool' => [
        'label'   => 'Motorpool',
        'doc'     => 'motorpool',
        'shape'   => 'items',
        'replaces' => 'config_motorpool_common.hpp (MotorPool_Common)',
        'blurb'   => 'The vehicles the motorpool offers, in categories. The id is the category, the name is its heading on screen.',
        'idHelp'  => 'One word, no spaces - Cars, Boats, Air, Armour.',
        'idPattern' => '/^[A-Za-z][A-Za-z0-9_]{0,31}$/',
        'fields'  => [
            'displayName' => ['label' => 'Heading', 'kind' => 'text',
                              'help' => 'Shown above the category on the motorpool screen. CARS, BOATS.'],
            'vehicles'    => ['label' => 'Vehicle classes', 'kind' => 'list',
                              'help' => 'One classname per line.'],
        ],
        'ordered' => true,
        'classKind' => 'vehicles',
    ],
    'cosmetics' => [
        'label'   => 'Vehicle cosmetics',
        'doc'     => 'cosmetics',
        'shape'   => 'items',
        'replaces' => 'config_cosmetics.hpp (GHOSTFR_Cosmetics)',
        'blurb'   => 'Paint schemes and fittings offered on a vehicle. Each entry names the vehicle class it applies to and the SQF that does it - _vehicle is the vehicle.',
        'idHelp'  => 'One word - Hunter_Green, MBT01_ToggleCamoNet.',
        'idPattern' => '/^[A-Za-z][A-Za-z0-9_]{0,63}$/',
        'fields'  => [
            'vehicle' => ['label' => 'Vehicle class', 'kind' => 'text',
                          'help' => 'The base class it is offered on - MRAP_01_base_F.'],
            'name'    => ['label' => 'Shown as', 'kind' => 'text', 'help' => 'Green Paint.'],
            'icon'    => ['label' => 'Icon', 'kind' => 'text', 'help' => 'Optional path.'],
            // ITS OWN ROW, UNDER THE REST. A block of SQF in a table cell is a
            // one-line box you cannot read three words of, and it squeezed the
            // four columns beside it. "block" means: not a column - a full
            // width box on the row underneath.
            'code'    => ['label' => 'SQF', 'kind' => 'block',
                          'help' => 'Runs with _vehicle set to the vehicle. A mistake here is a runtime error in game, not a build error.'],
        ],
        'ordered' => true,
        'classKind' => 'vehicles',
    ],
    'vehicleSpawner' => [
        'label'   => 'Vehicle spawner',
        'doc'     => 'vehicleSpawner',
        'shape'   => 'lists',
        'replaces' => 'config_vehicleSpawner.hpp (VehicleSpawner)',
        'blurb'   => 'What the engineer-course spawn pads offer, by kind.',
        'lists'   => ['ground', 'air', 'sea', 'static'],
        'openNames' => true,
        'classKind' => 'vehicles',
    ],
    'logistics' => [
        'label'   => 'Logistics',
        'doc'     => 'logistics',
        'shape'   => 'code',
        'replaces' => 'config_logistics.sqf',
        'blurb'   => 'The logistics table, as SQF. The mission used to compile this file; the unit can keep it here instead.',
        'global'  => 'missionConfig_logistics',
    ],
    'pylons' => [
        'label'   => 'Pylons',
        'doc'     => 'pylons',
        'shape'   => 'code',
        'replaces' => 'config_pylons.sqf',
        'blurb'   => 'Pylon loadouts, as SQF.',
        'global'  => 'missionConfig_pylons',
    ],
    // 'skill' WAS HERE. The AI skill block is twenty-two CBA sliders now
    // (2026-09-09) - general, aiming, spotting, what changes after dark, and
    // what a machinegunner or a sniper gets instead. A block of SQF nobody
    // could safely edit became numbers anybody can move.

    // THE UNIT'S OWN TRAIT NAMES. The engine has seven; everything else a role
    // puts on a man needs setUnitTrait's custom flag set, and a name with that
    // flag wrong is thrown away without a word. Listing them turns the role
    // editor's boxes into checkboxes and takes the flag out of anybody's hands.
    // Edited on the ORBAT page's Roles tab, beside the roles that assign them.
    'nets' => [
        'label'   => 'Messaging nets',
        'doc'     => 'nets',
        'shape'   => 'items',
        'replaces' => 'config_nets.hpp',
        'blurb'   => 'The nets TAC//MSG offers a mailbox for, and what each is for. A dot in an id makes it a sub-net of the one before it - "C2.reports" hangs under "C2". These are the names a role picks its nets from, and what a platoon commands on.',
        'idHelp'  => 'Short, upper-case, no spaces. C2, FIRES, LOG. A dot makes it a sub-net.',
        'idPattern' => '/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)*$/',
        'fields'  => [
            'name' => ['label' => 'What it is for', 'kind' => 'text',
                       'help' => 'Read by the people choosing a net, so say the job, not the frequency.'],
        ],
        'ordered' => true,
    ],
];

/**
 * Where a template's document lives.
 *
 * EVERY TYPE HAS VERSIONS. <unit>.<doc> is the common one and
 * <unit>.<doc>.<id> is a named version - of the arsenal, the nets, the welcome
 * screen, anything. The mod finds the named ones by listing the prefix, the
 * same way it finds roles and orders, so nothing needs a registry of names.
 */
function ghostd_template_doc_id(string $key, string $variant = ''): string
{
    $t = GHOSTD_TEMPLATES[$key] ?? null;
    if ($t === null) {
        throw new RuntimeException('No such template.');
    }
    $id = ghostd_config()['unit'] . '.' . $t['doc'];
    if ($variant !== '') {
        if (!ghostd_variant_ok($variant)) {
            throw new RuntimeException('A version id is letters, digits and underscore - "Arsenal_Banshee", not "Banshee arsenal".');
        }
        $id .= '.' . $variant;
    }
    return $id;
}

function ghostd_variant_ok(string $v): bool
{
    return (bool) preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $v);
}

/**
 * One template's items, in order, every field present.
 *
 * Normalised the same way for a stored document and for an empty one - a
 * fallback shaped differently from the real thing is a bug waiting to happen
 * (the application questions did exactly that).
 */
function ghostd_template_items(string $key, string $variant = ''): array
{
    $t = GHOSTD_TEMPLATES[$key];
    try {
        $doc = ghostd_get(ghostd_template_doc_id($key, $variant));
    } catch (Throwable $e) {
        return [];
    }
    $items = (is_array($doc['items'] ?? null)) ? $doc['items'] : [];

    $out = [];
    $n = 0;
    foreach ($items as $id => $it) {
        if (!is_array($it)) {
            continue;
        }
        $n += 10;
        $rec = ['id' => (string) $id, 'order' => (int) ($it['order'] ?? $n)];
        foreach ($t['fields'] as $f => $meta) {
            $v = $it[$f] ?? '';
            $rec[$f] = $meta['kind'] === 'list'
                ? array_values(array_map('strval', (array) $v))
                : (string) (is_array($v) ? implode(', ', $v) : $v);
        }
        $out[(string) $id] = $rec;
    }
    if (!empty($t['ordered'])) {
        uasort($out, static fn($a, $b) => $a['order'] <=> $b['order']);
    }
    return $out;
}

/**
 * Replace a template's items.
 *
 * The whole document goes at once, so ghostd_put copies the previous version
 * into the backup collection first. "from" records that the site wrote it, so a
 * later push from a mission file can be told apart from an edit made here.
 */
function ghostd_template_save(string $key, array $items, string $variant = ''): void
{
    $t = GHOSTD_TEMPLATES[$key];
    if ($items === []) {
        throw new RuntimeException('That would leave the template empty. The mission reads this at boot; an empty one is worse than an old one.');
    }
    ghostd_put(ghostd_template_doc_id($key, $variant), [
        'section'   => $t['doc'],
        'id'        => $variant,
        'items'     => $items,
        'from'      => 'DIVINER_Web',
        'updatedAt' => gmdate('Y-m-d H:i:s'),
    ]);
}

/** How many items each template holds, for the list page. Never throws. */
function ghostd_template_counts(): array
{
    $out = [];
    foreach (GHOSTD_TEMPLATES as $key => $t) {
        try {
            switch ($t['shape']) {
                case 'welcome':
                    $t = ghostd_welcome('')['text'];
                    $out[$key] = $t === '' ? 0 : substr_count($t, "\n") + 1;
                    break;
                case 'code':
                    // Lines of SQF - the only number that means anything here.
                    $c = ghostd_template_code($key);
                    $out[$key] = $c === '' ? 0 : substr_count($c, "\n") + 1;
                    break;
                case 'lists':
                    // Entries across every list, not the number of lists - the
                    // useful number is how much gear is in there.
                    $out[$key] = array_sum(array_map('count', ghostd_template_lists($key)));
                    break;
                default:
                    $out[$key] = count(ghostd_template_items($key));
            }
        } catch (Throwable $e) {
            $out[$key] = null;
        }
    }
    return $out;
}

// ---- the welcome shape ----------------------------------------------------
// A title, a subtitle and THE TEXT - one block of Arma structured text, typed
// with its own returns. It used to be a run of records, each with a size, a
// colour and an alignment, which is a worse version of the tags the game
// already reads (user, 2026-09-09: "instead of all this crap how about a
// simple editor that you can insert returns and set the html stuff arma uses").

const GHOSTD_WELCOME_ALIGN = [0 => 'left', 1 => 'center', 2 => 'right'];

/** {title, subtitle, text}, always complete. */
function ghostd_welcome(string $variant = ''): array
{
    $out = ['title' => '', 'subtitle' => '', 'text' => ''];
    try {
        $doc = ghostd_get(ghostd_template_doc_id('welcome', $variant));
    } catch (Throwable $e) {
        return $out;
    }
    if (!is_array($doc)) {
        return $out;
    }
    $out['title']    = (string) ($doc['title'] ?? '');
    $out['subtitle'] = (string) ($doc['subtitle'] ?? '');
    $out['text']     = (string) ($doc['text'] ?? '');

    // A welcome written before the text field is a run of line records. It is
    // rendered into the markup that says the same thing rather than making
    // somebody retype a briefing; saving stores the text and drops the lines.
    if ($out['text'] === '' && is_array($doc['lines'] ?? null)) {
        $out['text'] = ghostd_welcome_markup((array) $doc['lines']);
    }
    return $out;
}

/** The old [{text,size,colour,align}] records as structured text. */
function ghostd_welcome_markup(array $lines): string
{
    $out = [];
    foreach ($lines as $l) {
        if (!is_array($l)) {
            continue;
        }
        $text = (string) ($l['text'] ?? '');
        $attr = [];

        // Only what differs from the body default is written out - a paragraph
        // stays a paragraph, and the tags mark the headings.
        $size = round((float) ($l['size'] ?? 0.9), 2);
        if (abs($size - 0.9) > 0.001) {
            $attr[] = "size='" . $size . "'";
        }
        $hex = ghostd_rgb_to_hex((array) ($l['colour'] ?? $l['color'] ?? [1, 1, 1]));
        if ($hex !== '#ffffff') {
            $attr[] = "color='" . $hex . "'";
        }
        $align = (int) ($l['align'] ?? 0);
        if ($align !== 0) {
            $attr[] = "align='" . (GHOSTD_WELCOME_ALIGN[$align] ?? 'left') . "'";
        }

        $out[] = $attr === [] ? $text : '<t ' . implode(' ', $attr) . '>' . $text . '</t>';
    }
    return implode("\n", $out);
}

function ghostd_welcome_save(string $title, string $subtitle, string $text, string $variant = ''): void
{
    if (trim($text) === '') {
        throw new RuntimeException('A welcome screen with no text is just a title bar. Write something, or leave the whole thing empty by removing the document.');
    }
    ghostd_put(ghostd_template_doc_id('welcome', $variant), [
        'section'   => 'welcome',
        'id'        => $variant,
        'title'     => $title,
        'subtitle'  => $subtitle,
        'text'      => $text,
        'from'      => 'DIVINER_Web',
        'updatedAt' => gmdate('Y-m-d H:i:s'),
    ]);
}

/** The game stores colour as three 0-1 floats; a colour input speaks #rrggbb. */
function ghostd_rgb_to_hex(array $c): string
{
    return sprintf('#%02x%02x%02x',
        max(0, min(255, (int) round(((float) ($c[0] ?? 1)) * 255))),
        max(0, min(255, (int) round(((float) ($c[1] ?? 1)) * 255))),
        max(0, min(255, (int) round(((float) ($c[2] ?? 1)) * 255))));
}

function ghostd_hex_to_rgb(string $hex): array
{
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
        return [1, 1, 1];
    }
    return [
        round(hexdec(substr($hex, 1, 2)) / 255, 3),
        round(hexdec(substr($hex, 3, 2)) / 255, 3),
        round(hexdec(substr($hex, 5, 2)) / 255, 3),
    ];
}

// ---- the lists shape ------------------------------------------------------
// A document of named arrays: {section, lists: {weapons: [...], ...}}. The
// arsenal and the radar network are both this; they differ only in how many
// names they have and whether new ones may be invented.

/** {listName: [classnames]}, every declared name present even when empty. */
function ghostd_template_lists(string $key, string $variant = ''): array
{
    $t = GHOSTD_TEMPLATES[$key];
    $stored = [];
    try {
        $doc = ghostd_get(ghostd_template_doc_id($key, $variant));
        $stored = (is_array($doc['lists'] ?? null)) ? $doc['lists'] : [];
    } catch (Throwable $e) {
        $stored = [];
    }

    $out = [];
    foreach ($t['lists'] as $name) {
        $out[$name] = array_values(array_map('strval', (array) ($stored[$name] ?? [])));
    }
    // Names the unit added that the registry does not declare - kept, not lost.
    foreach ($stored as $name => $v) {
        if (!isset($out[$name])) {
            $out[(string) $name] = array_values(array_map('strval', (array) $v));
        }
    }
    return $out;
}

function ghostd_template_lists_save(string $key, array $lists, string $variant = ''): void
{
    $t = GHOSTD_TEMPLATES[$key];
    $clean = [];
    foreach ($lists as $name => $vals) {
        $name = trim((string) $name);
        if ($name === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $name)) {
            continue;
        }
        // A PASTE FROM A CONFIG FILE JUST WORKS. Nobody types these - they come
        // out of a .hpp, where every line is  "classname",  and out of the
        // arsenal export, where they are quoted too. Making somebody strip the
        // quotes and commas by hand is asking them to introduce a typo into a
        // list of two hundred names, so the quotes, commas and semicolons come
        // off here. A classname has none of those characters in it, so nothing
        // real is lost.
        $clean[$name] = array_values(array_unique(array_filter(
            array_map(static fn($x) => trim((string) $x, " 	
\"',;"), (array) $vals),
            static fn($x) => $x !== ''
        )));
    }
    // AN EMPTY VARIANT IS A REAL THING; AN EMPTY COMMON IS NOT. A per-element
    // arsenal that starts empty is how a mission says "this element has nothing
    // of its own yet" - the framework ships five of them. The COMMON arsenal is
    // what everybody draws from, so emptying that is a server full of people
    // with nothing to pull, and it stays refused.
    if ($clean === [] && $variant === '') {
        throw new RuntimeException('Every list is empty. The mission reads this at boot - an empty arsenal is a server full of people with nothing to draw.');
    }
    ghostd_put(ghostd_template_doc_id($key, $variant), [
        'section'   => $t['doc'],
        'id'        => $variant,
        'lists'     => $clean,
        'from'      => 'DIVINER_Web',
        'updatedAt' => gmdate('Y-m-d H:i:s'),
    ]);
}

/**
 * The classnames the mod exported, for the pickers.
 *
 * Written by the game (an admin presses EXPORT CLASSES), because only the game
 * knows what is actually loaded - a list typed by hand goes stale the day a mod
 * is added. Absent is fine: the editors fall back to plain text boxes, so a
 * unit that has never run the export can still type classnames.
 */
function ghostd_classnames(string $kind = 'all'): array
{
    static $doc = null;
    if ($doc === null) {
        try {
            $doc = ghostd_get(ghostd_config()['unit'] . '.classes');
        } catch (Throwable $e) {
            $doc = [];
        }
        if (!is_array($doc)) {
            $doc = [];
        }
    }
    $lists = (is_array($doc['lists'] ?? null)) ? $doc['lists'] : [];
    if ($kind !== 'all') {
        return array_values(array_map('strval', (array) ($lists[$kind] ?? [])));
    }
    $all = [];
    foreach ($lists as $v) {
        foreach ((array) $v as $c) {
            $all[] = (string) $c;
        }
    }
    sort($all);
    return array_values(array_unique($all));
}

// ---- variants -------------------------------------------------------------
// A template type may have more than one version: the common one lives at
// <unit>.<doc> and each variant at <unit>.<doc>.<id>. The mod fetches the
// common document and then lists the prefix, exactly as it does for roles.

/** Every variant id stored for a template, sorted. */
function ghostd_template_variants(string $key): array
{
    $t = GHOSTD_TEMPLATES[$key];
    $prefix = ghostd_config()['unit'] . '.' . $t['doc'] . '.';
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

/** Kept for the lists editor; ghostd_template_lists does the work now. */
function ghostd_variant_lists(string $key, string $variant = ''): array
{
    return ghostd_template_lists($key, $variant);
}

function ghostd_variant_lists_unused(string $key, string $variant = ''): array
{
    $t = GHOSTD_TEMPLATES[$key];
    if ($variant === '') {
        return ghostd_template_lists($key);
    }
    $stored = [];
    try {
        $doc = ghostd_get(ghostd_config()['unit'] . '.' . $t['doc'] . '.' . $variant);
        $stored = (is_array($doc['lists'] ?? null)) ? $doc['lists'] : [];
    } catch (Throwable $e) {
        $stored = [];
    }
    $out = [];
    foreach ($t['lists'] as $name) {
        $out[$name] = array_values(array_map('strval', (array) ($stored[$name] ?? [])));
    }
    foreach ($stored as $name => $v) {
        if (!isset($out[$name])) {
            $out[(string) $name] = array_values(array_map('strval', (array) $v));
        }
    }
    return $out;
}

function ghostd_variant_save(string $key, string $variant, array $lists): void
{
    ghostd_template_lists_save($key, $lists, $variant);
}

function ghostd_variant_save_unused(string $key, string $variant, array $lists): void
{
    $t = GHOSTD_TEMPLATES[$key];
    if ($variant === '') {
        ghostd_template_lists_save($key, $lists);
        return;
    }
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $variant)) {
        throw new RuntimeException('A variant id is a class name - letters, digits and underscore. "Arsenal_Banshee", not "Banshee arsenal".');
    }
    $clean = [];
    foreach ($lists as $name => $vals) {
        $name = trim((string) $name);
        if ($name === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $name)) {
            continue;
        }
        $clean[$name] = array_values(array_unique(array_filter(
            array_map('trim', (array) $vals), static fn($x) => $x !== ''
        )));
    }
    ghostd_put(ghostd_config()['unit'] . '.' . $t['doc'] . '.' . $variant, [
        'section'   => $t['doc'],
        'id'        => $variant,
        'lists'     => $clean,
        'from'      => 'DIVINER_Web',
        'updatedAt' => gmdate('Y-m-d H:i:s'),
    ]);
}

// ---- the code shape -------------------------------------------------------
// SQF held as text. This is the shape with real teeth: a mistake saved here is
// a runtime error in game, with no hemtt check between the two. The editor says
// so, the mod compiles it defensively, and a document that will not compile is
// ignored in favour of the mission's own file.

function ghostd_template_code(string $key, string $variant = ''): string
{
    try {
        $doc = ghostd_get(ghostd_template_doc_id($key, $variant));
    } catch (Throwable $e) {
        return '';
    }
    return (string) ($doc['code'] ?? '');
}

function ghostd_template_code_save(string $key, string $code, string $variant = ''): void
{
    $t = GHOSTD_TEMPLATES[$key];

    // The cheapest sanity a web server can offer for SQF it cannot run:
    // balanced brackets. It catches the paste that lost its last line.
    foreach ([['{', '}'], ['[', ']'], ['(', ')']] as $pair) {
        if (substr_count($code, $pair[0]) !== substr_count($code, $pair[1])) {
            throw new RuntimeException(
                'Unbalanced ' . $pair[0] . $pair[1] . ' - ' . substr_count($code, $pair[0]) .
                ' opening and ' . substr_count($code, $pair[1]) . ' closing. Nothing was saved.'
            );
        }
    }
    ghostd_put(ghostd_template_doc_id($key, $variant), [
        'section'   => $t['doc'],
        'id'        => $variant,
        'code'      => $code,
        'global'    => $t['global'] ?? '',
        'from'      => 'DIVINER_Web',
        'updatedAt' => gmdate('Y-m-d H:i:s'),
    ]);
}

// ---- the arsenal and motorpool layers -------------------------------------
// Gear is merged in four layers, narrowest last:
//
//   common          <unit>.arsenal                 everybody
//   platoon         <unit>.arsenal.plt_<id>        everyone in that platoon
//   squad           <unit>.arsenal.sqd_<name>      everyone in that squad
//   role            the role document's own arsenalWeapons/Items/... and its
//                   groupArsenal variant
//
// The document name is DERIVED from the platoon id or squad name rather than
// stored anywhere, so adding a platoon arsenal is creating a document and
// nothing else - no field to set, nothing to keep in step. The mod computes
// the same name with the same rule.

/** "BANSHEE 1-1" -> "BANSHEE_1_1". Upper case, anything else an underscore. */
function ghostd_slug(string $s): string
{
    $s = strtoupper(trim($s));
    $s = preg_replace('/[^A-Z0-9]+/', '_', $s) ?? $s;
    return trim($s, '_');
}

function ghostd_platoon_variant(string $platoonId): string
{
    return 'plt_' . ghostd_slug($platoonId);
}

function ghostd_role_variant(string $roleId): string
{
    return 'role_' . ghostd_slug($roleId);
}

function ghostd_squad_variant(string $squadName): string
{
    return 'sqd_' . ghostd_slug($squadName);
}

/**
 * A setting out of <unit>.settings, which is where the mod keeps them.
 *
 * The two that matter here name WHICH VERSION IS IN USE: currentArsenal picks
 * the common arsenal (the bare-bones one, or the camo set an operation is in)
 * and currentOrbat picks the order of battle. Both are read at mission start.
 */
/**
 * The setting that names which VERSION of a template is in use.
 *
 * "current" and the document's name - currentArsenal, currentNets,
 * currentMotorpool. The same rule the mod uses (ghostD_pac_fnc_svcSections), so
 * a template type added here is steerable from a mission without either side
 * being told about it separately.
 *
 * Empty for the unit's own records - ranks, skills, the trait catalogue. A unit
 * has one of each of those whatever mission is running.
 */
function ghostd_template_setting(string $key): string
{
    $t = GHOSTD_TEMPLATES[$key] ?? null;
    if ($t === null || in_array($key, ['traits'], true)) {
        return '';
    }
    return 'current' . ucfirst($t['doc']);
}

function ghostd_setting(string $key, string $default = ''): string
{
    try {
        $doc = ghostd_get(ghostd_config()['unit'] . '.settings');
        $v = ($doc['items'] ?? [])[$key] ?? $default;
        return is_scalar($v) ? (string) $v : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function ghostd_setting_save(string $key, string $value): void
{
    $id = ghostd_config()['unit'] . '.settings';
    $doc = ghostd_get($id);
    $doc = is_array($doc) ? $doc : [];
    unset($doc['_id']);
    $items = is_array($doc['items'] ?? null) ? $doc['items'] : [];
    // unitId, serverId and sync say where the database is and which server this
    // is; a website cannot tell a server either of those, and the mod ignores
    // them from here anyway.
    if (in_array($key, ['unitId', 'serverId', 'sync'], true)) {
        throw new RuntimeException('That setting belongs to the server, not the database.');
    }
    $items[$key] = $value;
    $doc['section']   = 'settings';
    $doc['items']     = $items;
    $doc['from']      = 'DIVINER_Web';
    $doc['updatedAt'] = gmdate('Y-m-d H:i:s');
    ghostd_put($id, $doc);
}

/** Does a variant document exist? Used to show "set up" against a link. */
function ghostd_variant_exists(string $key, string $variant): bool
{
    try {
        return ghostd_get(ghostd_template_doc_id($key, $variant)) !== null;
    } catch (Throwable $e) {
        return false;
    }
}
