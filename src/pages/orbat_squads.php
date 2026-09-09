<?php
/**
 * Squads: the roles they hold, and the channel they sit on.
 *
 * THE CHANNEL IS HERE BECAUSE NOBODY THINKS OF IT SEPARATELY. A squad with no
 * channel is half a squad, so the ACRE channel and the TFAR nets are edited
 * on the same row as the roles - even though they are stored in <unit>.radio
 * and the roles in <unit>.orbat.
 *
 * COPY, because the next squad is nearly always the last one with a different
 * name - four rifle squads differ by a digit.
 */

declare(strict_types=1);

// squad name => channel, from the radio plan
$acreOf = [];
foreach ((array) ($radio['srSquadChannel'] ?? []) as $r) {
    if (is_array($r) && isset($r[0])) { $acreOf[(string) $r[0]] = (int) ($r[1] ?? 0); }
}
$tfarOf = [];
foreach ((array) ($radio['tfarNets'] ?? []) as $r) {
    if (is_array($r) && isset($r[0])) {
        $tfarOf[(string) $r[0]] = [(int) ($r[1] ?? 0), (int) ($r[2] ?? 0)];
    }
}

// Which squads a platoon claims, so an unclaimed one can be pointed out.
$claimed = [];
foreach ($platoons as $p) {
    foreach ((array) ($p[4] ?? []) as $sq) { $claimed[(string) $sq] = true; }
}
$defined = [];
foreach ($groups as $g) { $defined[(string) ($g[0] ?? '')] = true; }
$unclaimed = array_diff(array_keys($defined), array_keys($claimed));
$orphans   = array_diff(array_keys($claimed), array_keys($defined));
?>
<?php if ($orphans !== []): ?>
  <p class="flash bad">A platoon lists these but no squad here gives them roles:
  <?= h(implode(', ', $orphans)) ?>. Nobody can slot into them.</p>
<?php endif; ?>
<?php if ($unclaimed !== []): ?>
  <p class="note readonly">Not listed by any platoon:
  <?= h(implode(', ', $unclaimed)) ?>. They will not appear in the group menu.</p>
<?php endif; ?>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="s" value="squads">
  <input type="hidden" name="what" value="squads">

  <h2>Squads <span class="dim"><?= count($groups) ?></span></h2>
  <p class="dim">Roles one per line, in slot order. The condition decides
  whether the squad appears - <code>true</code> for always. ACRE takes one
  channel number; TFAR takes a short-range and a long-range net.</p>

  <?php $rows = $groups; $rows[] = ['', [], 'true']; ?>
  <?php foreach ($rows as $i => $g): ?>
    <?php
      $name = (string) ($g[0] ?? '');
      $isNew = $i >= count($groups);
      $tf = $tfarOf[$name] ?? [0, 0];
    ?>
    <fieldset class="line">
      <legend><?= $isNew ? '<span class="key">new</span>' : h($name) ?></legend>
      <div class="fieldbox">
        <input type="text" name="g_name[<?= $i ?>]" value="<?= h($name) ?>" placeholder="squad name">
        <input type="text" name="g_cond[<?= $i ?>]" value="<?= h((string) ($g[2] ?? 'true')) ?>" placeholder="true">
        <label class="inlinelabel">ACRE ch
          <input type="number" name="g_acre[<?= $i ?>]" min="0" style="min-width:5rem"
                 value="<?= $isNew ? '' : h((string) ($acreOf[$name] ?? '')) ?>">
        </label>
        <label class="inlinelabel">TFAR sw
          <input type="number" name="g_tfar_sw[<?= $i ?>]" min="0" style="min-width:4.5rem"
                 value="<?= $isNew ? '' : h((string) $tf[0]) ?>">
        </label>
        <label class="inlinelabel">lr
          <input type="number" name="g_tfar_lr[<?= $i ?>]" min="0" style="min-width:4.5rem"
                 value="<?= $isNew ? '' : h((string) $tf[1]) ?>">
        </label>
        <?php if (!$isNew): ?>
          <label class="inlinelabel"><input type="checkbox" name="g_remove[]" value="<?= $i ?>"> remove</label>
        <?php endif; ?>
      </div>
      <textarea name="g_roles[<?= $i ?>]" rows="4" class="short"
                placeholder="one role id per line"><?= h(implode("\n", (array) ($g[1] ?? []))) ?></textarea>
    </fieldset>
  <?php endforeach; ?>

  <div class="actions"><button type="submit">Save squads and channels</button></div>
</form>

<h2>Squad arsenal and motorpool</h2>
<p class="dim">What this squad gets <em>on top of</em> the common and platoon
arsenals. A machine gun team's belts, a medic's extra kit - not the whole
loadout.</p>
<table class="grid">
  <thead><tr><th>Squad</th><th>Arsenal</th><th>Motorpool</th></tr></thead>
  <tbody>
  <?php foreach ($groups as $g): ?>
    <?php
      $sn = (string) ($g[0] ?? '');
      if ($sn === '') { continue; }
      $sv = ghostd_squad_variant($sn);
    ?>
    <tr>
      <td><strong><?= h($sn) ?></strong></td>
      <td><a href="?page=configedit&amp;t=arsenal&amp;v=<?= urlencode($sv) ?>">
            <?= ghostd_variant_exists('arsenal', $sv) ? 'edit' : 'create' ?></a>
          <span class="dim"><code><?= h($sv) ?></code></span></td>
      <td><a href="?page=configedit&amp;t=motorpool&amp;v=<?= urlencode($sv) ?>">
            <?= ghostd_variant_exists('motorpool', $sv) ? 'edit' : 'create' ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>Copy a squad</h2>
<p class="dim">Takes its roles, its condition and its channels. Four rifle
squads differ by a digit; this is how you make the other three.</p>
<form method="post" class="inline">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="s" value="squads">
  <input type="hidden" name="what" value="copysquad">
  <label for="from">Copy</label>
  <select id="from" name="from">
    <?php foreach ($groups as $g): ?>
      <option value="<?= h((string) ($g[0] ?? '')) ?>"><?= h((string) ($g[0] ?? '')) ?></option>
    <?php endforeach; ?>
  </select>
  <label for="to">to</label>
  <input type="text" id="to" name="to" placeholder="the new squad's name" required>
  <button type="submit">Copy</button>
</form>
