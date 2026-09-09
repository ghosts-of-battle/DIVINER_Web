<?php
/**
 * The radio plan, in two sub-tabs: ACRE and TFAR (drawn in orbat.php's bar).
 *
 * THEY ARE NOT THE SAME SHAPE, so they do not share a form.
 *
 *   ACRE   three bands - short, medium, long - each an open list of channels,
 *          every channel carrying its own frequency and label, and long range
 *          carrying a power as well. Radios are classnames and each has its
 *          own channel count.
 *
 *   TFAR   two bands only - short and long - and a FIXED number of channels
 *          per radio. A frequency is just the number the dial sits on, so the
 *          slots are numbered 1..n and you fill them in; there is no adding a
 *          ninth channel to a radio that has eight.
 *
 * Showing TFAR as an open-ended list would be a lie about the radio.
 */

declare(strict_types=1);

require_once __DIR__ . '/../roles.php';

// How many channels a TFAR radio actually has. Kept generous enough for the
// long-range sets and honest about being fixed.
const GHOSTD_TFAR_SLOTS = 8;

// $sub - which of ACRE and TFAR - is chosen by orbat.php, because the sub-tabs
// are drawn up in the page's one menu bar rather than a second bar down here.
$vq = $variant !== '' ? '&amp;v=' . urlencode($variant) : '';
?>

