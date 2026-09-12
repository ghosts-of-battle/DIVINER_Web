<?php
/**
 * The Reforger config generator.
 *
 * Arma Reforger reads factions, group presets, rank ladders and radio plans as
 * .conf RESOURCES LOADED AT WORLD INIT. They cannot be built from the database
 * at runtime the way the Arma 3 mod builds its structure. So the unit edits the
 * ORBAT here, and this turns it into the files the Reforger mod ships.
 *
 *   <unit>.rf.orbat   platoons, squads, slots   ->  Configs/Factions/*.conf
 *   <unit>.rf.ranks   the rank ladder           ->  m_aRanks inside each faction
 *   <unit>.rf.radio   the comms plan            ->  Configs/freqs/freqs.conf
 *
 * OFF BY DEFAULT. A unit that does not play Reforger never sees any of this -
 * see ghostd_rf_enabled(). The switch lives on Web settings.
 *
 * THE GUIDS ARE THE DANGEROUS PART. Every Enfusion resource and nested config
 * object carries a 16-hex GUID, and other files reference them BY that GUID:
 *
 *   m_CallsignInfo ... : "{F18786934FF98733}Configs/Callsigns/callsigns_able.conf"
 *
 * If a regeneration mints new GUIDs, every reference breaks and Workbench treats
 * every object as new. So a GUID is derived from a STABLE IDENTITY and then
 * remembered in <unit>.rf.guids, and a RENAME LOOKS UP THE OLD IDENTITY AND
 * KEEPS ITS GUID rather than orphaning everything that points at it.
 *
 * Nothing here writes into the game. It produces text; the admin downloads it,
 * drops it into the addon, commits, builds, republishes.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ---------------------------------------------------------------- settings --

function ghostd_rf_doc_id(string $what): string
{
    return ghostd_config()['unit'] . '.rf.' . $what;
}

function ghostd_rf_settings_doc_id(): string
{
    return ghostd_config()['unit'] . '.web.reforger';
}

/**
 * The generator's own settings. Stored beside Branding's <unit>.web and the
 * home page's <unit>.web.home, for the same reason: written from the site,
 * follows the database, survives a redeploy.
 */
function ghostd_rf_settings(bool $reload = false): array
{
    // Cached for the request: render.php asks on every page to decide whether
    // to draw the tab, and that must not be a database round trip per render.
    // $reload is how a save invalidates it - a function static cannot be reset
    // from outside, so the caller has to say so.
    static $cache = null;
    if ($cache !== null && !$reload) {
        return $cache;
    }

    try {
        $doc = ghostd_get(ghostd_rf_settings_doc_id());
    } catch (Throwable $e) {
        $doc = null;
    }

    $bool = static fn($v, bool $default) => is_bool($v) ? $v : $default;

    $cache = [
        // OFF BY DEFAULT. An Arma-3-only unit should never meet this feature.
        'enabled'   => $bool($doc['enabled'] ?? null, false),
        // Per artefact, because a unit may want generated frequencies while
        // keeping hand-written factions.
        'factions'  => $bool($doc['factions'] ?? null, true),
        'radio'     => $bool($doc['radio'] ?? null, true),
        'callsigns' => $bool($doc['callsigns'] ?? null, true),
        // Per platoon: a platoon that is Arma-3-only or hand-maintained is
        // skipped. A platoon absent from this map is INCLUDED - opting out is
        // the deliberate act, so a new platoon is never silently left out.
        'skip'      => is_array($doc['skip'] ?? null) ? $doc['skip'] : [],
        'prefix'    => (string) ($doc['prefix'] ?? ghostd_config()['unit']),
    ];

    return $cache;
}

