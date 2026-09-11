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
require_once __DIR__ . '/../promotion.php';

// EMBEDDED? The ORBAT page draws this grid inside its Variables tab rather
// than sending you to another page for it (user, 2026-09-09: custom variables
// "should be part of the orbat"). $recordEmbed is set by the page doing the
// embedding, which also sets $sec; the head, the foot and the breadcrumb are
// its job, not ours, and the forms post HERE and come back there.
$recordEmbed = $recordEmbed ?? false;
$recordFrom  = (string) ($_GET['from'] ?? ($_POST['from'] ?? ''));
$recordBack  = $recordFrom === 'orbat' ? '?page=orbat&s=variables' : '';

$sec = $recordEmbed ? $sec : (string) ($_GET['s'] ?? ($_POST['s'] ?? ''));
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

        // ONE ROW, ONE BUTTON (user, 2026-09-09: "for any place there needs to
        // be a delete just a fucking button with an are you sure pop up"). The
        // tick-and-save column is gone; this is the button posting into the
        // same page.
        if (($_POST['what'] ?? '') === 'delete') {
            $gone = trim((string) ($_POST['id'] ?? ''));
            $all  = ghostd_record_items($sec);
            if ($gone === '' || !isset($all[$gone])) {
                throw new RuntimeException('No such row.');
            }
            unset($all[$gone]);
            ghostd_record_save($sec, $all);
            if ($sec === 'ranks') {
                $th = ghostd_rank_thresholds();
                unset($th[$gone]);
                ghostd_rank_thresholds_save($th);
            }
            $msg = $gone . ' deleted.';
            $items = ghostd_record_items($sec);
            goto drawn;
        }

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
                    case 'choice':
                        // A value the field does not offer is not stored - the
                        // first option is what the mod would read anyway.
                        $opts = array_keys((array) ($fm['options'] ?? []));
                        $row[$f] = in_array(trim($v), $opts, true) ? trim($v) : ($opts[0] ?? '');
                        break;
                    default:
                        $row[$f] = trim($v);
                }
            }
            $items[$id] = $row;
        }

        // THE RANK RUNGS ARE EDITED ON THE RANK, and the promotion list is
        // only how to calculate - so each page has to put back what the other
        // owns instead of writing the document flat.
        if ($sec === 'promotion') {
            foreach (ghostd_record_items('promotion') as $pid => $pit) {
                if (str_starts_with((string) $pid, 'rank_')) {
                    $items[(string) $pid] = $pit;
                }
            }
            ghostd_auto_promote_save(isset($_POST['autoPromote']));
        }

        ghostd_record_save($sec, $items);

        // THE PICTURES. The game path went in with the row above; this is the
        // upload beside it - stored in <unit>.web.images, drawn by the site,
        // and invisible to the game.
        foreach ($meta['fields'] as $f => $fm) {
            if (($fm['kind'] ?? '') !== 'image') {
                continue;
            }
            $files = $_FILES['r_up_' . $f] ?? null;
            $clear = (array) ($_POST['r_clr_' . $f] ?? []);

            foreach ((array) ($_POST['r_id'] ?? []) as $i => $rid) {
                $rid = trim((string) $rid);
                if ($rid === '' || in_array((string) $i, $remove, true)) {
                    continue;
                }
                $key  = ghostd_record_image_key($sec, $rid, (string) $f);
                $orig = trim((string) ($_POST['r_orig'][$i] ?? ''));

                // A renamed row takes its picture with it.
                if ($orig !== '' && $orig !== $rid) {
                    $was = ghostd_record_image(ghostd_record_image_key($sec, $orig, (string) $f));
                    if ($was !== null) {
                        ghostd_record_image_put($key, (string) $was['mime'],
                            (string) base64_decode((string) $was['data'], true));
                        ghostd_record_image_put(ghostd_record_image_key($sec, $orig, (string) $f), null);
                    }
                }

                if (in_array((string) $i, $clear, true)) {
                    ghostd_record_image_put($key, null);
                }

                $err0 = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                if ($err0 !== UPLOAD_ERR_OK) {
                    continue;
                }
                if ((int) ($files['size'][$i] ?? 0) > GHOSTD_RECORD_IMAGE_MAX) {
                    throw new RuntimeException('"' . $rid . '" - that picture is '
                        . round(((int) $files['size'][$i]) / 1024) . ' KB and the limit is '
                        . round(GHOSTD_RECORD_IMAGE_MAX / 1024) . ' KB. It is an insignia, not a poster.');
                }
                // The browser's word for the type is not evidence; ask the file.
                $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file((string) $files['tmp_name'][$i]);
                if (!isset(GHOSTD_ASSET_TYPES[$mime])) {
                    throw new RuntimeException('"' . $rid . '" - that is a ' . $mime
                        . '. Use PNG, JPEG, GIF, WebP or SVG. A .paa goes in the box on the left.');
                }
                $bytes = (string) file_get_contents((string) $files['tmp_name'][$i]);
                ghostd_record_image_put($key, $mime, $bytes);
            }
        }

        if ($sec === 'ranks') {
            // Points required, off the ranks grid and into the promotion
            // document where the mod reads them.
            $rungs = [];
            foreach ((array) ($_POST['r_id'] ?? []) as $i => $rid) {
                $rid = trim((string) $rid);
                $pts = trim((string) ($_POST['r_points'][$i] ?? ''));
                if ($rid === '' || $pts === '' || !is_numeric($pts)
                    || in_array((string) $i, $remove, true)) {
                    continue;
                }
                $rungs[$rid] = $pts + 0;
            }
            ghostd_rank_thresholds_save($rungs);
        }

        $msg = count($items) . ' saved.';
        drawn:
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }

    // BACK WHERE IT WAS EDITED. A save made from the ORBAT's Variables tab
    // posts here, because this is where the logic lives - so it has to send
    // you back there rather than leaving you on a page you did not open.
    // Post/redirect/get, so a refresh does not re-save.
    if ($err === null && $recordBack !== '') {
        $_SESSION['ghostd_flash'] = $msg;
        header('Location: ' . $recordBack);
        return;
    }
}