<?php if ($sub === 'nets'): ?>

  <?php
    // THE NETS TAC//MSG OFFERS. Not channels - a net is a mailbox a man reads,
    // a channel is what he keys up on. They are set up together because a
    // platoon's net and its MR channel carry the same name, and a role's list
    // of nets is picked from exactly these.
    $netItems = [];
    try {
        $netItems = ghostd_template_items('nets');
    } catch (Throwable $e) {
        $netItems = [];
    }
    // Which roles read each one, which is the useful question about a net.
    $netUsers = [];
    try {
        foreach (ghostd_role_ids() as $rid) {
            foreach (ghostd_role($rid)['nets'] as $row) {
                $netUsers[(string) $row[0]][] = $rid;
            }
        }
    } catch (Throwable $e) {
        $netUsers = [];
    }
    $netPlatoons = [];
    foreach ($platoons as $p) {
        $n = (string) ($p[3] ?? '');
        if ($n !== '') { $netPlatoons[$n][] = (string) ($p[1] ?? $p[0] ?? ''); }
    }
  ?>

  <p class="note">The nets TAC//MSG opens a mailbox for. A <strong>dot</strong>
  makes one a sub-net of the one before it - <code>C2.reports</code> hangs under
  <code>C2</code> - and they are separate: a role listing <code>C2</code> does
  not get <code>C2.reports</code>. Squad nets are not listed here; they exist
  because the squads do.</p>
  <p class="dim">These are the names a role picks its nets from, and what a
  platoon commands on. <strong>Order</strong> is the place on the rail and in
  the mailbox list, lowest first. Clearing a name removes the row.</p>
  <?php
    // Shown in the order the rail draws them, not the order the document
    // happens to hold - the number in the box is the answer.
    uasort($netItems, static fn($a, $b) => ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0)));
  ?>

  <form method="post">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="v" value="<?= h($variant) ?>">
    <input type="hidden" name="s" value="radio">
    <input type="hidden" name="r" value="nets">
    <input type="hidden" name="what" value="msgnets">

    <table class="grid">
      <thead><tr><th style="width:10%">Order</th><th style="width:26%">Net</th>
          <th style="width:34%">What it is for</th><th style="width:22%">Read by</th>
          <th style="width:8%">Remove</th></tr></thead>
      <tbody>
      <?php $i = 0; foreach ($netItems as $nid => $n): ?>
        <tr>
          <td><input type="number" name="n_order[<?= $i ?>]" step="1"
                     value="<?= h((string) ($n['order'] ?? ($i + 1) * 10)) ?>"></td>
          <td><input type="text" name="n_id[<?= $i ?>]" value="<?= h((string) $nid) ?>"></td>
          <td><input type="text" name="n_name[<?= $i ?>]" value="<?= h((string) ($n['name'] ?? '')) ?>"></td>
          <td class="dim">
            <?php $u = $netUsers[(string) $nid] ?? []; ?>
            <?= $u === [] ? 'nobody' : count($u) . ' role' . (count($u) === 1 ? '' : 's') ?>
            <?php if (isset($netPlatoons[(string) $nid])): ?>
              &middot; <?= h(implode(', ', $netPlatoons[(string) $nid])) ?>
            <?php endif; ?>
          </td>
          <td><input type="checkbox" name="n_remove[]" value="<?= $i ?>"></td>
        </tr>
      <?php $i++; endforeach; ?>
      </tbody>
    </table>

    <div class="actions"><button type="submit">Save messaging nets</button></div>
  </form>

  <form method="post" class="inline">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="v" value="<?= h($variant) ?>">
    <input type="hidden" name="s" value="radio">
    <input type="hidden" name="r" value="nets">
    <input type="hidden" name="what" value="msgnetnew">
    <label for="nn_id">New net</label>
    <input type="text" id="nn_id" name="nn_id" placeholder="FIRES.cas" required
           pattern="[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)*">
    <input type="text" name="nn_name" placeholder="what it is for">
    <button type="submit">Add</button>
  </form>

  <p class="dim">Kept in <code><?= h(ghostd_template_doc_id('nets')) ?></code>.
  A role reads a net only if its own list names it - that IS the privacy rule.</p>

  <h2>Shared nets <span class="dim"><?= count($radioNets) ?></span></h2>
  <p class="dim">Squads that share a net across a platoon boundary. The net must
  be a name from the list above, and an MR channel of the same name.</p>

  <form method="post">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="v" value="<?= h($variant) ?>">
    <input type="hidden" name="s" value="radio">
    <input type="hidden" name="r" value="nets">
    <input type="hidden" name="what" value="nets">

    <?php $rows = $radioNets; $rows[] = ['', '', []]; ?>
    <?php foreach ($rows as $i => $n): ?>
      <?php $isNew = $i >= count($radioNets); ?>
      <fieldset class="line">
        <legend><?= $isNew ? '<span class="key">new</span>' : h((string) ($n[1] ?: $n[0])) ?></legend>
        <div class="fieldbox">
          <input type="text" name="n_id[<?= $i ?>]" value="<?= h((string) ($n[0] ?? '')) ?>"
                 placeholder="Ground1">
          <select name="n_name[<?= $i ?>]">
            <option value="">- pick the net -</option>
            <?php foreach (array_keys($netItems) as $nid): ?>
              <option value="<?= h((string) $nid) ?>"
                <?= (string) ($n[1] ?? '') === (string) $nid ? 'selected' : '' ?>><?= h((string) $nid) ?></option>
            <?php endforeach; ?>
            <?php if (($n[1] ?? '') !== '' && !isset($netItems[(string) $n[1]])): ?>
              <option value="<?= h((string) $n[1]) ?>" selected><?= h((string) $n[1]) ?> - no such net</option>
            <?php endif; ?>
          </select>
          <?php if (!$isNew): ?>
            <label class="inlinelabel"><input type="checkbox" name="n_remove[]" value="<?= $i ?>"> remove</label>
          <?php endif; ?>
        </div>
        <textarea name="n_squads[<?= $i ?>]" rows="2" class="short"
                  placeholder="one squad name per line"><?= h(implode("
", (array) ($n[2] ?? []))) ?></textarea>
      </fieldset>
    <?php endforeach; ?>

    <div class="actions"><button type="submit">Save shared nets</button></div>
  </form>

<?php endif; ?>

<?php if ($sub === 'acre'): ?>

  <?php
    $blocks = [
        'srChannels' => ['Short range', 'Squad nets. One row per channel.'],
        'mrChannels' => ['Medium range', 'Platoon and company nets.'],
        'lrChannels' => ['Long range', 'Detachment and fires nets. The last column is power for that net.'],
    ];
  ?>
  <p class="note">ACRE programmes each radio from these lists. A channel is an
  index, the frequency it sits on, and the label a player reads. Clearing both
  the frequency and the label removes a row.</p>

  <?php foreach ($blocks as $which => $meta): ?>
    <?php $rows = array_values(array_filter((array) ($radio[$which] ?? []), 'is_array')); ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="v" value="<?= h($variant) ?>">
      <input type="hidden" name="s" value="radio">
      <input type="hidden" name="r" value="acre">
      <input type="hidden" name="what" value="channels">
      <input type="hidden" name="which" value="<?= h($which) ?>">

      <h2><?= h($meta[0]) ?> <span class="dim"><?= count($rows) ?> channels</span></h2>
      <p class="dim"><?= h($meta[1]) ?></p>
      <table class="grid">
        <thead>
          <tr><th>Index</th><th>Frequency</th><th>Label</th>
          <?php if ($which === 'lrChannels'): ?><th>Power</th><?php endif; ?>
          <th>Remove</th></tr>
        </thead>
        <tbody>
        <?php $i = 0; foreach ($rows as $r): ?>
          <tr>
            <td class="dim"><?= h((string) ($r[0] ?? '')) ?>
                <input type="hidden" name="c_idx[<?= $i ?>]" value="<?= h((string) ($r[0] ?? '')) ?>"></td>
            <td><input type="text" name="c_freq[<?= $i ?>]" value="<?= h((string) ($r[1] ?? '')) ?>"></td>
            <td><input type="text" name="c_label[<?= $i ?>]" value="<?= h((string) ($r[2] ?? '')) ?>"></td>
            <?php if ($which === 'lrChannels'): ?>
              <td><input type="number" name="c_power[<?= $i ?>]" value="<?= h((string) ($r[3] ?? '')) ?>"></td>
            <?php endif; ?>
            <td><input type="checkbox" name="c_remove[]" value="<?= $i ?>"></td>
          </tr>
        <?php $i++; endforeach; ?>
        <?php for ($n = 0; $n < 3; $n++): $r = $i + $n; ?>
          <tr>
            <td class="dim">new
                <input type="hidden" name="c_idx[<?= $r ?>]" value="<?= $i + $n + 1 ?>"></td>
            <td><input type="text" name="c_freq[<?= $r ?>]"></td>
            <td><input type="text" name="c_label[<?= $r ?>]" placeholder="new channel"></td>
            <?php if ($which === 'lrChannels'): ?><td><input type="number" name="c_power[<?= $r ?>]"></td><?php endif; ?>
            <td></td>
          </tr>
        <?php endfor; ?>
        </tbody>
      </table>
      <div class="actions"><button type="submit">Save <?= h(strtolower($meta[0])) ?> channels</button></div>
    </form>
  <?php endforeach; ?>

  <h2>ACRE radios and power</h2>
  <form method="post" class="fields">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="v" value="<?= h($variant) ?>">
    <input type="hidden" name="s" value="radio">
    <input type="hidden" name="r" value="acre">
    <input type="hidden" name="what" value="acre">

    <label for="acreActiveRadio">Radio in hand on spawn</label>
    <input type="text" id="acreActiveRadio" name="acreActiveRadio"
           value="<?= h((string) ($radio['acreActiveRadio'] ?? '')) ?>">

    <div class="fieldbox">
      <label class="inlinelabel">SR power
        <input type="number" name="srPower" value="<?= h((string) ($radio['srPower'] ?? '')) ?>"></label>
      <label class="inlinelabel">MR power
        <input type="number" name="mrPower" value="<?= h((string) ($radio['mrPower'] ?? '')) ?>"></label>
      <label class="inlinelabel">LR power
        <input type="number" name="lrPower" value="<?= h((string) ($radio['lrPower'] ?? '')) ?>"></label>
    </div>
    <div class="fieldbox">
      <label class="inlinelabel">SR fallback ch
        <input type="number" name="srFallback" value="<?= h((string) ($radio['srFallback'] ?? '')) ?>"></label>
      <label class="inlinelabel">MR default ch
        <input type="number" name="mrDefault" value="<?= h((string) ($radio['mrDefault'] ?? '')) ?>"></label>
      <label class="inlinelabel">LR default ch
        <input type="number" name="lrDefault" value="<?= h((string) ($radio['lrDefault'] ?? '')) ?>"></label>
      <label class="inlinelabel">LR sat ch
        <input type="number" name="lrSatChannel" value="<?= h((string) ($radio['lrSatChannel'] ?? '')) ?>"></label>
      <label class="inlinelabel">LR local ch
        <input type="number" name="lrLocalChannel" value="<?= h((string) ($radio['lrLocalChannel'] ?? '')) ?>"></label>
    </div>

    <label for="srRadios">Short range radios <span class="dim">one classname per line</span></label>
    <textarea id="srRadios" name="srRadios" rows="2" class="short"><?= h(implode("\n", (array) ($radio['srRadios'] ?? []))) ?></textarea>
    <label for="mrRadios">Medium range radios</label>
    <textarea id="mrRadios" name="mrRadios" rows="2" class="short"><?= h(implode("\n", (array) ($radio['mrRadios'] ?? []))) ?></textarea>
    <label for="lrRadios">Long range radios</label>
    <textarea id="lrRadios" name="lrRadios" rows="2" class="short"><?= h(implode("\n", (array) ($radio['lrRadios'] ?? []))) ?></textarea>
    <label for="acreNoProgram">Never programmed <span class="dim">radios left exactly as issued</span></label>
    <textarea id="acreNoProgram" name="acreNoProgram" rows="2" class="short"><?= h(implode("\n", (array) ($radio['acreNoProgram'] ?? []))) ?></textarea>

    <div class="actions"><button type="submit">Save ACRE settings</button></div>
  </form>

<?php else: ?>

  <?php
    $sw = array_values((array) ($radio['tfarSrFreqs'] ?? []));
    $lr = array_values((array) ($radio['tfarLrFreqs'] ?? []));
    $slots = max(GHOSTD_TFAR_SLOTS, count($sw), count($lr));
  ?>
  <p class="note"><strong>TFAR is not ACRE.</strong> Two bands - short and long
  - and a <strong>fixed</strong> number of channels per radio, so these are
  numbered slots rather than a list you add to. Fill the slots you use and
  leave the rest empty; a squad's short and long nets on the
  <a href="?page=orbat&amp;s=squads<?= $vq ?>">Squads</a> tab are channel
  <em>numbers</em> pointing at these slots.</p>

  <form method="post">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="v" value="<?= h($variant) ?>">
    <input type="hidden" name="s" value="radio">
    <input type="hidden" name="r" value="tfar">
    <input type="hidden" name="what" value="tfar">

    <h2>Channels <span class="dim"><?= $slots ?> slots</span></h2>
    <table class="grid">
      <thead><tr><th>Channel</th><th>Short range frequency</th><th>Long range frequency</th></tr></thead>
      <tbody>
      <?php for ($i = 0; $i < $slots; $i++): ?>
        <tr>
          <td><strong><?= $i + 1 ?></strong></td>
          <td><input type="text" name="tfarSrFreqs[<?= $i ?>]" value="<?= h((string) ($sw[$i] ?? '')) ?>"></td>
          <td><input type="text" name="tfarLrFreqs[<?= $i ?>]" value="<?= h((string) ($lr[$i] ?? '')) ?>"></td>
        </tr>
      <?php endfor; ?>
      </tbody>
    </table>

    <h2>Radio and fallbacks</h2>
    <label for="tfarActiveRadio">Radio in hand on spawn</label>
    <input type="text" id="tfarActiveRadio" name="tfarActiveRadio"
           value="<?= h((string) ($radio['tfarActiveRadio'] ?? '')) ?>">

    <div class="fieldbox">
      <label class="inlinelabel">Short range fallback channel
        <input type="number" name="tfarSwFallback" min="0" max="<?= $slots ?>"
               value="<?= h((string) ($radio['tfarSwFallback'] ?? '')) ?>"></label>
      <label class="inlinelabel">Long range fallback channel
        <input type="number" name="tfarLrFallback" min="0" max="<?= $slots ?>"
               value="<?= h((string) ($radio['tfarLrFallback'] ?? '')) ?>"></label>
    </div>
    <p class="dim">Where somebody outside the ORBAT lands. 0 means leave the
    radio alone.</p>

    <div class="actions"><button type="submit">Save TFAR settings</button></div>
  </form>

<?php endif; ?>

<p class="note">Which squad sits on which channel is on the
<a href="?page=orbat&amp;s=squads<?= $vq ?>">Squads</a> tab - so adding a net
here does not mean touching every squad.</p>
