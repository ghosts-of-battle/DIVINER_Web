<?php
/**
 * The public home page's words: what a visitor reads before signing in.
 *
 * ONE SWITCH AND ONE BLOCK. Off, the site opens on the sign-in card as it
 * always has. On, a visitor lands on the home page first, with Sign in and
 * Apply buttons on it. Each block is a heading and a piece of HTML written in
 * the editor below (htmleditor.js - no library, like the rest of the site);
 * an empty block is not drawn. The HTML is cleaned on save - see home.php.
 *
 * Stored in <unit>.web.home, beside Branding's <unit>.web, for the same
 * reasons: written from the site, follows the database, survives a redeploy.
 */

declare(strict_types=1);

require_once __DIR__ . '/../home.php';

$cfg   = ghostd_config();
$docId = $cfg['unit'] . '.web.home';

/** What a unit tends to put in each block - a hint under the label, no more. */
$hints = [
    'about' => 'Who you are, what you play, when you play, and how to get in touch. The Sign in and Apply buttons are on the page already.',
];

$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $doc = ['section' => 'web', 'enabled' => isset($_POST['enabled']), 'headings' => [], 'blocks' => []];
        foreach (GHOSTD_HOME_BLOCKS as $key => $label) {
            $doc['headings'][$key] = mb_substr(trim((string) ($_POST['h_' . $key] ?? '')), 0, 80);
            $html = (string) ($_POST['b_' . $key] ?? '');
            if (strlen($html) > GHOSTD_HOME_BLOCK_MAX) {
                throw new RuntimeException($label . ' is ' . round(strlen($html) / 1024) . ' KB; the limit is '
                    . round(GHOSTD_HOME_BLOCK_MAX / 1024) . ' KB. Pictures belong in Media, linked from here.');
            }
            $doc['blocks'][$key] = ghostd_html_clean($html);
        }
        $doc['updatedAt'] = gmdate('Y-m-d H:i:s');
        ghostd_put($docId, $doc);
        $msg = $doc['enabled']
            ? 'Saved. Visitors now land on the home page.'
            : 'Saved. The home page is off; visitors land on the sign-in card.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$home = ghostd_home();

ghostd_head('Web settings', 'websettings');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<p class="note">The public page a visitor sees before signing in, stored in the
<code><?= h($docId) ?></code> document. It wears the login page's background
and logo from Branding. <a href="?page=home&amp;preview=1">Preview</a> it any
time, switched on or off.</p>

<form method="post" class="card fields">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">

  <h2>Home page</h2>
  <label class="inlinelabel">
    <input type="checkbox" name="enabled" <?= $home['enabled'] ? 'checked' : '' ?>>
    Show the home page before sign-in
    <span class="dim">off: visitors land on the sign-in card, as before</span>
  </label>

  <?php foreach (GHOSTD_HOME_BLOCKS as $key => $label): ?>
    <h2><?= h($label) ?></h2>
    <p class="dim"><?= h($hints[$key]) ?></p>
    <label for="h_<?= h($key) ?>">Heading <span class="dim">blank for none</span></label>
    <input type="text" id="h_<?= h($key) ?>" name="h_<?= h($key) ?>" maxlength="80"
           value="<?= h($home['headings'][$key]) ?>">
    <label for="b_<?= h($key) ?>">Content <span class="dim">empty: the block is not shown</span></label>
    <textarea id="b_<?= h($key) ?>" name="b_<?= h($key) ?>" rows="8" data-html-editor><?= h($home['blocks'][$key]) ?></textarea>
  <?php endforeach; ?>

  <p class="dim">Links and pictures take an address: paste one, or a public
  file's link from Media.</p>
  <button type="submit">Save</button>
</form>
<script src="htmleditor.js"></script>
<?php
ghostd_foot();
