<?php
/**
 * The calendar page: what is coming up and what was played.
 *
 * ONE PAGE, THREE READERS. A visitor who has not signed in sees the public
 * events - index.php sends this page round the login gate for that. A member
 * sees every event. An admin gets the form as well, and the list is the same
 * one the website shows through the feed, so what is written here is what the
 * public sees.
 */

declare(strict_types=1);

require_once __DIR__ . '/../events.php';
require_once __DIR__ . '/../opords.php';
require_once __DIR__ . '/../auth.php';

$signedIn = ghostd_logged_in();
$canEdit  = $signedIn && ghostd_can_edit();
$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        header('Location: ?page=login');
        exit;
    }
    ghostd_csrf_check();
    try {
        $action = (string) ($_POST['action'] ?? '');
        $tz = ghostd_events_timezone();
        if ($action === 'save') {
            $id = trim((string) ($_POST['id'] ?? ''));
            if ($id === '') {
                $id = ghostd_event_new_id();
            }
            $item = ghostd_event_from_post($_POST, $tz, $id);
            ghostd_events_put($item);
            $msg = 'Saved ' . $item['title'] . '.';
        } elseif ($action === 'delete') {
            ghostd_events_delete(trim((string) ($_POST['id'] ?? '')));
            $msg = 'Removed.';
        } elseif ($action === 'timezone') {
            ghostd_events_set_timezone(trim((string) ($_POST['timezone'] ?? '')));
            $msg = 'Time zone saved. Times below are shown in it.';
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$cal = ghostd_events();
$tz  = $cal['timezone'];
$items = $signedIn ? $cal['items'] : array_filter($cal['items'], static fn($e) => $e['public']);
[$upcoming, $past] = ghostd_events_split($items);
$next = null;
foreach ($upcoming as $e) {
    if (!$e['cancelled'] && $e['start'] !== '') {
        $next = $e;
        break;
    }
}

// The one being edited, if the admin followed an Edit link.
$editing = null;
if ($canEdit && isset($_GET['edit']) && isset($cal['items'][(string) $_GET['edit']])) {
    $editing = $cal['items'][(string) $_GET['edit']];
}
$opordIds = [];
if ($canEdit) {
    try {
        $opordIds = ghostd_opord_ids();
    } catch (Throwable $e) {
        $opordIds = [];
    }
}

/** A local time for the table, "Sat 11 Oct 2026, 19:00". */
$whenText = static function (array $e) use ($tz): string {
    $w = ghostd_event_when($e, $tz);
    return $w === null ? '' : $w->format('D j M Y, H:i');
};
/** The length, "4 h" or "90 min". */
$lenText = static function (int $m): string {
    if ($m <= 0) {
        return '';
    }
    return $m % 60 === 0 ? ($m / 60) . ' h' : $m . ' min';
};

$rowOf = static function (array $e) use ($whenText, $lenText, $canEdit, $signedIn): void {
    ?>
    <tr class="<?= $e['cancelled'] ? 'ev-cancelled' : '' ?>">
      <td class="ev-when"><?= h($whenText($e)) ?></td>
      <td><strong><?= h($e['title']) ?></strong>
        <?php if ($e['cancelled']): ?> <span class="pill hot">cancelled</span><?php endif; ?>
        <?php if ($signedIn && !$e['public']): ?> <span class="pill dimpill">members only</span><?php endif; ?>
        <?php if ($e['summary'] !== ''): ?><div class="dim"><?= h($e['summary']) ?></div><?php endif; ?>
      </td>
      <td><?= h($e['server']) ?></td>
      <td class="dim"><?= h($lenText($e['minutes'])) ?></td>
      <td>
        <?php if ($e['opord'] !== ''): ?>
          <?php if ($canEdit): ?><a href="?page=opord&amp;id=<?= urlencode($e['opord']) ?>"><code><?= h($e['opord']) ?></code></a>
          <?php else: ?><code><?= h($e['opord']) ?></code><?php endif; ?>
        <?php endif; ?>
      </td>
      <?php if ($canEdit): ?>
      <td class="rowacts">
        <a href="?page=events&amp;edit=<?= urlencode($e['id']) ?>#evform">Edit</a>
        <form method="post" class="inline confirm" style="display:inline">
          <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= h($e['id']) ?>">
          <button type="submit" class="btnquiet">Remove</button>
        </form>
      </td>
      <?php endif; ?>
    </tr>
    <?php
};

ghostd_head('Events', 'events');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
?>

<?php if ($next !== null): ?>
<div class="card ev-next">
  <p class="dim ev-label">Next up</p>
  <h2><?= h($next['title']) ?></h2>
  <p class="ev-nextwhen"><?= h($whenText($next)) ?> <span class="dim"><?= h($tz) ?></span>
    <?php if ($next['server'] !== ''): ?> &middot; <?= h($next['server']) ?><?php endif; ?>
    <?php if ($next['minutes'] > 0): ?> &middot; <?= h($lenText($next['minutes'])) ?><?php endif; ?>
  </p>
  <?php if ($next['summary'] !== ''): ?><p><?= h($next['summary']) ?></p><?php endif; ?>
  <p class="ev-count" data-start="<?= h($next['start']) ?>" aria-live="polite"></p>
</div>
<?php endif; ?>

<?php if (!$signedIn): ?>
<p class="note">These are the unit's public events, in <?= h($tz) ?> time.
<a href="?page=login">Sign in</a> to see the rest.</p>
<?php endif; ?>

<?php if ($canEdit): ?>
<details class="card" id="evform" <?= $editing !== null || $upcoming === [] ? 'open' : '' ?>>
  <summary><strong><?= $editing !== null ? 'Edit event' : 'Add an event' ?></strong>
    <span class="dim">times are in <?= h($tz) ?></span></summary>
  <form method="post" class="fields">
    <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= h($editing['id'] ?? '') ?>">

    <label for="ev_title">Title</label>
    <input type="text" id="ev_title" name="title" maxlength="120" required
           value="<?= h($editing['title'] ?? '') ?>" placeholder="Operation Iron Veil, phase 2">

    <label for="ev_start">Starts <span class="dim"><?= h($tz) ?></span></label>
    <?php $startLocal = $editing !== null ? (ghostd_event_when($editing, $tz)?->format('Y-m-d\TH:i') ?? '') : ''; ?>
    <input type="datetime-local" id="ev_start" name="start" required value="<?= h($startLocal) ?>">

    <label for="ev_minutes">Length <span class="dim">minutes; 0 for open-ended</span></label>
    <input type="number" id="ev_minutes" name="minutes" min="0" max="1440" step="15"
           value="<?= (int) ($editing['minutes'] ?? 240) ?>">

    <label for="ev_server">Server</label>
    <input type="text" id="ev_server" name="server" list="ev_servers" maxlength="60"
           value="<?= h($editing['server'] ?? GHOSTD_EVENT_SERVERS[0]) ?>">
    <datalist id="ev_servers">
      <?php foreach (GHOSTD_EVENT_SERVERS as $s): ?><option value="<?= h($s) ?>"></option><?php endforeach; ?>
    </datalist>

    <label for="ev_opord">Operation order <span class="dim">optional</span></label>
    <select id="ev_opord" name="opord">
      <option value="">none</option>
      <?php foreach ($opordIds as $oid): ?>
        <option value="<?= h($oid) ?>" <?= ($editing['opord'] ?? '') === $oid ? 'selected' : '' ?>><?= h($oid) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="ev_summary">Summary <span class="dim">one or two lines, shown on the list</span></label>
    <textarea id="ev_summary" name="summary" rows="2" maxlength="600" class="short"><?= h($editing['summary'] ?? '') ?></textarea>

    <label class="inlinelabel">
      <input type="checkbox" name="public" <?= ($editing === null || $editing['public']) ? 'checked' : '' ?>>
      Public <span class="dim">shown to visitors and on the website's feed</span>
    </label>
    <label class="inlinelabel">
      <input type="checkbox" name="cancelled" <?= !empty($editing['cancelled']) ? 'checked' : '' ?>>
      Cancelled <span class="dim">kept on the list, struck through</span>
    </label>

    <div class="actions">
      <button type="submit"><?= $editing !== null ? 'Save' : 'Add' ?></button>
      <?php if ($editing !== null): ?><a href="?page=events" class="btnlink btnquiet">Cancel</a><?php endif; ?>
    </div>
  </form>

  <form method="post" class="inline" style="margin-top:var(--s4)">
    <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
    <input type="hidden" name="action" value="timezone">
    <label for="ev_tz">Unit time zone</label>
    <select id="ev_tz" name="timezone">
      <?php foreach (DateTimeZone::listIdentifiers() as $z): ?>
        <option value="<?= h($z) ?>" <?= $z === $tz ? 'selected' : '' ?>><?= h($z) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btnquiet">Set</button>
    <span class="dim">the zone the form takes times in; stored times do not move</span>
  </form>
</details>
<?php endif; ?>

<h2>Upcoming</h2>
<?php if ($upcoming === []): ?>
  <p class="dim">Nothing scheduled.</p>
<?php else: ?>
<table class="grid ev-table">
  <thead><tr><th>When</th><th>Event</th><th>Server</th><th>Length</th><th>Order</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
  <tbody><?php foreach ($upcoming as $e) { $rowOf($e); } ?></tbody>
</table>
<?php endif; ?>

<?php if ($past !== []): ?>
<h2>Played</h2>
<table class="grid ev-table ev-past">
  <thead><tr><th>When</th><th>Event</th><th>Server</th><th>Length</th><th>Order</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
  <tbody><?php foreach (array_slice($past, 0, 30, true) as $e) { $rowOf($e); } ?></tbody>
</table>
<?php endif; ?>

<script>
/* The countdown under Next up. The time is UTC in the data attribute; the
   visitor's browser does the arithmetic, so nobody's zone is assumed. */
(function () {
  var el = document.querySelector('.ev-count');
  if (!el) { return; }
  var t = Date.parse(el.getAttribute('data-start'));
  if (isNaN(t)) { return; }
  function two(n) { return n < 10 ? '0' + n : '' + n; }
  function tick() {
    var d = t - Date.now();
    if (d <= 0) { el.textContent = 'Under way'; return; }
    var s = Math.floor(d / 1000), days = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60);
    el.textContent = (days ? days + 'd ' : '') + two(h) + 'h ' + two(m) + 'm ' + two(s % 60) + 's';
    setTimeout(tick, 1000);
  }
  tick();
})();
</script>
<?php
ghostd_foot();
