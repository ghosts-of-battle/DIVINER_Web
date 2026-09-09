<?php
/**
 * Orders of battle: how many there are, and which one is live.
 *
 * A UNIT BUILDS MORE THAN ONE. A full task force, a cut-down one for a small
 * night, a special arrangement for a campaign - and exactly one of them is what
 * missions run. That one is named by the "currentOrbat" setting, which
 * ghostD_pac_fnc_svcStructure reads at boot, so what is ticked here is what the
 * game loads.
 *
 * THE ROSTER FOLLOWS THE TICK. A player's group and role dropdowns come off the
 * default ORBAT, not off whichever version somebody has open - a roster full of
 * squads from a draft is a roster nobody can slot from.
 *
 * BUILT BY COPYING, nearly always. A second ORBAT is the first one with two
 * squads changed; typing fifteen squads again is how the two drift apart.
 */

declare(strict_types=1);

$default = ghostd_default_orbat_id();

// Every platoon the unit has, and which of them the ORBAT being edited holds.
$pool = ghostd_platoon_pool($variant);
$inThis = [];
foreach ($platoons as $p) { $inThis[(string) ($p[0] ?? '')] = true; }

// Every version, the common one first. The common one is not a document that
// has to exist - it is the absence of a name - so it is always offered.
$rows = [];
foreach (array_merge([''], ghostd_orbat_variants()) as $v) {
    $o = ghostd_orbat((string) $v);
    $slots = 0;
    foreach ($o['groups'] as $g) { $slots += count((array) ($g[1] ?? [])); }
    $rows[] = [
        'id'       => (string) $v,
        'exists'   => $o['exists'],
        'faction'  => $o['faction'],
        'side'     => $o['side'],
        'platoons' => count($o['platoons']),
        'squads'   => count($o['groups']),
        'slots'    => $slots,
        'nets'     => $o['nets'],
        'radio'    => $o['radio'],
    ];
}
?>
<h2>Orders of battle <span class="dim"><?= count($rows) ?></span></h2>
<p class="note"><strong>The mission config picks the order of battle</strong>
it runs, with <code>currentOrbat</code> in its <code>CfgGFA_PAC</code>. One of
these is the <strong>default</strong> - what a mission gets when it names none -
and that is the only thing set here. The roster's group and role lists follow
the default.</p>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="s" value="versions">
  <input type="hidden" name="what" value="default">

  <table class="grid">
    <thead>
      <tr><th>Default</th><th>Order of battle</th><th>Faction</th>
          <th>Platoons</th><th>Squads</th><th>Comms</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <?php $isDefault = ($r['id'] === $default); ?>
      <tr>
        <td>
          <label class="inlinelabel">
            <input type="radio" name="default" value="<?= h($r['id']) ?>" <?= $isDefault ? 'checked' : '' ?>>
            <?= $isDefault ? '<strong>default</strong>' : '' ?>
          </label>
        </td>
        <td><?= $r['id'] === ''
              ? '<strong>Common</strong>'
              : '<code>' . h($r['id']) . '</code>' ?>
            <?= $r['exists'] ? '' : ' <span class="dim">nothing saved yet</span>' ?></td>
        <td><?= h($r['faction']) ?> <span class="dim"><?= h($r['side']) ?></span></td>
        <td><?= $r['platoons'] ?></td>
        <td><?= $r['squads'] ?> <span class="dim"><?= $r['slots'] ?> slots</span></td>
        <td class="dim"><?= h(($r['nets'] !== '' ? $r['nets'] : 'default')
              . ' / ' . ($r['radio'] !== '' ? $r['radio'] : 'default')) ?></td>
        <td>
          <?php $vq = $r['id'] !== '' ? '&amp;v=' . urlencode($r['id']) : ''; ?>
          <a class="btnlink" href="?page=orbat&amp;s=versions<?= $vq ?>">Open</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="actions"><button type="submit">Make that one the default</button></div>
</form>
<p class="dim">Read at the next mission start. A mission already running keeps
the ORBAT it booted with.</p>

<h2>Who <?= $variant === '' ? 'the default' : h($variant) ?> is</h2>
<form method="post" class="fields">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="s" value="versions">
  <input type="hidden" name="what" value="faction">
  <label>Faction <span class="dim">the name over the role screen</span>
    <input type="text" name="faction" value="<?= h($faction) ?>" placeholder="Ghosts of Battle"></label>
  <label>Side <span class="dim">which side its groups are created on</span>
    <select name="side">
      <?php $side = strtoupper((string) (ghostd_orbat($variant)['side'] ?? 'WEST')); ?>
      <?php foreach (GHOSTD_SIDES as $k => $label): ?>
        <option value="<?= h($k) ?>" <?= $side === $k ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select></label>
  <div class="actions"><button type="submit">Save</button></div>
