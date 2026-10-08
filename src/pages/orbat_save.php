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
    // WHICH COMMS TEMPLATES THIS ORDER OF BATTLE USES.
    case 'comms':
        $wantRadio = trim((string) ($_POST['radioVersion'] ?? ''));
        $wantNets  = trim((string) ($_POST['netsVersion'] ?? ''));
        $wantArs   = trim((string) ($_POST['arsenalVersion'] ?? ''));
        $wantMp    = trim((string) ($_POST['motorpoolVersion'] ?? ''));
        foreach ([['radio', $wantRadio], ['nets', $wantNets],
                  ['arsenal', $wantArs], ['motorpool', $wantMp]] as $pair) {
            if ($pair[1] !== '' && !in_array($pair[1], ghostd_doc_variants($pair[0]), true)) {
                throw new RuntimeException('There is no ' . $pair[0] . ' template called "' . $pair[1] . '".');
            }
        }
        $editOrbat(static function (array &$doc) use ($wantRadio, $wantNets, $wantArs, $wantMp, &$msg) {
            $doc['radioVersion']     = $wantRadio;
            $doc['netsVersion']      = $wantNets;
            $doc['arsenalVersion']   = $wantArs;
            $doc['motorpoolVersion'] = $wantMp;
            $msg = 'Saved. The mission reads these at its next start.';
        });
        break;

    // THE ENGINE'S COEFFICIENTS, FOR EVERYBODY ON THIS ORDER OF BATTLE (user,
    // 2026-09-09: "these can be set via the orbat for all players"). They are
    // numbers describing the unit, not the job, so they are not on a role.
    case 'coefs':
        require_once __DIR__ . '/../roles.php';
        $coefs = [];
        foreach (GHOSTD_TRAIT_COEFS as $c => $_h) {
            $v = trim((string) ($_POST['coef_' . $c] ?? ''));
            if ($v === '' || !is_numeric($v)) {
                continue;
            }
            $coefs[$c] = max(0, $v + 0);
        }
        $editOrbat(static function (array &$doc) use ($coefs, &$msg) {
            $doc['coefs'] = $coefs;
            $msg = count($coefs) . ' coefficients saved. They apply at the next role selection.';
        });
        break;

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
        $msg = ($want === '' ? ghostd_orbat_doc_id('') : $want) . ' is the default.';
        break;

    case 'neworbat':
        $to = trim((string) ($_POST['to'] ?? ''));
        if (!ghostd_variant_ok($to)) {
            throw new RuntimeException('An order of battle is named with letters, digits and '
                . 'underscore, starting with a letter - "NightOps", not "Night Ops".');
        }
        if (in_array($to, ghostd_orbat_variants(), true)) {
            throw new RuntimeException('"' . $to . '" already exists.');
        }
        // EMPTY, NOT A COPY (user, 2026-09-09: "just a simple new button, only
        // squads and plt need a copy"). An order of battle is a choice of
        // platoons that already exist; there is nothing to duplicate.
        $src = ghostd_orbat('');
        ghostd_orbat_edit($to, static function (array &$doc) use ($src) {
            $doc['faction']   = $src['faction'];
            $doc['side']      = $src['side'];
            $doc['groups']    = [];
            $doc['platoons']  = [];
        });
        $msg = $to . ' created. Pick its platoons, then tick it as the default when it is ready.';
        break;

    // DELETE MEANS DELETE (user, 2026-09-09: "no delete on the orbat, the word
    // delete does not fucking do anything"). It used to refuse the default and
    // refuse the unnamed one, which on a unit with a single order of battle
    // meant the button was never offered at all. The two things it must not do
    // are leave the tick pointing at a document that is gone, and delete
    // something without saying what happened - so it does neither.
    case 'deleteorbat':
        $was = ghostd_orbat_doc_id($variant);
        ghostd_doc_delete($was);

        $msg = $was . ' deleted.';
        if ($variant === ghostd_default_orbat_id()) {
            $left = ghostd_orbat_variants();
            $next = $left === [] ? '' : (string) $left[0];
            ghostd_setting_save('currentOrbat', $next);
            $msg .= $next === ''
                ? ' There is no order of battle left: a mission falls back to its own config until one is made.'
                : ' ' . $next . ' is the default now.';
        }
        // Back to the list: the page it was deleted from is about nothing now.
        $variant = '';
        $docId   = $unit . '.orbat';
        $tab     = 'versions';
        break;

    // WHICH PLATOONS ARE IN THIS ORDER OF BATTLE. A platoon is written once, on
    // its own page; an order of battle is a CHOICE of which of them it holds.
    // This only writes that choice.
    case 'pickplatoons':
        $picked = (array) ($_POST['inorbat'] ?? []);
        $pool = ghostd_platoon_pool($variant);
        $editOrbat(static function (array &$doc) use ($picked, $pool, &$msg) {
            $out = [];
            foreach ($pool as $pid => $row) {
                if (in_array((string) $pid, $picked, true)) {
                    $out[] = $row;
                }
            }
            $doc['platoons'] = $out;
            $msg = count($out) . ' platoons in this order of battle';
        });
        break;

    // ---- the messaging nets -----------------------------------------------
    // A template, not part of the ORBAT document - one net list per unit, with
    // versions like everything else - but edited on the ORBAT's Radio tab,
    // because that is where somebody setting up comms is standing.
    case 'msgnets':
        $items = [];
        $order = 0;
        foreach ((array) ($_POST['n_id'] ?? []) as $i => $nid) {
            $nid = trim((string) $nid);
            if ($nid === '' || in_array((string) $i, (array) ($_POST['n_remove'] ?? []), true)) {
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)*$/', $nid)) {
                throw new RuntimeException('"' . $nid . '" is not a net name. Letters, digits and '
                    . 'underscore, with a dot to hang one under another - C2, C2.reports.');
            }
            // THE NUMBER IN THE BOX IS THE ORDER. It used to be the row's
            // position, which meant the only way to move a net was to retype
            // every name below it.
            $order += 10;
            $typed = $_POST['n_order'][$i] ?? '';
            $items[$nid] = [
                'id'    => $nid,
                'order' => is_numeric($typed) ? (int) $typed : $order,
                'name'  => trim((string) ($_POST['n_name'][$i] ?? '')),
            ];
        }
        uasort($items, static fn($a, $b) => $a['order'] <=> $b['order']);
        ghostd_template_save('nets', $items, $nv);
        $msg = count($items) . ' messaging nets saved.';
        break;

    // ---- deleting a whole comms template ----------------------------------
    // The Messaging, ACRE and TFAR tabs each list their templates and each row
    // has a Delete, like every other list on this site. A template an order of
    // battle still runs has no button - the list shows "in use" instead - so
    // this is the second line of that defence, not the first.
    case 'tpldel':
        require_once __DIR__ . '/../roles.php';        // ghostd_doc_delete
        $fam = (string) ($_POST['fam'] ?? '');
        $tpl = trim((string) ($_POST['tpl'] ?? ''));
        if ($fam !== 'nets' && $fam !== 'radio') {
            throw new RuntimeException('There is no template family called "' . $fam . '".');
        }
        if ($tpl === '') {
            throw new RuntimeException('The default is not a template - it is what an order of '
                . 'battle gets when it names none.');
        }
        foreach (ghostd_orbat_variants() as $ov) {
            $o = ghostd_orbat($ov);
            if ((string) ($fam === 'nets' ? $o['nets'] : $o['radio']) === $tpl) {
                throw new RuntimeException($tpl . ' is still run by the '
                    . ($ov === '' ? 'default' : $ov) . ' order of battle. Point that at another '
                    . 'one first.');
            }
        }
        $docId = $fam === 'nets'
            ? ghostd_template_doc_id('nets', $tpl)
            : ghostd_radio_doc_id($tpl);
        ghostd_doc_delete($docId);
        $msg = $docId . ' deleted. A copy went to the backup collection first.';
        break;

    case 'msgnetdel':
        $gone  = trim((string) ($_POST['net'] ?? ''));
        $items = ghostd_template_items('nets', $nv);
        if ($gone === '' || !isset($items[$gone])) {
            throw new RuntimeException('There is no net called "' . $gone . '".');
        }
        unset($items[$gone]);
        ghostd_template_save('nets', $items, $nv);
        $msg = $gone . ' deleted.';
        break;

    case 'msgnetnew':
        $nid = trim((string) ($_POST['nn_id'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)*$/', $nid)) {
            throw new RuntimeException('"' . $nid . '" is not a net name. Letters, digits and '
                . 'underscore, with a dot to hang one under another - C2, C2.reports.');
        }
        $items = ghostd_template_items('nets', $nv);
        if (isset($items[$nid])) {
            throw new RuntimeException('There is already a net called "' . $nid . '".');
        }
        $last = 0;
        foreach ($items as $it) {
            $last = max($last, (int) ($it['order'] ?? 0));
        }
        $items[$nid] = [
            'id'    => $nid,
            'order' => $last + 10,
            'name'  => trim((string) ($_POST['nn_name'] ?? '')),
        ];
        ghostd_template_save('nets', $items, $nv);
        $msg = $nid . ' added.';
        break;

    // ---- the unit's own trait names ---------------------------------------
    // NOT PART OF THE ORBAT DOCUMENT. A trait name does not change when the
    // order of battle does, so it is one set per unit - <unit>.traits - even
    // though it is edited on the ORBAT's Common tab, which is where somebody
    // looking for it will be.
    // CUSTOM TRAITS MOVED TO CONFIGS (user, 2026-09-09: "ar not traits part of
    // the config"). One set for the whole unit, edited beside the skills that
    // set them - see src/pages/record.php, section "traits".


    default:
        throw new RuntimeException('Nothing said which part to save.');
}

$msg .= ' Read at the next mission start.';
