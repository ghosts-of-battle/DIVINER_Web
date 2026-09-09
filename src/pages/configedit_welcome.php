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
  <p class="dim">Returns are line breaks. Select some text and press a button;
  the tags are Arma's own.</p>

  <div class="tagbar">
    <button type="button" data-wrap="heading">Heading</button>
    <input type="color" id="tagcolor" value="#cc4331" title="the colour Heading and Colour use">
    <button type="button" data-wrap="color">Colour</button>
    <button type="button" data-wrap="big">Bigger</button>
    <button type="button" data-wrap="small">Smaller</button>
    <button type="button" data-wrap="center">Centre</button>
    <button type="button" data-wrap="right">Right</button>
    <button type="button" data-wrap="br">Line break</button>
    <button type="button" data-wrap="img">Image</button>
    <button type="button" data-wrap="link">Link</button>
  </div>

  <textarea id="text" name="text" spellcheck="false"
            placeholder="Type the briefing. Select a line and press Heading."><?= h($w['text']) ?></textarea>

  <script>
  // THE BUTTONS ARE THE EDITOR. Each one wraps what is selected in the tag it
  // names, or drops the tag in with the caret between its halves when nothing
  // is selected (user, 2026-09-09: "a simple editor will have buttons to apply
  // the html tags").
  (function () {
    var ta = document.getElementById('text');
    var col = document.getElementById('tagcolor');
    function tag(kind) {
      var c = col.value;
      switch (kind) {
        case 'heading': return ["<t size='1.15' color='" + c + "'>", '</t>'];
        case 'color':   return ["<t color='" + c + "'>", '</t>'];
        case 'big':     return ["<t size='1.4'>", '</t>'];
        case 'small':   return ["<t size='0.8'>", '</t>'];
        case 'center':  return ["<t align='center'>", '</t>'];
        case 'right':   return ["<t align='right'>", '</t>'];
        case 'br':      return ['<br/>', ''];
        case 'img':     return ["<img image='", "' />"];
        case 'link':    return ["<a href='https://'>", '</a>'];
      }
      return ['', ''];
    }
    document.querySelector('.tagbar').addEventListener('click', function (e) {
      var b = e.target.closest('[data-wrap]');
      if (!b) { return; }
      var parts = tag(b.getAttribute('data-wrap'));
      var s = ta.selectionStart, t = ta.selectionEnd;
      var mid = ta.value.slice(s, t);
      ta.value = ta.value.slice(0, s) + parts[0] + mid + parts[1] + ta.value.slice(t);
      // Leave the caret where the typing goes next: inside the tag when it
      // wrapped nothing, after it when it wrapped a selection.
      var at = mid === '' ? s + parts[0].length : s + parts[0].length + mid.length + parts[1].length;
      ta.focus();
      ta.setSelectionRange(at, at);
    });
  })();
  </script>

  <div class="actions"><button type="submit">Save welcome screen</button></div>
</form>
<?php
ghostd_foot();
