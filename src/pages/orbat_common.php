<?php
/**
 * Common: who the unit is, before any of its parts.
 *
 * THE FIRST TAB BECAUSE IT IS THE FIRST QUESTION. The faction is the name over
 * the role screen - "GHOSTS OF BATTLE  ROLE SELECTION" - and the side is which
 * side of the war its groups are created on. Everything on the other four tabs
 * hangs off those two answers.
 *
 * It also carries the order the rest are filled in, which used to sit on every
 * tab and told you nothing once you were on the right one.
 */

declare(strict_types=1);

$side = strtoupper((string) ($o['side'] ?? ''));
if (!isset(GHOSTD_SIDES[$side])) {
    $side = 'WEST';
}

// What this version of the ORBAT currently holds - the answer to "am I looking
// at the right one", which is why the version tabs exist at all.
$slots = 0;
foreach ($groups as $g) { $slots += count((array) ($g[1] ?? [])); }

require_once __DIR__ . '/../roles.php';
$customTraits = ghostd_custom_traits();
?>
<h2>Who the unit is</h2>
<form method="post">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="s" value="common">
  <input type="hidden" name="what" value="faction">

  <div class="fields">
    <label>Faction
      <span class="dim">the name over the role screen, and wherever the unit is named in game</span>
      <input type="text" name="faction" value="<?= h($faction) ?>"
             placeholder="Ghosts of Battle"></label>

    <label>Side
      <span class="dim">which side of the war its groups are created on</span>
      <select name="side">
        <?php foreach (GHOSTD_SIDES as $k => $label): ?>
          <option value="<?= h($k) ?>" <?= $side === $k ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select></label>
  </div>

  <div class="actions"><button type="submit">Save</button></div>
</form>

<h2>This version</h2>
<p class="dim">Editing <strong><?= $variant === '' ? 'the common ORBAT' : h($variant) ?></strong>,
in <code><?= h($docId) ?></code>.</p>
<table class="kv">
  <tr><th>Platoons</th><td><?= count($platoons) ?></td></tr>
  <tr><th>Squads</th><td><?= count($groups) ?></td></tr>
  <tr><th>Slots in total</th><td><?= $slots ?></td></tr>
  <tr><th>Radio nets</th><td><?= count($radioNets) ?></td></tr>
</table>

<h2>Custom traits <span class="dim"><?= count($customTraits) ?></span></h2>
<p class="note">The trait names <strong>this unit</strong> invented, as opposed
to the seven the engine already has. A role assigns them by ticking, and the
<code>setUnitTrait</code> custom flag is set for you - which is the argument
that silently throws a trait away when it is wrong.</p>
<p class="dim">The name is what the mod reads. <strong>Kind</strong> is
<code>bool</code> for a yes/no or <code>number</code> for a value. Clearing the
name removes the row.</p>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="s" value="common">
  <input type="hidden" name="what" value="traits">

  <table class="grid">
    <thead><tr><th>Name</th><th>Shown as</th><th>Kind</th><th>What it does</th><th>Remove</th></tr></thead>
    <tbody>
    <?php $rows = $customTraits; $rows[''] = ['label' => '', 'kind' => 'bool', 'help' => '']; ?>
    <?php $i = 0; foreach ($rows as $tn => $meta): ?>
      <?php $isNew = ((string) $tn === ''); ?>
      <tr>
        <td><input type="text" name="t_id[<?= $i ?>]" value="<?= h((string) $tn) ?>"
                   placeholder="<?= $isNew ? 'draWhitelisted' : '' ?>" style="min-width:12rem"></td>
        <td><input type="text" name="t_label[<?= $i ?>]" value="<?= h($meta['label']) ?>"
                   placeholder="<?= $isNew ? 'DRA whitelisted' : '' ?>"></td>
        <td>
          <select name="t_kind[<?= $i ?>]">
            <option value="bool" <?= $meta['kind'] !== 'number' ? 'selected' : '' ?>>yes / no</option>
            <option value="number" <?= $meta['kind'] === 'number' ? 'selected' : '' ?>>a number</option>
          </select>
        </td>
        <td><input type="text" name="t_help[<?= $i ?>]" value="<?= h($meta['help']) ?>"
                   placeholder="<?= $isNew ? 'may draw from the drone rack' : '' ?>"></td>
        <td><?= $isNew ? '' : '<input type="checkbox" name="t_remove[]" value="' . $i . '">' ?></td>
      </tr>
    <?php $i++; endforeach; ?>
    </tbody>
  </table>

  <div class="actions"><button type="submit">Save custom traits</button></div>
</form>
<p class="dim">Kept in <code><?= h(ghostd_template_doc_id('traits')) ?></code> -
one set for the whole unit, not per ORBAT version, because a trait name does not
change when the order of battle does.</p>

<h2>The order to fill them in</h2>
<p class="dim">Working the other way round means typing names that do not
resolve yet, and nothing says so until somebody tries to slot in.</p>
<table class="grid">
  <tbody>
    <tr>
      <td style="width:9rem"><a class="btnlink" href="?page=orbat&amp;s=radio<?= $variant !== '' ? '&amp;v=' . urlencode($variant) : '' ?>">Radio</a></td>
      <td class="dim">The channels and frequencies. A squad cannot sit on a net that does not exist.</td>
    </tr>
    <tr>
      <td><a class="btnlink" href="?page=orbat&amp;s=roles<?= $variant !== '' ? '&amp;v=' . urlencode($variant) : '' ?>">Roles</a></td>
      <td class="dim">What a slot is made of - its nets, tiles, traits, loadout and arsenal. A squad can only hold roles that exist.</td>
    </tr>
    <tr>
      <td><a class="btnlink" href="?page=orbat&amp;s=squads<?= $variant !== '' ? '&amp;v=' . urlencode($variant) : '' ?>">Squads</a></td>
      <td class="dim">How many men, which roles, which channel. A platoon can only list squads that exist.</td>
    </tr>
    <tr>
      <td><a class="btnlink" href="?page=orbat&amp;s=platoons<?= $variant !== '' ? '&amp;v=' . urlencode($variant) : '' ?>">Platoons</a></td>
      <td class="dim">Which squads belong together, on which net and which long-range channel. A squad no platoon lists never appears in the group menu.</td>
    </tr>
  </tbody>
</table>
