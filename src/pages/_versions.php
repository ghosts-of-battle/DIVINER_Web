<?php
/**
 * The version list, shared by every template editor.
 *
 * ONE PLACE, because four editors each growing their own copy is how they
 * drift apart. Expects $key, $t, $variant and $variants to be set.
 *
 * '' is the common version at <unit>.<doc>; every other id is a named version
 * at <unit>.<doc>.<id>.
 *
 * KEEP IT SHORT (user, 2026-09-09: "keep it fuking simple and easy to use").
 * This was a dropdown with an Open button beside a text box with a Create
 * button, both labelled Version, over three lines of prose - two ways to do
 * one thing and nothing said which. It is one row of links now, and when
 * Default is the only version there is nothing to pick and it says so.
 */

declare(strict_types=1);

$vRows = array_merge([''], array_values($variants));
?>
<p class="dim"><a href="?page=config&amp;t=<?= urlencode($key) ?>">&larr; <?= h($t['label']) ?></a>
&middot; <code><?= h(ghostd_template_doc_id($key, $variant)) ?></code>
&middot; replaces <code><?= h($t['replaces']) ?></code></p>

<p class="note"><?= h($t['blurb']) ?></p>

<p class="vbar">
  <strong>Version</strong>
  <?php foreach ($vRows as $vv): ?>
    <?php if ($variant === $vv): ?>
      <span class="on"><?= $vv === '' ? 'Default' : h($vv) ?></span>
    <?php else: ?>
      <a href="?page=configedit&amp;t=<?= urlencode($key) ?><?= $vv !== '' ? '&amp;v=' . urlencode($vv) : '' ?>"><?= $vv === '' ? 'Default' : h($vv) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
  <span class="dim">&middot; Default is what a mission gets when it names none.
  Delete one on <a href="?page=config&amp;t=<?= urlencode($key) ?>">Templates</a>.</span>
</p>

<form method="get" class="inline">
  <input type="hidden" name="page" value="configedit">
  <input type="hidden" name="t" value="<?= h($key) ?>">
  <input type="text" name="v" required pattern="[A-Za-z][A-Za-z0-9_]*"
         placeholder="<?= h($t['variantHint'] ?? 'Tropical') ?>">
  <button type="submit">New version</button>
  <span class="dim">Opens empty; saving it creates it.</span>
</form>
