<?php
/**
 * One player, on a page of their own.
 *
 * EDITS ARE ONE FIELD AT A TIME, through ghostd_set_path on
 * players.<uid>.<field>. Writing the whole store back from a web form is how a
 * roster gets lost - the mod has done it twice - so this never sends a
 * document it did not just read, and never sends more of one than it changed.
 */

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

$cfg     = ghostd_config();
$storeId = $cfg['unit'];
$uid     = (string) ($_GET['uid'] ?? ($_POST['uid'] ?? ''));

$msg = null;
$err = null;

if (!preg_match('/^\d{5,20}$/', $uid)) {
    ghostd_head('Player', 'roster');
    ghostd_flash('bad', 'That is not a player uid.');
    echo '<p><a href="?page=roster">Back to the roster</a></p>';
    ghostd_foot();
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    $field = (string) ($_POST['field'] ?? '');
    $value = (string) ($_POST['value'] ?? '');

    if (!array_key_exists($field, GHOSTD_ROSTER_FIELDS)) {
        $err = 'That field is not editable from here.';
    } else {
        $store = ghostd_get($storeId);
        if ($store === null || !isset($store['players'][$uid])) {
            $err = 'No such player in the store.';
        } else {
            ghostd_set_path($storeId, 'players.' . $uid . '.' . $field, $value);
            ghostd_set_path($storeId, 'players.' . $uid . '.updatedAt', gmdate('Y-m-d H:i:s'));
            $msg = GHOSTD_ROSTER_FIELDS[$field] . ' updated.';
        }
    }
}

$store = ghostd_get($storeId);
$p     = $store['players'][$uid] ?? null;

if ($p === null) {
    ghostd_head('Player', 'roster');
    ghostd_flash('bad', 'No player with uid ' . h($uid) . ' is in the store.');
    echo '<p><a href="?page=roster">Back to the roster</a></p>';
    ghostd_foot();
    return;
}

$labels = static function (string $section): array {
    $doc = ghostd_get(ghostd_config()['unit'] . '.' . $section);
    $items = (is_array($doc['items'] ?? null)) ? $doc['items'] : [];
    $out = [];
    foreach ($items as $id => $it) {
        $out[(string) $id] = is_array($it) ? (string) ($it['name'] ?? $it['title'] ?? $id) : (string) $id;
    }
    return $out;
};
$rankNames   = $labels('ranks');
$statusNames = $labels('statuses');

$title = (string) ($p['name'] ?? $uid);

ghostd_head($title, 'roster');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<p class="dim"><a href="?page=roster">&larr; Roster</a></p>

<div class="tiles">
  <div class="tile"><span class="n"><?= h((string) ($p['operatorId'] ?? '-')) ?></span>operator id</div>
  <div class="tile"><span class="n"><?= h($rankNames[(string) ($p['rankId'] ?? '')] ?? '-') ?></span>rank</div>
  <div class="tile"><span class="n"><?= h($statusNames[(string) ($p['statusId'] ?? '')] ?? '-') ?></span>status</div>
  <div class="tile"><span class="n"><?= h((string) ($p['enlistedAt'] ?? '-')) ?></span>enlisted</div>
</div>

<h2>Details</h2>
<p class="dim">Steam id <code><?= h($uid) ?></code>. Each field saves on its
own - nothing else in the record is touched.</p>

<div class="card fields">
<?php foreach (GHOSTD_ROSTER_FIELDS as $f => $label): ?>
  <form method="post" class="inline">
    <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
    <input type="hidden" name="uid" value="<?= h($uid) ?>">
    <input type="hidden" name="field" value="<?= h($f) ?>">
    <label for="f_<?= h($f) ?>"><?= h($label) ?></label>
    <input type="text" id="f_<?= h($f) ?>" name="value" value="<?= h((string) ($p[$f] ?? '')) ?>"
           <?= $f === 'rankId' ? 'list="ranklist"' : ($f === 'statusId' ? 'list="statuslist"' : '') ?>>
    <button type="submit">Save</button>
  </form>
<?php endforeach; ?>
</div>

<datalist id="ranklist">
  <?php foreach ($rankNames as $rid => $rlabel): ?>
    <option value="<?= h((string) $rid) ?>"><?= h($rlabel) ?></option>
  <?php endforeach; ?>
</datalist>
<datalist id="statuslist">
  <?php foreach ($statusNames as $sid => $slabel): ?>
    <option value="<?= h((string) $sid) ?>"><?= h($slabel) ?></option>
  <?php endforeach; ?>
</datalist>

<h2>What the game owns</h2>
<p class="dim">Skills, awards, notes, training and loadouts are set on the
game's admin page, which validates them against the structure. They are shown
here so you can see them, not change them.</p>
<table class="kv">
  <tr><th>Skills</th><td><?= cell($p['skillIds'] ?? null) ?></td></tr>
  <tr><th>Qualifications</th><td><?= is_array($p['qualifications'] ?? null) ? count($p['qualifications']) : 0 ?></td></tr>
  <tr><th>Awards</th><td><?= is_array($p['awards'] ?? null) ? count($p['awards']) : 0 ?></td></tr>
  <tr><th>Training</th><td><?= is_array($p['training'] ?? null) ? count($p['training']) : 0 ?></td></tr>
  <tr><th>Notes</th><td><?= is_array($p['notes'] ?? null) ? count($p['notes']) : 0 ?></td></tr>
  <tr><th>Admin actions</th><td><?= is_array($p['adminActions'] ?? null) ? count($p['adminActions']) : 0 ?></td></tr>
  <tr><th>Promoted</th><td><?= h((string) ($p['promotedAt'] ?? '')) ?></td></tr>
  <tr><th>Last updated</th><td><?= h(when($p['updatedAt'] ?? null)) ?></td></tr>
  <tr><th>Server</th><td><?= cell($p['serverId'] ?? null) ?></td></tr>
</table>

<p class="note">The game overwrites this document when an admin presses SAVE and
at mission end. Edit between sessions, not during one.</p>
<?php
ghostd_foot();
