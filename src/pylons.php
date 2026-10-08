<?php
/**
 * The pylon presets, as a tree: vehicle, then preset, then its magazines.
 *
 * BROKEN DOWN BY VEHICLE AND PRESET, because that is what it is (user,
 * 2026-09-09: "have it broken down by vehicle then preset make it look like
 * the other editors"). It was one flat table with the vehicle class retyped on
 * every magazine row - eight identical cells to change a name.
 *
 * The DOCUMENT does not change. <unit>.pylons holds a block of SQF, the same
 * literal a mission's config_pylons.sqf holds:
 *
 *   { {baseClass, { {presetId, { {"displayName", s}, {"icon", s},
 *                                {"loadout", { {magazine, turret, rounds} }} }} }} }
 *
 * so this reads it, hands out a tree, and writes the same shape back.
 */

declare(strict_types=1);

require_once __DIR__ . '/templates.php';
require_once __DIR__ . '/roles.php';        // ghostd_sqf_encode / _decode

/**
 * [vehicle => [preset => ['name' =>, 'icon' =>, 'loadout' => [[mag, turret, rounds], ...]]]]
 *
 * The turret comes back as the text it is written in - "{0}" - because that is
 * what goes in the box and what is written back out.
 */
function ghostd_pylon_tree(string $variant = ''): array
{
    $code = ghostd_template_code('pylons', $variant);
    if (trim($code) === '') {
        return [];
    }
    try {
        $tree = ghostd_sqf_decode($code);
    } catch (Throwable $e) {
        return [];
    }

    $out = [];
    foreach ($tree as $veh) {
        if (!is_array($veh) || count($veh) < 2) {
            continue;
        }
        $vclass = (string) $veh[0];
        $out[$vclass] ??= [];

        foreach ((array) $veh[1] as $preset) {
            if (!is_array($preset) || count($preset) < 2) {
                continue;
            }
            $pid  = (string) $preset[0];
            $name = '';
            $icon = '';
            $load = [];
            foreach ((array) $preset[1] as $pair) {
                if (!is_array($pair) || count($pair) < 2) {
                    continue;
                }
                switch ((string) $pair[0]) {
                    case 'displayName': $name = (string) $pair[1]; break;
                    case 'icon':        $icon = (string) $pair[1]; break;
                    case 'loadout':     $load = (array) $pair[1];  break;
                }
            }

            $rows = [];
            foreach ($load as $l) {
                if (!is_array($l)) {
                    continue;
                }
                $rows[] = [
                    (string) ($l[0] ?? ''),
                    ghostd_sqf_encode(is_array($l[1] ?? null) ? $l[1] : [0], 0, false),
                    (string) ($l[2] ?? ''),
                ];
            }

            $out[$vclass][$pid] = [
                'name'    => $name !== '' ? $name : $pid,
                'icon'    => $icon,
                'loadout' => $rows,
            ];
        }
    }
    return $out;
}

/** The tree back into the document, in the shape the mod reads. */
function ghostd_pylon_tree_save(array $tree, string $variant = ''): void
{
    $out = [];
    foreach ($tree as $veh => $presets) {
        if ($presets === []) {
            continue;                      // a vehicle with no preset is no entry
        }
        $ps = [];
        foreach ($presets as $pid => $p) {
            $load = [];
            foreach ((array) ($p['loadout'] ?? []) as $l) {
                $mag = trim((string) ($l[0] ?? ''));
                if ($mag === '') {
                    continue;
                }
                $turret = trim((string) ($l[1] ?? '[0]'));
                $load[] = [$mag, ghostd_sqf_decode($turret === '' ? '[0]' : $turret),
                           is_numeric($l[2] ?? null) ? $l[2] + 0 : 0];
            }
            $ps[] = [(string) $pid, [
                ['displayName', (string) ($p['name'] ?? $pid)],
                ['icon', (string) ($p['icon'] ?? '')],
                ['loadout', $load],
            ]];
        }
        $out[] = [(string) $veh, $ps];
    }

    ghostd_template_code_save('pylons', $out === [] ? '' : ghostd_sqf_encode($out, 0, false), $variant);
}

/** How many magazines a vehicle's presets hold, for the card summary. */
function ghostd_pylon_count(array $presets): int
{
    $n = 0;
    foreach ($presets as $p) {
        $n += count((array) ($p['loadout'] ?? []));
    }
    return $n;
}
