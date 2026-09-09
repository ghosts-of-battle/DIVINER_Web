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

<h2>Versions</h2>
<?php if ($variants !== []): ?>
<nav class="sections">
  <span class="tabsep">version</span>
  <a href="?page=configedit&amp;t=<?= urlencode($key) ?>"
     class="<?= $variant === '' ? 'on' : '' ?>">Common</a>
  <?php foreach ($variants as $v): ?>
    <a href="?page=configedit&amp;t=<?= urlencode($key) ?>&amp;v=<?= urlencode($v) ?>"
       class="<?= $variant === $v ? 'on' : '' ?>"><?= h($v) ?></a>
  <?php endforeach; ?>
</nav>
<?php else: ?>
  <p class="dim">Only the common version so far. Name one below to add another.</p>
<?php endif; ?>
<form method="get" class="inline">
  <input type="hidden" name="page" value="configedit">
  <input type="hidden" name="t" value="<?= h($key) ?>">
  <label for="v">Open or create a version</label>
  <input type="text" id="v" name="v" value="<?= h($variant) ?>"
         placeholder="<?= h($t['variantHint'] ?? 'Arsenal_Banshee') ?>">
  <button type="submit">Open</button>
</form>
<p class="dim">Editing
  <strong><?= $variant === '' ? 'the common version' : h($variant) ?></strong>.
  A version that has never been saved opens empty - saving it creates it.</p>