function ghostd_rf_settings_save(array $in): array
{
    $doc = [
        'section'   => 'web',
        'enabled'   => !empty($in['enabled']),
        'factions'  => !empty($in['factions']),
        'radio'     => !empty($in['radio']),
        'callsigns' => !empty($in['callsigns']),
        'skip'      => array_values(array_filter(array_map('strval', (array) ($in['skip'] ?? [])))),
        'prefix'    => trim((string) ($in['prefix'] ?? '')),
        'updatedAt' => gmdate('Y-m-d H:i:s'),
    ];

    if ($doc['prefix'] === '') {
        $doc['prefix'] = ghostd_config()['unit'];
    }

    ghostd_put(ghostd_rf_settings_doc_id(), $doc);

    // The read above is cached per request; without this the page would redraw
    // from the pre-save values and look as though nothing had been saved.
    ghostd_rf_settings(true);

    return $doc;
}

function ghostd_rf_enabled(): bool
{
    return ghostd_rf_settings()['enabled'];
}

// -------------------------------------------------------------- the ledger --

/**
 * A stable 16-hex GUID for one identity, remembered forever.
 *
 * The hash only ever SEEDS a new entry. Once an identity has a GUID the ledger
 * is authoritative, so regenerating an unchanged ORBAT produces byte-identical
 * files and nothing that points at them breaks.
 *
 * $rename lets a squad or platoon change its name without changing its GUID:
 * pass the old identity and it is carried across.
 */
function ghostd_rf_guid(array &$ledger, string $identity, string $rename = ''): string
{
    if (isset($ledger[$identity]) && is_string($ledger[$identity])) {
        return $ledger[$identity];
    }

    if ($rename !== '' && isset($ledger[$rename]) && is_string($ledger[$rename])) {
        $ledger[$identity] = $ledger[$rename];      // carried across, not minted
        unset($ledger[$rename]);
        return $ledger[$identity];
    }

    // Seed: the first 16 hex of a hash of the identity. Deterministic, so two
    // admins generating the same ORBAT independently get the same file.
    $guid = strtoupper(substr(hash('sha256', 'GFW|' . $identity), 0, 16));

    // A collision would silently alias two resources. Walk until it is unique.
    $taken = array_flip($ledger);
    $salt  = 0;
    while (isset($taken[$guid])) {
        $salt++;
        $guid = strtoupper(substr(hash('sha256', 'GFW|' . $identity . '|' . $salt), 0, 16));
    }

    $ledger[$identity] = $guid;
    return $guid;
}

function ghostd_rf_ledger_load(): array
{
    try {
        $doc = ghostd_get(ghostd_rf_doc_id('guids'));
    } catch (Throwable $e) {
        return [];
    }
    return is_array($doc['guids'] ?? null) ? $doc['guids'] : [];
}

function ghostd_rf_ledger_save(array $ledger): void
{
    ghostd_put(ghostd_rf_doc_id('guids'), [
        'section'   => 'rf',
        'guids'     => $ledger,
        'updatedAt' => gmdate('Y-m-d H:i:s'),
    ]);
}

// -------------------------------------------------- reading the .rf. docs --
//
// The Reforger documents carry the SAME SHAPES as the Arma 3 ones - that is the
// point of the shared contract - but they are separate keys, because the roles,
// frequencies and rank ladder genuinely differ between the games.

/** One version of the Reforger ORBAT, every list present. Mirrors ghostd_orbat(). */
function ghostd_rf_orbat(string $variant = ''): array
{
    $id = ghostd_rf_doc_id('orbat') . ($variant === '' ? '' : '.' . $variant);

    try {
        $doc = ghostd_get($id);
    } catch (Throwable $e) {
        $doc = null;
    }

    $arr = static fn($v) => is_array($v) ? array_values(array_filter($v, 'is_array')) : [];

    return [
        'faction'  => (string) ($doc['faction'] ?? ''),
        'radio'    => (string) ($doc['radioVersion'] ?? ''),
        // Reforger has no engine "side": every platoon is a Faction, and they
        // are allied to each other. factionKey replaces it - see the shared
        // contract. A missing key falls back to the A3 side, and says so.
        'factionKey' => (string) ($doc['factionKey'] ?? ''),
        'side'     => strtoupper((string) ($doc['side'] ?? '')),
        'platoons' => $arr($doc['platoons'] ?? null),
        'groups'   => $arr($doc['groups'] ?? null),
        'exists'   => is_array($doc),
    ];
}

