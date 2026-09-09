<?php
/**
 * The platoons: one card each, edited on its own page.
 *
 * THIS TAB EDITS PLATOONS. Which of them a given order of battle holds is
 * chosen on the Orders of battle tab - a platoon is written once and selected
 * into as many orders of battle as want it.
 */

declare(strict_types=1);

require_once __DIR__ . '/../orbat.php';

$pool = ghostd_platoon_pool($variant);

$inThis = [];
foreach ($platoons as $p) { $inThis[(string) ($p[0] ?? '')] = true; }

$defined = [];
foreach ($groups as $g) { $defined[] = (string) ($g[0] ?? ''); }

$lrOf       = ghostd_lrplt_of($radio);
$lrChannels = ghostd_lr_channels($radio);
$vq         = $variant !== '' ? '&amp;v=' . urlencode($variant) : '';
?>
<h2>Platoons <span class="dim"><?= count($pool) ?></span></h2>
<p class="actions"><a class="btnlink" href="?page=platoon&amp;id=%2B<?= $vq ?>">+ New platoon</a></p>

<?php if ($pool === []): ?>
  <p class="dim">No platoons yet. Without one, no squad appears in the group menu.</p>
<?php endif; ?>

<?php foreach ($pool as $pid => $p): ?>
  <?php
    $pname    = (string) ($p[1] ?? '');
    $callsign = (string) ($p[2] ?? '');
    $net      = (string) ($p[3] ?? '');
    $squads   = array_values(array_map('strval', (array) ($p[4] ?? [])));
    $missing  = array_values(array_diff($squads, $defined));
    $lr       = $lrOf[(string) $pid] ?? null;
    $pv       = ghostd_platoon_variant((string) $pid);
  ?>
  <details class="card">
    <summary>
      <strong><?= h($pname !== '' ? $pname : (string) $pid) ?></strong>
      <code><?= h((string) $pid) ?></code>
      <span class="pill dimpill"><?= count($squads) ?> squads</span>
      <?php if ($net !== ''): ?><span class="pill"><?= h($net) ?></span><?php endif; ?>
      <?php if (!isset($inThis[(string) $pid])): ?>
        <span class="pill dimpill">not in this ORBAT</span>
      <?php endif; ?>
      <?php if ($missing !== []): ?><span class="pill hot"><?= count($missing) ?> missing</span><?php endif; ?>
      <a class="edit" href="?page=platoon&amp;id=<?= urlencode((string) $pid) ?><?= $vq ?>">edit</a>
    </summary>

    <table class="kv">
      <tr><th>Callsign</th><td><?= $callsign !== '' ? h($callsign) : '<span class="dim">none</span>' ?></td></tr>
      <tr><th>Messaging net</th><td><?= $net !== '' ? h($net) : '<span class="dim">none</span>' ?></td></tr>
      <tr><th>Long range</th><td><?= $lr === null
            ? '<span class="dim">the plan default</span>'
            : h($lrChannels[$lr] ?? ((string) $lr . ' - no such channel in the plan')) ?></td></tr>
      <tr><th>Squads</th><td><?= $squads === []
            ? '<span class="dim">none</span>' : h(implode(', ', $squads)) ?></td></tr>
      <?php if ($missing !== []): ?>
        <tr><th>No such squad</th><td class="bad"><?= h(implode(', ', $missing)) ?></td></tr>
      <?php endif; ?>
      <tr><th>Arsenal</th><td>
        <?php $ah = ghostd_variant_summary('arsenal', $pv); ?>
        <?= $ah === '' ? '<span class="dim">none of its own</span>' : h($ah) ?>
        &middot; <a href="?page=platoon&amp;id=<?= urlencode((string) $pid) ?>&amp;sec=arsenal<?= $vq ?>">open</a>
      </td></tr>
    </table>
  </details>
<?php endforeach; ?>

<p class="dim">Which of these a given order of battle holds is chosen on the
<a href="?page=orbat&amp;s=versions<?= $vq ?>">Orders of battle</a> tab.
Nets that cross a platoon boundary are on the
<a href="?page=orbat&amp;s=radio&amp;r=nets<?= $vq ?>">Radio tab</a>.</p>
