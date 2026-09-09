<?php
/**
 * The application form.
 *
 * Reachable by anybody signed in through Steam. Somebody already on the roster
 * is sent to their own details instead - they are in, there is nothing to
 * apply for.
 */

declare(strict_types=1);

require_once __DIR__ . '/../applications.php';

$cfg  = ghostd_config();
$uid  = ghostd_self_uid();
$msg  = null;
$err  = null;

$questions = ghostd_questions();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $answers = [];
        $missing = [];
        foreach ($questions as $qid => $q) {
            $v = trim((string) ($_POST['q_' . $qid] ?? ''));
            if ($q['required'] && $v === '') {
                $missing[] = $q['label'];
            }
            if ($v !== '') {
                $answers[$qid] = mb_substr($v, 0, 4000);
            }
        }
        if ($missing !== []) {
            $err = 'Still needed: ' . implode('; ', $missing);
        } else {
            ghostd_application_submit($answers);
            $msg = 'Application sent. An admin will look at it - you can come back and change your answers until they do.';
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$mine = ghostd_application($uid);
$prev = is_array($mine['answers'] ?? null) ? $mine['answers'] : [];
$status = (string) ($mine['status'] ?? '');

ghostd_head('Apply to join', 'apply');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }

if ($status === 'accepted') {
    ghostd_flash('good', 'Your application was accepted. Welcome - your record is on the roster.');
    ghostd_foot();
    return;
}
if ($status === 'rejected') {
    echo '<p class="note">Your application was not taken forward this time. '
       . 'Speak to an admin if you would like to know more.</p>';
    ghostd_foot();
    return;
}
?>
<p class="note">Signed in as <code><?= h($uid) ?></code>. Your answers are tied
to that Steam id, which is the same id the game knows you by - so if you are
accepted, nothing has to be retyped.</p>

<?php if ($mine !== null): ?>
  <p class="note">You applied on <?= h((string) ($mine['submittedAt'] ?? '')) ?>
  and it has not been decided yet. Changing anything below replaces your
  answers.</p>
<?php endif; ?>

<form method="post" class="card fields">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <?php foreach ($questions as $qid => $q): ?>
    <?php $val = (string) ($prev[$qid] ?? ''); $id = 'q_' . $qid; ?>
    <label for="<?= h($id) ?>">
      <?= h($q['label']) ?>
      <?php if (!$q['required']): ?><span class="dim">optional</span><?php endif; ?>
    </label>
    <?php if ($q['help'] !== ''): ?>
      <p class="dim qhelp"><?= h($q['help']) ?></p>
    <?php endif; ?>

    <?php if ($q['type'] === 'textarea'): ?>
      <textarea id="<?= h($id) ?>" name="<?= h($id) ?>" rows="4" class="short"
                <?= $q['required'] ? 'required' : '' ?>><?= h($val) ?></textarea>
    <?php elseif ($q['type'] === 'select'): ?>
      <select id="<?= h($id) ?>" name="<?= h($id) ?>" <?= $q['required'] ? 'required' : '' ?>>
        <option value="">-</option>
        <?php foreach ((array) ($q['options'] ?? []) as $opt): ?>
          <option value="<?= h($opt) ?>" <?= $val === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
        <?php endforeach; ?>
      </select>
    <?php else: ?>
      <input type="text" id="<?= h($id) ?>" name="<?= h($id) ?>" maxlength="300"
             value="<?= h($val) ?>" <?= $q['required'] ? 'required' : '' ?>>
    <?php endif; ?>
  <?php endforeach; ?>

  <div class="actions">
    <button type="submit"><?= $mine !== null ? 'Update my application' : 'Send application' ?></button>
  </div>
</form>
<?php
ghostd_foot();