/** The Reforger rank ladder, lowest first. */
function ghostd_rf_ranks(): array
{
    try {
        $doc = ghostd_get(ghostd_rf_doc_id('ranks'));
    } catch (Throwable $e) {
        return [];
    }

    $items = is_array($doc['items'] ?? null) ? $doc['items'] : [];
    $out = [];

    foreach ($items as $id => $r) {
        if (!is_array($r)) {
            continue;
        }
        $out[] = [
            'id'         => (string) $id,
            'name'       => (string) ($r['name'] ?? $id),
            'abbrev'     => (string) ($r['abbrev'] ?? ''),
            'engineRank' => strtoupper((string) ($r['engineRank'] ?? '')),
            'insignia'   => (string) ($r['insignia'] ?? ''),
            'order'      => (int) ($r['order'] ?? 0),
        ];
    }

    usort($out, static fn($a, $b) => $a['order'] <=> $b['order']);
    return $out;
}

/** The Reforger comms plan's items. */
function ghostd_rf_radio_items(string $variant = ''): array
{
    $id = ghostd_rf_doc_id('radio') . ($variant === '' ? '' : '.' . $variant);

    try {
        $doc = ghostd_get($id);
    } catch (Throwable $e) {
        return [];
    }

    return is_array($doc['items'] ?? null) ? $doc['items'] : [];
}

// ----------------------------------------------------------- .conf output --

/** A resource reference: "{GUID}path". */
function ghostd_rf_ref(string $guid, string $path): string
{
    return '"{' . $guid . '}' . $path . '"';
}

/** The .meta that sits beside every generated resource. */
function ghostd_rf_meta(string $guid, string $path): string
{
    return "MetaFileClass {\n"
         . ' Name "{' . $guid . '}' . $path . "\"\n"
         . " Configurations {\n"
         . "  CONFResourceClass PC {\n  }\n"
         . "  CONFResourceClass XBOX_ONE : PC {\n  }\n"
         . "  CONFResourceClass XBOX_SERIES : PC {\n  }\n"
         . "  CONFResourceClass PS4 : PC {\n  }\n"
         . "  CONFResourceClass PS5 : PC {\n  }\n"
         . "  CONFResourceClass HEADLESS : PC {\n  }\n"
         . " }\n"
         . "}\n";
}

/** A faction key is letters, digits and underscore - it is an identifier. */
function ghostd_rf_key(string $s): string
{
    $s = preg_replace('/[^A-Za-z0-9_]+/', '_', $s);
    return trim((string) $s, '_');
}

/** Escape a string for a .conf literal. */
function ghostd_rf_str(string $s): string
{
    // No backslash literals here on purpose: a doubled backslash does not
    // survive being written through layered quoting, and the halved version
    // is a parse error. chr(92) is unambiguous.
    $bs = chr(92);
    $s = str_replace($bs, $bs . $bs, $s);
    $s = str_replace('"', $bs . '"', $s);

    return '"' . $s . '"';
}

/**
 * A rank's engineRank, mapped onto a value Reforger actually has.
 *
 * THE LADDERS DO NOT MATCH. Arma 3 has COLONEL and Reforger does not; Reforger
 * has RENEGADE and Arma 3 does not. Writing "m_iRank COLONEL" into a faction
 * produces a config the engine cannot read, so the mapping happens HERE as well
 * as in the mod's GFW_RankMap - the two must agree.
 *
 * Returns '' for the lowest rung, which is emitted by OMITTING m_iRank entirely,
 * exactly as a working faction does.
 */
