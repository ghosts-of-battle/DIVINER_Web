<?php
/**
 * The supply catalogue: a card per crate, its contents inside it.
 *
 * Included by configedit.php when the template is 'logistics'. $t, $key and
 * $variant are set there.
 *
 * A CRATE IS EDITED ON ITS OWN PAGE - src/pages/crate.php - the way a pylon
 * preset and a report card are (user, 2026-09-09: "Logistics needs a simple
 * editor like other pages"). This page only says what exists.
 */

declare(strict_types=1);

require_once __DIR__ . '/../logistics.php';

$crates = ghostd_logistics_tree($variant);
$vq = $variant !== '' ? '&amp;v=' . urlencode($variant) : '';

ghostd_head($t['label'], 'config');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<?php require __DIR__ . '/_versions.php'; ?>

<p class="dim">What each crate holds. A crate is asked for by name, so renaming
one breaks whatever asks for it - add a new crate instead.</p>

<p class="actions"><a href="?page=crate<?= $vq ?>" class="btnlink">+ New crate</a></p>

<?php if ($crates === []): ?>
  <p class="dim">Nothing here yet.</p>
<?php endif; ?>

<?php foreach ($crates as $name => $rows): ?>
<details class="card">
  <summary>
    <strong><?= h((string) $name) ?></strong>
    <span class="pill dimpill"><?= count($rows) ?> lines</span>
    <span class="pill dimpill"><?= ghostd_logistics_count($rows) ?> items</span>
    <a class="edit" href="?page=crate&amp;name=<?= urlencode((string) $name) ?><?= $vq ?>">edit</a>
  </summary>

  <table class="grid">
    <thead><tr><th style="width:75%">Item</th><th style="width:25%">Count</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><code><?= h((string) $r[0]) ?></code></td>
          <td class="dim"><?= h((string) $r[1]) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</details>
<?php endforeach; ?>
<?php
ghostd_foot();
