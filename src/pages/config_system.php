<?php
/**
 * The System tab: the two forms TAC//PAC itself is made of.
 *
 * The shape of an operation order, and the kinds of PAC request. Both were PHP
 * constants; they are documents now, and this edits them.
 */

declare(strict_types=1);

require_once __DIR__ . '/../system.php';

$rows  = ghostd_opord_rows();
$kinds = ghostd_ticket_kinds();
?>
<h2>Operation order <span class="dim"><?= count($rows) ?> fields</span></h2>
<p class="dim">One row per field on the <a href="?page=opords">Orders</a> page.
Clearing a field removes it. Renaming one breaks any report template pointing at
<code>section.field</code>.</p>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="t" value="system">
  <input type="hidden" name="what" value="opord">

  <table class="grid">
    <thead>
      <tr><th style="width:20%">Section</th><th style="width:20%">Field</th>
          <th style="width:35%">Label</th><th style="width:17%">Kind</th>
          <th style="width:8%">Del</th></tr>
    </thead>
    <tbody>
    <?php $i = 0; foreach ($rows as $r): ?>
      <tr>
        <td><input type="text" name="o_section[<?= $i ?>]" value="<?= h($r['section']) ?>"></td>
        <td><input type="text" name="o_field[<?= $i ?>]" value="<?= h($r['field']) ?>"></td>
        <td><input type="text" name="o_label[<?= $i ?>]" value="<?= h($r['label']) ?>"></td>
        <td>
          <select name="o_kind[<?= $i ?>]">
            <?php foreach (['t' => 'one line', 'x' => 'paragraph', 'a' => 'a list'] as $k => $kl): ?>
              <option value="<?= h($k) ?>" <?= $r['kind'] === $k ? 'selected' : '' ?>><?= h($kl) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td><input type="checkbox" name="o_remove[]" value="<?= $i ?>"></td>
      </tr>
    <?php $i++; endforeach; ?>
    <?php for ($k = 0; $k < 2; $k++): $n = $i + $k; ?>
      <tr>
        <td><input type="text" name="o_section[<?= $n ?>]" placeholder="execution"></td>
        <td><input type="text" name="o_field[<?= $n ?>]" placeholder="scheme"></td>
        <td><input type="text" name="o_label[<?= $n ?>]" placeholder="Scheme of manoeuvre"></td>
        <td>
          <select name="o_kind[<?= $n ?>]">
            <option value="t">one line</option>
            <option value="x" selected>paragraph</option>
            <option value="a">a list</option>
          </select>
        </td>
        <td></td>
      </tr>
    <?php endfor; ?>
    </tbody>
  </table>

  <div class="actions"><button type="submit">Save</button></div>
</form>

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
