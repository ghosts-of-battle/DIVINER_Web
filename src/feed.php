<?php
/**
 * The public feed: what the unit's own website reads from TAC//PAC.
 *
 * OFF UNTIL A UNIT TURNS IT ON. DIVINER is run by more than one unit, and a
 * roster on the open web is each unit's decision, so the switch lives in the
 * unit's own document (<unit>.web.feed) and starts off. Each of the four
 * feeds - roster, order of battle, events, wiki - has its own tick as well.
 *
 * ONLY WHAT IS MEANT TO BE SEEN. The roster hands out a name, a rank, a
 * squad, a role, skill tags, a status and an enlistment date - what a unit
 * puts on a wall. Steam ids, Discord ids, emails, notes, loadouts, admin
 * actions and operator ids never leave this file; a player with publicHide
 * on their record is left out entirely. Events and wiki pages come through
 * only when marked public.
 *
 * READ WITH NO SESSION. The website fetches this from a visitor's browser, so
 * it answers without a cookie, allows any origin (the data is public by the
 * time it is here) and asks caches to keep it for a minute.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/branding.php';
require_once __DIR__ . '/records.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/orbat.php';
require_once __DIR__ . '/opords.php';
require_once __DIR__ . '/events.php';
require_once __DIR__ . '/wikipages.php';

const GHOSTD_FEEDS = [
    'roster' => 'Roster - name, rank, squad, role, skill tags, status',
    'orbat'  => 'Order of battle - the elements, their squads and who fills each slot',
    'events' => 'Events - the public ones, upcoming and recent',
    'wiki'   => 'Wiki - the public pages',
];

function ghostd_feed_doc_id(): string
{
    return ghostd_config()['unit'] . '.web.feed';
}

/** The switches, with every key present. Never fatal. */
function ghostd_feed_settings(): array
{
    try {
        $doc = ghostd_get(ghostd_feed_doc_id());
    } catch (Throwable $e) {
        $doc = null;
    }
    $out = ['enabled' => !empty($doc['enabled'])];
    foreach (GHOSTD_FEEDS as $k => $label) {
        $out[$k] = !isset($doc[$k]) || !empty($doc[$k]);   // on by default, behind the master switch
    }
    $out['updatedAt'] = (string) ($doc['updatedAt'] ?? '');
    return $out;
}

function ghostd_feed_settings_save(array $post): void
{
    $doc = ['section' => 'web', 'enabled' => !empty($post['enabled'])];
    foreach (GHOSTD_FEEDS as $k => $label) {
        $doc[$k] = !empty($post['feed_' . $k]);
    }
    ghostd_put(ghostd_feed_doc_id(), $doc);
}

/** The site's own address for a feed, absolute when the request tells us the host. */
function ghostd_feed_url(string $what, array $extra = []): string
{
    $q = http_build_query(['page' => 'feed', 'what' => $what] + $extra);
    $base = (string) (ghostd_config()['base_url'] ?? '');
    if ($base === '' && isset($_SERVER['HTTP_HOST'])) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $base = ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    }
    return rtrim($base, '/') . '/?' . $q;
}

/** A record picture the feed may hand out: rank insignia and award images only. */
function ghostd_feed_image_key_ok(string $key): bool
{
    return (bool) preg_match('/^(ranks|awards)__[A-Za-z0-9_-]+__(insignia|image)$/', $key);
}

function ghostd_feed_image_url(string $section, string $id, string $field): ?string
{
    $key = ghostd_record_image_key($section, $id, $field);
    if (!ghostd_feed_image_key_ok($key) || ghostd_record_image($key) === null) {
        return null;
    }
    return ghostd_feed_url('image', ['k' => $key]);
}

/** Answer and stop. */
function ghostd_feed_out(array $body, int $code = 200, int $ttl = 60): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: ' . ($ttl > 0 ? 'public, max-age=' . $ttl : 'no-store'));
    header('X-Content-Type-Options: nosniff');
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ---- the lookups the feeds share -----------------------------------------

/** id => item, from a {section, items} document; [] when it cannot be read. */
function ghostd_feed_items(string $section): array
{
    try {
        $doc = ghostd_get(ghostd_config()['unit'] . '.' . $section);
    } catch (Throwable $e) {
        return [];
    }
    $items = is_array($doc['items'] ?? null) ? $doc['items'] : [];
    return array_filter($items, 'is_array');
}

