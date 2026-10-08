<?php
/**
 * The System tab: the two forms TAC//PAC itself is made of.
 *
 * The shape of an operation order, and the kinds of PAC request. Both were PHP
 * constants; they are documents now, and this edits them.
 */

declare(strict_types=1);

require_once __DIR__ . '/../system.php';

$sections = ghostd_opord_sections();
$kinds    = ghostd_ticket_kinds();

$kindLabels = ['t' => 'one line', 'x' => 'paragraph', 'a' => 'a list'];
$fieldCount = 0;
foreach ($sections as $meta) { $fieldCount += count((array) ($meta['fields'] ?? [])); }
?>
<h2>Operation order <span class="dim"><?= count($sections) ?> sections,
  <?= $fieldCount ?> fields</span></h2>
<p class="dim">A card per section of the order on the
<a href="?page=opords">Orders</a> page. A report template points at
<code>section.field</code>, so renaming one breaks it.</p>

<p class="actions"><a href="?page=opord_section" class="btnlink">+ New section</a></p>

<?php foreach ($sections as $sid => $meta): ?>
<details class="card">
  <summary>
    <strong><?= h((string) ($meta['title'] ?? $sid)) ?></strong>
    <code><?= h((string) $sid) ?></code>
    <span class="pill dimpill"><?= count((array) ($meta['fields'] ?? [])) ?> fields</span>
    <a class="edit" href="?page=opord_section&amp;id=<?= urlencode((string) $sid) ?>">edit</a>
  </summary>

  <?php if (($meta['hint'] ?? '') !== ''): ?>
    <p class="dim"><?= h((string) $meta['hint']) ?></p>
  <?php endif; ?>

  <table class="grid">
    <thead><tr><th style="width:24%">Field</th><th style="width:38%">Label</th>
        <th style="width:16%">Kind</th><th style="width:22%">Key</th></tr></thead>
    <tbody>
    <?php foreach ((array) ($meta['fields'] ?? []) as $fid => $fm): ?>
      <tr>
        <td><code><?= h((string) $fid) ?></code></td>
        <td><?= h((string) ($fm['label'] ?? $fid)) ?></td>
        <td class="dim"><?= h($kindLabels[(string) ($fm['kind'] ?? 'x')] ?? 'paragraph') ?></td>
        <td class="dim"><code><?= h($sid . '.' . $fid) ?></code></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</details>
<?php endforeach; ?>

<h2>PAC requests <span class="dim"><?= count($kinds) ?> kinds</span></h2>
<p class="dim">A card per kind a player may raise on the
<a href="?page=tickets">PAC actions</a> page. The id is what every ticket
already raised carries, so renaming one orphans them.</p>

<p class="actions"><a href="?page=ticket_kind" class="btnlink">+ New kind</a></p>

<?php foreach ($kinds as $kid => $k): ?>
<details class="card">
  <summary>
    <strong><?= h((string) ($k['label'] ?? $kid)) ?></strong>
    <code><?= h((string) $kid) ?></code>
    <a class="edit" href="?page=ticket_kind&amp;id=<?= urlencode((string) $kid) ?>">edit</a>
  </summary>
  <table class="kv">
    <tr><th>What to put in it</th>
        <td><?= ($k['hint'] ?? '') !== '' ? h((string) $k['hint']) : '<span class="dim">nothing said</span>' ?></td></tr>
  </table>
</details>
<?php endforeach; ?>

<?php require __DIR__ . '/schemes_edit.php'; ?>
