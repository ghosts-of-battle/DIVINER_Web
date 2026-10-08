<?php
/**
 * The public home page and the branding, on one page: everything about how
 * the site looks, for the admin who is looking for exactly that.
 *
 * ONE SWITCH AND ONE BLOCK. Off, the site opens on the sign-in card as it
 * always has. On, a visitor lands on the home page first, with Sign in and
 * Apply buttons on it. Each block is a heading and a piece of HTML written in
 * the Wysi editor below (vendored, public/wysi.min.*, see wysi-site.js);
 * an empty block is not drawn. The HTML is cleaned on save - see home.php.
 *
 * Stored in <unit>.web.home, beside Branding's <unit>.web, for the same
 * reasons: written from the site, follows the database, survives a redeploy.
 */

declare(strict_types=1);

require_once __DIR__ . '/../home.php';
require_once __DIR__ . '/_branding.php';

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
        $what = (string) ($_POST['what'] ?? 'home');
        if (in_array($what, ['identity', 'upload', 'remove'], true)) {
            $msg = ghostd_branding_post($what);          // the Branding section's forms
        } else {
            $doc = ghostd_home_from_post($_POST);
            $doc['section']   = 'web';
            $doc['updatedAt'] = gmdate('Y-m-d H:i:s');
            ghostd_put($docId, $doc);
            $msg = $doc['enabled']
                ? 'Saved. Visitors now land on the home page.'
                : 'Saved. The home page is off; visitors land on the sign-in card.';
        }
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
and logo from Branding, below. <a href="?page=home&amp;preview=1">See the saved page</a>
any time, switched on or off; Preview below shows the form as it stands.</p>

<link rel="stylesheet" href="<?= h(ghostd_asset('wysi.min.css')) ?>">
<form method="post" class="card fields">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="what" value="home">

  <h2>Home page</h2>
  <label class="inlinelabel">
    <input type="checkbox" name="enabled" <?= $home['enabled'] ? 'checked' : '' ?>>
    Show the home page before sign-in
    <span class="dim">off: visitors land on the sign-in card, as before</span>
  </label>

  <h2>Layout</h2>
  <div class="layoutpick">
    <?php foreach (GHOSTD_HOME_LAYOUTS as $lk => [$llabel, $ldesc]): ?>
      <label class="layoutopt">
        <?= ghostd_home_wireframe($lk) ?>
        <span class="layoutname">
          <input type="radio" name="layout" value="<?= h($lk) ?>" <?= $home['layout'] === $lk ? 'checked' : '' ?>>
          <strong><?= h($llabel) ?></strong>
        </span>
        <span class="dim"><?= h($ldesc) ?></span>
      </label>
    <?php endforeach; ?>
  </div>

  <label>Position <span class="dim">where the card sits in the window, for any layout: across, then down</span></label>
  <div class="fieldrow posrow">
    <?php foreach (GHOSTD_HOME_ALIGNS as $ak => $alabel): ?>
      <label class="inlinelabel">
        <input type="radio" name="align" value="<?= h($ak) ?>" <?= $home['align'] === $ak ? 'checked' : '' ?>>
        <?= h($alabel) ?>
      </label>
    <?php endforeach; ?>
  </div>
  <div class="fieldrow posrow">
    <?php foreach (GHOSTD_HOME_VALIGNS as $vk => $vlabel): ?>
      <label class="inlinelabel">
        <input type="radio" name="valign" value="<?= h($vk) ?>" <?= $home['valign'] === $vk ? 'checked' : '' ?>>
        <?= h($vlabel) ?>
      </label>
    <?php endforeach; ?>
  </div>

  <label for="width">Width <span class="dim">of the window, in percent - small screens always get the full width</span></label>
  <div class="scalerow">
    <input type="range" id="width" name="width" min="<?= GHOSTD_HOME_WIDTH['min'] ?>" max="<?= GHOSTD_HOME_WIDTH['max'] ?>" step="5"
           value="<?= (int) $home['width'] ?>" oninput="this.nextElementSibling.value = this.value + '%'">
    <output><?= (int) $home['width'] ?>%</output>
  </div>

  <label for="height">Height <span class="dim">of the window, in percent - 0 is as tall as the words; taller pins the buttons to the bottom</span></label>
  <div class="scalerow">
    <input type="range" id="height" name="height" min="<?= GHOSTD_HOME_HEIGHT['min'] ?>" max="<?= GHOSTD_HOME_HEIGHT['max'] ?>" step="5"
           value="<?= (int) $home['height'] ?>" oninput="this.nextElementSibling.value = this.value == 0 ? 'fit' : this.value + '%'">
    <output><?= (int) $home['height'] === 0 ? 'fit' : (int) $home['height'] . '%' ?></output>
  </div>

  <label for="logoScale">Logo size <span class="dim">on this page only - 100% is 96px tall; the login card and the bar have their own, under Branding</span></label>
  <div class="scalerow">
    <input type="range" id="logoScale" name="logoScale" min="<?= GHOSTD_HOME_LOGO['min'] ?>" max="<?= GHOSTD_HOME_LOGO['max'] ?>" step="5"
           value="<?= (int) $home['logoScale'] ?>" oninput="this.nextElementSibling.value = this.value + '%'">
    <output><?= (int) $home['logoScale'] ?>%</output>
  </div>

  <?php foreach (GHOSTD_HOME_BLOCKS as $key => $label): ?>
    <h2><?= h($label) ?></h2>
    <p class="dim"><?= h($hints[$key]) ?></p>
    <label for="h_<?= h($key) ?>">Heading <span class="dim">blank for none</span></label>
    <input type="text" id="h_<?= h($key) ?>" name="h_<?= h($key) ?>" maxlength="80"
           value="<?= h($home['headings'][$key]) ?>">
    <label for="b_<?= h($key) ?>">Content <span class="dim">empty: the block is not shown</span></label>
    <textarea id="b_<?= h($key) ?>" name="b_<?= h($key) ?>" rows="8" data-wysi="html"><?= h($home['blocks'][$key]) ?></textarea>
  <?php endforeach; ?>

  <p class="dim">Links and pictures take an address: paste one, or a public
  file's link from Media.</p>
  <div class="actions">
    <button type="submit">Save</button>
    <button type="submit" class="btnquiet" formaction="?page=home&amp;preview=1" formmethod="post" formtarget="_blank">Preview</button>
    <span class="dim">Preview opens the page in a new tab with what is in this form, saved or not.</span>
  </div>
</form>

<?php ghostd_branding_forms(); ?>
<script src="<?= h(ghostd_asset('wysi.min.js')) ?>"></script>
<script src="<?= h(ghostd_asset('wysi-site.js')) ?>"></script>
<?php
ghostd_foot();