/** The ranks, in the order the document lists them, with their insignia. */
function ghostd_feed_ranks(): array
{
    $out = [];
    $i = 0;
    foreach (ghostd_feed_items('ranks') as $id => $r) {
        $out[(string) $id] = [
            'name'     => (string) ($r['name'] ?? $id),
            'abbrev'   => (string) ($r['abbrev'] ?? ''),
            'payGrade' => (string) ($r['payGrade'] ?? ''),
            'order'    => $i++,
            'insignia' => ghostd_feed_image_url('ranks', (string) $id, 'insignia'),
        ];
    }
    return $out;
}

function ghostd_feed_skills(): array
{
    $out = [];
    foreach (ghostd_feed_items('skills') as $id => $s) {
        $out[(string) $id] = [
            'name'   => (string) ($s['name'] ?? $id),
            'abbrev' => (string) ($s['abbrev'] ?? strtoupper((string) $id)),
            'color'  => (string) ($s['color'] ?? ''),
        ];
    }
    return $out;
}

function ghostd_feed_awards(): array
{
    $out = [];
    foreach (ghostd_feed_items('awards') as $id => $a) {
        $out[(string) $id] = [
            'name'  => (string) ($a['name'] ?? $id),
            'type'  => (string) ($a['type'] ?? ''),
            'image' => ghostd_feed_image_url('awards', (string) $id, 'image'),
        ];
    }
    return $out;
}

function ghostd_feed_statuses(): array
{
    $out = [];
    foreach (ghostd_feed_items('statuses') as $id => $s) {
        $out[(string) $id] = (string) ($s['name'] ?? $id);
    }
    return $out;
}

/** Role id => display name, one read per distinct role the roster uses. */
function ghostd_feed_role_names(array $ids): array
{
    $out = [];
    foreach (array_unique(array_filter($ids)) as $id) {
        $id = (string) $id;
        if (!ghostd_role_id_ok($id)) {
            continue;
        }
        $r = ghostd_role($id);
        $out[$id] = $r['exists'] && $r['name'] !== '' ? $r['name'] : $id;
    }
    return $out;
}

/** The unit's admin Steam ids - the feed calls them staff, and never hands the ids out. */
function ghostd_feed_admin_ids(): array
{
    try {
        $doc = ghostd_get(ghostd_config()['unit'] . '.admins');
    } catch (Throwable $e) {
        return [];
    }
    return array_map('strval', (array) ($doc['ids'] ?? []));
}

/**
 * The promotion formula, as the unit keeps it: what earns points and what
 * each rank needs. Operation values only - the site shows the rulebook.
 */
function ghostd_feed_promotion(): array
{
    $weights = [];
    $ladder = [];
    foreach (ghostd_feed_items('promotion') as $id => $it) {
        $id = (string) $id;
        $v = $it['value'] ?? 0;
        $v = is_numeric($v) ? $v + 0 : 0;
        if (str_starts_with($id, 'rank_')) {
            $ladder[substr($id, 5)] = $v;
        } else {
            $weights[$id] = ['name' => (string) ($it['name'] ?? $id), 'value' => $v];
        }
    }
    return ['weights' => $weights, 'ladder' => $ladder];
}

/** Role id => name, description and the rank it asks for, for the roles page. */
function ghostd_feed_role_info(array $ids): array
{
    $out = [];
    foreach (array_unique(array_filter($ids)) as $id) {
        $id = (string) $id;
        if (!ghostd_role_id_ok($id)) {
            continue;
        }
        $r = ghostd_role($id);
        if (!$r['exists']) {
            continue;
        }
        $out[$id] = [
            'name'        => $r['name'] !== '' ? $r['name'] : $id,
            'description' => $r['description'],
            'minRank'     => $r['minRank'],
        ];
    }
    return $out;
}

/** The store's players, minus anyone who asked not to be shown. */
function ghostd_feed_players(): array
{
    try {
        $store = ghostd_get(ghostd_config()['unit']);
    } catch (Throwable $e) {
        return [];
    }
    $players = is_array($store['players'] ?? null) ? $store['players'] : [];
    return array_filter($players, static fn($p) => is_array($p) && empty($p['publicHide']));
}

