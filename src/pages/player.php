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

require_once __DIR__ . '/../players.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();

    $store = ghostd_get($storeId);
    if ($store === null || !isset($store['players'][$uid])) {
        $err = 'No such player in the store.';
    } elseif (($_POST['what'] ?? '') !== '') {
        // THE LIST FIELDS - the same writes the game's admin page makes, with
        // the same shapes and the same log rows. See src/players.php.
        try {
            $rec  = (array) $store['players'][$uid];
            $now  = gmdate('Y-m-d H:i');
            [$byUid, $byName] = ghostd_actor();

            switch ((string) $_POST['what']) {
                case 'skills': {
                    $known = ghostd_record_labels('skills');
                    $want  = [];
                    foreach ((array) ($_POST['skills'] ?? []) as $sid) {
                        $sid = trim((string) $sid);
                        if ($sid !== '' && isset($known[$sid])) {
                            $want[] = $sid;
                        }
                    }
                    sort($want);

                    // A skill granted for the FIRST time is a qualification,
                    // dated - the game's rule, kept here.
                    $had   = array_map('strval', (array) ($rec['skillIds'] ?? []));
                    $quals = (array) ($rec['qualifications'] ?? []);
                    foreach ($want as $sid) {
                        if (in_array($sid, $had, true)) {
                            continue;
                        }
                        $seen = false;
                        foreach ($quals as $q) {
                            if ((string) ((array) $q)[0] === $sid) { $seen = true; break; }
                        }
                        if (!$seen) {
                            $quals[] = [$sid, $known[$sid], substr($now, 0, 10)];
                        }
                    }
                    ghostd_set_path($storeId, 'players.' . $uid . '.qualifications', $quals);
                    ghostd_player_write($store, $uid, 'skillIds', $want, 'skills',
                        'skills ' . implode(', ', array_map(static fn($x) => $known[$x] ?? $x, $want)));
                    $msg = count($want) . ' skills set.';
                    break;
                }

                case 'awardadd': {
                    $known = ghostd_record_labels('awards');
                    $aid   = trim((string) ($_POST['award'] ?? ''));
                    if (!isset($known[$aid])) {
                        throw new RuntimeException('Pick an award.');
                    }
                    $awards   = (array) ($rec['awards'] ?? []);
                    $awards[] = [$aid, $now, $byName, trim((string) ($_POST['citation'] ?? ''))];
                    ghostd_player_write($store, $uid, 'awards', $awards, 'awardAdd',
                        'awarded ' . $known[$aid]);
                    $msg = $known[$aid] . ' awarded.';
                    break;
                }

                case 'awardremove': {
                    $awards = array_values((array) ($rec['awards'] ?? []));
                    $i      = (int) ($_POST['i'] ?? -1);
                    if (!isset($awards[$i])) {
                        throw new RuntimeException('That award is not on the record.');
                    }
                    $gone = (array) $awards[$i];
                    unset($awards[$i]);
                    ghostd_player_write($store, $uid, 'awards', array_values($awards), 'awardRemove',
                        'removed ' . (ghostd_record_labels('awards')[(string) $gone[0]] ?? (string) $gone[0]));
                    $msg = 'Award removed.';
                    break;
                }

                case 'trainingadd': {
                    $courses = ghostd_record_labels('trainings');
                    $course  = trim((string) ($_POST['course'] ?? ''));
                    if ($course !== '' && !isset($courses[$course])) {
                        throw new RuntimeException('There is no course called "' . $course . '".');
                    }
                    [$when, $text] = ghostd_dated_text((string) ($_POST['text'] ?? ''), $now);
                    if ($course === '' && $text === '') {
                        throw new RuntimeException('A course, a note, or both - not neither.');
                    }
                    $training   = (array) ($rec['training'] ?? []);
                    $training[] = [$when, $byName, $text, $course];
                    ghostd_player_write($store, $uid, 'training', $training, 'trainingAdd',
                        trim($when . ' ' . ($courses[$course] ?? '') . ($text !== '' ? ' - ' . $text : '')));
                    $msg = 'Training added.';
                    break;
                }

                case 'trainingremove': {
                    $training = array_values((array) ($rec['training'] ?? []));
                    $i        = (int) ($_POST['i'] ?? -1);
                    if (!isset($training[$i])) {
                        throw new RuntimeException('That training entry is not on the record.');
                    }
                    $gone = (array) $training[$i];
                    unset($training[$i]);
                    ghostd_player_write($store, $uid, 'training', array_values($training),
                        'trainingRemove', trim((string) ($gone[0] ?? '') . ' ' . (string) ($gone[2] ?? '')));
                    $msg = 'Training entry removed.';
                    break;
                }

                case 'noteadd': {
                    $text = trim((string) ($_POST['text'] ?? ''));
                    if ($text === '') {
                        throw new RuntimeException('An empty note says nothing.');
                    }
                    $notes   = (array) ($rec['notes'] ?? []);
                    $notes[] = [$now, $byName, $text];
                    ghostd_player_write($store, $uid, 'notes', $notes, 'noteAdd', $text);
                    $msg = 'Note added.';
                    break;
                }

                default:
                    throw new RuntimeException('Unknown action.');
            }
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
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

// One implementation of "id => name for a section" - ghostd_record_labels in
// src/players.php. This page had its own copy of it.
$rankNames   = ghostd_record_labels('ranks');
$statusNames = ghostd_record_labels('statuses');

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
<p class="dim">Steam id <?= steamlink($uid) ?>. One save for the section;
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
      <select id="f_<?= h($f) ?>" name="<?= h($f) ?>">
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

<?php
  $skillNames  = ghostd_record_labels('skills');
  $awardNames  = ghostd_record_labels('awards');
  $courseNames = ghostd_record_labels('trainings');
  $has         = array_map('strval', (array) ($p['skillIds'] ?? []));
?>

<h2>Skills</h2>
<p class="dim">The same list the PAC's admin page sets. Granting one for the
first time writes a dated qualification that stays on the record.</p>
<form method="post">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="uid" value="<?= h($uid) ?>">
  <input type="hidden" name="what" value="skills">
  <div class="fieldrow">
  <?php foreach ($skillNames as $sid => $sname): ?>
    <label class="inlinelabel">
      <input type="checkbox" name="skills[]" value="<?= h((string) $sid) ?>"
             <?= in_array((string) $sid, $has, true) ? 'checked' : '' ?>>
      <?= h($sname) ?>
    </label>
  <?php endforeach; ?>
  </div>
  <?php if ($skillNames === []): ?>
    <p class="dim">No skills in <code><?= h($unit ?? ghostd_config()['unit']) ?>.skills</code> yet.</p>
  <?php endif; ?>
  <div class="actions"><button type="submit">Save skills</button></div>
</form>

<h2>Qualifications <span class="dim"><?= count((array) ($p['qualifications'] ?? [])) ?></span></h2>
<p class="dim">When each skill was first held. Written by the grant above; not
edited by hand.</p>
<table class="grid">
  <thead><tr><th style="width:40%">Skill</th><th style="width:60%">Since</th></tr></thead>
  <tbody>
  <?php foreach ((array) ($p['qualifications'] ?? []) as $q): $q = (array) $q; ?>
    <tr><td><?= h((string) ($q[1] ?? $q[0] ?? '')) ?></td>
        <td class="dim"><?= h((string) ($q[2] ?? '')) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>Training <span class="dim"><?= count((array) ($p['training'] ?? [])) ?></span></h2>
<table class="grid">
  <thead><tr><th style="width:16%">When</th><th style="width:24%">Course</th>
      <th style="width:44%">Note</th><th style="width:16%">By</th></tr></thead>
  <tbody>
  <?php foreach (array_values((array) ($p['training'] ?? [])) as $ti => $t): $t = (array) $t; ?>
    <tr>
      <td class="dim"><?= h((string) ($t[0] ?? '')) ?></td>
      <td><?= h($courseNames[(string) ($t[3] ?? '')] ?? (string) ($t[3] ?? '')) ?></td>
      <td><?= h((string) ($t[2] ?? '')) ?></td>
      <td class="dim"><?= h((string) ($t[1] ?? '')) ?>
        <form method="post" class="inline">
          <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
          <input type="hidden" name="uid" value="<?= h($uid) ?>">
          <input type="hidden" name="what" value="trainingremove">
          <input type="hidden" name="i" value="<?= (int) $ti ?>">
          <button type="submit" class="hot">remove</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<form method="post" class="inline">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="uid" value="<?= h($uid) ?>">
  <input type="hidden" name="what" value="trainingadd">
  <label for="course">Course</label>
  <select id="course" name="course">
    <option value="">- none -</option>
    <?php foreach ($courseNames as $cid => $cname): ?>
      <option value="<?= h((string) $cid) ?>"><?= h($cname) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="text" name="text" placeholder="note - lead with 2026-08-01 to back-date it">
  <button type="submit">Add training</button>
</form>

<h2>Awards <span class="dim"><?= count((array) ($p['awards'] ?? [])) ?></span></h2>
<table class="grid">
  <thead><tr><th style="width:26%">Award</th><th style="width:18%">When</th>
      <th style="width:36%">Citation</th><th style="width:20%">By</th></tr></thead>
  <tbody>
  <?php foreach (array_values((array) ($p['awards'] ?? [])) as $ai => $a): $a = (array) $a; ?>
    <tr>
      <td><?= h($awardNames[(string) ($a[0] ?? '')] ?? (string) ($a[0] ?? '')) ?></td>
      <td class="dim"><?= h((string) ($a[1] ?? '')) ?></td>
      <td><?= h((string) ($a[3] ?? '')) ?></td>
      <td class="dim"><?= h((string) ($a[2] ?? '')) ?>
        <form method="post" class="inline">
          <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
          <input type="hidden" name="uid" value="<?= h($uid) ?>">
          <input type="hidden" name="what" value="awardremove">
          <input type="hidden" name="i" value="<?= (int) $ai ?>">
          <button type="submit" class="hot">remove</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<form method="post" class="inline">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="uid" value="<?= h($uid) ?>">
  <input type="hidden" name="what" value="awardadd">
  <label for="award">Award</label>
  <select id="award" name="award">
    <?php foreach ($awardNames as $aid => $aname): ?>
      <option value="<?= h((string) $aid) ?>"><?= h($aname) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="text" name="citation" placeholder="citation, may be empty">
  <button type="submit">Award it</button>
</form>

<h2>Notes <span class="dim"><?= count((array) ($p['notes'] ?? [])) ?></span></h2>
<table class="grid">
  <thead><tr><th style="width:18%">When</th><th style="width:18%">By</th>
      <th style="width:64%">Note</th></tr></thead>
  <tbody>
  <?php foreach ((array) ($p['notes'] ?? []) as $n): $n = (array) $n; ?>
    <tr><td class="dim"><?= h((string) ($n[0] ?? '')) ?></td>
        <td class="dim"><?= h((string) ($n[1] ?? '')) ?></td>
        <td><?= h((string) ($n[2] ?? '')) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>

<form method="post" class="inline">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="uid" value="<?= h($uid) ?>">
  <input type="hidden" name="what" value="noteadd">
  <label for="note">Note</label>
  <input type="text" id="note" name="text" placeholder="what happened">
  <button type="submit">Add note</button>
</form>

<h2>The rest</h2>
<table class="kv">
  <tr><th>Admin actions</th><td><?= is_array($p['adminActions'] ?? null) ? count($p['adminActions']) : 0 ?></td></tr>
  <tr><th>Loadouts</th><td><?= is_array($p['loadouts'] ?? null) ? count($p['loadouts']) : 0 ?></td></tr>
  <tr><th>Promoted</th><td><?= h((string) ($p['promotedAt'] ?? '')) ?></td></tr>
  <tr><th>Last updated</th><td><?= h(when($p['updatedAt'] ?? null)) ?></td></tr>
  <tr><th>Server</th><td><?= cell($p['serverId'] ?? null) ?></td></tr>
</table>

<p class="note">The game overwrites this document when an admin presses SAVE and
at mission end. Edit between sessions, not during one.</p>
<?php
ghostd_foot();
