<?php
/**
 * ONE order of battle: who it is, what comms it runs, which platoons are in it.
 *
 * Opened from the list (user, 2026-09-09: "orbat you edit orbats in the orbat
 * you select platoons, and radio and messaging templates"). The platoons
 * themselves are written on the Platoons tab and only SELECTED here - an order
 * of battle is a choice of platoons, not a second place to write them down.
 */

declare(strict_types=1);

$cur  = ghostd_orbat($variant);
$pool = ghostd_platoon_pool($variant);

$inThis = [];
foreach ($platoons as $p) { $inThis[(string) ($p[0] ?? '')] = true; }
?>
<p><a href="?page=orbat&amp;s=versions">&larr; Orders of battle</a></p>

<h2><code><?= h(ghostd_orbat_doc_id($variant)) ?></code></h2>

<form method="post" class="fields">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="s" value="one">
  <input type="hidden" name="what" value="faction">
  <label>Faction <span class="dim">the name over the role screen</span>
    <input type="text" name="faction" value="<?= h($faction) ?>" placeholder="Ghosts of Battle"></label>
  <label>Side <span class="dim">which side its groups are created on</span>
    <select name="side">
      <?php $side = strtoupper((string) ($cur['side'] ?? 'WEST')); ?>
      <?php foreach (GHOSTD_SIDES as $k => $label): ?>
        <option value="<?= h($k) ?>" <?= $side === $k ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select></label>
  <div class="actions"><button type="submit">Save</button></div>
</form>

<h2>Everybody in it</h2>
<p class="dim">The engine's numeric coefficients, set once for every player on
this order of battle. 1 is normal.</p>

<form method="post" class="fields">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="s" value="one">
  <input type="hidden" name="what" value="coefs">
  <?php require_once __DIR__ . '/../roles.php'; ?>
  <?php foreach (GHOSTD_TRAIT_COEFS as $c => $help): ?>
    <label><?= h($c) ?> <span class="dim"><?= h($help) ?></span>
      <input type="number" step="0.05" min="0" name="coef_<?= h($c) ?>"
             value="<?= h((string) ($cur['coefs'][$c] ?? 1)) ?>"></label>
  <?php endforeach; ?>
  <div class="actions"><button type="submit">Save</button></div>
</form>

<h2>What it runs</h2>
<p class="dim">Each of these is a template with named versions, written on its
own page. This says which ones this order of battle uses - the mission gets
them without naming a single setting.</p>

<form method="post" class="fields">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="s" value="one">
  <input type="hidden" name="what" value="comms">
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
  <label>Arsenal
    <select name="arsenalVersion">
      <option value="">Default</option>
      <?php foreach (ghostd_doc_variants('arsenal') as $v): ?>
        <option value="<?= h($v) ?>" <?= ($cur['arsenal'] ?? '') === $v ? 'selected' : '' ?>><?= h($v) ?></option>
      <?php endforeach; ?>
    </select></label>
  <label>Motorpool
    <select name="motorpoolVersion">
      <option value="">Default</option>
      <?php foreach (ghostd_doc_variants('motorpool') as $v): ?>
        <option value="<?= h($v) ?>" <?= ($cur['motorpool'] ?? '') === $v ? 'selected' : '' ?>><?= h($v) ?></option>
      <?php endforeach; ?>
    </select></label>
  <div class="actions"><button type="submit">Save</button></div>
</form>

<h2>Platoons <span class="dim"><?= count($platoons) ?> of <?= count($pool) ?></span></h2>

<?php if ($pool === []): ?>
  <p class="dim">No platoons exist yet.
  <a href="?page=platoon&amp;id=%2B">Make one</a>.</p>
<?php else: ?>
<form method="post">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="s" value="one">
  <input type="hidden" name="what" value="pickplatoons">

  <table class="grid">
    <thead><tr><th style="width:8%">In</th><th style="width:32%">Platoon</th>
        <th style="width:20%">Net</th><th style="width:40%">Squads</th></tr></thead>
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

<?php if ($variant !== ''): ?>
  <h2>Remove</h2>
  <form method="post" class="danger">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="v" value="<?= h($variant) ?>">
    <input type="hidden" name="s" value="one">
    <input type="hidden" name="what" value="deleteorbat">
    <p class="dim">The default cannot be deleted - make another one the default
    first.</p>
    <button type="submit" class="hot">Delete <?= h($variant) ?></button>
  </form>
<?php endif; ?>
