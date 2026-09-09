<?php
/**
 * Squads: the list. One card each, opened on its own page.
 *
 * THE SAME ARRANGEMENT AS THE REPORT DECK AND THE ROLES. A card says what the
 * squad is, who has it and what it is on the radio; Edit opens it with room for
 * its slots, its channels and its arsenal.
 *
 * The two warnings at the top are the only things this page knows that no other
 * page can answer: a squad nobody claims never appears in the group menu, and a
 * squad a platoon claims but nobody defines is a slot list that does not exist.
 */

declare(strict_types=1);

require_once __DIR__ . '/../orbat.php';
require_once __DIR__ . '/../roles.php';

$acreOf = ghostd_acre_of($radio);
$tfarOf = ghostd_tfar_of($radio);

$claimedBy = [];
foreach ($platoons as $p) {
    foreach ((array) ($p[4] ?? []) as $sq) {
        $claimedBy[(string) $sq] = (string) ($p[1] ?? $p[0] ?? '');
    }
}
$defined = [];
foreach ($groups as $g) { $defined[(string) ($g[0] ?? '')] = true; }
$unclaimed = array_diff(array_keys($defined), array_keys($claimedBy));
$orphans   = array_diff(array_keys($claimedBy), array_keys($defined));

$roleIds = ghostd_role_ids();
$vq = $variant !== '' ? '&amp;v=' . urlencode($variant) : '';
?>
<?php if ($orphans !== []): ?>
  <p class="flash bad">A platoon lists these but no squad here gives them roles:
  <?= h(implode(', ', $orphans)) ?>. Nobody can slot into them.
  <a class="btnlink" href="?page=squad&amp;sq=%2B<?= $vq ?>">Create a squad</a></p>
<?php endif; ?>
<?php if ($unclaimed !== []): ?>
  <p class="note readonly">Not listed by any platoon:
  <?= h(implode(', ', $unclaimed)) ?>. They will not appear in the group menu.</p>
<?php endif; ?>

<h2>Squads <span class="dim"><?= count($groups) ?></span></h2>
<p class="actions"><a class="btnlink" href="?page=squad&amp;sq=%2B<?= $vq ?>">+ New squad</a></p>

<?php if ($groups === []): ?>
  <p class="dim">No squads yet.</p>
<?php endif; ?>

<?php foreach ($groups as $g): ?>
  <?php
    $sn    = (string) ($g[0] ?? '');
    $roles = array_values(array_map('strval', (array) ($g[1] ?? [])));
    $cond  = (string) ($g[2] ?? 'true');
    $tf    = $tfarOf[$sn] ?? [0, 0];
    $sv    = ghostd_squad_variant($sn);
    $bad   = array_values(array_diff($roles, $roleIds));
  ?>
  <details class="card">
    <summary>
      <strong><?= h($sn) ?></strong>
      <span class="pill dimpill"><?= count($roles) ?> slots</span>
      <?php if (isset($claimedBy[$sn])): ?>
        <span class="pill"><?= h($claimedBy[$sn]) ?></span>
      <?php else: ?>
        <span class="pill hot">no platoon</span>
      <?php endif; ?>
      <?php if (isset($acreOf[$sn])): ?>
        <span class="pill dimpill">ACRE <?= (int) $acreOf[$sn] ?></span>
      <?php endif; ?>
      <?php if ($bad !== []): ?><span class="pill hot"><?= count($bad) ?> bad slots</span><?php endif; ?>
      <a class="edit" href="?page=squad&amp;sq=<?= urlencode($sn) ?><?= $vq ?>">edit</a>
    </summary>

    <table class="kv">
      <tr><th>Slots</th><td><?= $roles === []
            ? '<span class="dim">none - nobody can slot in</span>'
            : h(implode(', ', $roles)) ?></td></tr>
      <?php if ($bad !== []): ?>
        <tr><th>No document</th><td class="bad"><?= h(implode(', ', $bad)) ?> - those slots will not fill</td></tr>
      <?php endif; ?>
      <tr><th>Offered when</th><td><code><?= h($cond) ?></code>
          <?= $cond === 'true' ? '<span class="dim">- always</span>'
              : '<span class="dim">- only while that is true; otherwise the squad is not drawn</span>' ?></td></tr>
      <tr><th>ACRE</th><td><?= isset($acreOf[$sn]) ? (int) $acreOf[$sn] : '<span class="dim">not set</span>' ?></td></tr>
      <tr><th>TFAR</th><td><?= $tf[0] || $tf[1]
            ? 'short ' . (int) $tf[0] . ', long ' . (int) $tf[1]
            : '<span class="dim">not set</span>' ?></td></tr>
      <tr><th>Arsenal</th><td>
        <?php $has = ghostd_variant_summary('arsenal', $sv); ?>
        <?= $has === '' ? '<span class="dim">common only</span>' : h($has) ?>
        &middot; <a href="?page=configedit&amp;t=arsenal&amp;v=<?= urlencode($sv) ?>"><?= $has === '' ? 'create' : 'open' ?></a>
      </td></tr>
    </table>
  </details>
<?php endforeach; ?>

<p class="note">Copying a squad - which takes its slots, its condition and its
channels - is on the squad's own page.</p>
