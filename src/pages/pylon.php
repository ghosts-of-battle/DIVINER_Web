<?php
/**
 * ONE pylon preset: the vehicle it is for, and the magazines it loads.
 *
 * Opened from the Pylons card list. The flat table it replaces made somebody
 * retype the vehicle class on every magazine row (user, 2026-09-09: "have it
 * broken down by vehicle then preset make it look like the other editors").
 *
 * A VEHICLE IS A BASE CLASS, matched with isKindOf, so one entry covers every
 * variant that inherits from it.
 */

declare(strict_types=1);

require_once __DIR__ . '/../pylons.php';

$variant = trim((string) ($_GET['v'] ?? ($_POST['v'] ?? '')));
$veh     = trim((string) ($_GET['veh'] ?? ($_POST['origveh'] ?? '')));
$preset  = trim((string) ($_GET['preset'] ?? ($_POST['origpreset'] ?? '')));

$vq  = $variant !== '' ? '&amp;v=' . urlencode($variant) : '';
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $tree = ghostd_pylon_tree($variant);

        if (($_POST['action'] ?? '') === 'delete') {
            if ($veh === '' || $preset === '' || !isset($tree[$veh][$preset])) {
                throw new RuntimeException('Nothing to delete.');
            }
            unset($tree[$veh][$preset]);
            if ($tree[$veh] === []) {
                unset($tree[$veh]);
            }
            ghostd_pylon_tree_save($tree, $variant);
            header('Location: ?page=configedit&t=pylons' . ($variant !== '' ? '&v=' . urlencode($variant) : ''));
            exit;
        }

        $newVeh    = trim((string) ($_POST['veh'] ?? ''));
        $newPreset = trim((string) ($_POST['preset'] ?? ''));
        $name      = trim((string) ($_POST['name'] ?? ''));
        $icon      = trim((string) ($_POST['icon'] ?? ''));

        if ($newVeh === '') {
            throw new RuntimeException('A preset needs a vehicle class - the base class it is offered on.');
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $newPreset)) {
            throw new RuntimeException('"' . $newPreset . '" is not a preset id - letters, digits and '
                . 'underscore. It is what the mod stores against the vehicle.');
        }

        $load = [];
        $remove = (array) ($_POST['m_remove'] ?? []);
        foreach ((array) ($_POST['m_mag'] ?? []) as $i => $mag) {
            $mag = trim((string) $mag);
            if ($mag === '' || in_array((string) $i, $remove, true)) {
                continue;
            }
            $turret = trim((string) ($_POST['m_turret'][$i] ?? '{0}'));
            if ($turret === '') {
                $turret = '{0}';
            }
            try {
                ghostd_sqf_decode($turret);
            } catch (Throwable $e) {
                throw new RuntimeException('"' . $turret . '" is not a turret path. It is a list of '
                    . 'numbers: {0} the main turret, {0,0} one mounted on it, {} the driver.');
            }
            $rounds = trim((string) ($_POST['m_rounds'][$i] ?? ''));
            $load[] = [$mag, $turret, is_numeric($rounds) ? $rounds + 0 : 0];
        }
        if ($load === []) {
            throw new RuntimeException('A preset with no magazines loads nothing. Add one, or delete '
                . 'the preset.');
        }

        // A renamed vehicle or preset moves the entry rather than leaving both.
        if ($veh !== '' && $preset !== '' && isset($tree[$veh][$preset])) {
            unset($tree[$veh][$preset]);
            if ($tree[$veh] === []) {
                unset($tree[$veh]);
            }
        }
        $tree[$newVeh] ??= [];
        $tree[$newVeh][$newPreset] = [
            'name'    => $name !== '' ? $name : $newPreset,
            'icon'    => $icon,
            'loadout' => $load,
        ];

        ghostd_pylon_tree_save($tree, $variant);
        header('Location: ?page=configedit&t=pylons' . ($variant !== '' ? '&v=' . urlencode($variant) : ''));
        exit;
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$tree   = ghostd_pylon_tree($variant);
$isEdit = $veh !== '' && $preset !== '' && isset($tree[$veh][$preset]);
$p      = $isEdit ? $tree[$veh][$preset] : ['name' => '', 'icon' => '', 'loadout' => []];

