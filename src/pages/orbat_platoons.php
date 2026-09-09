<?php
/**
 * Platoons: the squads they hold, and the net they command on.
 *
 * A platoon is the last thing to fill in - it names squads, which name roles,
 * which sit on nets. Everything it refers to should exist by the time you get
 * here.
 */

declare(strict_types=1);

$known = [];
foreach ($groups as $g) { $known[] = (string) ($g[0] ?? ''); }
?>
<form method="post">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="s" value="platoons">
  <input type="hidden" name="what" value="platoons">

  <h2>Platoons <span class="dim"><?= count($platoons) ?></span></h2>
  <p class="dim">Id, the name shown, its callsign, the net it uses, and the
  squads it holds - one per line, matching the names on the Squads tab.</p>

  <?php $rows = $platoons; $rows[] = ['', '', '', '', []]; ?>
  <?php foreach ($rows as $i => $p): ?>
    <?php $isNew = $i >= count($platoons); ?>
    <fieldset class="line">
      <legend><?= $isNew ? '<span class="key">new</span>' : h((string) ($p[1] ?: $p[0])) ?></legend>
      <div class="fieldbox">
        <input type="text" name="p_id[<?= $i ?>]" value="<?= h((string) ($p[0] ?? '')) ?>" placeholder="id">
        <input type="text" name="p_name[<?= $i ?>]" value="<?= h((string) ($p[1] ?? '')) ?>" placeholder="shown as">
        <input type="text" name="p_callsign[<?= $i ?>]" value="<?= h((string) ($p[2] ?? '')) ?>" placeholder="callsign">
        <input type="text" name="p_net[<?= $i ?>]" value="<?= h((string) ($p[3] ?? '')) ?>" placeholder="net" list="netlist">
        <?php if (!$isNew): ?>
          <label class="inlinelabel"><input type="checkbox" name="p_remove[]" value="<?= $i ?>"> remove</label>
        <?php endif; ?>
      </div>
      <textarea name="p_squads[<?= $i ?>]" rows="3" class="short"
                placeholder="one squad name per line"><?= h(implode("\n", (array) ($p[4] ?? []))) ?></textarea>
    </fieldset>
  <?php endforeach; ?>

  <div class="actions"><button type="submit">Save platoons</button></div>
</form>

<h2>Platoon arsenal and motorpool</h2>
<p class="dim">Gear is merged in layers, narrowest last: the
<a href="?page=configedit&amp;t=arsenal">common arsenal</a> everybody draws
from, then the platoon's, then the squad's, then the role's own. A platoon
holds only what that platoon gets <em>in addition</em> - so these are usually
short.</p>
<table class="grid">
  <thead><tr><th>Platoon</th><th>Arsenal</th><th>Motorpool</th></tr></thead>
  <tbody>
  <?php foreach ($platoons as $p): ?>
    <?php
      $pid = (string) ($p[0] ?? '');
      if ($pid === '') { continue; }
      $pv = ghostd_platoon_variant($pid);
      $hasA = ghostd_variant_exists('arsenal', $pv);
      $hasM = ghostd_variant_exists('motorpool', $pv);
    ?>
    <tr>
      <td><strong><?= h((string) ($p[1] ?: $pid)) ?></strong>
          <span class="dim"><?= h($pid) ?></span></td>
      <td><a href="?page=configedit&amp;t=arsenal&amp;v=<?= urlencode($pv) ?>">
            <?= $hasA ? 'edit' : 'create' ?></a>
          <span class="dim"><code><?= h($pv) ?></code></span></td>
      <td><a href="?page=configedit&amp;t=motorpool&amp;v=<?= urlencode($pv) ?>">
            <?= $hasM ? 'edit' : 'create' ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>Faction</h2>
<form method="post" class="inline">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="s" value="platoons">
  <input type="hidden" name="what" value="faction">
  <label for="faction">Faction name</label>
  <input type="text" id="faction" name="faction" value="<?= h($faction) ?>">
  <button type="submit">Save</button>
</form>

<datalist id="netlist">
  <?php foreach (ghostd_template_items('nets') as $nid => $n): ?>
    <option value="<?= h((string) $nid) ?>"></option>
  <?php endforeach; ?>
</datalist>
<p class="dim">Squads known: <?= $known === [] ? 'none yet' : h(implode(', ', $known)) ?></p>
