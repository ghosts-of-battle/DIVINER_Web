<?php
/**
 * A config that is SQF - logistics, pylons, the AI skill block.
 *
 * Included by configedit.php when the template's shape is 'code'. $t, $key,
 * $variant and $variants are already set there.
 *
 * THIS IS THE SHAPE WITH TEETH. Everywhere else a bad save is a wrong value;
 * here it is code the game will run, with no hemtt check between this box and
 * the server. So: brackets are counted before anything is written, the mod
 * compiles defensively and falls back to the mission's own file if the text
 * will not compile, and the page says both of those things out loud.
 */

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        ghostd_template_code_save($key, (string) ($_POST['code'] ?? ''), $variant);
        $msg = 'Saved. It is compiled at the next mission start - if it will not compile, the mission falls back to its own file and says so in the .rpt.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$code = ghostd_template_code($key, $variant);

ghostd_head($t['label'], 'config');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }

require __DIR__ . '/_versions.php';
?>
<p class="note readonly"><strong>This is code.</strong> A mistake here is a
runtime error in game, not a build error - nothing between this box and the
server checks it. Brackets are counted when you save; everything else is on
you. The mission's own <code><?= h($t['replaces']) ?></code> stays as the
fallback until you delete it.</p>

<p class="dim">Sets <code><?= h(ghostd_config()['unit']) ?></code>'s
<code>ghostFR_<?= h($t['global'] ?? '') ?></code>.
<?php if (!empty($t['wrap'])): ?>
  Compiled with <code><?= h($t['wrap']) ?></code> in front, so
  <code>_unit</code> is set.
<?php endif; ?>
</p>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="t" value="<?= h($key) ?>">
  <input type="hidden" name="v" value="<?= h($variant) ?>">
  <textarea name="code" rows="28" spellcheck="false"
            class="codebox"><?= h($code) ?></textarea>
  <div class="actions"><button type="submit">Save <?= h(strtolower($t['label'])) ?></button></div>
</form>
<?php
ghostd_foot();
