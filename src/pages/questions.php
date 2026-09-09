<?php
/**
 * The questions applicants are asked.
 *
 * SAVED AS A WHOLE TABLE, not row by row. A question set is one thing - the
 * order matters and the ids have to stay unique - so the page posts all of it
 * and writes it once. ghostd_put copies the previous version into the backup
 * collection first, so a bad save is recoverable.
 *
 * IDS ARE THE ANSWER KEYS. Renaming an id orphans the answers already given
 * under the old one; applications.php shows those as "question removed" rather
 * than dropping them silently.
 */

declare(strict_types=1);

require_once __DIR__ . '/../applications.php';

$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        if (($_POST['action'] ?? '') === 'defaults') {
            $items = [];
            $i = 0;
            foreach (GHOSTD_DEFAULT_QUESTIONS as $qid => $q) {
                $items[$qid] = $q + ['order' => ++$i * 10, 'options' => $q['options'] ?? []];
            }
            ghostd_questions_save($items);
            $msg = 'Reset to the standard question set.';
        } else {
            $ids      = (array) ($_POST['id'] ?? []);
            $labels   = (array) ($_POST['label'] ?? []);
            $types    = (array) ($_POST['type'] ?? []);
            $helps    = (array) ($_POST['help'] ?? []);
            $options  = (array) ($_POST['options'] ?? []);
            $required = (array) ($_POST['required'] ?? []);
            $remove   = (array) ($_POST['remove'] ?? []);

            $items = [];
            $order = 0;
            foreach ($ids as $row => $rawId) {
                $id = strtolower(trim((string) $rawId));
                $label = trim((string) ($labels[$row] ?? ''));
                if ($id === '' || $label === '' || in_array((string) $row, $remove, true)) {
                    continue;   // blank rows and removals just do not survive
                }
                if (!preg_match('/^[a-z0-9_]{1,40}$/', $id)) {
                    throw new RuntimeException('"' . $id . '" is not a usable id - letters, digits and underscore only.');
                }
                if (isset($items[$id])) {
                    throw new RuntimeException('Two questions share the id "' . $id . '".');
                }
                $type = (string) ($types[$row] ?? 'text');
                $items[$id] = [
                    'label'    => $label,
                    'type'     => isset(GHOSTD_QUESTION_TYPES[$type]) ? $type : 'text',
                    'required' => isset($required[$row]),
                    'help'     => trim((string) ($helps[$row] ?? '')),
                    'options'  => array_values(array_filter(array_map(
                        'trim',
                        preg_split('/\r?\n|\|/', (string) ($options[$row] ?? '')) ?: []
                    ), static fn($o) => $o !== '')),
                    'order'    => $order += 10,
                ];
            }
            if ($items === []) {
                throw new RuntimeException('That would leave no questions at all.');
            }
            ghostd_questions_save($items);
            $msg = count($items) . ' questions saved.';
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$questions = ghostd_questions();

ghostd_head('Application questions', 'applications');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<p class="note">What somebody is asked at <code>?page=apply</code>. Order here
is the order they see. An empty label removes a question when you save;
answers already given to it are kept and shown as "question removed" on the
application.</p>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <?php $row = 0; foreach ($questions as $qid => $q): ?>
    <fieldset class="line">
      <legend><?= h($q['label']) ?> <span class="key"><?= h((string) $qid) ?></span></legend>

      <div class="fieldbox">
        <input type="text" name="id[<?= $row ?>]" value="<?= h((string) $qid) ?>"
               title="The answer key. Changing it orphans answers already given.">
        <input type="text" name="label[<?= $row ?>]" value="<?= h($q['label']) ?>"
               placeholder="The question itself">
        <select name="type[<?= $row ?>]">
          <?php foreach (GHOSTD_QUESTION_TYPES as $t => $tl): ?>
            <option value="<?= h($t) ?>" <?= $q['type'] === $t ? 'selected' : '' ?>><?= h($tl) ?></option>
          <?php endforeach; ?>
        </select>
        <label class="inlinelabel">
          <input type="checkbox" name="required[<?= $row ?>]" <?= $q['required'] ? 'checked' : '' ?>> required
        </label>
        <label class="inlinelabel">
          <input type="checkbox" name="remove[]" value="<?= $row ?>"> remove
        </label>
      </div>
      <div class="fieldbox">
        <input type="text" name="help[<?= $row ?>]" value="<?= h($q['help']) ?>"
               placeholder="Help text under the question (optional)">
        <input type="text" name="options[<?= $row ?>]" value="<?= h(implode(' | ', $q['options'])) ?>"
               placeholder="Choices, separated by | (only for 'Choose one')">
      </div>
    </fieldset>
  <?php $row++; endforeach; ?>

  <fieldset class="line">
    <legend>Add a question</legend>
    <?php for ($i = 0; $i < 3; $i++): $r = $row + $i; ?>
      <div class="fieldbox">
        <input type="text" name="id[<?= $r ?>]" placeholder="id (e.g. squad_role)">
        <input type="text" name="label[<?= $r ?>]" placeholder="The question">
        <select name="type[<?= $r ?>]">
          <?php foreach (GHOSTD_QUESTION_TYPES as $t => $tl): ?>
            <option value="<?= h($t) ?>"><?= h($tl) ?></option>
          <?php endforeach; ?>
        </select>
        <label class="inlinelabel"><input type="checkbox" name="required[<?= $r ?>]"> required</label>
        <input type="text" name="help[<?= $r ?>]" placeholder="help (optional)">
        <input type="text" name="options[<?= $r ?>]" placeholder="a | b | c">
      </div>
    <?php endfor; ?>
  </fieldset>

  <div class="actions"><button type="submit">Save questions</button></div>
</form>

<form method="post" class="danger">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="action" value="defaults">
  <button type="submit" class="hot">Reset to the standard set</button>
  <p class="dim">Replaces every question with the nine this site ships with,
  including Arma experience and milsim experience.</p>
</form>
<?php
ghostd_foot();
