<?php
/**
 * The welcome screen - a title, a subtitle, and the text.
 *
 * Included by configedit.php when the template's shape is 'welcome'. $t and
 * $key are already set there.
 *
 * ONE BOX. This was a stack of fieldsets, one per line, each carrying a size,
 * a colour and an alignment - which is a worse spelling of the tags Arma
 * already reads (user, 2026-09-09: "instead of all this crap how about a
 * simple editor that you can insert returns and set the html stuff arma
 * uses"). Then a textarea with buttons that wrapped the selection in tags.
 * Now a real editor (Wysi, user 2026-09-11: "use for the editors both for in
 * arma html and the front page"): headings, bold, underline, alignment,
 * colour, links and pictures - the things the game can show - and the
 * document still holds Arma's own tags. armatext.php does the translation
 * both ways, so a briefing typed as tags before today opens in the editor.
 *
 * The mod turns the returns into <br/> and hands the whole block to the panel
 * as one piece of structured text - see ghostD_pac_fnc_welcomeShow.
 */

declare(strict_types=1);

require_once __DIR__ . '/../armatext.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        // The editor posts HTML; the document keeps structured text with
        // plain newlines, which is what the game reads back.
        $text = ghostd_html_to_arma((string) ($_POST['text'] ?? ''));

        ghostd_welcome_save(
            trim((string) ($_POST['title'] ?? '')),
            trim((string) ($_POST['subtitle'] ?? '')),
            rtrim($text),
            $variant
        );
        $msg = 'Saved. Players see this at the next mission start.';
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
<link rel="stylesheet" href="wysi.min.css">

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

  <label for="text">Text</label>
  <p class="dim">A paragraph is a line on the screen. Headings are the game's
  text sizes, bold is its bold font. Arma has no italic and no lists, so the
  editor has none either.</p>

  <textarea id="text" name="text" data-wysi="arma" spellcheck="false"
            placeholder="Type the briefing."><?= h(ghostd_arma_to_html($w['text'])) ?></textarea>

  <div class="tagbar" data-wysi-colour="#text">
    <input type="color" id="tagcolor" value="#cc4331" title="the colour to apply">
    <button type="button" data-do="colour">Colour</button>
    <button type="button" data-do="plain">Plain</button>
    <span class="dim">Select some text first. Plain strips its colour, bold and underline.</span>
  </div>

  <div class="actions"><button type="submit">Save welcome screen</button></div>
</form>
<script src="wysi.min.js"></script>
<script src="wysi-site.js"></script>
<?php
ghostd_foot();
