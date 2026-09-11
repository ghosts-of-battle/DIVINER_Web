<?php
/**
 * The media folder: what the unit shares, and who may read it.
 *
 * One list, one upload box, one Delete per row with an are-you-sure - the same
 * shape as every other list on this site.
 */

declare(strict_types=1);

require_once __DIR__ . '/../media.php';

$msg = null;
$err = null;

// WHO IS UPLOADING. A name for the column, not an id: the column is read by
// people, and "shared by 76561198…" tells nobody anything.
$who = trim((string) ($_SESSION['ghostd']['name'] ?? '')) ?: 'someone';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        switch ((string) ($_POST['what'] ?? '')) {
            case 'upload':
                $vis  = (string) ($_POST['visibility'] ?? 'site');
                $note = (string) ($_POST['note'] ?? '');
                $n = 0;
                // One <input multiple>, so PHP hands back parallel arrays.
                $files = $_FILES['file'] ?? null;
                if (!is_array($files) || !isset($files['name'])) {
                    throw new RuntimeException('No file was chosen.');
                }
                $names = (array) $files['name'];
                foreach (array_keys($names) as $i) {
                    if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    ghostd_media_add([
                        'name'     => $files['name'][$i],
                        'type'     => $files['type'][$i] ?? '',
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i],
                    ], $who, $vis, $note);
                    $n++;
                }
                if ($n === 0) {
                    throw new RuntimeException('No file was chosen.');
                }
                $msg = $n . ($n === 1 ? ' file' : ' files') . ' shared.';
                break;

            case 'edit':
                ghostd_media_update((string) ($_POST['id'] ?? ''), [
                    'name'       => $_POST['name'] ?? '',
                    'note'       => $_POST['note'] ?? '',
                    'visibility' => (string) ($_POST['visibility'] ?? 'site'),
                ]);
                $msg = 'Saved.';
                break;

            case 'delete':
                $f = ghostd_media_one((string) ($_POST['id'] ?? ''));
                ghostd_media_delete((string) ($_POST['id'] ?? ''));
                $msg = ($f['name'] ?? 'The file') . ' deleted.';
                break;

            default:
                throw new RuntimeException('Nothing said what to do.');
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$files = ghostd_media_all();
$dir   = ghostd_media_dir();

ghostd_head('Media', 'media');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<p class="dim">Files the unit shares. They are kept outside the web root and
handed out by this app, never by the web server - so <strong>site only</strong>
really is site only. <?= count($files) ?> file<?= count($files) === 1 ? '' : 's' ?>.</p>

<?php if (!is_dir($dir) || !is_writable($dir)): ?>
  <p class="flash bad">The media folder is missing or not writable by the web
  user: <code><?= h($dir) ?></code>. On the server:
  <code>sudo mkdir -p <?= h($dir) ?> &amp;&amp; sudo chown root:apache <?= h($dir) ?> &amp;&amp; sudo chmod 750 <?= h($dir) ?></code></p>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="fieldbox uploadbox">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="what" value="upload">
  <label class="stacked">Add files
    <input type="file" name="file[]" multiple required></label>
  <label class="stacked">Who may read them
    <select name="visibility">
      <option value="site">Site only - anyone signed in</option>
      <option value="public">Public - anyone with the link</option>
    </select></label>
  <label class="stacked">Note <span class="dim">optional</span>
    <input type="text" name="note" placeholder="what it is"></label>
  <div class="actions"><button type="submit">Share</button></div>
</form>

<p class="dim">Takes <?= h(implode(', ', array_keys(GHOSTD_MEDIA_TYPES))) ?>,
up to <?= h(ghostd_media_size(GHOSTD_MEDIA_MAX)) ?> each.</p>

<?php // One form for every row's Delete, reached by form="mediadel". ?>
<form method="post" id="mediadel">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="what" value="delete">
</form>

<?php if ($files === []): ?>
  <p class="note readonly">Nothing shared yet.</p>
<?php else: ?>
<table class="grid">
  <thead>
    <tr><th style="width:26%">File</th><th style="width:10%">Size</th>
        <th style="width:16%">Who may read</th><th style="width:18%">Shared by</th>
        <th style="width:14%">Link</th><th style="width:16%"></th></tr>
  </thead>
  <tbody>
  <?php foreach ($files as $id => $f): ?>
    <tr>
      <td>
        <form method="post" class="inline" id="ed_<?= h($id) ?>">
          <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
          <input type="hidden" name="what" value="edit">
          <input type="hidden" name="id" value="<?= h($id) ?>">
          <input type="text" name="name" value="<?= h($f['name']) ?>">
        </form>
        <?php if ($f['note'] !== ''): ?><span class="dim"><?= h($f['note']) ?></span><?php endif; ?>
      </td>
      <td class="dim"><?= h(ghostd_media_size($f['size'])) ?></td>
      <td>
        <select name="visibility" form="ed_<?= h($id) ?>">
          <option value="site" <?= $f['visibility'] === 'site' ? 'selected' : '' ?>>Site only</option>
          <option value="public" <?= $f['visibility'] === 'public' ? 'selected' : '' ?>>Public</option>
        </select>
      </td>
      <td class="dim"><?= h($f['by']) ?><br><span class="dim"><?= h($f['at']) ?></span></td>
      <td><a class="btnlink" href="?page=file&amp;id=<?= urlencode($id) ?>" target="_blank">Open</a></td>
      <td class="rowacts">
        <button type="submit" form="ed_<?= h($id) ?>">Save</button>
        <button type="submit" form="mediadel" name="id" value="<?= h($id) ?>" class="hot"
                onclick="return confirm('Delete <?= h($f['name']) ?>? The file goes too, and that cannot be undone.');">Delete</button>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php
ghostd_foot();