$items = ghostd_record_items($sec);

// The promotion list is the weights. The rungs are on the ranks page.
if ($sec === 'promotion') {
    $items = array_filter($items, static fn($k) => !str_starts_with((string) $k, 'rank_'),
        ARRAY_FILTER_USE_KEY);
}
$thresholds = $sec === 'ranks' ? ghostd_rank_thresholds() : [];

// WHICH SKILL SETS WHICH TRAIT. A name a skill owns is applied by PAC and
// skipped on the role, so it is said here rather than left to be discovered.
$owners = $sec === 'traits' ? ghostd_trait_owners() : [];
$dlist  = [];
foreach ($meta['fields'] as $f => $fm) {
    if (($fm['datalist'] ?? '') === 'traitEffects') {
        $dlist['traitEffects'] = ghostd_effect_options();
    }
}

if (!$recordEmbed) { ghostd_head($meta['label'], 'records'); }
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<?php if (!$recordEmbed): ?>
<p><a href="?page=records">&larr; Configs</a> &middot;
<code><?= h(ghostd_record_doc_id($sec)) ?></code></p>
<?php else: ?>
<p class="dim"><code><?= h(ghostd_record_doc_id($sec)) ?></code> &middot;
<?= h($meta['blurb']) ?></p>
<?php endif; ?>

<h2><?= h($meta['label']) ?> <span class="dim"><?= count($items) ?></span></h2>
<p class="dim"><?= $meta['blurb'] ?> Clearing an id removes it.</p>

<?php // ACTION IS EXPLICIT so the grid works when another page draws it -
      // without it the post goes to whatever page is in the address bar. ?>
<form method="post" id="rowdel" action="?page=record&amp;s=<?= urlencode($sec) ?><?= $recordFrom !== '' ? '&amp;from=' . urlencode($recordFrom) : '' ?>">
  <input type="hidden" name="from" value="<?= h($recordFrom) ?>">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="s" value="<?= h($sec) ?>">
  <input type="hidden" name="what" value="delete">
</form>

