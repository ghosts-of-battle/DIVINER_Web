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
    ];
}
?>
<h2>Orders of battle <span class="dim"><?= count($rows) ?></span></h2>
<p class="note">One of these is <strong>live</strong>. Missions load it at
start, and the roster's group and role dropdowns come from it - so ticking a
different one changes what everybody can be slotted into.</p>
<p class="dim"><strong>Edit</strong> opens that order of battle's squads, and
every tab from then on stays on it - the bar above shows which. <em>Details</em>
is its faction, its side and what it holds.</p>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="s" value="versions">
  <input type="hidden" name="what" value="default">

  <table class="grid">
    <thead>
      <tr><th style="width:5rem">Live</th><th>Version</th><th>Faction</th><th>Side</th>
          <th>Platoons</th><th>Squads</th><th>Slots</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <?php $isDefault = ($r['id'] === $default); ?>
      <tr>
        <td>
          <label class="inlinelabel">
            <input type="radio" name="default" value="<?= h($r['id']) ?>" <?= $isDefault ? 'checked' : '' ?>>
            <?= $isDefault ? '<strong>live</strong>' : '' ?>
          </label>
        </td>
        <td><?= $r['id'] === ''
              ? '<strong>Common</strong>'
              : '<code>' . h($r['id']) . '</code>' ?>
            <?= $r['exists'] ? '' : ' <span class="dim">nothing saved yet</span>' ?></td>
        <td><?= h($r['faction']) ?></td>
        <td class="dim"><?= h($r['side']) ?></td>
        <td><?= $r['platoons'] ?></td>
        <td><?= $r['squads'] ?></td>
        <td><?= $r['slots'] ?></td>
        <td>
          <?php $vq = $r['id'] !== '' ? '&amp;v=' . urlencode($r['id']) : ''; ?>
          <a class="btnlink" href="?page=orbat&amp;s=squads<?= $vq ?>">Edit</a>
          <a class="dim" href="?page=orbat&amp;s=common<?= $vq ?>">details</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="actions"><button type="submit">Make that one live</button></div>
</form>
<p class="dim">Read at the next mission start. A mission already running keeps
the ORBAT it booted with.</p>

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
    ORBAT cannot be deleted, and neither can the live one - make another one
    live first.</p>
    <button type="submit" class="hot">Delete <?= h($variant) ?></button>
  </form>
<?php endif; ?>