$known = ghostd_classnames('vehicles');

$rows = (array) $p['loadout'];
$have = count($rows);
// ONE SPARE ROW so the page works with no JavaScript, and an add button for
// when you are putting in a dozen (user, 2026-09-09: "when you edit a plyon
// config there is no add button to add a new mag").
$rows[] = ['', '{0}', ''];

ghostd_head($isEdit ? $veh . ' - ' . $preset : 'New pylon preset', 'config');
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<p><a href="?page=configedit&amp;t=pylons<?= $vq ?>">&larr; Pylons</a></p>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="origveh" value="<?= h($veh) ?>">
  <input type="hidden" name="origpreset" value="<?= h($preset) ?>">

  <div class="fields">
    <label>Vehicle <span class="dim">a base class - every variant that inherits from it is covered</span>
      <input type="text" name="veh" value="<?= h($veh) ?>" required
             placeholder="B_MBT_01_cannon_F" <?= $known !== [] ? 'list="classlist"' : '' ?>></label>
    <label>Preset id <span class="dim">letters, digits and underscore</span>
      <input type="text" name="preset" value="<?= h($preset) ?>" required
             pattern="[A-Za-z0-9_]+" placeholder="default"></label>
    <label>Shown as <span class="dim">the name on the preset menu</span>
      <input type="text" name="name" value="<?= h((string) $p['name']) ?>" placeholder="Standard"></label>
    <label>Icon <span class="dim">texture path, may be empty</span>
      <input type="text" name="icon" value="<?= h((string) $p['icon']) ?>"></label>
  </div>

  <h2>Magazines <span class="dim"><?= $have ?></span></h2>
  <p class="dim"><strong>Turret</strong> is the standard Arma path -
  <code>{0}</code> the main turret, <code>{0,0}</code> one mounted on it,
  <code>{}</code> the driver.</p>

  <table class="grid">
    <thead><tr><th style="width:56%">Magazine</th><th style="width:16%">Turret</th>
        <th style="width:16%">Rounds</th><th style="width:12%">Del</th></tr></thead>
    <tbody id="mags">
    <?php foreach ($rows as $i => $r): ?>
      <tr>
        <td><input type="text" name="m_mag[<?= $i ?>]" value="<?= h((string) $r[0]) ?>"
                   placeholder="24Rnd_120mm_APFSDS_shells"></td>
        <td><input type="text" name="m_turret[<?= $i ?>]" value="<?= h((string) $r[1]) ?>"></td>
        <td><input type="number" name="m_rounds[<?= $i ?>]" value="<?= h((string) $r[2]) ?>"></td>
        <td><?= $i < $have ? '<input type="checkbox" name="m_remove[]" value="' . $i . '">' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <template id="mags-row">
    <tr>
      <td><input type="text" name="m_mag[__I__]" placeholder="24Rnd_120mm_APFSDS_shells"></td>
      <td><input type="text" name="m_turret[__I__]" value="{0}"></td>
      <td><input type="number" name="m_rounds[__I__]"></td>
      <td></td>
    </tr>
  </template>

  <p class="actions"><button type="button" data-addrow="mags" id="addmag">+ Add magazine</button></p>

  <div class="actions">
    <button type="submit"><?= $isEdit ? 'Save changes' : 'Create preset' ?></button>
    <a href="?page=configedit&amp;t=pylons<?= $vq ?>">Cancel</a>
  </div>
</form>

<?php if ($isEdit): ?>
<form method="post" class="danger" onsubmit="return confirm('Delete this preset?');">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="origveh" value="<?= h($veh) ?>">
  <input type="hidden" name="origpreset" value="<?= h($preset) ?>">
  <input type="hidden" name="action" value="delete">
  <p class="dim">Removes this preset. The vehicle goes with it when it was its
  last one.</p>
  <button type="submit" class="hot">Delete <?= h($preset) ?></button>
</form>
<?php endif; ?>

<?php if ($known !== []): ?>
  <datalist id="classlist">
    <?php foreach (array_slice($known, 0, 3000) as $c): ?>
      <option value="<?= h($c) ?>"></option>
    <?php endforeach; ?>
  </datalist>
<?php endif; ?>
<?php
ghostd_foot();
