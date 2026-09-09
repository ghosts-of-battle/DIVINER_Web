<?php
/**
 * The roster: every player in the store document, with the fields an admin
 * actually changes.
 *
 * EDITS ARE ONE FIELD AT A TIME, through ghostd_set_path on
 * players.<uid>.<field>. Writing the whole store back from a web form is how a
 * roster gets lost - the mod has done it twice - so this never sends a document
 * it did not just read, and never sends more of one than it changed.
 */

declare(strict_types=1);

$cfg   = ghostd_config();
$unit  = $cfg['unit'];
$storeId = $unit;

// Editable scalar fields. Anything not here (loadouts, notes, adminActions,
// sessions) is left to the game, which owns its shape.
const ROSTER_FIELDS = [
    'name'        => 'Name',
    'milsimName'  => 'Milsim name',
    'rankId'      => 'Rank',
    'roleId'      => 'Role',
    'groupId'     => 'Group',
    'statusId'    => 'Status',
    'company'     => 'Company',
    'clearance'   => 'Clearance',
    'discordId'   => 'Discord id',
    'reportsTo'   => 'Reports to',
];

$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    $uid   = (string) ($_POST['uid'] ?? '');
    $field = (string) ($_POST['field'] ?? '');
    $value = (string) ($_POST['value'] ?? '');

    if ($uid === '' || !preg_match('/^\d{5,20}$/', $uid)) {
        $err = 'That is not a player uid.';
    } elseif (!array_key_exists($field, ROSTER_FIELDS)) {
        $err = 'That field is not editable from here.';
    } else {
        $store = ghostd_get($storeId);
        if ($store === null || !isset($store['players'][$uid])) {
            $err = 'No such player in the store.';
        } else {
            ghostd_set_path($storeId, 'players.' . $uid . '.' . $field, $value);
            ghostd_set_path($storeId, 'players.' . $uid . '.updatedAt', gmdate('Y-m-d H:i:s'));
            $msg = ROSTER_FIELDS[$field] . ' updated for ' . ($store['players'][$uid]['name'] ?? $uid) . '.';
        }
    }
}

$store   = ghostd_get($storeId);
$players = (is_array($store['players'] ?? null)) ? $store['players'] : [];

// Structure lookups, so the table shows "Sergeant" rather than "sergeant".
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

uasort($players, static fn($a, $b) => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

ghostd_head('Roster', 'roster');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }

if ($players === []) {
    echo '<p class="dim">No players in the store yet.</p>';
    ghostd_foot();
    return;
}
?>
<p class="note">The game overwrites this document when an admin presses SAVE and
at mission end. Edit between sessions, not during one.</p>

<table class="grid">
  <thead>
    <tr>
      <th>Operator</th><th>Name</th><th>Rank</th><th>Role</th>
      <th>Group</th><th>Status</th><th>Skills</th><th>Updated</th><th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($players as $uid => $p): ?>
    <tr>
      <td><code><?= h((string) ($p['operatorId'] ?? '')) ?></code></td>
      <td><?= h((string) ($p['name'] ?? '')) ?>
          <?php if (!empty($p['milsimName'])): ?><span class="dim"><?= h((string) $p['milsimName']) ?></span><?php endif; ?>
      </td>
      <td><?= h($rankNames[(string) ($p['rankId'] ?? '')] ?? (string) ($p['rankId'] ?? '')) ?></td>
      <td><?= cell($p['roleId'] ?? null) ?></td>
      <td><?= cell($p['groupId'] ?? null) ?></td>
      <td><?= h($statusNames[(string) ($p['statusId'] ?? '')] ?? (string) ($p['statusId'] ?? '')) ?></td>
      <td><?= is_array($p['skillIds'] ?? null) ? count($p['skillIds']) : 0 ?></td>
      <td class="dim"><?= h(when($p['updatedAt'] ?? null)) ?></td>
      <td><a href="#p<?= h((string) $uid) ?>">edit</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>Edit</h2>
<?php foreach ($players as $uid => $p): ?>
<details id="p<?= h((string) $uid) ?>" class="card">
  <summary><?= h((string) ($p['name'] ?? $uid)) ?> <span class="dim"><?= h((string) $uid) ?></span></summary>
  <div class="fields">
  <?php foreach (ROSTER_FIELDS as $f => $label): ?>
    <form method="post" class="inline">
      <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
      <input type="hidden" name="uid" value="<?= h((string) $uid) ?>">
      <input type="hidden" name="field" value="<?= h($f) ?>">
      <label><?= h($label) ?></label>
      <input type="text" name="value" value="<?= h((string) ($p[$f] ?? '')) ?>">
      <button type="submit">Save</button>
    </form>
  <?php endforeach; ?>
  </div>
  <p class="dim">Skills, awards, notes, training and loadouts are left to the
  game's admin page, which validates them against the structure.</p>
</details>
<?php endforeach; ?>
<?php
ghostd_foot();
