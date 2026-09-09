<?php
/**
 * One PAC action: the thread, and what can be done to it.
 *
 * Visibility is checked here as well as on the list - a member who guesses an
 * id must get the same answer as a member who was never shown one.
 */

declare(strict_types=1);

require_once __DIR__ . '/../system.php';

require_once __DIR__ . '/../tickets.php';

$id  = (string) ($_GET['id'] ?? ($_POST['id'] ?? ''));
$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $status = (string) ($_POST['status'] ?? '');
        ghostd_ticket_reply(
            $id,
            (string) ($_POST['text'] ?? ''),
            $status !== '' ? $status : null,
            isset($_POST['private'])
        );
        $msg = $status !== '' ? 'Marked ' . (GHOSTD_TICKET_STATUSES[$status] ?? $status) . '.' : 'Reply added.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$t = null;
try {
    $t = ghostd_ticket($id);
} catch (Throwable $e) {
    $err = $err ?? $e->getMessage();
}

// Filtered for whoever is reading before anything renders it.
if ($t !== null) {
    $t = ghostd_ticket_for_reader($t);
}

if ($t === null || !ghostd_ticket_visible($t)) {
    ghostd_head('PAC action', 'tickets');
    ghostd_flash('bad', 'No PAC action with that id, or it is not yours to read.');
    echo '<p><a href="?page=tickets">Back to the list</a></p>';
    ghostd_foot();
    return;
}

$st      = (string) ($t['status'] ?? 'open');
$replies = is_array($t['replies'] ?? null) ? $t['replies'] : [];

$aboutName = '';
$about = (string) ($t['about'] ?? '');
if ($about !== '') {
    try {
        $store = ghostd_get(ghostd_config()['unit']);
        $aboutName = (string) ($store['players'][$about]['name'] ?? $about);
    } catch (Throwable $e) {
        $aboutName = $about;
    }
}

ghostd_head((string) ($t['subject'] ?? 'PAC action'), 'tickets');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<p class="dim"><a href="?page=tickets">&larr; PAC actions</a></p>

<table class="kv">
  <tr><th>Id</th><td><code><?= h((string) ($t['id'] ?? '')) ?></code></td></tr>
  <tr><th>Kind</th><td><?= h(ghostd_ticket_kinds()[(string) ($t['kind'] ?? '')]['label'] ?? (string) ($t['kind'] ?? '')) ?></td></tr>
  <tr><th>Raised by</th><td><?= h((string) ($t['raisedByName'] ?? $t['raisedBy'] ?? '')) ?>
      <span class="dim"><?= h((string) ($t['raisedBy'] ?? '')) ?></span></td></tr>
  <?php if ($about !== ''): ?>
    <tr><th>About</th><td><a href="?page=player&amp;uid=<?= urlencode($about) ?>"><?= h($aboutName) ?></a></td></tr>
  <?php endif; ?>
  <tr><th>State</th><td><span class="pill <?= $st === 'open' ? '' : ($st === 'declined' ? 'hot' : 'dimpill') ?>"><?= h(GHOSTD_TICKET_STATUSES[$st] ?? $st) ?></span></td></tr>
  <tr><th>Raised</th><td><?= h((string) ($t['createdAt'] ?? '')) ?></td></tr>
</table>

<h2>Thread</h2>
<?php foreach ($replies as $r): ?>
  <div class="card reply">
    <p class="dim replyhead">
      <strong><?= h((string) ($r['byName'] ?? $r['byUid'] ?? '')) ?></strong>
      <?= h((string) ($r['at'] ?? '')) ?>
      <?php if (!empty($r['status'])): ?>
        <span class="pill dimpill">marked <?= h(GHOSTD_TICKET_STATUSES[(string) $r['status']] ?? (string) $r['status']) ?></span>
      <?php endif; ?>
      <?php if (!empty($r['private'])): ?>
        <span class="pill hot">private</span>
      <?php endif; ?>
    </p>
    <?php $txt = (string) ($r['text'] ?? ''); ?>
    <?php if ($txt !== ''): ?><p><?= nl2br(h($txt)) ?></p><?php endif; ?>
  </div>
<?php endforeach; ?>

<h2>Add to it</h2>
<form method="post" class="card fields">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= h((string) ($t['id'] ?? '')) ?>">
  <label for="text">Reply</label>
  <textarea id="text" name="text" rows="4" class="short"></textarea>

  <?php if (ghostd_is_admin()): ?>
    <label class="inlinelabel">
      <input type="checkbox" name="private" value="1">
      Private note - only admins see this
    </label>
    <label for="status">Change the state <span class="dim">optional</span></label>
    <select id="status" name="status">
      <option value="">leave as <?= h(GHOSTD_TICKET_STATUSES[$st] ?? $st) ?></option>
      <?php foreach (GHOSTD_TICKET_STATUSES as $k => $label): ?>
        <?php if ($k !== $st): ?><option value="<?= h($k) ?>"><?= h($label) ?></option><?php endif; ?>
      <?php endforeach; ?>
    </select>
  <?php else: ?>
    <p class="dim">An admin decides the outcome; you can keep adding to the thread.</p>
  <?php endif; ?>

  <div class="actions"><button type="submit">Send</button></div>
</form>
<?php
ghostd_foot();
