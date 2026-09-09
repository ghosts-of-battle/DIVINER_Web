<?php
/**
 * PAC actions: the list, and the form to raise one.
 *
 * A member sees their own and any raised about them; an admin sees the lot.
 * That filtering is done in ghostd_ticket_visible, which the single-ticket page
 * checks too - so a member guessing an id still gets nowhere.
 */

declare(strict_types=1);

require_once __DIR__ . '/../tickets.php';

$msg = null;
$err = null;
$raised = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $raised = ghostd_ticket_raise(
            (string) ($_POST['kind'] ?? ''),
            (string) ($_POST['subject'] ?? ''),
            (string) ($_POST['body'] ?? ''),
            trim((string) ($_POST['about'] ?? ''))
        );
        $msg = 'Raised as ' . $raised . '. An admin will pick it up.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$all = [];
$dbErr = null;
try {
    $all = ghostd_tickets();
} catch (Throwable $e) {
    $dbErr = $e->getMessage();
}
$mine = array_values(array_filter($all, 'ghostd_ticket_visible'));

$filter = (string) ($_GET['show'] ?? 'open');
$shown = array_values(array_filter($mine, static function ($t) use ($filter) {
    $s = (string) ($t['status'] ?? 'open');
    return $filter === 'all' ? true : ($filter === 'open' ? $s === 'open' : $s === $filter);
}));

// The roster, so an award recommendation can name somebody.
$players = [];
try {
    $store = ghostd_get(ghostd_config()['unit']);
    $players = (is_array($store['players'] ?? null)) ? $store['players'] : [];
    uasort($players, static fn($a, $b) => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
} catch (Throwable $e) {
    // Raising one without naming anybody still works.
}

ghostd_head('PAC actions', 'tickets');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
if ($dbErr !== null) { ghostd_flash('bad', 'PAC actions could not be read: ' . $dbErr); }
?>
<p class="note">Leave, award recommendations, requests and problems - anything
that needs somebody to answer. Raise one here or in game; they are the same
list either way.</p>

<details class="card" <?= $shown === [] ? 'open' : '' ?>>
  <summary><strong>Raise a PAC action</strong></summary>
  <form method="post" class="fields">
    <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">

    <label for="kind">What kind</label>
    <select id="kind" name="kind" required>
      <?php foreach (GHOSTD_TICKET_KINDS as $k => $meta): ?>
        <option value="<?= h($k) ?>"><?= h($meta['label']) ?> - <?= h($meta['hint']) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="subject">Subject <span class="dim">one line</span></label>
    <input type="text" id="subject" name="subject" maxlength="200" required
           placeholder="Away 14-21 March / Recommend Cpl Miller for ...">

    <label for="about">Who it is about <span class="dim">optional - for an award recommendation</span></label>
    <select id="about" name="about">
      <option value="">-</option>
      <?php foreach ($players as $uid => $p): ?>
        <option value="<?= h((string) $uid) ?>"><?= h((string) ($p['name'] ?? $uid)) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="body">Details</label>
    <textarea id="body" name="body" rows="6" class="short"
              placeholder="Dates, what happened, what you are asking for."></textarea>

    <div class="actions"><button type="submit">Raise it</button></div>
  </form>
</details>

<h2>
  <?= ghostd_is_admin() ? 'Everything raised' : 'Yours' ?>
  <span class="dim"><?= count($shown) ?> of <?= count($mine) ?></span>
</h2>
<p class="dim">
  <?php foreach (['open' => 'Open', 'accepted' => 'Accepted', 'declined' => 'Declined', 'closed' => 'Closed', 'all' => 'All'] as $k => $label): ?>
    <a href="?page=tickets&amp;show=<?= h($k) ?>" <?= $filter === $k ? 'class="on"' : '' ?>><?= h($label) ?></a>
    <?= $k !== 'all' ? ' &middot; ' : '' ?>
  <?php endforeach; ?>
</p>

<?php if ($shown === []): ?>
  <p class="dim">Nothing here.</p>
<?php else: ?>
<table class="grid">
  <thead><tr><th>Id</th><th>Kind</th><th>Subject</th><th>Raised by</th><th>About</th><th>State</th><th>Updated</th></tr></thead>
  <tbody>
  <?php foreach ($shown as $t): ?>
    <?php $tid = (string) ($t['id'] ?? ''); $st = (string) ($t['status'] ?? 'open'); ?>
    <tr>
      <td><a href="?page=ticket&amp;id=<?= urlencode($tid) ?>"><code><?= h($tid) ?></code></a></td>
      <td><?= h(GHOSTD_TICKET_KINDS[(string) ($t['kind'] ?? '')]['label'] ?? (string) ($t['kind'] ?? '')) ?></td>
      <td><a href="?page=ticket&amp;id=<?= urlencode($tid) ?>"><?= h((string) ($t['subject'] ?? '')) ?></a></td>
      <td><?= h((string) ($t['raisedByName'] ?? $t['raisedBy'] ?? '')) ?></td>
      <td><?php
        $ab = (string) ($t['about'] ?? '');
        echo $ab === '' ? '<span class="dim">-</span>'
           : h((string) ($players[$ab]['name'] ?? $ab));
      ?></td>
      <td><span class="pill <?= $st === 'open' ? '' : ($st === 'declined' ? 'hot' : 'dimpill') ?>"><?= h(GHOSTD_TICKET_STATUSES[$st] ?? $st) ?></span></td>
      <td class="dim"><?= h((string) ($t['updatedAt'] ?? '')) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php
ghostd_foot();
