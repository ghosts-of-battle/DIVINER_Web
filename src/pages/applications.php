<?php
/**
 * Applications, for an admin to read and decide.
 *
 * ACCEPTING CREATES THE RECORD, using the Steam id the applicant proved they
 * own - so the roster entry is made from a verified id rather than one typed
 * off a Discord message. The record is seeded exactly as fnc_record would
 * leave it: an operator id, today's date, and nothing granted.
 */

declare(strict_types=1);

require_once __DIR__ . '/../applications.php';

$cfg     = ghostd_config();
$storeId = $cfg['unit'];
$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $uid    = (string) ($_POST['uid'] ?? '');
        $action = (string) ($_POST['action'] ?? '');
        if (!preg_match('/^\d{5,20}$/', $uid)) {
            throw new RuntimeException('That is not a Steam id.');
        }
        $app = ghostd_application($uid);
        if ($app === null) {
            throw new RuntimeException('No such application.');
        }

        if ($action === 'accept') {
            $store   = ghostd_get($storeId);
            $players = (is_array($store['players'] ?? null)) ? $store['players'] : [];

            if (isset($players[$uid])) {
                $msg = 'Already on the roster - marked accepted, no new record made.';
            } else {
                if ($store === null) {
                    throw new RuntimeException('There is no store document yet, so there is no roster to add to.');
                }
                // The same shape fnc_recordFields defines - see roster.php.
                $answers = is_array($app['answers'] ?? null) ? $app['answers'] : [];
                $rec = [
                    'name'           => (string) ($app['name'] ?? ''),
                    'operatorId'     => ghostd_next_operator_id($players),
                    'milsimName'     => (string) ($answers['callsign'] ?? ''),
                    'discordId'      => '',
                    'email'          => '',
                    'enlistedAt'     => gmdate('Y-m-d'),
                    'rankId'         => '',
                    'promotedAt'     => '',
                    'clearance'      => '',
                    'statusId'       => '',
                    'company'        => '',
                    'groupId'        => '',
                    'roleId'         => '',
                    'reportsTo'      => '',
                    'skillIds'       => [],
                    'qualifications' => [],
                    'awards'         => [],
                    'excused'        => [],
                    'adminActions'   => [],
                    'notes'          => [],
                    'training'       => [],
                    'loadouts'       => new stdClass(),
                    'updatedAt'      => gmdate('Y-m-d H:i:s'),
                    'serverId'       => (string) ($store['serverId'] ?? ''),
                ];
                ghostd_set_path($storeId, 'players.' . $uid, $rec);
                $msg = 'Accepted. ' . ($rec['name'] !== '' ? $rec['name'] : $uid)
                     . ' is on the roster as ' . $rec['operatorId'] . '.';
            }
            ghostd_set_path(ghostd_application_doc_id($uid), 'status', 'accepted');
            ghostd_set_path(ghostd_application_doc_id($uid), 'decidedAt', gmdate('Y-m-d H:i:s'));

        } elseif ($action === 'reject') {
            ghostd_set_path(ghostd_application_doc_id($uid), 'status', 'rejected');
            ghostd_set_path(ghostd_application_doc_id($uid), 'decidedAt', gmdate('Y-m-d H:i:s'));
            $msg = 'Marked as not taken forward. Nothing was written to the roster.';

        } elseif ($action === 'reopen') {
            ghostd_set_path(ghostd_application_doc_id($uid), 'status', 'new');
            $msg = 'Reopened - they can edit their answers again.';
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$apps = [];
$dbErr = null;
try {
    $apps = ghostd_applications();
} catch (Throwable $e) {
    $dbErr = $e->getMessage();
}
$questions = ghostd_questions();

ghostd_head('Applications', 'applications');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
if ($dbErr !== null) { ghostd_flash('bad', 'Applications could not be read: ' . $dbErr); ghostd_foot(); return; }

$new = array_filter($apps, static fn($a) => ($a['status'] ?? 'new') === 'new');
?>
<div class="tiles">
  <div class="tile"><span class="n"><?= count($new) ?></span>waiting</div>
  <div class="tile"><span class="n"><?= count($apps) ?></span>in total</div>
  <div class="tile"><span class="n"><?= count($questions) ?></span>questions asked</div>
</div>
<p class="dim"><a href="?page=questions">Edit the questions</a> applicants are asked.</p>

<?php if ($apps === []): ?>
  <p class="dim">Nobody has applied yet. The form is at
  <code>?page=apply</code>, and the login page offers it to anybody who signs
  in with Steam and is not already on the roster.</p>
<?php endif; ?>

<?php foreach ($apps as $a): ?>
  <?php $uid = (string) ($a['steamId'] ?? ''); $st = (string) ($a['status'] ?? 'new'); ?>
  <details class="card" <?= $st === 'new' ? 'open' : '' ?>>
    <summary>
      <strong><?= h((string) ($a['name'] ?? $uid)) ?></strong>
      <span class="dim"><?= h($uid) ?></span>
      <span class="pill <?= $st === 'new' ? '' : ($st === 'accepted' ? '' : 'hot') ?><?= $st === 'accepted' ? ' dimpill' : '' ?>">
        <?= h(GHOSTD_APPLICATION_STATUSES[$st] ?? $st) ?>
      </span>
      <span class="dim"><?= h((string) ($a['submittedAt'] ?? '')) ?></span>
    </summary>

    <table class="kv">
      <?php foreach ($questions as $qid => $q): ?>
        <tr>
          <th><?= h($q['label']) ?></th>
          <td><?php
            $v = (string) (($a['answers'][$qid] ?? '') ?: '');
            echo $v === '' ? '<span class="dim">-</span>' : nl2br(h($v));
          ?></td>
        </tr>
      <?php endforeach; ?>
      <?php
        // Anything answered against a question that has since been removed.
        $extra = array_diff_key((array) ($a['answers'] ?? []), $questions);
      ?>
      <?php foreach ($extra as $k => $v): ?>
        <tr><th><?= h((string) $k) ?> <span class="dim">(question removed)</span></th>
            <td><?= nl2br(h((string) $v)) ?></td></tr>
      <?php endforeach; ?>
    </table>

    <div class="actions">
      <a class="btnlink" href="https://steamcommunity.com/profiles/<?= h($uid) ?>"
         target="_blank" rel="noopener noreferrer">Steam profile</a>
      <?php if ($st === 'new'): ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
          <input type="hidden" name="uid" value="<?= h($uid) ?>">
          <input type="hidden" name="action" value="accept">
          <button type="submit">Accept and add to roster</button>
        </form>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
          <input type="hidden" name="uid" value="<?= h($uid) ?>">
          <input type="hidden" name="action" value="reject">
          <button type="submit" class="hot">Not this time</button>
        </form>
      <?php else: ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
          <input type="hidden" name="uid" value="<?= h($uid) ?>">
          <input type="hidden" name="action" value="reopen">
          <button type="submit">Reopen</button>
        </form>
      <?php endif; ?>
    </div>
  </details>
<?php endforeach; ?>
<?php
ghostd_foot();
