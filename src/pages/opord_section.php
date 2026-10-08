<?php
/**
 * ONE section of the operation order, edited the way a report card is.
 *
 * The whole order used to be one 22-row grid of section / field / label / kind
 * (user, 2026-09-09: "no for the 14th time - should be like the messages
 * template editor"). A section is a card: it has a title and a hint, it holds
 * fields, and it is opened from the list on its own.
 *
 * THE DOCUMENT IS STILL FLAT. <unit>.system.opord is one row per field carrying
 * its section - see ghostd_opord_rows() - so this reads every row, replaces the
 * ones belonging to this section, and writes them all back in the order they
 * were in. A section keeps its place in the order; a new one goes on the end.
 */

declare(strict_types=1);

require_once __DIR__ . '/../system.php';

const GHOSTD_OPORD_KINDS = ['t' => 'one line', 'x' => 'paragraph', 'a' => 'a list'];

$orig = trim((string) ($_GET['id'] ?? ($_POST['orig'] ?? '')));

$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $all = ghostd_opord_rows();

        if (($_POST['action'] ?? '') === 'delete') {
            if ($orig === '') {
                throw new RuntimeException('Nothing to delete.');
            }
            $kept = array_values(array_filter($all, static fn($r) => $r['section'] !== $orig));
            ghostd_opord_rows_save($kept);
            header('Location: ?page=config&t=system');
            exit;
        }

        $sec   = trim((string) ($_POST['sec'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        $hint  = trim((string) ($_POST['hint'] ?? ''));

        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $sec)) {
            throw new RuntimeException('"' . $sec . '" is not a section id - letters, digits and '
                . 'underscore, starting with a letter. A report template points at '
                . '<code>section.field</code>, so it cannot hold a space.');
        }
        if ($orig !== $sec && $orig === '') {
            foreach ($all as $r) {
                if ($r['section'] === $sec) {
                    throw new RuntimeException('There is already a section called "' . $sec . '".');
                }
            }
        }

        // This section's fields, in the order the form gives them.
        $mine = [];
        $remove = (array) ($_POST['f_remove'] ?? []);
        foreach ((array) ($_POST['f_field'] ?? []) as $i => $fid) {
            $fid = trim((string) $fid);
            if ($fid === '' || in_array((string) $i, $remove, true)) {
                continue;
            }
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $fid)) {
                throw new RuntimeException('"' . $fid . '" is not a field id - letters, digits and '
                    . 'underscore, starting with a letter.');
            }
            $kind = (string) ($_POST['f_kind'][$i] ?? 'x');
            $mine[] = [
                'section'      => $sec,
                'sectionTitle' => $title !== '' ? $title : $sec,
                'sectionHint'  => $hint,
                'field'        => $fid,
                'label'        => trim((string) ($_POST['f_label'][$i] ?? '')) ?: $fid,
                'kind'         => isset(GHOSTD_OPORD_KINDS[$kind]) ? $kind : 'x',
                'help'         => trim((string) ($_POST['f_help'][$i] ?? '')),
            ];
        }
        if ($mine === []) {
            throw new RuntimeException('A section with no fields is nothing on the page. Add one, or '
                . 'delete the section.');
        }

        // Put them back where this section already was; a new one goes last.
        $out  = [];
        $done = false;
        foreach ($all as $r) {
            if ($orig !== '' && $r['section'] === $orig) {
                if (!$done) {
                    foreach ($mine as $m) { $out[] = $m; }
                    $done = true;
                }
                continue;
            }
            $out[] = $r;
        }
        if (!$done) {
            foreach ($mine as $m) { $out[] = $m; }
        }

        ghostd_opord_rows_save($out);
        header('Location: ?page=config&t=system');
        exit;
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$sections = ghostd_opord_sections();
$isEdit   = $orig !== '' && isset($sections[$orig]);

