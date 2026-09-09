<?php
/**
 * Every ORBAT save, in one place.
 *
 * Included by orbat.php inside its try/catch, with $editOrbat, $editRadio,
 * $lines, $variant and $msg in scope. One file so the four sub-menus cannot
 * grow four different ideas of what a squad is.
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
            foreach (['tfarSrFreqs', 'tfarLrFreqs'] as $k) {
                if (isset($_POST[$k])) {
                    $items[$k] = array_values(array_filter(array_map('trim',
                        preg_split('/\r?\n/', (string) $_POST[$k]) ?: []), static fn($x) => $x !== ''));
                }
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

    // ---- Squads ------------------------------------------------------------
    // The squad and its channel are one thought, so one save writes both
    // documents: the roles into <unit>.orbat, the channels into <unit>.radio.
    case 'squads':
        $keep = [];
        $editOrbat(static function (array &$doc) use (&$keep, $lines, &$msg) {
            $out = [];
            foreach ((array) ($_POST['g_name'] ?? []) as $i => $gname) {
                $gname = trim((string) $gname);
                if ($gname === '' || in_array((string) $i, (array) ($_POST['g_remove'] ?? []), true)) {
                    continue;
                }
                $cond = trim((string) ($_POST['g_cond'][$i] ?? ''));
                $out[] = [$gname, $lines((string) ($_POST['g_roles'][$i] ?? '')), $cond === '' ? 'true' : $cond];
                $keep[$i] = $gname;
            }
            $doc['groups'] = $out;
            $msg = count($out) . ' squads saved';
        });

        $editRadio(static function (array &$items) use ($keep, &$msg) {
            $acre = [];
            $tfar = [];
            foreach ($keep as $i => $name) {
                $ch = trim((string) ($_POST['g_acre'][$i] ?? ''));
                if ($ch !== '') { $acre[] = [$name, (int) $ch]; }

                $sw = trim((string) ($_POST['g_tfar_sw'][$i] ?? ''));
                $lr = trim((string) ($_POST['g_tfar_lr'][$i] ?? ''));
                if ($sw !== '' || $lr !== '') { $tfar[] = [$name, (int) $sw, (int) $lr]; }
            }
            $items['srSquadChannel'] = $acre;
            $items['tfarNets'] = $tfar;
            $msg .= ', ' . count($acre) . ' ACRE and ' . count($tfar) . ' TFAR channel settings.';
        });
        break;

    // A squad copied keeps its roles and channel - the point of copying one is
    // that the next squad is nearly the same.
    case 'copysquad':
        $from = trim((string) ($_POST['from'] ?? ''));
        $to   = trim((string) ($_POST['to'] ?? ''));
        if ($from === '' || $to === '') {
            throw new RuntimeException('Copying needs a squad to copy and a name for the new one.');
        }
        $editOrbat(static function (array &$doc) use ($from, $to, &$msg) {
            $groups = is_array($doc['groups'] ?? null) ? $doc['groups'] : [];
            $src = null;
            foreach ($groups as $g) {
                if ((string) ($g[0] ?? '') === $from) { $src = $g; break; }
            }
            if ($src === null) {
                throw new RuntimeException('No squad called "' . $from . '".');
            }
            foreach ($groups as $g) {
                if ((string) ($g[0] ?? '') === $to) {
                    throw new RuntimeException('"' . $to . '" already exists.');
                }
            }
            $src[0] = $to;
            $groups[] = $src;
            $doc['groups'] = $groups;
            $msg = 'Copied ' . $from . ' to ' . $to . '.';
        });
        $editRadio(static function (array &$items) use ($from, $to, &$msg) {
            foreach (['srSquadChannel', 'tfarNets'] as $k) {
                $rows = is_array($items[$k] ?? null) ? $items[$k] : [];
                foreach ($rows as $r) {
                    if ((string) ($r[0] ?? '') === $from) {
                        $copy = $r;
                        $copy[0] = $to;
                        $rows[] = $copy;
                        break;
                    }
                }
                $items[$k] = $rows;
            }
            $msg .= ' Its channels came with it - change them.';
        });
        break;

    // ---- Platoons ----------------------------------------------------------
    case 'platoons':
        $editOrbat(static function (array &$doc) use ($lines, &$msg) {
            $out = [];
            foreach ((array) ($_POST['p_id'] ?? []) as $i => $pid) {
                $pid = trim((string) $pid);
                if ($pid === '' || in_array((string) $i, (array) ($_POST['p_remove'] ?? []), true)) {
                    continue;
                }
                $out[] = [
                    $pid,
                    trim((string) ($_POST['p_name'][$i] ?? '')),
                    trim((string) ($_POST['p_callsign'][$i] ?? '')),
                    trim((string) ($_POST['p_net'][$i] ?? '')),
                    $lines((string) ($_POST['p_squads'][$i] ?? '')),
                ];
            }
            $doc['platoons'] = $out;
            $msg = count($out) . ' platoons saved.';
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
