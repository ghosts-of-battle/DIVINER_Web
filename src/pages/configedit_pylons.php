<?php
/**
 * Pylon presets: a card per vehicle, its presets inside it.
 *
 * Included by configedit.php when the template's shape is 'pylons'. $t, $key
 * and $variant are set there.
 *
 * A PRESET IS EDITED ON ITS OWN PAGE - src/pages/pylon.php - the way a report
 * card and an operation order section are (user, 2026-09-09: "have it broken
 * down by vehicle then preset make it look like the other editors"). This page
 * only says what exists.
 */

declare(strict_types=1);

require_once __DIR__ . '/../pylons.php';

$tree = ghostd_pylon_tree($variant);
ksort($tree);

$vq = $variant !== '' ? '&amp;v=' . urlencode($variant) : '';

ghostd_head($t['label'], 'config');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<?php require __DIR__ . '/_versions.php'; ?>

<p class="dim">The <strong>vehicle</strong> is a base class, matched with
<code>isKindOf</code>, so one entry covers every variant that inherits from it.
A vehicle with no entry gets no preset menu.</p>

<p class="actions"><a href="?page=pylon<?= $vq ?>" class="btnlink">+ New vehicle</a></p>

<?php if ($tree === []): ?>
  <p class="dim">Nothing here yet.</p>
<?php endif; ?>

<?php foreach ($tree as $veh => $presets): ?>
<details class="card">
  <summary>
    <strong><?= h((string) $veh) ?></strong>
    <span class="pill dimpill"><?= count($presets) ?> preset<?= count($presets) === 1 ? '' : 's' ?></span>
    <span class="pill dimpill"><?= ghostd_pylon_count($presets) ?> magazines</span>
    <a class="edit" href="?page=pylon&amp;veh=<?= urlencode((string) $veh) ?><?= $vq ?>">+ preset</a>
  </summary>

  <table class="grid">
    <thead><tr><th style="width:18%">Preset</th><th style="width:26%">Shown as</th>
        <th style="width:48%">Magazines</th><th style="width:8%"></th></tr></thead>
    <tbody>
    <?php foreach ($presets as $pid => $p): ?>
      <tr>
        <td><code><?= h((string) $pid) ?></code></td>
        <td><?= h((string) ($p['name'] ?? $pid)) ?></td>
        <td class="dim"><?php
          $mags = [];
          foreach ((array) ($p['loadout'] ?? []) as $l) {
              $mags[] = (string) $l[0] . ' x' . (string) $l[2];
          }
          echo $mags === [] ? '<span class="dim">none</span>' : h(implode(', ', $mags));
        ?></td>
        <td><a class="btnlink" href="?page=pylon&amp;veh=<?= urlencode((string) $veh) ?>&amp;preset=<?= urlencode((string) $pid) ?><?= $vq ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</details>
<?php endforeach; ?>
<?php
ghostd_foot();
