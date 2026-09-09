<?php
/**
 * Squads: a list, and one squad open at a time.
 *
 * A LIST FIRST, NOT FIFTEEN OPEN FORMS. Fifteen squads each with a role
 * textarea is a page nobody can read. So: a table of what exists, and an Edit
 * button that opens exactly one.
 *
 * ROLES ARE DROPDOWNS, not typed. A role id typed by hand that does not match
 * a role document is a slot nobody can fill, and nothing says so until somebody
 * tries. Choosing from the roles that exist makes that impossible.
 *
 * THE NUMBER OF SLOTS IS A FIELD. A squad is "eight men" before it is a list of
 * eight roles - set the count, then fill the slots. Shrinking it drops the
 * slots off the end; growing it adds empty ones.
 */

declare(strict_types=1);

// The roles that exist, for the dropdowns.
$roleIds = [];
try {
    $rp = $unit . '.role.';
    foreach (ghostd_keys() as $k) {
        if (str_starts_with($k, $rp)) { $roleIds[] = substr($k, strlen($rp)); }
    }
    sort($roleIds);
} catch (Throwable $e) {
    $roleIds = [];
}

// squad => channel, from the radio plan
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

$claimed = [];
foreach ($platoons as $p) {
    foreach ((array) ($p[4] ?? []) as $sq) { $claimed[(string) $sq] = true; }
}
$defined = [];
foreach ($groups as $g) { $defined[(string) ($g[0] ?? '')] = true; }
$unclaimed = array_diff(array_keys($defined), array_keys($claimed));
$orphans   = array_diff(array_keys($claimed), array_keys($defined));

// Which squad is open. "+" opens a new one.
$edit = (string) ($_GET['sq'] ?? '');
$editIdx = null;
foreach ($groups as $i => $g) {
    if ((string) ($g[0] ?? '') === $edit) { $editIdx = $i; break; }
}
$isNew = ($edit === '+');
$cur = $editIdx !== null ? $groups[$editIdx] : ['', [], 'true'];

$vq = $variant !== '' ? '&amp;v=' . urlencode($variant) : '';
?>
<?php if ($orphans !== []): ?>
  <p class="flash bad">A platoon lists these but no squad here gives them roles:
  <?= h(implode(', ', $orphans)) ?>. Nobody can slot into them.</p>
<?php endif; ?>
<?php if ($unclaimed !== []): ?>
  <p class="note readonly">Not listed by any platoon:
  <?= h(implode(', ', $unclaimed)) ?>. They will not appear in the group menu.</p>
<?php endif; ?>

<h2>Squads <span class="dim"><?= count($groups) ?></span></h2>
<table class="grid">
  <thead>
    <tr><th>Squad</th><th>Slots</th><th>ACRE</th><th>TFAR sw/lr</th><th>Shown when</th><th>Arsenal</th><th></th></tr>
  </thead>
  <tbody>
  <?php foreach ($groups as $g): ?>
    <?php
      $sn = (string) ($g[0] ?? '');
      $tf = $tfarOf[$sn] ?? [0, 0];
      $sv = ghostd_squad_variant($sn);
    ?>
    <tr>
      <td><strong><?= h($sn) ?></strong></td>
      <td><?= count((array) ($g[1] ?? [])) ?></td>
      <td><?= isset($acreOf[$sn]) ? (int) $acreOf[$sn] : '<span class="dim">-</span>' ?></td>
      <td><?= $tf[0] ?: 0 ?> / <?= $tf[1] ?: 0 ?></td>
      <td class="dim"><code><?= h((string) ($g[2] ?? 'true')) ?></code></td>
      <td><a href="?page=configedit&amp;t=arsenal&amp;v=<?= urlencode($sv) ?>">
          <?= ghostd_variant_exists('arsenal', $sv) ? 'edit' : 'create' ?></a></td>
      <td><a class="btnlink" href="?page=orbat&amp;s=squads<?= $vq ?>&amp;sq=<?= urlencode($sn) ?>">Edit</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p><a class="btnlink" href="?page=orbat&amp;s=squads<?= $vq ?>&amp;sq=%2B">Add a squad</a></p>

