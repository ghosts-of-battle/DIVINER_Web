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
require_once __DIR__ . '/../orbat.php';
require_once __DIR__ . '/../roles.php';

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

    $store = ghostd_get($storeId);
    if ($store === null || !isset($store['players'][$uid])) {
        $err = 'No such player in the store.';
    } else {
        // ONE SAVE FOR THE SECTION, not one per line - a page of eight fields
        // with eight buttons is eight round trips to change somebody's rank and
        // group. Still ONE WRITE PER CHANGED FIELD underneath: this never sends
        // a document it did not just read, which is the rule that stops a web
        // form losing the roster.
        $was   = $store['players'][$uid];
        $saved = [];
        foreach (GHOSTD_ROSTER_FIELDS as $field => $label) {
            if (!array_key_exists($field, $_POST)) {
                continue;
            }
            $value = trim((string) $_POST[$field]);
            if ($value === (string) ($was[$field] ?? '')) {
                continue;                      // unchanged - nothing to write
            }
            ghostd_set_path($storeId, 'players.' . $uid . '.' . $field, $value);
            $saved[] = $label;
        }
        if ($saved !== []) {
            ghostd_set_path($storeId, 'players.' . $uid . '.updatedAt', gmdate('Y-m-d H:i:s'));
            $msg = implode(', ', $saved) . ' updated.';
        } else {
            $msg = 'Nothing changed.';
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

// WHAT A FIELD MAY BE. Typing a group or a role by hand is how a roster ends
// up naming a squad that does not exist - and nothing says so until somebody
// cannot slot in. These come off the DEFAULT ORBAT, the one missions run, not
// whichever version happens to be open in the editor.
$squadNames = [];
foreach (ghostd_default_orbat()['groups'] as $g) {
    $sn = (string) ($g[0] ?? '');
    if ($sn !== '') { $squadNames[$sn] = $sn; }
}

$roleNames = [];
foreach (ghostd_role_ids() as $rid) {
    $rr = ghostd_role($rid);
    $roleNames[$rid] = $rr['name'] !== '' ? $rr['name'] . ' (' . $rid . ')' : $rid;
}

// The roles this player's own squad asks for come first and on their own; a man
// in a squad should be given one of ITS jobs, not any of the twenty-five.
$mySquad = (string) ($p['groupId'] ?? '');
$squadRoles = ghostd_roles_for_squad($mySquad);

/** The choices for a field, or null when it is free text. */
$choices = static function (string $f) use ($rankNames, $statusNames, $squadNames, $roleNames, $squadRoles): ?array {
    switch ($f) {
        case 'rankId':   return $rankNames;
        case 'statusId': return $statusNames;
        case 'groupId':  return $squadNames;
        case 'roleId':
            if ($squadRoles === []) { return $roleNames; }
            $out = [];
            foreach ($squadRoles as $r) { $out[$r] = $roleNames[$r] ?? $r . ' (no document)'; }
            return $out;
        default: return null;
    }
};

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
<p class="dim">Steam id <code><?= h($uid) ?></code>. One save for the section;
only the fields you actually changed are written, and nothing else in the
record is touched.</p>

<form method="post" class="card fields">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="uid" value="<?= h($uid) ?>">
<?php foreach (GHOSTD_ROSTER_FIELDS as $f => $label): ?>
    <label for="f_<?= h($f) ?>"><?= h($label) ?></label>
    <?php $opts = $choices($f); $now = (string) ($p[$f] ?? ''); ?>
    <?php if ($opts === null): ?>
      <input type="text" id="f_<?= h($f) ?>" name="<?= h($f) ?>" value="<?= h($now) ?>">
    <?php else: ?>
      <select id="f_<?= h($f) ?>" name="<?= h($f) ?>" style="min-width:18rem">
        <option value="">- none -</option>
        <?php foreach ($opts as $oid => $olabel): ?>
          <option value="<?= h((string) $oid) ?>" <?= $now === (string) $oid ? 'selected' : '' ?>><?= h($olabel) ?></option>
        <?php endforeach; ?>
        <?php if ($now !== '' && !isset($opts[$now])): ?>
          <option value="<?= h($now) ?>" selected><?= h($now) ?> - not in the order of battle</option>
        <?php endif; ?>
      </select>
    <?php endif; ?>
<?php endforeach; ?>
  <div class="actions"><button type="submit">Save details</button></div>
</form>

<p class="dim">Group and role come from the
<strong>default order of battle</strong> - the one missions run
<?= ghostd_default_orbat_id() === '' ? '(the common one)' : '(' . h(ghostd_default_orbat_id()) . ')' ?>.
<?php if ($mySquad !== '' && $squadRoles !== []): ?>
The roles offered are the slots <?= h($mySquad) ?> actually has; change the group
and save to see that squad's instead.
<?php elseif ($mySquad !== ''): ?>
<?= h($mySquad) ?> has no slots in that ORBAT, so every role is offered.
<?php endif; ?></p>

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