function ghostd_rf_engine_rank(string $want, array &$notes, string $rankId): string
{
    $want = strtoupper(trim($want));

    $known = ['CORPORAL', 'SERGEANT', 'LIEUTENANT', 'CAPTAIN', 'MAJOR', 'RENEGADE'];

    if ($want === '' || $want === 'PRIVATE') {
        return '';
    }

    if (in_array($want, $known, true)) {
        return $want;
    }

    if ($want === 'COLONEL') {
        // Arma 3's top rung. Reforger stops at MAJOR.
        $notes[] = 'Rank "' . $rankId . '" is COLONEL, which Reforger does not have - written as MAJOR.';
        return 'MAJOR';
    }

    $notes[] = 'Rank "' . $rankId . '" names engine rank "' . $want
             . '", which does not exist - written as the lowest rung.';
    return '';
}

/**
 * One platoon as an SCR_Faction .conf.
 *
 * Modelled on a real working faction, 183rd_ABLE.conf: the platoon IS the
 * faction, its squads are m_aPredefinedGroups, and every sibling platoon is
 * listed as friendly so they are one side that shares.
 *
 * m_aFriendlyFactionsIds is DERIVED, never typed. That removes the whole class
 * of bug where a new platoon is friendly to six of the seven others.
 */
function ghostd_rf_faction_conf(array $platoon, array $orbat, array $ranks,
                                array $siblings, array &$ledger, array $opts,
                                array &$notes): string
{
    $prefix   = $opts['prefix'];
    $pltId    = (string) ($platoon[0] ?? '');
    $pltName  = (string) ($platoon[1] ?? $pltId);
    $callsign = (string) ($platoon[2] ?? '');
    $squads   = array_values(array_filter(array_map('strval', (array) ($platoon[4] ?? []))));

    $key  = ghostd_rf_key($prefix . '_' . $pltId);
    $path = 'Configs/Factions/' . $key . '.conf';

    $out  = 'SCR_Faction : "{E7B7EA4823DD81F0}Configs/Factions/BLUFOR.conf" {' . "\n";
    $out .= ' FactionKey ' . ghostd_rf_str($key) . "\n";
    $out .= ' UIInfo SCR_FactionUIInfo "{' . ghostd_rf_guid($ledger, 'ui|' . $key) . '}" {' . "\n";
    $out .= '  Name ' . ghostd_rf_str($pltName) . "\n";
    $out .= '  m_sNameUpper ' . ghostd_rf_str(strtoupper($pltName)) . "\n";
    $out .= " }\n";

    // Every sibling, plus the parent faction. Derived.
    $out .= " m_aFriendlyFactionsIds {\n";
    if ($orbat['factionKey'] !== '') {
        $out .= '  ' . ghostd_rf_str($orbat['factionKey']) . "\n";
    }
    foreach ($siblings as $sib) {
        $out .= '  ' . ghostd_rf_str(ghostd_rf_key($prefix . '_' . $sib)) . "\n";
    }
    $out .= " }\n";

    // The platoon net, and the key that keeps it separate from another net on
    // the same band. ACRE had no equivalent of the encryption key.
    $netFreq = (int) ($opts['platoonFreq'][$pltId] ?? 0);
    if ($netFreq > 0) {
        $out .= ' m_iFactionRadioFrequency ' . $netFreq . "\n";
    }
    if ($callsign !== '') {
        $out .= ' "Radio encryption key" ' . ghostd_rf_str($key) . "\n";
    }

    // THE SQUADS. m_aPredefinedGroups plus m_bCreateOnlyPredefinedGroups is the
    // slot table at the squad layer, in config - players cannot invent groups,
    // so the ORBAT is the only structure that exists.
    $out .= " m_aPredefinedGroups {\n";
    foreach ($squads as $squad) {
        $roles = [];
        foreach ($orbat['groups'] as $g) {
            if (strcasecmp((string) ($g[0] ?? ''), $squad) === 0) {
                $roles = array_values(array_filter(array_map('strval', (array) ($g[1] ?? []))));
                break;
            }
        }

        $gGuid = ghostd_rf_guid($ledger, 'group|' . $key . '|' . $squad);
        $freq  = (int) ($opts['squadFreq'][$squad] ?? 0);

        $out .= '  SCR_GroupPreset "{' . $gGuid . '}" {' . "\n";
        $out .= '   m_sGroupName ' . ghostd_rf_str($squad) . "\n";
        // Size comes from the slot count: a squad with six billets holds six.
        $out .= '   m_iGroupSize ' . max(1, count($roles)) . "\n";
        if ($freq > 0) {
            $out .= '   m_iRadioFrequency ' . $freq . "\n";
        }
        $out .= "  }\n";
    }
    $out .= " }\n";
    $out .= " m_bCreateOnlyPredefinedGroups 1\n";
    $out .= " m_bEnableAutoGroupCreationWhenFull 0\n";

    // THE RANK LADDER, from <unit>.rf.ranks - the same source for every faction.
    if ($ranks) {
        $out .= " m_aRanks {\n";
        foreach ($ranks as $r) {
            $rGuid = ghostd_rf_guid($ledger, 'rank|' . $key . '|' . $r['id']);
            $out .= '  SCR_CharacterRank "{' . $rGuid . '}" {' . "\n";
            // The lowest rung omits m_iRank and takes the enum default, exactly
            // as a working faction does.
            $engine = ghostd_rf_engine_rank($r['engineRank'], $notes, $r['id']);
            if ($engine !== '') {
                $out .= '   m_iRank ' . $engine . "\n";
            }
            $out .= '   m_sRankName ' . ghostd_rf_str($r['name']) . "\n";
            $out .= '   m_sRankNameUpper ' . ghostd_rf_str(strtoupper($r['name'])) . "\n";
            if ($r['abbrev'] !== '') {
                $out .= '   m_sRankNameShort ' . ghostd_rf_str($r['abbrev']) . "\n";
            }
            if ($r['insignia'] !== '') {
                $out .= '   m_sInsignia ' . ghostd_rf_str($r['insignia']) . "\n";
            }
            $out .= "  }\n";
        }
        // RENEGADE is always emitted: the engine uses it regardless of what a
        // unit's ladder says, and a faction without it behaves oddly.
        $out .= '  SCR_CharacterRank "{' . ghostd_rf_guid($ledger, 'rank|' . $key . '|__renegade') . '}" {' . "\n";
        $out .= "   m_iRank RENEGADE\n";
        $out .= '   m_sRankName "Renegade"' . "\n";
        $out .= '   m_sRankNameUpper "RENEGADE"' . "\n";
        $out .= "  }\n";
        $out .= " }\n";
    }

    $out .= "}\n";

    return $out;
}

