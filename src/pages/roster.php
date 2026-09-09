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

$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    ghostd_csrf_check();
    $uid  = trim((string) ($_POST['uid'] ?? ''));
    $name = trim((string) ($_POST['name'] ?? ''));

    $store   = ghostd_get($storeId);
    $current = (is_array($store['players'] ?? null)) ? $store['players'] : [];

    if (!preg_match('/^\d{5,20}$/', $uid)) {
        $err = 'A Steam id is digits only - 17 of them for a real account. It is '
             . 'the same id the game logs as the player uid.';
    } elseif (isset($current[$uid])) {
        $err = 'That Steam id is already on the roster as '
             . ($current[$uid]['name'] ?? $uid) . '.';
    } elseif ($store === null) {
        $err = 'There is no store document yet. The game writes it on SAVE, or at mission end.';
    } else {
        // THE WHOLE SHAPE, NOT JUST THE FIELDS THE FORM ASKED FOR. The mod's
        // FUNC(record) seeds every key from FUNC(recordFields) precisely so no
        // reader has to guard for a field that appeared later; a record this
        // site creates has to honour the same contract.
        $rec = [
            'name'           => $name,
            'operatorId'     => ghostd_next_operator_id($current),
            'milsimName'     => trim((string) ($_POST['milsimName'] ?? '')),
            'discordId'      => trim((string) ($_POST['discordId'] ?? '')),
            'enlistedAt'     => gmdate('Y-m-d'),
            'rankId'         => trim((string) ($_POST['rankId'] ?? '')),
            'promotedAt'     => '',
            'clearance'      => '',
            'statusId'       => trim((string) ($_POST['statusId'] ?? '')),
            'company'        => '',
            'groupId'        => trim((string) ($_POST['groupId'] ?? '')),
            'roleId'         => trim((string) ($_POST['roleId'] ?? '')),
            'reportsTo'      => '',
            'skillIds'       => [],
            'qualifications' => [],
            'awards'         => [],
            'excused'        => [],
            'adminActions'   => [],
            'notes'          => [],
            'training'       => [],
            // An empty PHP array encodes as a BSON array; loadouts is a map.
            'loadouts'       => new stdClass(),
            'updatedAt'      => gmdate('Y-m-d H:i:s'),
            'serverId'       => (string) ($store['serverId'] ?? ''),
        ];

        ghostd_set_path($storeId, 'players.' . $uid, $rec);
        $msg = 'Added ' . ($name !== '' ? $name : $uid) . ' as ' . $rec['operatorId']
             . '. Rank, role and skills are assigned in game, or below.';
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

?>
<p class="note">The game overwrites this document when an admin presses SAVE and
at mission end. Edit between sessions, not during one.</p>

<?php if (ghostd_can_edit()): ?>
<details class="card" id="addop"<?= $players === [] ? ' open' : '' ?>>
  <summary><strong>Add an operator</strong> <span class="dim">someone who has not played yet</span></summary>
  <p class="dim">A record is normally created the first time somebody joins the
  server. Add one here to have them on the roster before that - a new recruit,
  or a transfer. The Steam id is the key and cannot be changed afterwards: it is
  on their Steam profile, and it is what the game logs as the player uid.</p>
  <form method="post" class="fields">
    <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
    <input type="hidden" name="action" value="add">

    <label for="a_uid">Steam id <span class="dim">digits only, required</span></label>
    <input type="text" id="a_uid" name="uid" inputmode="numeric" pattern="[0-9]{5,20}"
           placeholder="76561198000000000" required>

    <label for="a_name">Name</label>
    <input type="text" id="a_name" name="name" placeholder="the name they play under">

    <label for="a_milsim">Milsim name <span class="dim">what the unit calls them</span></label>
    <input type="text" id="a_milsim" name="milsimName" placeholder="Cpl J. Miller">

    <label for="a_discord">Discord id <span class="dim">the numeric user id, not the handle</span></label>
    <input type="text" id="a_discord" name="discordId" inputmode="numeric"
           placeholder="337...">

    <label for="a_rank">Rank id <span class="dim">from the structure, or blank</span></label>
    <input type="text" id="a_rank" name="rankId" list="ranklist" placeholder="private">
    <datalist id="ranklist">
      <?php foreach ($rankNames as $rid => $rlabel): ?>
        <option value="<?= h((string) $rid) ?>"><?= h($rlabel) ?></option>
      <?php endforeach; ?>
    </datalist>

    <label for="a_status">Status id</label>
    <input type="text" id="a_status" name="statusId" list="statuslist">
    <datalist id="statuslist">
      <?php foreach ($statusNames as $sid => $slabel): ?>
        <option value="<?= h((string) $sid) ?>"><?= h($slabel) ?></option>
      <?php endforeach; ?>
    </datalist>

    <label for="a_group">Group id</label>
    <input type="text" id="a_group" name="groupId">

    <label for="a_role">Role id</label>
    <input type="text" id="a_role" name="roleId">

    <div class="actions"><button type="submit">Add to roster</button></div>
  </form>
  <p class="dim">An operator id is issued automatically. Skills, awards and
  training stay with the game's admin page, which validates them against the
  structure.</p>
</details>
<?php endif; ?>

<?php if ($players === []): ?>
<p class="dim">No players in the store yet - add the first one above.</p>
<?php else: ?>
<table class="grid">
  <thead>
    <tr>
      <th>Operator</th><th>Name</th><th>Rank</th><th>Role</th>
      <th>Group</th><th>Status</th><th>Discord</th><th>Skills</th><th>Updated</th><th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($players as $uid => $p): ?>
    <tr>
      <td><code><?= h((string) ($p['operatorId'] ?? '')) ?></code>
          <span class="dim"><?= steamlink((string) $uid, 'steam') ?></span></td>
      <td><a href="?page=player&amp;uid=<?= urlencode((string) $uid) ?>"><?= h((string) ($p['name'] ?? $uid)) ?></a>
          <?php if (!empty($p['milsimName'])): ?><span class="dim"><?= h((string) $p['milsimName']) ?></span><?php endif; ?>
      </td>
      <td><?= h($rankNames[(string) ($p['rankId'] ?? '')] ?? (string) ($p['rankId'] ?? '')) ?></td>
      <td><?= cell($p['roleId'] ?? null) ?></td>
      <td><?= cell($p['groupId'] ?? null) ?></td>
      <td><?= h($statusNames[(string) ($p['statusId'] ?? '')] ?? (string) ($p['statusId'] ?? '')) ?></td>
      <td><?php $d = (string) ($p['discordId'] ?? ''); ?>
          <?php if ($d !== ''): ?><code><?= h($d) ?></code>
          <?php else: ?><span class="pill dimpill" title="No Discord id: the bot cannot match this operator">unlinked</span><?php endif; ?>
      </td>
      <td><?= is_array($p['skillIds'] ?? null) ? count($p['skillIds']) : 0 ?></td>
      <td class="dim"><?= h(when($p['updatedAt'] ?? null)) ?></td>
      <td><a href="?page=player&amp;uid=<?= urlencode((string) $uid) ?>">open</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php endif; ?>
<?php
ghostd_foot();