/** The one record shape the feed hands out. Nothing else is read from $p. */
function ghostd_feed_player_public(array $p): array
{
    $name = trim((string) ($p['milsimName'] ?? ''));
    if ($name === '') {
        $name = trim((string) ($p['name'] ?? ''));
    }
    $enlisted = (string) ($p['enlistedAt'] ?? '');
    return [
        'name'     => $name,
        'rank'     => (string) ($p['rankId'] ?? ''),
        'status'   => (string) ($p['statusId'] ?? ''),
        'group'    => (string) ($p['groupId'] ?? ''),
        'role'     => (string) ($p['roleId'] ?? ''),
        'skills'   => array_values(array_map('strval', (array) ($p['skillIds'] ?? []))),
        'awards'   => array_values(array_map(
            static fn($a) => is_array($a) ? (string) ($a['id'] ?? $a[0] ?? '') : (string) $a,
            (array) ($p['awards'] ?? [])
        )),
        'enlisted' => preg_match('/^\d{4}-\d{2}-\d{2}/', $enlisted) ? substr($enlisted, 0, 10) : '',
    ];
}

/** The order of battle as the feed sees it: elements, squads, slots. */
function ghostd_feed_elements(): array
{
    $o = ghostd_default_orbat();
    $variant = ghostd_default_orbat_id();
    $elements = [];
    foreach ($o['platoons'] as $row) {
        $squads = [];
        foreach (array_map('strval', (array) ($row[4] ?? [])) as $sname) {
            $s = ghostd_squad($variant, $sname);
            $squads[] = [
                'name'  => $sname,
                'type'  => $s !== null && $s['type'] !== '' ? $s['type'] : 'inf',
                'roles' => $s !== null ? $s['roles'] : [],
            ];
        }
        $elements[] = [
            'id'       => (string) ($row[0] ?? ''),
            'name'     => (string) ($row[1] ?? ''),
            'callsign' => (string) ($row[2] ?? ''),
            'net'      => (string) ($row[3] ?? ''),
            'squads'   => $squads,
        ];
    }
    return ['faction' => $o['faction'], 'side' => $o['side'], 'elements' => $elements];
}

// ---- the four feeds ----------------------------------------------------------

function ghostd_feed_roster(): array
{
    $players = ghostd_feed_players();
    $ob = ghostd_feed_elements();

    // Sorted the way the unit stands: element, squad, slot, then name. A man
    // in no squad goes to the end, by rank then name.
    $squadOrder = [];
    $slotOrder = [];
    $n = 0;
    foreach ($ob['elements'] as $el) {
        foreach ($el['squads'] as $sq) {
            $squadOrder[$sq['name']] = $n++;
            $slotOrder[$sq['name']] = $sq['roles'];
        }
    }
    $ranks = ghostd_feed_ranks();
    $admins = ghostd_feed_admin_ids();
    $rows = [];
    foreach ($players as $uid => $p) {
        $pub = ghostd_feed_player_public($p);
        if ($pub['name'] === '') {
            continue;
        }
        // STAFF ARE THE ADMINS (user, 2026-10-08): the id is matched here and
        // goes no further.
        $pub['staff'] = in_array((string) $uid, $admins, true);
        $sq = $squadOrder[$pub['group']] ?? 9999;
        $slot = 9999;
        if ($sq !== 9999) {
            $at = array_search($pub['role'], $slotOrder[$pub['group']], true);
            $slot = $at === false ? 9998 : $at;
        }
        $rank = $ranks[$pub['rank']]['order'] ?? -1;
        $rows[] = ['k' => [$sq, $slot, -$rank, strtolower($pub['name'])], 'p' => $pub];
    }
    usort($rows, static fn($a, $b) => $a['k'] <=> $b['k']);
    $list = array_column($rows, 'p');

    return [
        'unit'        => ghostd_unit_name(),
        'generatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
        'ranks'       => $ranks,
        'skills'      => ghostd_feed_skills(),
        'statuses'    => ghostd_feed_statuses(),
        'awards'      => ghostd_feed_awards(),
        'roles'       => ghostd_feed_role_names(array_column($list, 'role')),
        'promotion'   => ghostd_feed_promotion(),
        'players'     => $list,
    ];
}

