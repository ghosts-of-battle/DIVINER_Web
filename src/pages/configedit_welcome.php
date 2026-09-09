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
 * uses"). Returns are returns, and the tags are the game's own.
 *
 * The mod turns the returns into <br/> and hands the whole block to the panel
 * as one piece of structured text - see ghostD_pac_fnc_welcomeShow.
 */

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        // CRLF is what a browser posts and it is not what the game wants to
        // read back; the document keeps plain newlines.
        $text = str_replace(["\r\n", "\r"], "\n", (string) ($_POST['text'] ?? ''));

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
  <p class="dim">Returns are line breaks. Arma's own tags do the rest:
  <code>&lt;t size='1.15' color='#cc4331'&gt;HEADING&lt;/t&gt;</code>,
  <code>align='center'</code>, <code>&lt;br/&gt;</code>,
  <code>&lt;img image='path.paa'/&gt;</code>,
  <code>&lt;a href='https://...'&gt;link&lt;/a&gt;</code>.
  Size multiplies the body size, so 1.15 is a heading and 2 is twice the text.
  A literal <code>&lt;</code> is <code>&amp;lt;</code>.</p>
  <textarea id="text" name="text" spellcheck="false"
            placeholder="&lt;t size='1.15' color='#cc4331'&gt;SITUATION&lt;/t&gt;
The situation, in as many paragraphs as it takes."><?= h($w['text']) ?></textarea>

  <div class="actions"><button type="submit">Save welcome screen</button></div>
</form>
<?php
ghostd_foot();