/**
 * The comms plan, as OETA_RadioPlanConfigRoot.
 *
 * DERIVE, DO NOT ENUMERATE. The 183rd's hand-written freqs.conf is 364 lines of
 * object tree with a Workbench GUID per row and every frequency typed by hand;
 * inserting a net means retyping the ones below it, and it had already drifted.
 * Here a squad's frequency comes from its ORBAT ROW ORDER - base + index * step
 * - which is the Reforger form of the mod's own
 * "channel = rowIndex * srBlockSize + 1". Reorder the ORBAT and every squad
 * moves correctly, with nothing to retype.
 */
function ghostd_rf_freqs_conf(array $plans, array &$ledger): string
{
    $out = "OETA_RadioPlanConfigRoot {\n Plans {\n";

    foreach ($plans as $name => $plan) {
        $pGuid = ghostd_rf_guid($ledger, 'plan|' . $name);
        $out .= '  OETA_RadioPlanConfig "{' . $pGuid . '}" {' . "\n";
        $out .= '   Name ' . ghostd_rf_str((string) $name) . "\n";

        if (!empty($plan['base'])) {
            $out .= '   BaseFrequenciesKHz ' . ghostd_rf_str(implode(' ', $plan['base'])) . "\n";
        }

        if (!empty($plan['slots'])) {
            $out .= "   Slots {\n";
            foreach ($plan['slots'] as $i => $slot) {
                $sGuid = ghostd_rf_guid($ledger, 'slot|' . $name . '|' . $slot['id']);
                $out .= '    OETA_RadioPlanSlotConfig "{' . $sGuid . '}" {' . "\n";
                $out .= '     FrequencyKHz ' . (int) $slot['freq'] . "\n";
                $out .= '     Text ' . ghostd_rf_str((string) $slot['text']) . "\n";
                $out .= "    }\n";
            }
            $out .= "   }\n";
        }

        $out .= "  }\n";
    }

    $out .= " }\n}\n";
    return $out;
}

