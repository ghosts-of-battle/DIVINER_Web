<?php
/**
 * ONE supply crate: its name, and what is in it.
 *
 * Opened from the Logistics card list. One row per item, an add button for a
 * long fill, and one spare row so it still works with no JavaScript.
 */

declare(strict_types=1);

require_once __DIR__ . '/../logistics.php';

$variant = trim((string) ($_GET['v'] ?? ($_POST['v'] ?? '')));
$name    = trim((string) ($_GET['name'] ?? ($_POST['origname'] ?? '')));

$vq  = $variant !== '' ? '&amp;v=' . urlencode($variant) : '';
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $tree = ghostd_logistics_tree($variant);

        if (($_POST['action'] ?? '') === 'delete') {
            if ($name === '' || !isset($tree[$name])) {
                throw new RuntimeException('Nothing to delete.');
            }
            unset($tree[$name]);
            ghostd_logistics_save($tree, $variant);
            header('Location: ?page=configedit&t=logistics' . ($variant !== '' ? '&v=' . urlencode($variant) : ''));
            exit;
        }

        $newName = trim((string) ($_POST['name'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_]+$/', $newName)) {
            throw new RuntimeException('"' . $newName . '" is not a crate name - letters, digits and '
                . 'underscore. It is what a script asks for by name.');
        }

        $rows   = [];
        $remove = (array) ($_POST['c_remove'] ?? []);
        foreach ((array) ($_POST['c_item'] ?? []) as $i => $cls) {
            $cls = trim((string) $cls);
            if ($cls === '' || in_array((string) $i, $remove, true)) {
                continue;
            }
            $n = trim((string) ($_POST['c_count'][$i] ?? ''));
            $rows[] = [$cls, is_numeric($n) ? $n + 0 : 1];
        }
        if ($rows === []) {
            throw new RuntimeException('A crate with nothing in it is not a crate. Add a line, or '
                . 'delete the crate.');
        }

        // A rename moves the crate rather than leaving both behind.
        if ($name !== '' && isset($tree[$name])) {
            unset($tree[$name]);
        }
        $tree[$newName] = $rows;

        ghostd_logistics_save($tree, $variant);
        header('Location: ?page=configedit&t=logistics' . ($variant !== '' ? '&v=' . urlencode($variant) : ''));
        exit;
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$tree   = ghostd_logistics_tree($variant);
$isEdit = $name !== '' && isset($tree[$name]);
$rows   = $isEdit ? $tree[$name] : [];
$have   = count($rows);
$rows[] = ['', ''];

$known = ghostd_classnames('items');

ghostd_head($isEdit ? $name : 'New crate', 'config');
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<p><a href="?page=configedit&amp;t=logistics<?= $vq ?>">&larr; Logistics</a></p>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="origname" value="<?= h($name) ?>">

  <div class="fields">
    <label>Crate <span class="dim">what a script asks for by name</span>
      <input type="text" name="name" value="<?= h($name) ?>" required
             pattern="[A-Za-z0-9_]+" placeholder="crate_medical"></label>
  </div>

  <h2>Contents <span class="dim"><?= $have ?></span></h2>

  <table class="grid">
    <thead><tr><th style="width:64%">Item</th><th style="width:24%">Count</th>
        <th style="width:12%">Del</th></tr></thead>
    <tbody id="lines">
    <?php foreach ($rows as $i => $r): ?>
      <tr>
        <td><input type="text" name="c_item[<?= $i ?>]" value="<?= h((string) $r[0]) ?>"
                   placeholder="ACE_fieldDressing" <?= $known !== [] ? 'list="classlist"' : '' ?>></td>
        <td><input type="number" name="c_count[<?= $i ?>]" value="<?= h((string) $r[1]) ?>"></td>
        <td><?= $i < $have ? '<input type="checkbox" name="c_remove[]" value="' . $i . '">' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <template id="lines-row">
    <tr>
      <td><input type="text" name="c_item[__I__]" placeholder="ACE_fieldDressing" list="classlist"></td>
      <td><input type="number" name="c_count[__I__]"></td>
      <td></td>
    </tr>
  </template>

  <p class="actions"><button type="button" data-addrow="lines" id="addline">+ Add item</button></p>

  <div class="actions">
    <button type="submit"><?= $isEdit ? 'Save changes' : 'Create crate' ?></button>
    <a href="?page=configedit&amp;t=logistics<?= $vq ?>">Cancel</a>
  </div>
</form>

<?php if ($isEdit): ?>
<form method="post" class="danger" onsubmit="return confirm('Delete this crate?');">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="origname" value="<?= h($name) ?>">
  <input type="hidden" name="action" value="delete">
  <p class="dim">Whatever asks for this crate by name gets nothing afterwards.</p>
  <button type="submit" class="hot">Delete <?= h($name) ?></button>
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