</form>

<h2>Communications for <?= $variant === '' ? 'the default' : h($variant) ?></h2>
<p class="note">Which comms templates this order of battle runs. They are
written on the <a href="?page=orbat&amp;s=radio<?= $variant !== '' ? '&amp;v=' . urlencode($variant) : '' ?>">Communications</a>
tab; this says which of them it uses. A comms plan is written around squads, so
a different order of battle often wants a different one.</p>

<form method="post" class="fields">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="s" value="versions">
  <input type="hidden" name="what" value="comms">
  <?php $cur = ghostd_orbat($variant); ?>
  <label>Messaging nets
    <select name="netsVersion">
      <option value="">Default</option>
      <?php foreach (ghostd_doc_variants('nets') as $v): ?>
        <option value="<?= h($v) ?>" <?= $cur['nets'] === $v ? 'selected' : '' ?>><?= h($v) ?></option>
      <?php endforeach; ?>
    </select></label>
  <label>Radio plan
    <select name="radioVersion">
      <option value="">Default</option>
      <?php foreach (ghostd_doc_variants('radio') as $v): ?>
        <option value="<?= h($v) ?>" <?= $cur['radio'] === $v ? 'selected' : '' ?>><?= h($v) ?></option>
      <?php endforeach; ?>
    </select></label>
  <div class="actions"><button type="submit">Save</button></div>
</form>

<h2>Platoons in <?= $variant === '' ? 'the default' : h($variant) ?>
  <span class="dim"><?= count($platoons) ?> of <?= count($pool) ?></span></h2>
<p class="note">An order of battle is a <strong>choice of platoons</strong>. They
are written once on the <a href="?page=orbat&amp;s=platoons<?= $variant !== '' ? '&amp;v=' . urlencode($variant) : '' ?>">Platoons</a>
tab; this says which of them this one holds. Open a different order of battle
above to choose its platoons.</p>

<?php if ($pool === []): ?>
  <p class="dim">No platoons exist yet.
  <a href="?page=platoon&amp;id=%2B">Make one</a>.</p>
<?php else: ?>
<form method="post">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="s" value="versions">
  <input type="hidden" name="what" value="pickplatoons">

  <table class="grid">
    <thead><tr><th>In</th><th>Platoon</th><th>Net</th><th>Squads</th></tr></thead>
    <tbody>
    <?php foreach ($pool as $pid => $p): ?>
      <tr>
        <td><input type="checkbox" name="inorbat[]" value="<?= h((string) $pid) ?>"
                   <?= isset($inThis[(string) $pid]) ? 'checked' : '' ?>></td>
        <td><strong><?= h((string) ($p[1] ?? $pid)) ?></strong> <code><?= h((string) $pid) ?></code></td>
        <td><?= ($p[3] ?? '') !== '' ? h((string) $p[3]) : '<span class="dim">none</span>' ?></td>
        <td class="dim"><?= h(implode(', ', array_map('strval', (array) ($p[4] ?? [])))) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="actions"><button type="submit">Save which platoons are in it</button></div>
</form>
<?php endif; ?>

<h2>Build another</h2>
<p class="dim">A second order of battle is nearly always the first one with a
couple of squads changed, so copying is the way to make one. An empty one is
fifteen squads of typing.</p>

<form method="post" class="inline">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="s" value="versions">
  <input type="hidden" name="what" value="neworbat">

  <label for="from">Copy</label>
  <select id="from" name="from">
    <?php foreach ($rows as $r): ?>
      <option value="<?= h($r['id']) ?>" <?= $r['id'] === $default ? 'selected' : '' ?>>
        <?= $r['id'] === '' ? 'Common' : h($r['id']) ?>
        (<?= $r['squads'] ?> squads)</option>
    <?php endforeach; ?>
  </select>
  <label for="to">to</label>
  <input type="text" id="to" name="to" placeholder="NightOps" required
         pattern="[A-Za-z][A-Za-z0-9_]*">
  <button type="submit">Copy it</button>
  <span class="dim">Letters, digits and underscore. Its squads keep their
  channels - those belong to the squad, not to the version.</span>
</form>

<?php if ($variant !== ''): ?>
  <h2>Remove <?= h($variant) ?></h2>
  <form method="post" class="danger">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="v" value="<?= h($variant) ?>">
    <input type="hidden" name="s" value="versions">
    <input type="hidden" name="what" value="deleteorbat">
    <p class="dim">Deletes the version you are currently editing. The common
    ORBAT cannot be deleted, and neither can the default - make another one the
    default first.</p>
    <button type="submit" class="hot">Delete <?= h($variant) ?></button>
  </form>
<?php endif; ?>
