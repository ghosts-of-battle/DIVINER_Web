<?php
/**
 * Platoons: the list. One card each, opened on its own page.
 *
 * A platoon is the last thing to fill in - it names squads, which name roles,
 * which sit on nets - so this page's job is to say whether what it names
 * actually exists.
 */

declare(strict_types=1);

require_once __DIR__ . '/../orbat.php';

$defined = [];
foreach ($groups as $g) { $defined[] = (string) ($g[0] ?? ''); }

$lrOf       = ghostd_lrplt_of($radio);
$lrChannels = ghostd_lr_channels($radio);

$netIds = [];
try {
    foreach (ghostd_template_items('nets') as $nid => $n) { $netIds[] = (string) $nid; }
} catch (Throwable $e) {
    $netIds = [];
}

$vq = $variant !== '' ? '&amp;v=' . urlencode($variant) : '';
?>
<h2>Platoons <span class="dim"><?= count($platoons) ?></span></h2>
<p class="actions"><a class="btnlink" href="?page=platoon&amp;id=%2B<?= $vq ?>">+ New platoon</a></p>

<?php if ($platoons === []): ?>
  <p class="dim">No platoons yet. Without one, no squad appears in the group menu.</p>
<?php endif; ?>

<?php foreach ($platoons as $p): ?>
  <?php
    $pid      = (string) ($p[0] ?? '');
    $pname    = (string) ($p[1] ?? '');
    $callsign = (string) ($p[2] ?? '');
    $net      = (string) ($p[3] ?? '');
    $squads   = array_values(array_map('strval', (array) ($p[4] ?? [])));
    $missing  = array_values(array_diff($squads, $defined));
    $lr       = $lrOf[$pid] ?? null;
    $pv       = ghostd_platoon_variant($pid);
  ?>
  <details class="card">
    <summary>
      <strong><?= h($pname !== '' ? $pname : $pid) ?></strong>
      <code><?= h($pid) ?></code>
      <span class="pill dimpill"><?= count($squads) ?> squads</span>
      <?php if ($net !== ''): ?><span class="pill"><?= h($net) ?></span><?php endif; ?>
      <?php if ($lr !== null): ?><span class="pill dimpill">LR <?= (int) $lr ?></span><?php endif; ?>
      <?php if ($missing !== []): ?><span class="pill hot"><?= count($missing) ?> missing</span><?php endif; ?>
      <a class="edit" href="?page=platoon&amp;id=<?= urlencode($pid) ?><?= $vq ?>">edit</a>
    </summary>

    <table class="kv">
      <tr><th>Callsign</th><td><?= $callsign !== '' ? h($callsign) : '<span class="dim">none</span>' ?></td></tr>
      <tr><th>Messaging net</th><td>
        <?php if ($net === ''): ?><span class="dim">none</span>
        <?php elseif ($netIds !== [] && !in_array($net, $netIds, true)): ?>
          <span class="bad"><?= h($net) ?> - no such net</span>
        <?php else: ?><?= h($net) ?><?php endif; ?>
      </td></tr>
      <tr><th>Long range</th><td><?= $lr === null
            ? '<span class="dim">the plan default</span>'
            : h($lrChannels[$lr] ?? ((string) $lr . ' - no such channel in the plan')) ?></td></tr>
      <tr><th>Squads</th><td><?= $squads === []
            ? '<span class="dim">none</span>' : h(implode(', ', $squads)) ?></td></tr>
      <?php if ($missing !== []): ?>
        <tr><th>No such squad</th><td class="bad"><?= h(implode(', ', $missing)) ?> - a hole in the platoon</td></tr>
      <?php endif; ?>
      <tr><th>Arsenal</th><td>
        <?php $ah = ghostd_variant_summary('arsenal', $pv); ?>
        <?= $ah === '' ? '<span class="dim">common only</span>' : h($ah) ?>
        &middot; <a href="?page=configedit&amp;t=arsenal&amp;v=<?= urlencode($pv) ?>"><?= $ah === '' ? 'create' : 'open' ?></a>
      </td></tr>
      <tr><th>Motorpool</th><td>
        <?php $mh = ghostd_variant_summary('motorpool', $pv); ?>
        <?= $mh === '' ? '<span class="dim">common only</span>' : h($mh) ?>
        &middot; <a href="?page=configedit&amp;t=motorpool&amp;v=<?= urlencode($pv) ?>"><?= $mh === '' ? 'create' : 'open' ?></a>
      </td></tr>
    </table>
  </details>
<?php endforeach; ?>

<!-- ------------------------------------------------ the ORBAT's own nets -->
<h2>Radio nets <span class="dim"><?= count($radioNets) ?></span></h2>
<p class="dim">The ORBAT's own grouping of squads onto nets, separate from the
messaging nets above. Id, what it is called, and the squads on it - one per line.</p>
<form method="post">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="s" value="platoons">
  <input type="hidden" name="what" value="nets">

  <?php $rows = $radioNets; $rows[] = ['', '', []]; ?>
  <?php foreach ($rows as $i => $n): ?>
    <?php $isNew = $i >= count($radioNets); ?>
    <fieldset class="line">
      <legend><?= $isNew ? '<span class="key">new</span>' : h((string) ($n[1] ?: $n[0])) ?></legend>
      <div class="fieldbox">
        <input type="text" name="n_id[<?= $i ?>]" value="<?= h((string) ($n[0] ?? '')) ?>" placeholder="id">
        <input type="text" name="n_name[<?= $i ?>]" value="<?= h((string) ($n[1] ?? '')) ?>" placeholder="what it is called">
        <?php if (!$isNew): ?>
          <label class="inlinelabel"><input type="checkbox" name="n_remove[]" value="<?= $i ?>"> remove</label>
        <?php endif; ?>
      </div>
      <textarea name="n_squads[<?= $i ?>]" rows="3" class="short"
                placeholder="one squad name per line"><?= h(implode("\n", (array) ($n[2] ?? []))) ?></textarea>
    </fieldset>
  <?php endforeach; ?>

  <div class="actions"><button type="submit">Save radio nets</button></div>
</form>
