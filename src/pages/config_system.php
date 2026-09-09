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
<p class="dim">What a player may raise on the
<a href="?page=tickets">PAC actions</a> page. The id is what every ticket
already raised carries, so renaming one orphans them.</p>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="t" value="system">
  <input type="hidden" name="what" value="ticketkinds">

  <table class="grid">
    <thead><tr><th style="width:20%">Id</th><th style="width:30%">Shown as</th>
        <th style="width:42%">What to put in it</th><th style="width:8%">Del</th></tr></thead>
    <tbody>
    <?php $i = 0; foreach ($kinds as $kid => $k): ?>
      <tr>
        <td><input type="text" name="k_id[<?= $i ?>]" value="<?= h((string) $kid) ?>"></td>
        <td><input type="text" name="k_label[<?= $i ?>]" value="<?= h($k['label']) ?>"></td>
        <td><input type="text" name="k_hint[<?= $i ?>]" value="<?= h($k['hint']) ?>"></td>
        <td><input type="checkbox" name="k_remove[]" value="<?= $i ?>"></td>
      </tr>
    <?php $i++; endforeach; ?>
    <?php for ($j = 0; $j < 2; $j++): $n = $i + $j; ?>
      <tr>
        <td><input type="text" name="k_id[<?= $n ?>]" placeholder="transfer"></td>
        <td><input type="text" name="k_label[<?= $n ?>]" placeholder="Transfer request"></td>
        <td><input type="text" name="k_hint[<?= $n ?>]" placeholder="what to say in it"></td>
        <td></td>
      </tr>
    <?php endfor; ?>
    </tbody>
  </table>

  <div class="actions"><button type="submit">Save</button></div>
</form>
