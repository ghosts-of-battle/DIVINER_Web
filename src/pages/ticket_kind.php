<?php
/**
 * ONE kind of PAC request, opened from the System tab.
 *
 * The same card-and-page the operation order's sections and the report deck's
 * templates use (user, 2026-09-09: "why did you not do the pac request the
 * same, they are on the same fucking page").
 *
 * THE ID IS CARRIED BY EVERY TICKET ALREADY RAISED, so renaming one orphans
 * them - it is asked for once, on the way in, and left alone after.
 */

declare(strict_types=1);

require_once __DIR__ . '/../system.php';

$orig = trim((string) ($_GET['id'] ?? ($_POST['orig'] ?? '')));

$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $kinds = ghostd_ticket_kinds();

        if (($_POST['action'] ?? '') === 'delete') {
            if ($orig === '' || !isset($kinds[$orig])) {
                throw new RuntimeException('Nothing to delete.');
            }
            unset($kinds[$orig]);
            ghostd_ticket_kinds_save($kinds);
            header('Location: ?page=config&t=system');
            exit;
        }

        $id    = trim((string) ($_POST['kid'] ?? ''));
        $label = trim((string) ($_POST['label'] ?? ''));
        $hint  = trim((string) ($_POST['hint'] ?? ''));

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $id)) {
            throw new RuntimeException('"' . $id . '" is not a kind id - lower case letters, digits '
                . 'and underscore, starting with a letter. It is what every ticket carries.');
        }
        if ($orig !== $id && isset($kinds[$id])) {
            throw new RuntimeException('There is already a kind called "' . $id . '".');
        }

        // A rename replaces the entry in place, so the order on the page holds.
        $out = [];
        $done = false;
        foreach ($kinds as $kid => $k) {
            if ($orig !== '' && (string) $kid === $orig) {
                $out[$id] = ['label' => $label !== '' ? $label : $id, 'hint' => $hint];
                $done = true;
                continue;
            }
            $out[(string) $kid] = $k;
        }
        if (!$done) {
            $out[$id] = ['label' => $label !== '' ? $label : $id, 'hint' => $hint];
        }

        ghostd_ticket_kinds_save($out);
        header('Location: ?page=config&t=system');
        exit;
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$kinds  = ghostd_ticket_kinds();
$isEdit = $orig !== '' && isset($kinds[$orig]);

$id    = $isEdit ? $orig : '';
$label = $isEdit ? (string) ($kinds[$orig]['label'] ?? $orig) : '';
$hint  = $isEdit ? (string) ($kinds[$orig]['hint'] ?? '') : '';

ghostd_head($isEdit ? 'Edit ' . $orig : 'New request kind', 'config');
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<p><a href="?page=config&amp;t=system">&larr; System</a></p>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="orig" value="<?= h($orig) ?>">

  <div class="fields">
    <label>Id <span class="dim">lower case - what every ticket already raised carries</span>
      <input type="text" name="kid" value="<?= h($id) ?>" required
             pattern="[a-z][a-z0-9_]*" placeholder="transfer"></label>
    <label>Shown as <span class="dim">the name on the PAC actions page</span>
      <input type="text" name="label" value="<?= h($label) ?>" placeholder="Transfer request"></label>
    <label>What to put in it <span class="dim">one line, under the box</span>
      <input type="text" name="hint" value="<?= h($hint) ?>"
             placeholder="Which squad, and why."></label>
  </div>

  <div class="actions">
    <button type="submit"><?= $isEdit ? 'Save changes' : 'Create kind' ?></button>
    <a href="?page=config&amp;t=system">Cancel</a>
  </div>
</form>

<?php if ($isEdit): ?>
<form method="post" class="danger"
      onsubmit="return confirm('Delete this kind? Tickets already raised keep it.');">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="orig" value="<?= h($orig) ?>">
  <input type="hidden" name="action" value="delete">
  <p class="dim">Tickets already raised keep this kind; it simply stops being
  offered. The last kind cannot be removed.</p>
  <button type="submit" class="hot">Delete <?= h($orig) ?></button>
</form>
<?php endif; ?>
<?php
ghostd_foot();
