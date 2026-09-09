<?php
/**
 * One config template, edited as a table.
 *
 * SAVED WHOLE, not row by row. The order matters and the ids have to stay
 * unique, so the page posts all of it and writes once - and ghostd_put backs
 * the previous version up before it does.
 *
 * AN EMPTY LABEL REMOVES A ROW. Same convention as the application questions,
 * so there is one rule to remember rather than a delete button per row.
 */

declare(strict_types=1);

require_once __DIR__ . '/../templates.php';

$key = (string) ($_GET['t'] ?? ($_POST['t'] ?? ''));
if (!isset(GHOSTD_TEMPLATES[$key])) {
    ghostd_head('Templates', 'config');
    ghostd_flash('bad', 'No such template.');
    echo '<p><a href="?page=config">Back to the templates</a></p>';
    ghostd_foot();
    return;
}
$t = GHOSTD_TEMPLATES[$key];

$msg = null;
$err = null;

// Every template type has versions: '' is the common one, anything else a
// named version stored at <unit>.<doc>.<id>.
$variant  = trim((string) ($_GET['v'] ?? ($_POST['v'] ?? '')));
$variants = ghostd_template_variants($key);

// The welcome screen is its own shape - a title and an ordered run of lines,
// not a keyed list - so it has its own form below.
if ($t['shape'] === 'welcome') {
    require __DIR__ . '/configedit_welcome.php';
    return;
}
if ($t['shape'] === 'lists') {
    require __DIR__ . '/configedit_lists.php';
    return;
}
if ($t['shape'] === 'code') {
    require __DIR__ . '/configedit_code.php';
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $ids    = (array) ($_POST['id'] ?? []);
        $remove = (array) ($_POST['remove'] ?? []);

        $items = [];
        $order = 0;
        foreach ($ids as $row => $rawId) {
            $id = trim((string) $rawId);
            if ($id === '' || in_array((string) $row, $remove, true)) {
                continue;
            }
            if (!preg_match($t['idPattern'], $id)) {
                throw new RuntimeException('"' . $id . '" is not a usable id. ' . $t['idHelp']);
            }
            if (isset($items[$id])) {
                throw new RuntimeException('Two rows share the id "' . $id . '".');
            }

            $rec = ['id' => $id, 'order' => $order += 10];
            $blank = true;
            foreach ($t['fields'] as $f => $meta) {
                $raw = (string) ($_POST[$f][$row] ?? '');
                if ($meta['kind'] === 'list') {
                    $rec[$f] = array_values(array_filter(array_map('trim',
                        preg_split('/\r?\n/', $raw) ?: []), static fn($x) => $x !== ''));
                    if ($rec[$f] !== []) { $blank = false; }
                } else {
                    $rec[$f] = trim($raw);
                    if ($rec[$f] !== '') { $blank = false; }
                }
            }
            // An id with nothing against it is a row somebody started and
            // abandoned, not a net with no purpose.
            if ($blank) {
                continue;
            }
            $items[$id] = $rec;
        }

        ghostd_template_save($key, $items, $variant);
        $msg = count($items) . ' ' . strtolower($t['label']) . ' saved. The mission reads this at its next start.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$items = [];
$readErr = null;
try {
    $items = ghostd_template_items($key, $variant);
} catch (Throwable $e) {
    $readErr = $e->getMessage();
}

ghostd_head($t['label'], 'config');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
if ($readErr !== null) { ghostd_flash('bad', 'Could not be read: ' . $readErr); }
?>
<?php require __DIR__ . '/_versions.php'; ?>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="t" value="<?= h($key) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">

  <table class="grid">
    <thead>
      <tr>
        <th>Id</th>
        <?php foreach ($t['fields'] as $f => $meta): ?>
          <th><?= h($meta['label']) ?></th>
        <?php endforeach; ?>
        <th>Remove</th>
      </tr>
    </thead>
    <tbody>
    <?php $row = 0; foreach ($items as $id => $it): ?>
      <tr>
        <td><input type="text" name="id[<?= $row ?>]" value="<?= h((string) $id) ?>" size="14"></td>
        <?php foreach ($t['fields'] as $f => $meta): ?>
          <td>
            <?php if ($meta['kind'] === 'list'): ?>
              <textarea name="<?= h($f) ?>[<?= $row ?>]" rows="2" class="short"><?= h(implode("\n", (array) ($it[$f] ?? []))) ?></textarea>
            <?php else: ?>
              <input type="text" name="<?= h($f) ?>[<?= $row ?>]" value="<?= h((string) ($it[$f] ?? '')) ?>">
            <?php endif; ?>
          </td>
        <?php endforeach; ?>
        <td><input type="checkbox" name="remove[]" value="<?= $row ?>"></td>
      </tr>
    <?php $row++; endforeach; ?>

    <?php for ($i = 0; $i < 3; $i++): $r = $row + $i; ?>
      <tr>
        <td><input type="text" name="id[<?= $r ?>]" size="14" placeholder="new id"></td>
        <?php foreach ($t['fields'] as $f => $meta): ?>
          <td>
            <?php if ($meta['kind'] === 'list'): ?>
              <textarea name="<?= h($f) ?>[<?= $r ?>]" rows="2" class="short" placeholder="one per line"></textarea>
            <?php else: ?>
              <input type="text" name="<?= h($f) ?>[<?= $r ?>]" placeholder="<?= h($meta['label']) ?>">
            <?php endif; ?>
          </td>
        <?php endforeach; ?>
        <td></td>
      </tr>
    <?php endfor; ?>
    </tbody>
  </table>

  <p class="dim"><strong>Id:</strong> <?= h($t['idHelp']) ?>
  Order is the order of the rows. Clearing every field on a row removes it.</p>

  <div class="actions"><button type="submit">Save <?= h(strtolower($t['label'])) ?></button></div>
</form>
<?php
ghostd_foot();
