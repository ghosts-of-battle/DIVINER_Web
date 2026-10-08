<?php
/**
 * A config that is named lists of classnames - the arsenal, the radar network.
 *
 * Included by configedit.php when the template's shape is 'lists'. $t and $key
 * are already set there.
 *
 * PASTE FROM A CONFIG FILE AND IT WORKS. These lists come out of a .hpp, where
 * every line reads  "classname",  - so the quotes, the commas and any trailing
 * semicolon are stripped on save. A classname contains none of those, so
 * nothing real is lost, and nobody has to hand-edit two hundred lines.
 *
 * ONE PER LINE, AND A PICKER WHEN THERE IS ONE. If the game has exported its
 * classnames (<unit>.classes) the boxes get a datalist, so a name is chosen
 * rather than typed. Without the export they are plain textareas - a unit that
 * has never run it can still work, it just gets no autocomplete.
 */

declare(strict_types=1);

$known = ghostd_classnames($t['classKind'] ?? 'all');

// WHICH VERSION. A type may hold more than one - the common arsenal plus one
// per element. '' is the common document; anything else is a variant.
// Every template type has versions now.
$variant  = trim((string) ($_GET['v'] ?? ($_POST['v'] ?? '')));
$variants = ghostd_template_variants($key);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $lists = [];

        foreach ((array) ($_POST['list'] ?? []) as $name => $raw) {
            $lists[(string) $name] = preg_split('/\r?\n/', (string) $raw) ?: [];
        }

        // Lists the unit invented, name and contents side by side.
        $newNames = (array) ($_POST['newname'] ?? []);
        $newVals  = (array) ($_POST['newlist'] ?? []);
        foreach ($newNames as $i => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $lists[$name] = preg_split('/\r?\n/', (string) ($newVals[$i] ?? '')) ?: [];
        }

        ghostd_variant_save($key, $variant, $lists);
        $total = array_sum(array_map(static fn($l) => count(array_filter(array_map('trim', $l))), $lists));
        $msg = $total . ' entries saved across ' . count(array_filter($lists)) . ' lists. Read at the next mission start.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$lists = ghostd_template_lists($key, $variant);

ghostd_head($t['label'], 'config');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<?php require __DIR__ . '/_versions.php'; ?>

<p class="note"><strong>One classname per line. Quotes and commas are not
needed</strong> - and are not a problem either. Paste a block straight out of a
<code>.hpp</code>, where every line reads <code>"classname",</code>, and the
quotes, commas and any trailing semicolon are stripped when you save. A
classname contains none of those characters, so nothing real is lost and nobody
has to hand-edit two hundred lines.</p>

<?php if ($known === []): ?>
  <p class="note readonly"><strong>No classname list yet.</strong> These are
  plain text boxes until the game exports what it has loaded - in the admin
  console, <strong>EXPORT CLASSES</strong>. Until then a typo here is only found
  in game.</p>
<?php else: ?>
  <p class="dim"><?= count($known) ?> classnames known from the game's export -
  start typing and the box will suggest them.</p>
  <datalist id="classlist">
    <?php foreach (array_slice($known, 0, 3000) as $c): ?>
      <option value="<?= h($c) ?>"></option>
    <?php endforeach; ?>
  </datalist>
<?php endif; ?>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="t" value="<?= h($key) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">

  <?php foreach ($lists as $name => $vals): ?>
    <fieldset class="line">
      <legend>
        <?= h($name) ?>
        <span class="key"><?= count($vals) ?></span>
        <?php if (str_starts_with((string) $name, 'items')): ?>
          <span class="key">merged as items</span>
        <?php endif; ?>
      </legend>
      <textarea name="list[<?= h((string) $name) ?>]" rows="<?= max(3, min(14, count($vals) + 2)) ?>"
                class="short" spellcheck="false"
                placeholder="one classname per line"><?= h(implode("\n", $vals)) ?></textarea>
    </fieldset>
  <?php endforeach; ?>

  <?php if (!empty($t['openNames'])): ?>
    <fieldset class="line">
      <legend>Add a list</legend>
      <p class="dim">The mod merges every list in this document. A name
      beginning <code>items</code> is merged as items; anything else is merged
      by its own kind, so the name has to match what it holds.</p>
      <?php for ($i = 0; $i < 2; $i++): ?>
        <div class="fieldbox">
          <input type="text" name="newname[<?= $i ?>]" placeholder="list name, e.g. items_explosives">
          <textarea name="newlist[<?= $i ?>]" rows="3" class="short"
                    placeholder="one classname per line"></textarea>
        </div>
      <?php endfor; ?>
    </fieldset>
  <?php endif; ?>

  <div class="actions"><button type="submit">Save <?= h(strtolower($t['label'])) ?></button></div>
</form>
<?php
ghostd_foot();
