<?php
/**
 * The radio plan: what ACRE and TFAR are set up with.
 *
 * FREQUENCIES HERE, SQUAD CHANNELS ON THE SQUADS TAB. This page decides what
 * channels exist; the squads tab decides which squad sits on which. Splitting
 * them that way means adding a net does not mean touching every squad.
 *
 * ONE BLOCK PER SAVE. The channel lists are long and the settings are short -
 * mixing them into one form makes a typo in a frequency cost the lot.
 */

declare(strict_types=1);

/** A channel list as rows of [index, frequency, label(, power)]. */
$chanRows = static function (string $which) use ($radio): array {
    $out = [];
    foreach ((array) ($radio[$which] ?? []) as $r) {
        if (is_array($r)) { $out[] = $r; }
    }
    return $out;
};

$blocks = [
    'srChannels' => ['Short range', 'The squad net channels - one row per channel.'],
    'mrChannels' => ['Medium range', 'Platoon and company nets.'],
    'lrChannels' => ['Long range', 'Detachment and fires nets. The fourth column is power for that net.'],
];
?>
<h2>Channels</h2>
<p class="dim">Index is what the radio calls the channel; frequency is what it
transmits on; the label is what a player reads. Clearing both frequency and
label removes a row.</p>

<?php foreach ($blocks as $which => $meta): ?>
  <?php $rows = $chanRows($which); ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="v" value="<?= h($variant) ?>">
    <input type="hidden" name="s" value="radio">
    <input type="hidden" name="what" value="channels">
    <input type="hidden" name="which" value="<?= h($which) ?>">

    <h3><?= h($meta[0]) ?> <span class="dim"><?= count($rows) ?> channels &middot; <?= h($meta[1]) ?></span></h3>
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

<h2>ACRE</h2>
<form method="post" class="fields">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="s" value="radio">
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
  <label for="acreNoProgram">Never programmed <span class="dim">radios left alone</span></label>
  <textarea id="acreNoProgram" name="acreNoProgram" rows="2" class="short"><?= h(implode("\n", (array) ($radio['acreNoProgram'] ?? []))) ?></textarea>

  <div class="actions"><button type="submit">Save ACRE settings</button></div>
</form>

<h2>TFAR</h2>
<form method="post" class="fields">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <input type="hidden" name="s" value="radio">
  <input type="hidden" name="what" value="tfar">

  <label for="tfarActiveRadio">Radio in hand on spawn</label>
  <input type="text" id="tfarActiveRadio" name="tfarActiveRadio"
         value="<?= h((string) ($radio['tfarActiveRadio'] ?? '')) ?>">

  <div class="fieldbox">
    <label class="inlinelabel">SW fallback
      <input type="number" name="tfarSwFallback" value="<?= h((string) ($radio['tfarSwFallback'] ?? '')) ?>" style="min-width:5rem"></label>
    <label class="inlinelabel">LR fallback
      <input type="number" name="tfarLrFallback" value="<?= h((string) ($radio['tfarLrFallback'] ?? '')) ?>" style="min-width:5rem"></label>
  </div>

  <label for="tfarSrFreqs">Short range frequencies <span class="dim">one per line, in channel order</span></label>
  <textarea id="tfarSrFreqs" name="tfarSrFreqs" rows="4" class="short"><?= h(implode("\n", (array) ($radio['tfarSrFreqs'] ?? []))) ?></textarea>

  <label for="tfarLrFreqs">Long range frequencies</label>
  <textarea id="tfarLrFreqs" name="tfarLrFreqs" rows="4" class="short"><?= h(implode("\n", (array) ($radio['tfarLrFreqs'] ?? []))) ?></textarea>

  <div class="actions"><button type="submit">Save TFAR settings</button></div>
</form>

<p class="note">Which squad sits on which channel is on the
<a href="?page=orbat&amp;s=squads<?= $variant !== '' ? '&amp;v=' . urlencode($variant) : '' ?>">Squads</a>
tab - so adding a net here does not mean touching every squad.</p>
