<?php
/**
 * The nightly backup, read-only, admins only.
 *
 * FOR THE DAY MONGO IS NOT THERE (user, 2026-09-09). Nothing on this page
 * touches the database: it reads the file cron wrote, so it still answers when
 * the service is down - which is the only time anybody wants it.
 *
 * READ ONLY. There is no import button and no restore: this hands an admin the
 * document to put into a mission, and nothing here can change what is in Mongo.
 */

declare(strict_types=1);

require_once __DIR__ . '/../backup.php';

if (!ghostd_is_admin()) {
    ghostd_head('Backup', 'backup');
    ghostd_flash('bad', 'Admins only.');
    ghostd_foot();
    return;
}

$meta = ghostd_backup_meta();

$showId  = trim((string) ($_GET['doc'] ?? ''));
$showDoc = $showId !== '' ? ghostd_backup_doc($showId) : null;

// The whole file in a browser tab - a read-only window on the lot.
if (isset($_GET['raw']) && $meta !== null) {
    header('Content-Type: text/plain; charset=utf-8');
    readfile($meta['path']);
    exit;
}

// The file itself, when asked for - this is how it gets into a mission.
if (isset($_GET['download']) && $meta !== null) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . basename($meta['path']) . '"');
    header('Content-Length: ' . $meta['bytes']);
    readfile($meta['path']);
    exit;
}

ghostd_head('Backup', 'backup');
?>
<?php if ($meta === null): ?>
  <p class="note">There is no backup yet. It is written by
  <code>tools/backup.php</code> from cron, once a day - see
  <code>DEPLOY.md</code>.</p>
<?php else: ?>
  <?php $stale = $meta['age'] > 172800; ?>
  <table class="kv">
    <tr><th>Taken</th><td><?= h($meta['takenAt']) ?> UTC
        <?php if ($stale): ?>
          <span class="pill hot">stale - <?= (int) floor($meta['age'] / 86400) ?> days old</span>
        <?php endif; ?></td></tr>
    <tr><th>Documents</th><td><?= (int) $meta['count'] ?></td></tr>
    <tr><th>Size</th><td><?= h(ghostd_bytes($meta['bytes'])) ?></td></tr>
  </table>

  <p class="actions">
    <a class="btnlink" href="?page=backup&amp;download=1">Download the file</a>
  </p>

  <p class="dim">One JSON object - <code>{unit, takenAt, count, docs}</code>,
  every document under <code>docs</code> keyed by its id. Read only: nothing on
  this page writes to Mongo or to the file.</p>

  <h2>Read it</h2>
  <form method="get" class="inline">
    <input type="hidden" name="page" value="backup">
    <label for="doc">Document</label>
    <select id="doc" name="doc">
      <option value="">- pick one -</option>
      <?php foreach (ghostd_backup_ids() as $bid): ?>
        <option value="<?= h($bid) ?>" <?= $bid === $showId ? 'selected' : '' ?>><?= h($bid) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Show</button>
    <a class="btnlink" href="?page=backup&amp;raw=1" target="_blank" rel="noopener">The whole file</a>
  </form>

  <?php if ($showId !== '' && $showDoc !== null): ?>
    <textarea readonly spellcheck="false"><?= h($showDoc) ?></textarea>
  <?php elseif ($showId !== ''): ?>
    <p class="dim">No <code><?= h($showId) ?></code> in this backup.</p>
  <?php endif; ?>

  <h2>Kept <span class="dim"><?= count(ghostd_backup_list()) ?></span></h2>
  <table class="grid">
    <thead><tr><th style="width:50%">File</th><th style="width:25%">Taken</th>
        <th style="width:25%">Size</th></tr></thead>
    <tbody>
    <?php foreach (ghostd_backup_list() as $b): ?>
      <tr>
        <td><code><?= h($b['name']) ?></code></td>
        <td class="dim"><?= h(gmdate('Y-m-d H:i', $b['mtime'])) ?></td>
        <td class="dim"><?= h(ghostd_bytes($b['bytes'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php
ghostd_foot();