/**
 * Generate everything, honouring the switches.
 *
 * Returns [ 'files' => [path => contents], 'notes' => [...], 'ledger' => [...] ].
 * NOTHING IS WRITTEN TO THE DATABASE HERE except, by the caller, the ledger -
 * a preview must not mint GUIDs that a download then disagrees with, so the
 * ledger the caller saves is the one this returned.
 */
function ghostd_rf_generate(): array
{
    $cfg   = ghostd_rf_settings();
    $notes = [];

    if (!$cfg['enabled']) {
        return ['files' => [], 'notes' => ['The generator is off.'], 'ledger' => []];
    }

    $ledger = ghostd_rf_ledger_load();
    $orbat  = ghostd_rf_orbat();
    $ranks  = ghostd_rf_ranks();
    $files  = [];

    if (!$orbat['exists']) {
        $notes[] = 'There is no ' . ghostd_rf_doc_id('orbat')
                 . ' document yet, so there is nothing to generate.';
        return ['files' => [], 'notes' => $notes, 'ledger' => $ledger];
    }

    if (!$ranks) {
        $notes[] = 'No ' . ghostd_rf_doc_id('ranks')
                 . ' - factions will be written without a rank ladder.';
    }

    if ($orbat['factionKey'] === '' && $orbat['side'] !== '') {
        $notes[] = 'The ORBAT has no factionKey, only the Arma 3 side "'
                 . $orbat['side'] . '". Reforger has no engine side - set a '
                 . 'factionKey (e.g. US) so the platoons ally with the right parent.';
    }

    // ---- frequencies, derived from ORBAT row order ----
    $radio       = ghostd_rf_radio_items($orbat['radio']);
    $base        = (int) ($radio['squadBaseKHz'] ?? 50000);
    $step        = (int) ($radio['squadStepKHz'] ?? 1000);
    $pltBase     = (int) ($radio['platoonBaseKHz'] ?? 500000);
    $pltStep     = (int) ($radio['platoonStepKHz'] ?? 10000);
    $squadFreq   = [];
    $platoonFreq = [];

    foreach ($orbat['groups'] as $i => $g) {
        $name = (string) ($g[0] ?? '');
        if ($name !== '') {
            $squadFreq[$name] = $base + ($i * $step);
        }
    }

    foreach ($orbat['platoons'] as $i => $p) {
        $id = (string) ($p[0] ?? '');
        if ($id !== '') {
            $platoonFreq[$id] = $pltBase + ($i * $pltStep);
        }
    }

    $opts = [
        'prefix'      => $cfg['prefix'],
        'squadFreq'   => $squadFreq,
        'platoonFreq' => $platoonFreq,
    ];

    // ---- factions, one per platoon ----
    $allIds = [];
    foreach ($orbat['platoons'] as $p) {
        $id = (string) ($p[0] ?? '');
        if ($id !== '' && !in_array($id, $cfg['skip'], true)) {
            $allIds[] = $id;
        }
    }

    if ($cfg['factions']) {
        foreach ($orbat['platoons'] as $p) {
            $id = (string) ($p[0] ?? '');
            if ($id === '' || in_array($id, $cfg['skip'], true)) {
                continue;
            }

            $siblings = array_values(array_diff($allIds, [$id]));
            $key      = ghostd_rf_key($cfg['prefix'] . '_' . $id);
            $path     = 'Configs/Factions/' . $key . '.conf';

            $files[$path] = ghostd_rf_faction_conf($p, $orbat, $ranks, $siblings, $ledger, $opts, $notes);
            $files[$path . '.meta'] = ghostd_rf_meta(
                ghostd_rf_guid($ledger, 'file|faction|' . $key), $path);
        }

        if (!$allIds) {
            $notes[] = 'The ORBAT has no platoons, so no factions were written.';
        }
    }

    // ---- the comms plan ----
    if ($cfg['radio']) {
        $plans = [];
        foreach ($orbat['platoons'] as $p) {
            $id = (string) ($p[0] ?? '');
            if ($id === '' || in_array($id, $cfg['skip'], true)) {
                continue;
            }

            $slots = [];
            foreach ((array) ($p[4] ?? []) as $squad) {
                $squad = (string) $squad;
                if ($squad === '' || !isset($squadFreq[$squad])) {
                    continue;
                }
                $slots[] = ['id' => $squad, 'freq' => $squadFreq[$squad], 'text' => $squad];
            }

            $plans[$id] = [
                'base'  => isset($platoonFreq[$id]) ? [$platoonFreq[$id]] : [],
                'slots' => $slots,
            ];
        }

        if ($plans) {
            $path = 'Configs/freqs/freqs.conf';
            $files[$path] = ghostd_rf_freqs_conf($plans, $ledger);
            $files[$path . '.meta'] = ghostd_rf_meta(
                ghostd_rf_guid($ledger, 'file|freqs'), $path);
        }
    }

    // One faction per platoon means a rank note would repeat per platoon.
    $notes = array_values(array_unique($notes));

    // KNOWN GAP, stated rather than hidden: ghostd_rf_guid() can carry a GUID
    // across a rename, but nothing calls it with the old identity yet, because
    // the site does not record squad and platoon renames. So TODAY a rename
    // mints a new GUID and anything referencing the old one must be re-pointed.
    // Regenerating an UNCHANGED order of battle is byte-identical - that part
    // works and is what protects the common case.
    return ['files' => $files, 'notes' => $notes, 'ledger' => $ledger];
}

