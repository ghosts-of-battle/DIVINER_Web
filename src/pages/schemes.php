<?php
/**
 * The colour schemes - <unit>.schemes.
 *
 * The document the TAC//PAD is painted from in game and the site is painted
 * from here. This is the editor for it; the game has its own.
 */

declare(strict_types=1);

require_once __DIR__ . '/../schemes.php';

$unit  = ghostd_config()['unit'];
$docId = $unit . '.schemes';

$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $items = [];
        foreach ((array) ($_POST['s_id'] ?? []) as $i => $id) {
            $id = trim((string) $id);
            if ($id === '' || in_array((string) $i, (array) ($_POST['s_remove'] ?? []), true)) {
                continue;
            }
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $id)) {
                throw new RuntimeException('"' . $id . '" is not a scheme id - letters, digits and '
                    . 'underscore, starting with a letter.');
            }
            foreach (['ground', 'ink', 'accent'] as $t) {
                $v = strtolower(trim((string) ($_POST['s_' . $t][$i] ?? '')));
                if (!ghostd_is_hex($v)) {
                    throw new RuntimeException('"' . $id . '" needs a ' . $t . ' colour as #rrggbb.');
                }
                $items[$id][$t] = $v;
            }
            $items[$id]['id']   = $id;
            $items[$id]['name'] = trim((string) ($_POST['s_name'][$i] ?? '')) ?: $id;
        }

        $doc = ghostd_get($docId);
        $doc = is_array($doc) ? $doc : [];
        unset($doc['_id']);
        $doc['section']   = 'schemes';
        $doc['items']     = $items;
        $doc['from']      = 'DIVINER_Web';
        $doc['updatedAt'] = gmdate('Y-m-d H:i:s');
        ghostd_put($docId, $doc);

        $msg = count($items) . ' schemes saved.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$schemes = ghostd_schemes();

ghostd_head('Colour schemes', 'schemes');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<p class="dim"><code><?= h($docId) ?></code> - the same document the TAC//PAD
uses in game. Clearing an id removes it.</p>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">

  <table class="grid">
    <thead>
      <tr><th style="width:16%">Id</th><th style="width:24%">Shown as</th>
          <th style="width:17%">Ground</th><th style="width:17%">Ink</th>
          <th style="width:17%">Accent</th><th style="width:9%">Del</th></tr>
    </thead>
    <tbody>
    <?php $i = 0; foreach ($schemes as $id => $s): ?>
      <tr>
        <td><input type="text" name="s_id[<?= $i ?>]" value="<?= h((string) $id) ?>"></td>
        <td><input type="text" name="s_name[<?= $i ?>]" value="<?= h($s['name']) ?>"></td>
        <td><input type="color" name="s_ground[<?= $i ?>]" value="<?= h($s['ground']) ?>"></td>
        <td><input type="color" name="s_ink[<?= $i ?>]" value="<?= h($s['ink']) ?>"></td>
        <td><input type="color" name="s_accent[<?= $i ?>]" value="<?= h($s['accent']) ?>"></td>
        <td><input type="checkbox" name="s_remove[]" value="<?= $i ?>"></td>
      </tr>
    <?php $i++; endforeach; ?>
    <?php for ($k = 0; $k < 2; $k++): $n = $i + $k; ?>
      <tr>
        <td><input type="text" name="s_id[<?= $n ?>]" placeholder="GFR_Winter"></td>
        <td><input type="text" name="s_name[<?= $n ?>]" placeholder="GFR WINTER"></td>
        <td><input type="color" name="s_ground[<?= $n ?>]" value="#101010"></td>
        <td><input type="color" name="s_ink[<?= $n ?>]" value="#e6e5e2"></td>
        <td><input type="color" name="s_accent[<?= $n ?>]" value="#cc4331"></td>
        <td></td>
      </tr>
    <?php endfor; ?>
    </tbody>
  </table>

  <div class="actions"><button type="submit">Save</button></div>
</form>
<?php
ghostd_foot();