<?php if ($editIdx !== null || $isNew): ?>
  <?php
    $roles = (array) ($cur[1] ?? []);
    $want  = (int) ($_GET['n'] ?? max(1, count($roles)));
    $want  = max(1, min(40, $want));
    $name  = $isNew ? '' : (string) ($cur[0] ?? '');
    $tf    = $tfarOf[$name] ?? [0, 0];
  ?>
  <h2><?= $isNew ? 'New squad' : 'Editing ' . h($name) ?></h2>

  <form method="get" class="inline">
    <input type="hidden" name="page" value="orbat">
    <input type="hidden" name="s" value="squads">
    <?php if ($variant !== ''): ?><input type="hidden" name="v" value="<?= h($variant) ?>"><?php endif; ?>
    <input type="hidden" name="sq" value="<?= h($isNew ? '+' : $name) ?>">
    <label for="n">Number of slots</label>
    <input type="number" id="n" name="n" min="1" max="40" value="<?= $want ?>" style="min-width:5rem">
    <button type="submit">Set</button>
    <span class="dim">Fewer drops the slots off the end; more adds empty ones. Nothing is saved until you save the squad.</span>
  </form>

  <form method="post">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="v" value="<?= h($variant) ?>">
    <input type="hidden" name="s" value="squads">
    <input type="hidden" name="what" value="squad">
    <input type="hidden" name="was" value="<?= h($isNew ? '' : $name) ?>">

    <div class="fieldbox">
      <label class="inlinelabel">Name
        <input type="text" name="name" value="<?= h($name) ?>" required style="min-width:12rem"></label>
      <label class="inlinelabel">Shown when
        <input type="text" name="cond" value="<?= h((string) ($cur[2] ?? 'true')) ?>" style="min-width:8rem"></label>
      <label class="inlinelabel">ACRE ch
        <input type="number" name="acre" min="0" value="<?= h((string) ($acreOf[$name] ?? '')) ?>" style="min-width:5rem"></label>
      <label class="inlinelabel">TFAR sw
        <input type="number" name="tfar_sw" min="0" value="<?= h((string) $tf[0]) ?>" style="min-width:4.5rem"></label>
      <label class="inlinelabel">lr
        <input type="number" name="tfar_lr" min="0" value="<?= h((string) $tf[1]) ?>" style="min-width:4.5rem"></label>
    </div>

    <h3>Slots <span class="dim">in order - slot 1 is the squad leader's</span></h3>
    <?php if ($roleIds === []): ?>
      <p class="note readonly">No role documents exist, so there is nothing to
      choose. Create roles first - the <a href="?page=orbat&amp;s=roles<?= $vq ?>">Roles</a>
      tab lists what there is.</p>
    <?php endif; ?>
    <table class="grid">
      <thead><tr><th>Slot</th><th>Role</th></tr></thead>
      <tbody>
      <?php for ($i = 0; $i < $want; $i++): ?>
        <?php $sel = (string) ($roles[$i] ?? ''); ?>
        <tr>
          <td><strong><?= $i + 1 ?></strong></td>
          <td>
            <select name="roles[<?= $i ?>]" style="min-width:16rem">
              <option value="">- empty -</option>
              <?php foreach ($roleIds as $rid): ?>
                <option value="<?= h($rid) ?>" <?= $sel === $rid ? 'selected' : '' ?>><?= h($rid) ?></option>
              <?php endforeach; ?>
              <?php if ($sel !== '' && !in_array($sel, $roleIds, true)): ?>
                <option value="<?= h($sel) ?>" selected><?= h($sel) ?> (no document)</option>
              <?php endif; ?>
            </select>
          </td>
        </tr>
      <?php endfor; ?>
      </tbody>
    </table>

    <div class="actions">
      <button type="submit">Save squad</button>
      <a class="btnlink" href="?page=orbat&amp;s=squads<?= $vq ?>">Done</a>
    </div>
  </form>

  <?php if (!$isNew): ?>
    <form method="post" class="danger">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="v" value="<?= h($variant) ?>">
      <input type="hidden" name="s" value="squads">
      <input type="hidden" name="what" value="deletesquad">
      <input type="hidden" name="name" value="<?= h($name) ?>">
      <button type="submit" class="hot">Remove <?= h($name) ?></button>
    </form>
  <?php endif; ?>
<?php endif; ?>

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
