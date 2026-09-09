<?php
/**
 * The version switcher, shared by every template editor.
 *
 * ONE PLACE, because four editors each growing their own copy is how they
 * drift apart. Expects $key, $t, $variant and $variants to be set.
 *
 * '' IS THE COMMON VERSION and lives at <unit>.<doc>; every other id is a
 * named version at <unit>.<doc>.<id>. What a named version means is the
 * consumer's business - the arsenal merges it on top of the common one for the
 * roles that name it; a second welcome screen is just a second welcome screen.
 */

declare(strict_types=1);
?>
<p class="dim"><a href="?page=config">&larr; Templates</a> &middot;
Mongo doc <code><?= h(ghostd_template_doc_id($key, $variant)) ?></code> &middot;
replaces <code><?= h($t['replaces']) ?></code></p>

<p class="note"><?= h($t['blurb']) ?></p>

<h2>Versions <span class="dim"><?= count($variants) + 1 ?></span></h2>

<?php // A DROPDOWN, NOT TABS. The list comes out of Mongo - every document
      // named "<unit>.<doc>." - and there is no limit on how many a unit
      // makes, so a row of tabs runs off the page (user, 2026-09-09: "there
      // will be too many at some point to have tabs"). Nothing here is a
      // hard-coded list. ?>
<form method="get" class="inline">
  <input type="hidden" name="page" value="configedit">
  <input type="hidden" name="t" value="<?= h($key) ?>">
  <label for="vpick">Version</label>
  <select id="vpick" name="v">
    <option value="" <?= $variant === '' ? 'selected' : '' ?>>Default</option>
    <?php foreach ($variants as $v): ?>
      <option value="<?= h($v) ?>" <?= $variant === $v ? 'selected' : '' ?>><?= h($v) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Open</button>
  <span class="dim">Default is what a mission gets when it names no version.</span>
</form>

<form method="get" class="inline">
  <input type="hidden" name="page" value="configedit">
  <input type="hidden" name="t" value="<?= h($key) ?>">
  <label for="v">New version</label>
  <input type="text" id="v" name="v"
         placeholder="<?= h($t['variantHint'] ?? 'Arsenal_Banshee') ?>">
  <button type="submit">Create</button>
</form>

<p class="dim">Editing
  <strong><?= $variant === '' ? 'the default' : h($variant) ?></strong>.
  A version that has never been saved opens empty - saving it creates it.</p>
