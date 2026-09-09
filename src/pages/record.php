<?php
/**
 * ONE set of unit records, as a grid: the id, then the fields the mod declares.
 *
 * SAVED WHOLE. The ids have to stay unique and the order is the order, so the
 * page posts all of it and writes once - ghostd_put backs the previous version
 * up before it does.
 *
 * WHAT IS NOT SHOWN RIDES ALONG. An item keeps every field this page does not
 * draw - the admin list's addedBy and addedAt, anything the game writes later -
 * because the whole document is replaced on save and dropping them would be a
 * silent delete.
 */

declare(strict_types=1);

require_once __DIR__ . '/../records.php';

$sec = (string) ($_GET['s'] ?? ($_POST['s'] ?? ''));
if (!isset(GHOSTD_RECORDS[$sec])) {
    ghostd_head('Configs', 'records');
    ghostd_flash('bad', 'No such record set.');
    echo '<p><a href="?page=records">Back</a></p>';
    ghostd_foot();
    return;
}
$meta = GHOSTD_RECORDS[$sec];

$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $was     = ghostd_record_items($sec);
        $pattern = (string) ($meta['idPattern'] ?? '/^[A-Za-z0-9_]+$/');

        $items  = [];
        $remove = (array) ($_POST['r_remove'] ?? []);
        foreach ((array) ($_POST['r_id'] ?? []) as $i => $id) {
            $id = trim((string) $id);
            if ($id === '' || in_array((string) $i, $remove, true)) {
                continue;
            }
            if (!preg_match($pattern, $id)) {
                throw new RuntimeException('"' . $id . '" is not an id here - ' . strip_tags((string) $meta['idHelp']) . '.');
            }
            if (isset($items[$id])) {
                throw new RuntimeException('"' . $id . '" is in the list twice.');
            }

            // Start from what was stored, so an unseen field survives.
            $row = (array) ($was[(string) ($_POST['r_orig'][$i] ?? $id)] ?? []);
            $row['id'] = $id;

            foreach ($meta['fields'] as $f => $fm) {
                $v = (string) ($_POST['r_' . $f][$i] ?? '');
                switch ($fm['kind']) {
                    case 'number':
                        $row[$f] = is_numeric(trim($v)) ? trim($v) + 0 : 0;
                        break;
                    case 'list':
                        $row[$f] = array_values(array_filter(array_map('trim', explode(',', $v)),
                            static fn($x) => $x !== ''));
                        break;
                    default:
                        $row[$f] = trim($v);
                }
            }
            $items[$id] = $row;
        }

        ghostd_record_save($sec, $items);
        $msg = count($items) . ' saved.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$items = ghostd_record_items($sec);

ghostd_head($meta['label'], 'records');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<p><a href="?page=records">&larr; Configs</a> &middot;
<code><?= h(ghostd_record_doc_id($sec)) ?></code></p>

<h2><?= h($meta['label']) ?> <span class="dim"><?= count($items) ?></span></h2>
<p class="dim"><?= $meta['blurb'] ?> Clearing an id removes it.</p>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="s" value="<?= h($sec) ?>">

  <?php
    $cols  = count($meta['fields']) + 2;      // id + fields + del
    $idW   = 18;
    $delW  = 7;
    $fldW  = (int) floor((100 - $idW - $delW) / max(1, count($meta['fields'])));
  ?>
  <table class="grid">
    <thead>
      <tr><th style="width:<?= $idW ?>%">Id</th>
      <?php foreach ($meta['fields'] as $fm): ?>
        <th style="width:<?= $fldW ?>%"><?= h($fm['label']) ?></th>
      <?php endforeach; ?>
      <th style="width:<?= $delW ?>%">Del</th></tr>
    </thead>
    <tbody>
    <?php $i = 0; foreach ($items as $id => $it): ?>
      <tr>
        <td><input type="text" name="r_id[<?= $i ?>]" value="<?= h((string) $id) ?>">
            <input type="hidden" name="r_orig[<?= $i ?>]" value="<?= h((string) $id) ?>"></td>
        <?php foreach ($meta['fields'] as $f => $fm): ?>
          <?php
            $v = $it[$f] ?? '';
            if (is_array($v)) { $v = implode(', ', array_map('strval', $v)); }
          ?>
          <td><input type="<?= $fm['kind'] === 'number' ? 'number' : 'text' ?>"
                     name="r_<?= h($f) ?>[<?= $i ?>]" value="<?= h((string) $v) ?>"
                     <?= isset($fm['help']) ? 'title="' . h($fm['help']) . '"' : '' ?>></td>
        <?php endforeach; ?>
        <td><input type="checkbox" name="r_remove[]" value="<?= $i ?>"></td>
      </tr>
    <?php $i++; endforeach; ?>
      <tr>
        <td><input type="text" name="r_id[<?= $i ?>]" placeholder="new"></td>
        <?php foreach ($meta['fields'] as $f => $fm): ?>
          <td><input type="<?= $fm['kind'] === 'number' ? 'number' : 'text' ?>"
                     name="r_<?= h($f) ?>[<?= $i ?>]"
                     placeholder="<?= h((string) ($fm['help'] ?? $fm['label'])) ?>"></td>
        <?php endforeach; ?>
        <td></td>
      </tr>
    </tbody>
  </table>

  <div class="actions"><button type="submit">Save <?= h(strtolower($meta['label'])) ?></button></div>
</form>
<?php
ghostd_foot();
