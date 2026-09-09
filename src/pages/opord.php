<?php
/**
 * The OPORD writing tool: one section at a time, in the order they are briefed.
 *
 * SECTIONS SAVE INDEPENDENTLY. An order is written over an evening, not in one
 * sitting, and a single Save for the whole thing means losing the lot to a
 * timeout. Each section posts only itself.
 */

declare(strict_types=1);

require_once __DIR__ . '/../opords.php';

$id  = strtolower(trim((string) ($_GET['id'] ?? ($_POST['id'] ?? ''))));
$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $current = ghostd_opord($id);
        $sec = (string) ($_POST['section'] ?? '');

        if (!isset(GHOSTD_OPORD_SECTIONS[$sec])) {
            throw new RuntimeException('Unknown section.');
        }
        foreach (GHOSTD_OPORD_SECTIONS[$sec]['fields'] as $f => $fm) {
            $raw = (string) ($_POST['f_' . $f] ?? '');
            $current[$sec][$f] = $fm['kind'] === 'a' ? ghostd_lines($raw) : trim($raw);
        }
        ghostd_opord_save($id, $current);
        $msg = GHOSTD_OPORD_SECTIONS[$sec]['title'] . ' saved.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

if (!ghostd_opord_valid_id($id)) {
    ghostd_head('Operation order', 'opords');
    ghostd_flash('bad', 'That is not an operation order id.');
    echo '<p><a href="?page=opords">Back to the orders</a></p>';
    ghostd_foot();
    return;
}

$o = ghostd_opord($id);
$title = trim((string) ($o['header']['title'] ?? '')) ?: $id;

ghostd_head($title, 'opords');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<p class="dim"><a href="?page=opords">&larr; Operation orders</a> &middot;
document <code><?= h(ghostd_opord_doc_id($id)) ?></code></p>

<p class="note">Each section saves on its own. The game reads this at the next
mission start, and a report template's <code>autoFill</code> pulls single
fields out of it by <code>section.field</code> - so
<code>situation.enemy</code> written here is what fills that box on a compose
card in game.</p>

<?php foreach (GHOSTD_OPORD_SECTIONS as $sec => $meta): ?>
  <?php
    $filled = 0;
    foreach ($meta['fields'] as $f => $fm) {
        $v = $o[$sec][$f] ?? '';
        if ((is_array($v) && $v !== []) || (!is_array($v) && trim((string) $v) !== '')) { $filled++; }
    }
    $total = count($meta['fields']);
  ?>
  <details class="card" <?= $filled === 0 ? '' : 'open' ?>>
    <summary>
      <strong><?= h($meta['title']) ?></strong>
      <span class="dim"><?= h($meta['hint']) ?></span>
      <span class="pill <?= $filled === 0 ? 'dimpill' : '' ?>"><?= $filled ?>/<?= $total ?></span>
    </summary>

    <form method="post" class="fields">
      <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= h($id) ?>">
      <input type="hidden" name="section" value="<?= h($sec) ?>">

      <?php foreach ($meta['fields'] as $f => $fm): ?>
        <?php $v = $o[$sec][$f] ?? ''; ?>
        <label for="f_<?= h($f) ?>">
          <?= h($fm['label']) ?>
          <span class="dim"><code><?= h($sec) ?>.<?= h($f) ?></code></span>
        </label>
        <?php if ($fm['help'] !== ''): ?>
          <p class="dim qhelp"><?= h($fm['help']) ?></p>
        <?php endif; ?>

        <?php if ($fm['kind'] === 't'): ?>
          <input type="text" id="f_<?= h($f) ?>" name="f_<?= h($f) ?>" maxlength="300"
                 value="<?= h((string) $v) ?>">
        <?php elseif ($fm['kind'] === 'a'): ?>
          <textarea id="f_<?= h($f) ?>" name="f_<?= h($f) ?>" rows="3" class="short"
                    placeholder="one per line"><?= h(implode("\n", (array) $v)) ?></textarea>
        <?php else: ?>
          <textarea id="f_<?= h($f) ?>" name="f_<?= h($f) ?>" rows="6" class="short"><?= h((string) $v) ?></textarea>
        <?php endif; ?>
      <?php endforeach; ?>

      <div class="actions"><button type="submit">Save <?= h(strtolower($meta['title'])) ?></button></div>
    </form>
  </details>
<?php endforeach; ?>
<?php
ghostd_foot();
