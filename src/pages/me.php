<?php
/**
 * Your own details.
 *
 * WHAT A PERSON MAY CHANGE ABOUT THEMSELVES is deliberately short - a
 * preferred name and two ways to be contacted, all optional. Rank, role,
 * group, skills and awards are things the unit gives you, and a roster where
 * people set their own rank is not a roster.
 *
 * THE WRITE IS NOT TRUSTED FROM HERE. ghostd_set_self_path checks the field is
 * allowed and that the uid is the signed-in Steam id, so this page cannot be
 * talked into editing somebody else by a crafted POST.
 */

declare(strict_types=1);

require_once __DIR__ . '/../system.php';

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../records.php';   // ghostd_rank_insignia()

$cfg     = ghostd_config();
$storeId = $cfg['unit'];
$uid     = ghostd_self_uid();

$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $saved = [];
        foreach (GHOSTD_SELF_FIELDS as $field => $label) {
            if (!array_key_exists($field, $_POST)) {
                continue;
            }
            $value = trim((string) $_POST[$field]);

            if ($field === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $err = 'That does not look like an email address. Leave it empty if you would rather not give one.';
                continue;
            }
            if ($field === 'discordId' && $value !== '' && !preg_match('/^\d{5,25}$/', $value)) {
                $err = 'A Discord id is the long number, not your handle - Discord, '
                     . 'Settings, Advanced, Developer Mode, then right-click yourself and Copy User ID.';
                continue;
            }

            ghostd_set_self_path($storeId, $uid, $field, $value);
            $saved[] = $label;
        }
        if ($saved !== [] && $err === null) {
            $msg = implode(' and ', $saved) . ' saved.';
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$store  = null;
$me     = null;
$dbDown = null;
try {
    $store = ghostd_get($storeId);
    $me    = $store['players'][$uid] ?? null;
} catch (Throwable $e) {
    $dbDown = $e->getMessage();
}

$labels = static function (string $section): array {
    try {
        $doc = ghostd_get(ghostd_config()['unit'] . '.' . $section);
    } catch (Throwable $e) {
        return [];
    }
    $items = (is_array($doc['items'] ?? null)) ? $doc['items'] : [];
    $out = [];
    foreach ($items as $id => $it) {
        $out[(string) $id] = is_array($it) ? (string) ($it['name'] ?? $it['title'] ?? $id) : (string) $id;
    }
    return $out;
};

ghostd_head('My details', 'me');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }

if ($dbDown !== null) {
    ghostd_flash('bad', 'The roster cannot be read right now: ' . $dbDown);
    ghostd_foot();
    return;
}

if ($me === null) {
    ?>
    <div class="card">
      <h2>You are not on the roster yet</h2>
      <p>Steam says you are <?= steamlink($uid) ?>, and that id has no
      record with this unit. A record is created the first time you join the
      server - so if you have played here, it will already exist under a
      different Steam account than the one you just used.</p>
      <p class="dim">If you are joining, an admin can add you before your first
      session: give them the id above.</p>
    </div>
    <?php
    ghostd_foot();
    return;
}

$rankNames   = $labels('ranks');
$statusNames = $labels('statuses');
?>
<div class="tiles">
  <div class="tile"><span class="n"><?= h((string) ($me['operatorId'] ?? '-')) ?></span>operator id</div>
  <div class="tile"><span class="n"><?= ghostd_rank_insignia((string) ($me['rankId'] ?? '')) ?><?= h($rankNames[(string) ($me['rankId'] ?? '')] ?? '-') ?></span>rank</div>
  <div class="tile"><span class="n"><?= h($statusNames[(string) ($me['statusId'] ?? '')] ?? '-') ?></span>status</div>
  <div class="tile"><span class="n"><?= h((string) ($me['enlistedAt'] ?? '-')) ?></span>enlisted</div>
</div>

<h2>What you can change</h2>
<p class="dim">All of it is optional. The unit already has your Steam id; the
rest is what you choose to share.</p>

<form method="post" class="card fields">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">

  <label for="f_milsimName">Preferred name <span class="dim">optional - what the unit calls you, e.g. "Cpl J. Miller"</span></label>
  <input type="text" id="f_milsimName" name="milsimName" maxlength="64"
         value="<?= h((string) ($me['milsimName'] ?? '')) ?>">

  <label for="f_discordId">Discord id <span class="dim">optional - the long number, not your handle. Lets the Discord bot match you to this record.</span></label>
  <input type="text" id="f_discordId" name="discordId" inputmode="numeric" maxlength="25"
         value="<?= h((string) ($me['discordId'] ?? '')) ?>">

  <label for="f_email">Email <span class="dim">optional - only for the unit to contact you. Never shown to other players and never posted by the bot.</span></label>
  <input type="text" id="f_email" name="email" maxlength="120"
         value="<?= h((string) ($me['email'] ?? '')) ?>">

  <div class="actions"><button type="submit">Save</button></div>
</form>

<h2>My PAC requests</h2>
<?php
  require_once __DIR__ . '/../tickets.php';
  $mine = ghostd_my_tickets($uid);
?>
<?php if ($mine === []): ?>
  <p class="dim">You have not raised any.
  <a href="?page=tickets">Raise one</a> - leave, an award recommendation, a
  request or a problem.</p>
<?php else: ?>
  <table class="grid">
    <thead><tr><th>Id</th><th>Kind</th><th>Subject</th><th>State</th><th>Replies</th><th>Raised</th></tr></thead>
    <tbody>
    <?php foreach ($mine as $t): ?>
      <?php $st = (string) ($t['status'] ?? 'open'); ?>
      <tr>
        <td><a href="?page=ticket&amp;id=<?= urlencode((string) ($t['id'] ?? '')) ?>"><code><?= h((string) ($t['id'] ?? '')) ?></code></a></td>
        <td><?= h(ghostd_ticket_kinds()[(string) ($t['kind'] ?? '')]['label'] ?? '') ?></td>
        <td><a href="?page=ticket&amp;id=<?= urlencode((string) ($t['id'] ?? '')) ?>"><?= h((string) ($t['subject'] ?? '')) ?></a></td>
        <td><span class="pill <?= $st === 'open' ? '' : ($st === 'declined' ? 'hot' : 'dimpill') ?>"><?= h(GHOSTD_TICKET_STATUSES[$st] ?? $st) ?></span></td>
        <td><?= count($t['replies'] ?? []) ?></td>
        <td class="dim"><?= h((string) ($t['createdAt'] ?? '')) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="dim">You see the public replies on your own requests. Admins may
  also keep private notes, which you will not see.</p>
<?php endif; ?>

<h2>What only an admin can change</h2>
<table class="kv">
  <tr><th>Name</th><td><?= cell($me['name'] ?? null) ?></td></tr>
  <tr><th>Rank</th><td><?= ghostd_rank_insignia((string) ($me['rankId'] ?? '')) ?><?= h($rankNames[(string) ($me['rankId'] ?? '')] ?? (string) ($me['rankId'] ?? '')) ?></td></tr>
  <tr><th>Role</th><td><?= cell($me['roleId'] ?? null) ?></td></tr>
  <tr><th>Group</th><td><?= cell($me['groupId'] ?? null) ?></td></tr>
  <tr><th>Skills</th><td><?= is_array($me['skillIds'] ?? null) ? count($me['skillIds']) : 0 ?></td></tr>
  <tr><th>Awards</th><td><?= is_array($me['awards'] ?? null) ? count($me['awards']) : 0 ?></td></tr>
</table>
<p class="note">Ask an admin in game if any of that is wrong. Changes you make
here are read by the game at the next mission start.</p>
<?php
ghostd_foot();
