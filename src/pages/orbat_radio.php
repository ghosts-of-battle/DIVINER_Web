<?php
/**
 * The radio plan, in two sub-tabs: ACRE and TFAR.
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

// How many channels a TFAR radio actually has. Kept generous enough for the
// long-range sets and honest about being fixed.
const GHOSTD_TFAR_SLOTS = 8;

$sub = (string) ($_GET['r'] ?? ($_POST['r'] ?? 'acre'));
if (!in_array($sub, ['acre', 'tfar'], true)) {
    $sub = 'acre';
}

$vq = $variant !== '' ? '&amp;v=' . urlencode($variant) : '';
?>
<nav class="sections subtabs">
  <a href="?page=orbat&amp;s=radio&amp;r=acre<?= $vq ?>" class="<?= $sub === 'acre' ? 'on' : '' ?>">ACRE</a>
  <a href="?page=orbat&amp;s=radio&amp;r=tfar<?= $vq ?>" class="<?= $sub === 'tfar' ? 'on' : '' ?>">TFAR</a>
</nav>

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
            <td><input type="number" name="c_idx[<?= $i ?>]" value="<?= h((string) ($r[0] ?? '')) ?>" style="min-width:4.5rem"></td>
            <td><input type="text" name="c_freq[<?= $i ?>]" value="<?= h((string) ($r[1] ?? '')) ?>" style="min-width:6rem"></td>
            <td><input type="text" name="c_label[<?= $i ?>]" value="<?= h((string) ($r[2] ?? '')) ?>"></td>
            <?php if ($which === 'lrChannels'): ?>
              <td><input type="number" name="c_power[<?= $i ?>]" value="<?= h((string) ($r[3] ?? '')) ?>" style="min-width:6rem"></td>
            <?php endif; ?>
            <td><input type="checkbox" name="c_remove[]" value="<?= $i ?>"></td>
          </tr>
        <?php $i++; endforeach; ?>
        <?php for ($n = 0; $n < 3; $n++): $r = $i + $n; ?>
          <tr>
            <td><input type="number" name="c_idx[<?= $r ?>]" value="<?= $i + $n + 1 ?>" style="min-width:4.5rem"></td>
            <td><input type="text" name="c_freq[<?= $r ?>]" style="min-width:6rem"></td>
            <td><input type="text" name="c_label[<?= $r ?>]" placeholder="new channel"></td>
            <?php if ($which === 'lrChannels'): ?><td><input type="number" name="c_power[<?= $r ?>]" style="min-width:6rem"></td><?php endif; ?>
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
        <input type="number" name="srPower" value="<?= h((string) ($radio['srPower'] ?? '')) ?>" style="min-width:6rem"></label>
      <label class="inlinelabel">MR power
        <input type="number" name="mrPower" value="<?= h((string) ($radio['mrPower'] ?? '')) ?>" style="min-width:6rem"></label>
      <label class="inlinelabel">LR power
        <input type="number" name="lrPower" value="<?= h((string) ($radio['lrPower'] ?? '')) ?>" style="min-width:6rem"></label>
    </div>
    <div class="fieldbox">
      <label class="inlinelabel">SR fallback ch
        <input type="number" name="srFallback" value="<?= h((string) ($radio['srFallback'] ?? '')) ?>" style="min-width:5rem"></label>
      <label class="inlinelabel">MR default ch
        <input type="number" name="mrDefault" value="<?= h((string) ($radio['mrDefault'] ?? '')) ?>" style="min-width:5rem"></label>
      <label class="inlinelabel">LR default ch
        <input type="number" name="lrDefault" value="<?= h((string) ($radio['lrDefault'] ?? '')) ?>" style="min-width:5rem"></label>
      <label class="inlinelabel">LR sat ch
        <input type="number" name="lrSatChannel" value="<?= h((string) ($radio['lrSatChannel'] ?? '')) ?>" style="min-width:5rem"></label>
      <label class="inlinelabel">LR local ch
        <input type="number" name="lrLocalChannel" value="<?= h((string) ($radio['lrLocalChannel'] ?? '')) ?>" style="min-width:5rem"></label>
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
          <td><input type="text" name="tfarSrFreqs[<?= $i ?>]" value="<?= h((string) ($sw[$i] ?? '')) ?>" style="min-width:8rem"></td>
          <td><input type="text" name="tfarLrFreqs[<?= $i ?>]" value="<?= h((string) ($lr[$i] ?? '')) ?>" style="min-width:8rem"></td>
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
               value="<?= h((string) ($radio['tfarSwFallback'] ?? '')) ?>" style="min-width:5rem"></label>
      <label class="inlinelabel">Long range fallback channel
        <input type="number" name="tfarLrFallback" min="0" max="<?= $slots ?>"
               value="<?= h((string) ($radio['tfarLrFallback'] ?? '')) ?>" style="min-width:5rem"></label>
    </div>
    <p class="dim">Where somebody outside the ORBAT lands. 0 means leave the
    radio alone.</p>

    <div class="actions"><button type="submit">Save TFAR settings</button></div>
  </form>

<?php endif; ?>

<p class="note">Which squad sits on which channel is on the
<a href="?page=orbat&amp;s=squads<?= $vq ?>">Squads</a> tab - so adding a net
here does not mean touching every squad.</p>
