<?php
/**
 * The saves the ORBAT TABS make - the radio plan, the faction, the net list.
 *
 * Included by orbat.php inside its try/catch, with $editOrbat, $editRadio,
 * $lines, $variant and $msg in scope.
 *
 * A SQUAD AND A PLATOON SAVE THEMSELVES, on their own pages (squad.php,
 * platoon.php), because each is a record with sections rather than a row in a
 * form. They go through the same ghostd_orbat_edit / ghostd_radio_edit as
 * everything here, so there is still one idea of what a save is - just not one
 * file.
 */

declare(strict_types=1);

$what = (string) ($_POST['what'] ?? '');

switch ($what) {

    // ---- Radio ------------------------------------------------------------
    case 'acre':
        $editRadio(static function (array &$items) use (&$msg) {
            foreach (['srRadios', 'mrRadios', 'lrRadios', 'acreNoProgram'] as $k) {
                if (isset($_POST[$k])) {
                    $items[$k] = array_values(array_filter(array_map('trim',
                        preg_split('/\r?\n/', (string) $_POST[$k]) ?: []), static fn($x) => $x !== ''));
                }
            }
            foreach (['acreActiveRadio'] as $k) {
                if (isset($_POST[$k])) { $items[$k] = trim((string) $_POST[$k]); }
            }
            foreach (['srPower', 'mrPower', 'lrPower', 'srFallback', 'mrDefault',
                      'lrDefault', 'lrSatChannel', 'lrLocalChannel'] as $k) {
                if (isset($_POST[$k]) && $_POST[$k] !== '') { $items[$k] = (int) $_POST[$k]; }
            }
            $msg = 'ACRE settings saved.';
        });
        break;

    case 'tfar':
        $editRadio(static function (array &$items) use (&$msg) {
            // NUMBERED SLOTS, so the INDEX is the channel number. A blank
            // slot 3 must stay slot 3 - closing the gap would shift every
            // squad's channel by one and nobody would know until they keyed up.
            // Trailing blanks are dropped; gaps in the middle are kept.
            foreach (['tfarSrFreqs', 'tfarLrFreqs'] as $k) {
                if (!isset($_POST[$k]) || !is_array($_POST[$k])) {
                    continue;
                }
                $slots = [];
                foreach ($_POST[$k] as $i => $v) {
                    $slots[(int) $i] = trim((string) $v);
                }
                ksort($slots);
                while ($slots !== [] && end($slots) === '') {
                    array_pop($slots);
                }
                $items[$k] = array_values($slots);
            }
            if (isset($_POST['tfarActiveRadio'])) {
                $items['tfarActiveRadio'] = trim((string) $_POST['tfarActiveRadio']);
            }
            foreach (['tfarSwFallback', 'tfarLrFallback'] as $k) {
                if (isset($_POST[$k]) && $_POST[$k] !== '') { $items[$k] = (int) $_POST[$k]; }
            }
            $msg = 'TFAR settings saved.';
        });
        break;

    // Channel lists: [index, frequency, label] rows.
    case 'channels':
        $which = (string) ($_POST['which'] ?? '');
        if (!in_array($which, ['srChannels', 'mrChannels', 'lrChannels'], true)) {
            throw new RuntimeException('Unknown channel list.');
        }
        $editRadio(static function (array &$items) use ($which, &$msg) {
            $rows = [];
            foreach ((array) ($_POST['c_idx'] ?? []) as $i => $idx) {
                $label = trim((string) ($_POST['c_label'][$i] ?? ''));
                $freq  = trim((string) ($_POST['c_freq'][$i] ?? ''));
                if ($label === '' && $freq === '') {
                    continue;
                }
                if (in_array((string) $i, (array) ($_POST['c_remove'] ?? []), true)) {
                    continue;
                }
                $row = [(int) $idx, is_numeric($freq) ? $freq + 0 : $freq, $label];
                // Long range carries a fourth value - the power for that net.
                $pw = trim((string) ($_POST['c_power'][$i] ?? ''));
                if ($which === 'lrChannels' && $pw !== '') { $row[] = (int) $pw; }
                $rows[] = $row;
            }
            $items[$which] = $rows;
            $msg = count($rows) . ' channels saved.';
        });
        break;

    case 'faction':
        $editOrbat(static function (array &$doc) use (&$msg) {
            $doc['faction'] = trim((string) ($_POST['faction'] ?? ''));
            $msg = 'Faction name saved.';
        });
        break;

    // ---- the ORBAT's own net list -----------------------------------------
    case 'nets':
        $editOrbat(static function (array &$doc) use ($lines, &$msg) {
            $out = [];
            foreach ((array) ($_POST['n_id'] ?? []) as $i => $nid) {
                $nid = trim((string) $nid);
                if ($nid === '' || in_array((string) $i, (array) ($_POST['n_remove'] ?? []), true)) {
                    continue;
                }
                $out[] = [$nid, trim((string) ($_POST['n_name'][$i] ?? '')),
                          $lines((string) ($_POST['n_squads'][$i] ?? ''))];
            }
            $doc['radioNets'] = $out;
            $msg = count($out) . ' radio nets saved.';
        });
        break;

    default:
        throw new RuntimeException('Nothing said which part to save.');
}

$msg .= ' Read at the next mission start.';