$sec    = $isEdit ? $orig : '';
$title  = $isEdit ? (string) ($sections[$orig]['title'] ?? $orig) : '';
$hint   = $isEdit ? (string) ($sections[$orig]['hint'] ?? '') : '';
$fields = $isEdit ? (array) $sections[$orig]['fields'] : [];

ghostd_head($isEdit ? 'Edit ' . $orig : 'New section', 'config');
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<p><a href="?page=config&amp;t=system">&larr; System</a></p>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="orig" value="<?= h($orig) ?>">

  <h2>The section</h2>
  <div class="fields">
    <label>Id <span class="dim">what a report template points at, as <code>id.field</code></span>
      <input type="text" name="sec" value="<?= h($sec) ?>" required
             pattern="[A-Za-z][A-Za-z0-9_]*" placeholder="situation"></label>
    <label>Title <span class="dim">the heading on the Orders page</span>
      <input type="text" name="title" value="<?= h($title) ?>" placeholder="Situation"></label>
    <label>Hint <span class="dim">one line under the heading, may be empty</span>
      <input type="text" name="hint" value="<?= h($hint) ?>"></label>
  </div>

  <h2>Fields</h2>
  <?php
    $rows = [];
    foreach ($fields as $fid => $fm) {
        $rows[] = ['field' => (string) $fid, 'label' => (string) ($fm['label'] ?? $fid),
                   'kind' => (string) ($fm['kind'] ?? 'x'), 'help' => (string) ($fm['help'] ?? '')];
    }
    $have = count($rows);
    for ($k = 0; $k < 3; $k++) {
        $rows[] = ['field' => '', 'label' => '', 'kind' => 'x', 'help' => ''];
    }
  ?>
  <?php foreach ($rows as $i => $r): ?>
    <fieldset class="line">
      <legend>
        <?= $i < $have ? 'Field ' . ($i + 1) : '<span class="key">new</span>' ?>
        <?php if ($i < $have): ?>
          <span class="dim key">key: <code><?= h(($sec !== '' ? $sec : 'section') . '.' . $r['field']) ?></code></span>
        <?php endif; ?>
      </legend>
      <div class="fields">
        <label>Field id
          <input type="text" name="f_field[<?= $i ?>]" value="<?= h($r['field']) ?>"
                 pattern="[A-Za-z][A-Za-z0-9_]*" placeholder="overview"></label>
        <label>Label <span class="dim">what the writer sees</span>
          <input type="text" name="f_label[<?= $i ?>]" value="<?= h($r['label']) ?>"
                 placeholder="Overview"></label>
        <label>Kind
          <select name="f_kind[<?= $i ?>]">
            <?php foreach (GHOSTD_OPORD_KINDS as $k => $kl): ?>
              <option value="<?= h($k) ?>" <?= $r['kind'] === $k ? 'selected' : '' ?>><?= h($kl) ?></option>
            <?php endforeach; ?>
          </select></label>
        <label>Help <span class="dim">under the box, may be empty</span>
          <input type="text" name="f_help[<?= $i ?>]" value="<?= h($r['help']) ?>"></label>
      </div>
      <?php if ($i < $have): ?>
        <label class="inlinelabel">
          <input type="checkbox" name="f_remove[]" value="<?= $i ?>"> remove this field
        </label>
      <?php endif; ?>
    </fieldset>
  <?php endforeach; ?>

  <div class="actions">
    <button type="submit"><?= $isEdit ? 'Save changes' : 'Create section' ?></button>
    <a href="?page=config&amp;t=system">Cancel</a>
  </div>
</form>

<?php if ($isEdit): ?>
<form method="post" class="danger"
      onsubmit="return confirm('Delete this section and its fields?');">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="orig" value="<?= h($orig) ?>">
  <input type="hidden" name="action" value="delete">
  <p class="dim">Removes the section and every field in it. Orders already
  written keep what is in them; the fields simply stop being offered.</p>
  <button type="submit" class="hot">Delete <?= h($orig) ?></button>
</form>
<?php endif; ?>
<?php
ghostd_foot();
