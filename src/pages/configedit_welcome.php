<?php
/**
 * The welcome screen, edited as a title and a run of lines.
 *
 * Included by configedit.php when the template's shape is 'welcome'. $t and
 * $key are already set there.
 *
 * COLOUR IS THREE FLOATS IN THE GAME and #rrggbb in a browser, so it is
 * converted on the way in and out rather than making somebody type 0.85.
 */

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $texts  = (array) ($_POST['text'] ?? []);
        $sizes  = (array) ($_POST['size'] ?? []);
        $cols   = (array) ($_POST['colour'] ?? []);
        $aligns = (array) ($_POST['align'] ?? []);
        $remove = (array) ($_POST['remove'] ?? []);

        $lines = [];
        foreach ($texts as $row => $raw) {
            $text = trim((string) $raw);
            if ($text === '' || in_array((string) $row, $remove, true)) {
                continue;
            }
            $size = (float) ($sizes[$row] ?? 0.9);
            $lines[] = [
                'text'   => $text,
                'size'   => max(0.4, min(3.0, $size)),
                'colour' => ghostd_hex_to_rgb((string) ($cols[$row] ?? '#ffffff')),
                'align'  => max(0, min(2, (int) ($aligns[$row] ?? 0))),
            ];
        }

        ghostd_welcome_save(
            trim((string) ($_POST['title'] ?? '')),
            trim((string) ($_POST['subtitle'] ?? '')),
            $lines,
            $variant
        );
        $msg = count($lines) . ' lines saved. Players see this at the next mission start.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$w = ghostd_welcome($variant);

ghostd_head($t['label'], 'config');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<?php require __DIR__ . '/_versions.php'; ?>

<form method="post" class="fields">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="t" value="<?= h($key) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">

  <label for="title">Title</label>
  <input type="text" id="title" name="title" maxlength="80" value="<?= h($w['title']) ?>"
         placeholder="FRAMEWORK">

  <label for="subtitle">Subtitle</label>
  <input type="text" id="subtitle" name="subtitle" maxlength="120" value="<?= h($w['subtitle']) ?>"
         placeholder="HERDING CATS SINCE 2034">

  <h2>Lines</h2>
  <p class="dim">In order, top to bottom. A heading is just a line with a
  bigger size and a colour. Clearing the text removes the line.</p>

  <?php
    $rows = $w['lines'];
    for ($i = 0; $i < 3; $i++) {
        $rows[] = ['text' => '', 'size' => ($i === 0 ? 1.15 : 0.9), 'colour' => [1, 1, 1], 'align' => 0];
    }
  ?>
  <?php foreach ($rows as $row => $l): ?>
    <fieldset class="line">
      <legend>
        <?= $row < count($w['lines']) ? 'Line ' . ($row + 1) : '<span class="key">new</span>' ?>
      </legend>
      <textarea name="text[<?= $row ?>]" rows="3" class="short"
                placeholder="The text of this line"><?= h((string) $l['text']) ?></textarea>
      <div class="fieldbox">
        <label class="inlinelabel">size
          <input type="number" name="size[<?= $row ?>]" step="0.05" min="0.4" max="3"
                 value="<?= h((string) $l['size']) ?>" style="min-width:5rem">
        </label>
        <label class="inlinelabel">colour
          <input type="color" name="colour[<?= $row ?>]" value="<?= h(ghostd_rgb_to_hex($l['colour'])) ?>">
        </label>
        <label class="inlinelabel">align
          <select name="align[<?= $row ?>]">
            <?php foreach (GHOSTD_WELCOME_ALIGN as $v => $lbl): ?>
              <option value="<?= (int) $v ?>" <?= (int) $l['align'] === (int) $v ? 'selected' : '' ?>><?= h($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php if ($row < count($w['lines'])): ?>
          <label class="inlinelabel">
            <input type="checkbox" name="remove[]" value="<?= $row ?>"> remove
          </label>
        <?php endif; ?>
      </div>
    </fieldset>
  <?php endforeach; ?>

  <div class="actions"><button type="submit">Save welcome screen</button></div>
</form>
<?php
ghostd_foot();
