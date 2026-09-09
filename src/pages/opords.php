<?php
/**
 * Operation orders: the list, and starting a new one.
 */

declare(strict_types=1);

require_once __DIR__ . '/../system.php';

require_once __DIR__ . '/../opords.php';

$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $id = strtolower(trim((string) ($_POST['id'] ?? '')));
        $title = trim((string) ($_POST['title'] ?? ''));

        if (!ghostd_opord_valid_id($id)) {
            throw new RuntimeException('An id is lower-case letters, digits and underscore, 2 to 40 characters - it becomes a config class name in the game. "op_ironveil", not "Op Iron Veil".');
        }
        if (in_array($id, ghostd_opord_ids(), true)) {
            throw new RuntimeException('There is already an order with that id.');
        }

        $blank = ghostd_opord($id);          // every section, empty
        $blank['header']['title'] = $title !== '' ? $title : strtoupper(str_replace('_', ' ', $id));
        $blank['header']['date'] = gmdate('d M Y');
        ghostd_opord_save($id, $blank);

        header('Location: ?page=opord&id=' . urlencode($id));
        exit;
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$ids = [];
$dbErr = null;
try {
    $ids = ghostd_opord_ids();
} catch (Throwable $e) {
    $dbErr = $e->getMessage();
}

$current = '';
try {
    $settings = ghostd_get(ghostd_config()['unit'] . '.settings');
    $items = (is_array($settings['items'] ?? null)) ? $settings['items'] : $settings;
    $current = (string) ($items['currentOpord'] ?? '');
} catch (Throwable $e) {
    // Not knowing which is current is cosmetic.
}

ghostd_head('Operation orders', 'opords');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
if ($dbErr !== null) { ghostd_flash('bad', 'Orders could not be read: ' . $dbErr); ghostd_foot(); return; }
?>
<p class="note">One document per order - <code><?= h(ghostd_config()['unit']) ?>.opord.&lt;id&gt;</code>.
The game reads them at mission start, and the <strong>currentOpord</strong>
setting decides which one the compose cards fill from.</p>

<details class="card" <?= $ids === [] ? 'open' : '' ?>>
  <summary><strong>Start a new order</strong></summary>
  <form method="post" class="fields">
    <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
    <label for="id">Id <span class="dim">lower-case, no spaces - it becomes a config class</span></label>
    <input type="text" id="id" name="id" pattern="[a-z0-9_]{2,40}" required placeholder="op_ironveil">
    <label for="title">Title</label>
    <input type="text" id="title" name="title" maxlength="200" placeholder="OPERATION IRON VEIL">
    <div class="actions"><button type="submit">Create and start writing</button></div>
  </form>
</details>

<?php if ($ids === []): ?>
  <p class="dim">No orders yet.</p>
<?php else: ?>
<table class="grid">
  <thead><tr><th>Id</th><th>Title</th><th>Date</th><th>Written</th><th>Current</th></tr></thead>
  <tbody>
  <?php foreach ($ids as $oid): ?>
    <?php
      $o = ghostd_opord($oid);
      $done = 0; $total = 0;
      foreach (ghostd_opord_sections() as $sec => $meta) {
          foreach ($meta['fields'] as $f => $fm) {
              $total++;
              $v = $o[$sec][$f] ?? '';
              if ((is_array($v) && $v !== []) || (!is_array($v) && trim((string) $v) !== '')) { $done++; }
          }
      }
    ?>
    <tr>
      <td><a href="?page=opord&amp;id=<?= urlencode($oid) ?>"><code><?= h($oid) ?></code></a></td>
      <td><a href="?page=opord&amp;id=<?= urlencode($oid) ?>"><?= h((string) ($o['header']['title'] ?? '')) ?></a></td>
      <td><?= h((string) ($o['header']['date'] ?? '')) ?></td>
      <td class="dim"><?= $done ?>/<?= $total ?> fields</td>
      <td><?= $current === $oid ? '<span class="pill">current</span>' : '<span class="dim">-</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php
ghostd_foot();
