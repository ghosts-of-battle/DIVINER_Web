<?php
/**
 * Pylon and turret presets, as a table.
 *
 * It was a box of SQF. The file is a literal array - no logic in it at all - so
 * there was never a reason to make somebody edit brackets:
 *
 *   [ [baseClass, [ [presetId, [ ["displayName", n], ["icon", i],
 *                                ["loadout", [ [magazine, turret, rounds] ]] ]] ]] ]
 *
 * ONE ROW PER MAGAZINE, carrying the vehicle and the preset it belongs to -
 * the same arrangement as the operation order's fields. Vehicles and presets
 * appear in the order their first row does; clearing the magazine removes a row.
 *
 * The text is parsed with the same reader the role loadouts use, and written
 * back the way a config file writes it, so the document stays something a
 * person could paste into config_pylons.sqf.
 */

declare(strict_types=1);

require_once __DIR__ . '/../roles.php';        // ghostd_sqf_decode / _encode

$variant  = trim((string) ($_GET['v'] ?? ($_POST['v'] ?? '')));
$variants = ghostd_template_variants($key);

$msg = null;
$err = null;

/** The stored array, flattened to [vehicle, preset, name, magazine, turret, rounds]. */
function ghostd_pylon_rows(string $code): array
{
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
            if ($load === []) {
                $out[] = [$vclass, $pid, $name, $icon, '', '[0]', ''];
                continue;
            }
            foreach ($load as $l) {
                if (!is_array($l)) {
                    continue;
                }
                $out[] = [
                    $vclass, $pid, $name, $icon,
                    (string) ($l[0] ?? ''),
                    ghostd_sqf_encode(is_array($l[1] ?? null) ? $l[1] : [0]),
                    (string) ($l[2] ?? ''),
                ];
            }
        }
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        // Rebuild the nesting from the flat rows, keeping the order they arrive.
        $tree = [];
        foreach ((array) ($_POST['p_vehicle'] ?? []) as $i => $veh) {
            $veh = trim((string) $veh);
            $pid = trim((string) ($_POST['p_preset'][$i] ?? ''));
            $mag = trim((string) ($_POST['p_mag'][$i] ?? ''));
            if ($veh === '' || $pid === '' || $mag === ''
                || in_array((string) $i, (array) ($_POST['p_remove'] ?? []), true)) {
                continue;
            }
            $turret = trim((string) ($_POST['p_turret'][$i] ?? '[0]'));
            try {
                $turret = ghostd_sqf_decode($turret === '' ? '[0]' : $turret);
            } catch (Throwable $e) {
                throw new RuntimeException('"' . $turret . '" is not a turret path. '
                    . 'It is a list of numbers: [0] the main turret, [0,0] one on it, [] the driver.');
            }
            $rounds = trim((string) ($_POST['p_rounds'][$i] ?? ''));

            $tree[$veh] ??= [];
            $tree[$veh][$pid] ??= [
                'name'    => trim((string) ($_POST['p_name'][$i] ?? '')) ?: $pid,
                'icon'    => trim((string) ($_POST['p_icon'][$i] ?? '')),
                'loadout' => [],
            ];
            $tree[$veh][$pid]['loadout'][] = [$mag, $turret, is_numeric($rounds) ? $rounds + 0 : 0];
        }

        // Back into the shape the mod reads.
        $out = [];
        foreach ($tree as $veh => $presets) {
            $ps = [];
            foreach ($presets as $pid => $p) {
                $ps[] = [(string) $pid, [
                    ['displayName', $p['name']],
                    ['icon', $p['icon']],
                    ['loadout', $p['loadout']],
                ]];
            }
            $out[] = [(string) $veh, $ps];
        }

        ghostd_template_code_save($key, $out === [] ? '' : ghostd_sqf_encode($out), $variant);
        $msg = count($out) . ' vehicles saved. The mission reads this at its next start.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$rows  = ghostd_pylon_rows(ghostd_template_code($key, $variant));
$known = ghostd_classnames('vehicles');

ghostd_head($t['label'], 'config');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<?php require __DIR__ . '/_versions.php'; ?>

<p class="note">One row per magazine. The <strong>vehicle</strong> is a base
class and is matched with <code>isKindOf</code>, so one entry covers every
variant that inherits from it. A vehicle with no entry simply gets no preset
menu.</p>
<p class="dim"><strong>Turret</strong> is the standard Arma path -
<code>[0]</code> the main turret, <code>[0,0]</code> one mounted on it,
<code>[]</code> the driver. Clearing the magazine removes a row; a preset's name
comes from its first row.</p>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="t" value="<?= h($key) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">

  <table class="grid">
    <thead>
      <tr><th style="width:20%">Vehicle</th><th style="width:12%">Preset</th>
          <th style="width:15%">Shown as</th><th style="width:28%">Magazine</th>
          <th style="width:10%">Turret</th><th style="width:8%">Rounds</th>
          <th style="width:7%">Del</th></tr>
    </thead>
    <tbody>
    <?php $i = 0; foreach ($rows as $r): ?>
      <tr>
        <td><input type="text" name="p_vehicle[<?= $i ?>]" value="<?= h($r[0]) ?>"
                   <?= $known !== [] ? 'list="classlist"' : '' ?>></td>
        <td><input type="text" name="p_preset[<?= $i ?>]" value="<?= h($r[1]) ?>"></td>
        <td><input type="text" name="p_name[<?= $i ?>]" value="<?= h($r[2]) ?>"></td>
        <td><input type="text" name="p_mag[<?= $i ?>]" value="<?= h($r[4]) ?>">
            <input type="hidden" name="p_icon[<?= $i ?>]" value="<?= h($r[3]) ?>"></td>
        <td><input type="text" name="p_turret[<?= $i ?>]" value="<?= h($r[5]) ?>"></td>
        <td><input type="number" name="p_rounds[<?= $i ?>]" value="<?= h($r[6]) ?>"></td>
        <td><input type="checkbox" name="p_remove[]" value="<?= $i ?>"></td>
      </tr>
    <?php $i++; endforeach; ?>
    <?php for ($k = 0; $k < 3; $k++): $n = $i + $k; ?>
      <tr>
        <td><input type="text" name="p_vehicle[<?= $n ?>]" placeholder="B_MBT_01_cannon_F"
                   <?= $known !== [] ? 'list="classlist"' : '' ?>></td>
        <td><input type="text" name="p_preset[<?= $n ?>]" placeholder="default"></td>
        <td><input type="text" name="p_name[<?= $n ?>]" placeholder="Standard"></td>
        <td><input type="text" name="p_mag[<?= $n ?>]" placeholder="24Rnd_120mm_APFSDS_shells">
            <input type="hidden" name="p_icon[<?= $n ?>]" value=""></td>
        <td><input type="text" name="p_turret[<?= $n ?>]" placeholder="[0]"></td>
        <td><input type="number" name="p_rounds[<?= $n ?>]" placeholder="24"></td>
        <td></td>
      </tr>
    <?php endfor; ?>
    </tbody>
  </table>

  <div class="actions"><button type="submit">Save</button></div>
</form>

<?php if ($known !== []): ?>
  <datalist id="classlist">
    <?php foreach (array_slice($known, 0, 3000) as $c): ?>
      <option value="<?= h($c) ?>"></option>
    <?php endforeach; ?>
  </datalist>
<?php endif; ?>
<?php
ghostd_foot();