/**
 * Send the generated files as a download, and exit.
 *
 * ZipArchive is the PECL zip extension and is NOT guaranteed to be installed -
 * this site deliberately has no Composer and no build step, so it cannot assume
 * one. Without it the files go out as a single annotated text bundle, which is
 * worse but never leaves an admin with nothing.
 */
function ghostd_rf_send_zip(array $files): void
{
    $stamp = gmdate('Ymd-His');

    if (class_exists('ZipArchive')) {
        $tmp = tempnam(sys_get_temp_dir(), 'gfw');
        $zip = new ZipArchive();

        if ($zip->open($tmp, ZipArchive::OVERWRITE) === true) {
            foreach ($files as $path => $body) {
                $zip->addFromString($path, $body);
            }
            $zip->close();

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="ghostframework-' . $stamp . '.zip"');
            header('Content-Length: ' . (string) filesize($tmp));
            readfile($tmp);
            unlink($tmp);
            exit;
        }

        unlink($tmp);
    }

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="ghostframework-' . $stamp . '.txt"');

    echo "The zip extension is not installed on this server, so here is every\n";
    echo "file as one document. Create each path and paste the block under it.\n";

    foreach ($files as $path => $body) {
        echo "\n";
        echo "================================================================\n";
        echo "FILE: " . $path . "\n";
        echo "================================================================\n";
        echo $body;
    }

    exit;
}
