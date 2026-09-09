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
        // One scheme, one button - see the row above. Its own branch, so it
        // does not run the whole-table save underneath it.
        if (($_POST['what'] ?? '') === 'deletescheme') {
            $gone = trim((string) ($_POST['scheme'] ?? ''));
            $doc  = ghostd_get($docId);
            $doc  = is_array($doc) ? $doc : [];
            unset($doc['_id']);
            $items = is_array($doc['items'] ?? null) ? $doc['items'] : [];
            if ($gone === '' || !isset($items[$gone])) {
                throw new RuntimeException('There is no scheme called "' . $gone . '".');
            }
            unset($items[$gone]);
            $doc['items'] = $items;
            ghostd_put($docId, $doc);
            $msg = $gone . ' deleted.';
        } else {
        $items = [];
        foreach ((array) ($_POST['s_id'] ?? []) as $i => $id) {
            $id = trim((string) $id);
            if ($id === '' || in_array((string) $i, (array) ($_POST['s_remove'] ?? []), true)) {
                continue;
            }
            if (isset(GHOSTD_MOD_SCHEMES[$id])) {
                throw new RuntimeException('"' . $id . '" is one of the six the mod ships and is '
                    . 'not editable here - they live in ghostD_tacpad_fnc_theme. Give yours another id.');
            }
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $id)) {
                throw new RuntimeException('"' . $id . '" is not a scheme id - letters, digits and '
                    . 'underscore, starting with a letter.');
            }
            foreach (['ground', 'ink', 'accent'] as $t) {
                $v = strtolower(trim((string) ($_POST['s_' . $t][$i] ?? '')));
                if (!ghostd_is_hex($v)) {
                    throw new RuntimeException('"' . $id . '" needs a ' . $t . ' color as #rrggbb.');
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
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$schemes = ghostd_schemes();

?>
<?php $mine = array_filter($schemes, static fn($s) => empty($s['locked'])); ?>
<h2>Color schemes <span class="dim"><?= count($mine) ?> of this unit's,
  <?= count(GHOSTD_MOD_SCHEMES) ?> the mod's</span></h2>
<p class="dim"><code><?= h($docId) ?></code> - the same document the TAC//PAD is
painted from in game, and this site with it. Clearing an id removes it.</p>
<?php if ($msg !== null) { ghostd_flash('good', $msg); } ?>
<?php if ($err !== null) { ghostd_flash('bad', $err); } ?>

<form method="post" id="schemedel">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="what" value="deletescheme">
</form>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">

  <table class="grid">
    <thead>
      <tr><th style="width:16%">Id</th><th style="width:24%">Shown as</th>
          <th style="width:17%">Ground</th><th style="width:17%">Ink</th>
          <th style="width:17%">Accent</th><th style="width:9%"></th></tr>
    </thead>
    <tbody id="schemes">
    <?php $i = 0; foreach ($schemes as $id => $s): ?>
      <?php if (!empty($s['locked'])): ?>
        <?php // THE MOD'S OWN SIX. Shown because the site paints with them and
              // you should be able to see what they are; not editable, because
              // they live in ghostD_tacpad_fnc_theme and a change here would be
              // a lie. ?>
        <tr>
          <td><code><?= h((string) $id) ?></code></td>
          <td><?= h($s['name']) ?> <span class="dim">the mod's own</span></td>
          <td><input type="color" value="<?= h($s['ground']) ?>" disabled></td>
          <td><input type="color" value="<?= h($s['ink']) ?>" disabled></td>
          <td><input type="color" value="<?= h($s['accent']) ?>" disabled></td>
          <td class="dim">-</td>
        </tr>
      <?php else: ?>
        <tr>
          <td><input type="text" name="s_id[<?= $i ?>]" value="<?= h((string) $id) ?>"></td>
          <td><input type="text" name="s_name[<?= $i ?>]" value="<?= h($s['name']) ?>"></td>
          <td><input type="color" name="s_ground[<?= $i ?>]" value="<?= h($s['ground']) ?>"></td>
          <td><input type="color" name="s_ink[<?= $i ?>]" value="<?= h($s['ink']) ?>"></td>
          <td><input type="color" name="s_accent[<?= $i ?>]" value="<?= h($s['accent']) ?>"></td>
          <td><button type="submit" form="schemedel" name="scheme" value="<?= h((string) $id) ?>" class="hot"
                      onclick="return confirm('Delete the scheme <?= h((string) $id) ?>?');">Delete</button></td>
        </tr>
        <?php $i++; ?>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php // ONE spare row, so the page works with no JavaScript. ?>
      <tr>
        <td><input type="text" name="s_id[<?= $i ?>]" placeholder="GFR_Winter"></td>
        <td><input type="text" name="s_name[<?= $i ?>]" placeholder="GFR WINTER"></td>
        <td><input type="color" name="s_ground[<?= $i ?>]" value="#101010"></td>
        <td><input type="color" name="s_ink[<?= $i ?>]" value="#e6e5e2"></td>
        <td><input type="color" name="s_accent[<?= $i ?>]" value="#cc4331"></td>
        <td></td>
      </tr>
    </tbody>
  </table>

  <template id="schemes-row">
    <tr>
      <td><input type="text" name="s_id[__I__]" placeholder="GFR_Winter"></td>
      <td><input type="text" name="s_name[__I__]" placeholder="GFR WINTER"></td>
      <td><input type="color" name="s_ground[__I__]" value="#101010"></td>
      <td><input type="color" name="s_ink[__I__]" value="#e6e5e2"></td>
      <td><input type="color" name="s_accent[__I__]" value="#cc4331"></td>
      <td></td>
    </tr>
  </template>

  <p class="actions"><button type="button" data-addrow="schemes">+ Add scheme</button></p>

  <div class="actions"><button type="submit">Save</button></div>
</form>
