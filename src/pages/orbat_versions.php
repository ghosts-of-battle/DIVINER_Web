<?php
/**
 * The orders of battle: what there is, and which one a mission gets by default.
 *
 * A LIST, AND NOTHING ELSE (user, 2026-09-09: "this is not a list of fucking
 * orbats to edit"). One is opened and edited on its own page. This page says
 * what exists, ticks the default, and copies one to make another.
 *
 * THE UNNAMED ONE IS NOT "COMMON". <unit>.orbat is the document a mission gets
 * when its currentOrbat names none; the rest are <unit>.orbat.<name>. The list
 * shows the documents, because that is what they are.
 */

declare(strict_types=1);

$default = ghostd_default_orbat_id();

$rows = [];
foreach (array_merge([''], ghostd_orbat_variants()) as $v) {
    $o = ghostd_orbat((string) $v);
    // The unnamed document is offered only when it is actually there. On a unit
    // whose orders of battle are all named it is not, and listing it put an
    // empty row with 0 platoons in the table (2026-09-09).
    if ((string) $v === '' && !$o['exists']) {
        continue;
    }
    $slots = 0;
    foreach ($o['groups'] as $g) { $slots += count((array) ($g[1] ?? [])); }
    $rows[] = [
        'id'       => (string) $v,
        'doc'      => ghostd_orbat_doc_id((string) $v),
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
<p class="dim">A mission runs the one its <code>currentOrbat</code> names, or
the default below when it names none.</p>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="s" value="versions">
  <input type="hidden" name="what" value="default">

  <table class="grid">
    <thead>
      <tr><th style="width:10%">Default</th><th style="width:26%">Order of battle</th>
          <th style="width:16%">Faction</th><th style="width:10%">Platoons</th>
          <th style="width:14%">Squads</th><th style="width:16%">Comms</th>
          <th style="width:8%"></th></tr>
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
        <td><code><?= h($r['doc']) ?></code>
            <?= $r['exists'] ? '' : ' <span class="dim">nothing saved yet</span>' ?></td>
        <td><?= h($r['faction']) ?> <span class="dim"><?= h($r['side']) ?></span></td>
        <td><?= $r['platoons'] ?></td>
        <td><?= $r['squads'] ?> <span class="dim"><?= $r['slots'] ?> slots</span></td>
        <td class="dim"><?= h(($r['nets'] !== '' ? $r['nets'] : 'default')
              . ' / ' . ($r['radio'] !== '' ? $r['radio'] : 'default')) ?></td>
        <td>
          <?php $vq = $r['id'] !== '' ? '&amp;v=' . urlencode($r['id']) : ''; ?>
          <a class="btnlink" href="?page=orbat&amp;s=one<?= $vq ?>">Edit</a>
          <?php // The delete belongs to the form below the table: a form inside
                // a form is not HTML, and this is the row you want it on. ?>
          <button type="submit" form="orbatdel" name="v" value="<?= h($r['id']) ?>" class="hot"
                  onclick="return confirm('Delete <?= h($r['doc']) ?>?<?= $r['id'] === $default
                      ? ' It is the default - the tick goes to whatever is left.' : '' ?> Its platoons and squads are kept.');">Delete</button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="actions"><button type="submit">Make that one the default</button></div>
</form>

<form method="post" id="orbatdel">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="s" value="versions">
  <input type="hidden" name="what" value="deleteorbat">
</form>

<h2>New</h2>
<form method="post" class="inline">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="s" value="versions">
  <input type="hidden" name="what" value="neworbat">
  <label for="to">Name</label>
  <input type="text" id="to" name="to" placeholder="NightOps" required
         pattern="[A-Za-z][A-Za-z0-9_]*">
  <button type="submit">New order of battle</button>
  <span class="dim">Letters, digits and underscore. Squads and platoons are
  what get copied; an order of battle is a choice of platoons.</span>
</form>
