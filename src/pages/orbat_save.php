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

    // Who the unit is: the name over the role screen, and the side it fights
    // on. One save - they are one answer.
    case 'faction':
        $editOrbat(static function (array &$doc) use (&$msg) {
            $doc['faction'] = trim((string) ($_POST['faction'] ?? ''));
            $side = strtoupper(trim((string) ($_POST['side'] ?? '')));
            // A side the engine does not have is a side nothing can be created
            // on, so an unknown one is simply not written.
            if (isset(GHOSTD_SIDES[$side])) {
                $doc['side'] = $side;
            }
            $msg = 'Faction and side saved.';
        });
        break;

    // ---- which order of battle is live, and building another --------------
    // The tick is a SETTING, not part of any ORBAT document: it says which of
    // them the game loads, and a document cannot sensibly claim to be the one
    // in use while four others claim the same.
    case 'default':
        $want = trim((string) ($_POST['default'] ?? ''));
        if ($want !== '' && !in_array($want, ghostd_orbat_variants(), true)) {
            throw new RuntimeException('There is no order of battle called "' . $want . '".');
        }
        ghostd_setting_save('currentOrbat', $want);
        $msg = ($want === '' ? 'The common order of battle' : $want) . ' is live.';
        break;

    case 'neworbat':
        $from = trim((string) ($_POST['from'] ?? ''));
        $to   = trim((string) ($_POST['to'] ?? ''));
        if (!ghostd_variant_ok($to)) {
            throw new RuntimeException('An order of battle is named with letters, digits and '
                . 'underscore, starting with a letter - "NightOps", not "Night Ops".');
        }
        if (in_array($to, ghostd_orbat_variants(), true)) {
            throw new RuntimeException('"' . $to . '" already exists.');
        }
        $src = ghostd_orbat($from);
        ghostd_orbat_edit($to, static function (array &$doc) use ($src) {
            $doc['faction']   = $src['faction'];
            $doc['side']      = $src['side'];
            $doc['groups']    = $src['groups'];
            $doc['platoons']  = $src['platoons'];
            $doc['radioNets'] = $src['radioNets'];
        });
        $msg = 'Copied ' . ($from === '' ? 'the common ORBAT' : $from) . ' to ' . $to
             . '. It is not live until you tick it.';
        break;

    case 'deleteorbat':
        if ($variant === '') {
            throw new RuntimeException('The common order of battle cannot be deleted - it is what '
                . 'everything falls back to.');
        }
        if ($variant === ghostd_default_orbat_id()) {
            throw new RuntimeException('"' . $variant . '" is the live one. Make another live first, '
                . 'or the next mission would start with no order of battle.');
        }
        ghostd_doc_delete(ghostd_orbat_doc_id($variant));
        $msg = $variant . ' deleted.';
        break;

    // ---- the unit's own trait names ---------------------------------------
    // NOT PART OF THE ORBAT DOCUMENT. A trait name does not change when the
    // order of battle does, so it is one set per unit - <unit>.traits - even
    // though it is edited on the ORBAT's Common tab, which is where somebody
    // looking for it will be.
    case 'traits':
        $items = [];
        $order = 0;
        foreach ((array) ($_POST['t_id'] ?? []) as $i => $tid) {
            $tid = trim((string) $tid);
            if ($tid === '' || in_array((string) $i, (array) ($_POST['t_remove'] ?? []), true)) {
                continue;
            }
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $tid)) {
                throw new RuntimeException('"' . $tid . '" is not a trait name - it is read by '
                    . 'setUnitTrait, so it is letters, digits and underscore with no spaces.');
            }
            $order += 10;
            $items[$tid] = [
                'id'    => $tid,
                'order' => $order,
                'label' => trim((string) ($_POST['t_label'][$i] ?? '')) ?: $tid,
                'kind'  => ((string) ($_POST['t_kind'][$i] ?? 'bool')) === 'number' ? 'number' : 'bool',
                'where' => ((string) ($_POST['t_where'][$i] ?? 'variable')) === 'trait' ? 'trait' : 'variable',
                'help'  => trim((string) ($_POST['t_help'][$i] ?? '')),
            ];
        }
        ghostd_template_save('traits', $items);
        $msg = count($items) . ' custom traits saved.';
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