function ghostd_feed_orbat(): array
{
    $ob = ghostd_feed_elements();
    $players = array_map('ghostd_feed_player_public', ghostd_feed_players());
    $ranks = ghostd_feed_ranks();

    // Who fills each slot: the first player of that squad with that role who
    // has not been seated yet, in slot order - the same rule the game slots by.
    $byGroup = [];
    foreach ($players as $p) {
        if ($p['group'] !== '' && $p['name'] !== '') {
            $byGroup[$p['group']][] = $p;
        }
    }
    $roleIds = [];
    foreach ($ob['elements'] as &$el) {
        foreach ($el['squads'] as &$sq) {
            $pool = $byGroup[$sq['name']] ?? [];
            $slots = [];
            foreach ($sq['roles'] as $role) {
                $roleIds[] = $role;
                $seat = null;
                foreach ($pool as $i => $p) {
                    if ($p['role'] === $role) {
                        $seat = ['name' => $p['name'], 'rank' => $p['rank'], 'rankName' => $ranks[$p['rank']]['name'] ?? ''];
                        unset($pool[$i]);
                        break;
                    }
                }
                $slots[] = ['role' => $role, 'player' => $seat];
            }
            $sq['slots'] = $slots;
            // Anyone in the squad whose role is not a slot of it still belongs on the chart.
            $sq['extra'] = array_values(array_map(
                static fn($p) => ['name' => $p['name'], 'rank' => $p['rank'], 'role' => $p['role']],
                $pool
            ));
            unset($sq['roles']);
        }
        unset($sq);
    }
    unset($el);

    return [
        'unit'        => ghostd_unit_name(),
        'generatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
        'faction'     => $ob['faction'],
        'side'        => $ob['side'],
        'roles'       => ghostd_feed_role_names($roleIds),
        'roleInfo'    => ghostd_feed_role_info($roleIds),
        'ranks'       => $ranks,
        'elements'    => $ob['elements'],
    ];
}

function ghostd_feed_events(): array
{
    $cal = ghostd_events();
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $floor = $now->modify('-' . GHOSTD_EVENTS_PAST_DAYS . ' days');
    [$up, $past] = ghostd_events_split($cal['items'], $now);

    $titles = [];
    $shape = static function (array $e) use (&$titles): array {
        $op = null;
        if ($e['opord'] !== '' && ghostd_opord_valid_id($e['opord'])) {
            if (!isset($titles[$e['opord']])) {
                $o = ghostd_opord($e['opord']);
                $titles[$e['opord']] = trim((string) ($o['header']['title'] ?? '')) ?: strtoupper(str_replace('_', ' ', $e['opord']));
            }
            $op = ['id' => $e['opord'], 'title' => $titles[$e['opord']]];
        }
        $end = '';
        if ($e['start'] !== '' && $e['minutes'] > 0) {
            $end = (new DateTimeImmutable($e['start']))->modify('+' . $e['minutes'] . ' minutes')->format('Y-m-d\TH:i:s\Z');
        }
        return [
            'id'        => $e['id'],
            'title'     => $e['title'],
            'start'     => $e['start'],
            'end'       => $end,
            'minutes'   => $e['minutes'],
            'server'    => $e['server'],
            'opord'     => $op,
            'summary'   => $e['summary'],
            'cancelled' => $e['cancelled'],
        ];
    };

    $upcoming = [];
    $next = null;
    foreach ($up as $e) {
        if (!$e['public']) {
            continue;
        }
        $row = $shape($e);
        $upcoming[] = $row;
        if ($next === null && !$e['cancelled'] && $e['start'] !== '') {
            $next = $row;
        }
    }
    $recent = [];
    foreach ($past as $e) {
        if (!$e['public']) {
            continue;
        }
        $when = ghostd_event_when($e, 'UTC');
        if ($when !== null && $when < $floor) {
            continue;
        }
        $recent[] = $shape($e);
    }
    return [
        'unit'        => ghostd_unit_name(),
        'generatedAt' => $now->format('Y-m-d\TH:i:s\Z'),
        'timezone'    => $cal['timezone'],
        'next'        => $next,
        'upcoming'    => $upcoming,
        'recent'      => $recent,
    ];
}

function ghostd_feed_wiki(): array
{
    $pages = [];
    foreach (ghostd_wiki_index(true) as $p) {
        $pages[] = ['slug' => $p['slug'], 'title' => $p['title'], 'updatedAt' => $p['updatedAt']];
    }
    return ['unit' => ghostd_unit_name(), 'generatedAt' => gmdate('Y-m-d\TH:i:s\Z'), 'pages' => $pages];
}

/** One public page, or null for a page that is private or not there. */
function ghostd_feed_page(string $slug): ?array
{
    $p = ghostd_wiki_page($slug);
    if ($p === null || !$p['public']) {
        return null;
    }
    return ['unit' => ghostd_unit_name(), 'slug' => $p['slug'], 'title' => $p['title'], 'html' => $p['html'], 'updatedAt' => $p['updatedAt']];
}