<form method="post" enctype="multipart/form-data" action="?page=record&amp;s=<?= urlencode($sec) ?><?= $recordFrom !== '' ? '&amp;from=' . urlencode($recordFrom) : '' ?>">
  <input type="hidden" name="from" value="<?= h($recordFrom) ?>">
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
      <?php if ($sec === 'ranks'): ?><th style="width:12%">Points required</th><?php endif; ?>
      <?php if ($sec === 'traits'): ?><th style="width:16%">Set by a skill</th><?php endif; ?>
      <th style="width:<?= $delW ?>%"></th></tr>
    </thead>
    <tbody id="rows">
    <?php $i = 0; foreach ($items as $id => $it): ?>
      <tr>
        <td><input type="text" name="r_id[<?= $i ?>]" value="<?= h((string) $id) ?>">
            <input type="hidden" name="r_orig[<?= $i ?>]" value="<?= h((string) $id) ?>"></td>
        <?php foreach ($meta['fields'] as $f => $fm): ?>
          <?php
            $v = $it[$f] ?? '';
            if (is_array($v)) { $v = implode(', ', array_map('strval', $v)); }
          ?>
          <?php if ($fm['kind'] === 'image'): ?>
            <?php $ik = ghostd_record_image_key($sec, (string) $id, (string) $f); ?>
            <td>
              <input type="text" name="r_<?= h($f) ?>[<?= $i ?>]" value="<?= h((string) $v) ?>"
                     placeholder="<?= h((string) ($fm['help'] ?? '')) ?>"
                     title="the game path - a .paa the browser cannot draw">
              <div class="fieldrow">
                <?php if (ghostd_record_image($ik) !== null): ?>
                  <img class="recthumb" src="?page=recimg&amp;k=<?= urlencode($ik) ?>" alt="">
                  <label class="inlinelabel">
                    <input type="checkbox" name="r_clr_<?= h($f) ?>[]" value="<?= $i ?>"> drop
                  </label>
                <?php endif; ?>
                <input type="file" name="r_up_<?= h($f) ?>[<?= $i ?>]" accept="image/*">
              </div>
            </td>
          <?php elseif ($fm['kind'] === 'choice'): ?>
            <td><select name="r_<?= h($f) ?>[<?= $i ?>]"
                        <?= isset($fm['help']) ? 'title="' . h($fm['help']) . '"' : '' ?>>
              <?php foreach ((array) ($fm['options'] ?? []) as $ov => $ol): ?>
                <option value="<?= h((string) $ov) ?>" <?= (string) $v === (string) $ov ? 'selected' : '' ?>><?= h($ol) ?></option>
              <?php endforeach; ?>
            </select></td>
          <?php else: ?>
            <td><input type="<?= $fm['kind'] === 'number' ? 'number' : 'text' ?>"
                       name="r_<?= h($f) ?>[<?= $i ?>]" value="<?= h((string) $v) ?>"
                       <?= isset($fm['datalist']) ? 'list="dl_' . h($fm['datalist']) . '"' : '' ?>
                       <?= isset($fm['help']) ? 'title="' . h($fm['help']) . '"' : '' ?>></td>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($sec === 'traits'): ?>
          <td class="dim"><?= isset($owners[strtolower((string) $id)])
                ? h(implode(', ', $owners[strtolower((string) $id)])) . ' - a role cannot also set it'
                : 'nobody - a role may set it' ?></td>
        <?php endif; ?>
        <?php if ($sec === 'ranks'): ?>
          <td><input type="number" step="1" name="r_points[<?= $i ?>]"
                     value="<?= h((string) ($thresholds[(string) $id] ?? '')) ?>"
                     title="points a man needs before he can hold this rank"></td>
        <?php endif; ?>
        <td><button type="submit" form="rowdel" name="id" value="<?= h((string) $id) ?>" class="hot"
                    onclick="return confirm('Delete <?= h((string) $id) ?>?');">Delete</button></td>
      </tr>
    <?php $i++; endforeach; ?>
      <tr>
        <td><input type="text" name="r_id[<?= $i ?>]" placeholder="new"></td>
        <?php foreach ($meta['fields'] as $f => $fm): ?>
          <?php if ($fm['kind'] === 'choice'): ?>
            <td><select name="r_<?= h($f) ?>[<?= $i ?>]">
              <?php foreach ((array) ($fm['options'] ?? []) as $ov => $ol): ?>
                <option value="<?= h((string) $ov) ?>"><?= h($ol) ?></option>
              <?php endforeach; ?>
            </select></td>
          <?php else: ?>
            <td><input type="<?= $fm['kind'] === 'number' ? 'number' : 'text' ?>"
                       name="r_<?= h($f) ?>[<?= $i ?>]"
                       placeholder="<?= h((string) ($fm['help'] ?? $fm['label'])) ?>"></td>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($sec === 'ranks'): ?><td><input type="number" name="r_points[<?= $i ?>]"></td><?php endif; ?>
        <td></td>
      </tr>
    </tbody>
  </table>

  <template id="rows-row">
    <tr>
      <td><input type="text" name="r_id[__I__]" placeholder="new"></td>
      <?php foreach ($meta['fields'] as $f => $fm): ?>
        <?php if ($fm['kind'] === 'choice'): ?>
          <td><select name="r_<?= h($f) ?>[__I__]">
            <?php foreach ((array) ($fm['options'] ?? []) as $ov => $ol): ?>
              <option value="<?= h((string) $ov) ?>"><?= h($ol) ?></option>
            <?php endforeach; ?>
          </select></td>
        <?php else: ?>
          <td><input type="<?= $fm['kind'] === 'number' ? 'number' : 'text' ?>"
                     name="r_<?= h($f) ?>[__I__]"
                     placeholder="<?= h((string) ($fm['help'] ?? $fm['label'])) ?>"></td>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if ($sec === 'ranks'): ?><td><input type="number" name="r_points[__I__]"></td><?php endif; ?>
      <td></td>
    </tr>
  </template>

  <p class="actions"><button type="button" data-addrow="rows">+ Add</button></p>

  <?php if ($sec === 'promotion'): ?>
    <p class="dim">Points required to hold a rank are on the
    <a href="?page=record&amp;s=ranks">ranks</a> page - this is how the points
    are counted.</p>
    <label class="inlinelabel">
      <input type="checkbox" name="autoPromote" <?= ghostd_auto_promote() ? 'checked' : '' ?>>
      Promote automatically when a man is over the line
    </label>
    <p class="dim">Off, the dashboard lists who is due and a human does it.</p>
  <?php endif; ?>

  <div class="actions"><button type="submit">Save <?= h(strtolower($meta['label'])) ?></button></div>
</form>

<?php foreach ($dlist as $name => $values): ?>
  <datalist id="dl_<?= h((string) $name) ?>">
    <?php foreach ($values as $v): ?><option value="<?= h((string) $v) ?>"></option><?php endforeach; ?>
  </datalist>
<?php endforeach; ?>
<?php
if (!$recordEmbed) { ghostd_foot(); }
