<?php
/**
 * The way in.
 *
 * STEAM IS THE DOOR; THE PASSWORD IS THE SPARE KEY UNDER THE MAT. Steam says
 * who somebody is, which is what the roster is keyed on and what an audit
 * trail needs. The shared password says only that somebody knows a secret, so
 * it is offered behind an "Admin" button rather than as an equal choice - and
 * a unit that does not want it at all can switch it off from the branding page
 * (hidePassword), after which it is not offered here at all.
 */

declare(strict_types=1);

require_once __DIR__ . '/../steam.php';
require_once __DIR__ . '/../branding.php';

$error = '';

// The password form only exists if there IS a password, and only if the unit
// has not hidden it. Both are checked again before anything is verified.
$passwordOffered = ghostd_password_enabled() && !ghostd_password_hidden();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $passwordOffered) {
    // The token is issued on the GET that drew this form.
    ghostd_csrf_check();
    if (ghostd_login((string) ($_POST['secret'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        header('Location: ?page=dashboard');
        exit;
    }
    // Slow a guesser down without holding a worker for long.
    usleep(400000);
    // Which half was wrong is not said: that is a hint worth withholding.
    $error = ghostd_secret_required() ? 'Wrong secret or password.' : 'Wrong password.';
}

if (ghostd_logged_in()) {
    header('Location: ?page=dashboard');
    exit;
}

if (($_GET['error'] ?? '') === 'steam') {
    $error = 'Steam did not confirm that sign-in. Try again.';
}

$brand   = ghostd_branding();
$tagline = trim((string) $brand['tagline']);

ghostd_head('Sign in');
?>
<div class="gate">
  <div class="gatemark">
    <?php if (ghostd_brand_asset('logo') !== null): ?>
      <img class="gatelogo" src="<?= ghostd_asset_url('logo') ?>" alt="<?= h(ghostd_unit_name()) ?>">
    <?php endif; ?>
    <h2 class="gatename"><?= h(ghostd_unit_name()) ?></h2>
    <p class="gatetag"><?= $tagline !== '' ? h($tagline) : 'Personnel and operations' ?></p>
  </div>

  <?php if ($error !== ''): ?>
    <p class="flash bad"><?= h($error) ?></p>
  <?php endif; ?>

  <?php if (ghostd_steam_enabled()): ?>
    <a class="steam" href="?page=steam">
      <svg class="steammark" viewBox="0 0 32 32" width="20" height="20" aria-hidden="true" focusable="false">
        <path fill="currentColor" d="M16 1C8.1 1 1.6 7 1 14.7l8.1 3.3a4.5 4.5 0 0 1 2.5-.8h.2l3.6-5.2v-.1a6 6 0 1 1 6 6h-.2l-5.1 3.7v.2a4.5 4.5 0 0 1-8.9.9L1.4 20A15 15 0 1 0 16 1z"/>
        <path fill="currentColor" d="M10.4 23.7l-1.9-.8a3.4 3.4 0 1 0 1.8-4.4l1.9.8a2.5 2.5 0 1 1-1.8 4.4zM25.4 12a4 4 0 1 0-8 0 4 4 0 0 0 8 0zm-7 0a3 3 0 1 1 6 0 3 3 0 0 1-6 0z"/>
      </svg>
      <span>Sign in through Steam</span>
    </a>
    <p class="gatehelp">Use the Steam account you play on. Members can keep
    their own details up to date; admins get the whole manager.</p>

    <a class="applybtn" href="?page=steam">Apply to join</a>
    <p class="gatehelp">Applying signs you in through Steam first, so your
    answers arrive with the id the game knows you by - nothing to retype if you
    are accepted.</p>

    <p class="gatewhy"><strong>Why Steam?</strong> Arma knows you by your Steam
    id - Bohemia provides no sign-in of its own - so it is the only name that
    matches the roster the game writes. You type your password to Steam, never
    to this site, and no password of yours is stored on this server.</p>
  <?php else: ?>
    <p class="gatehelp">Steam sign-in is switched off for this site.</p>
  <?php endif; ?>

  <?php if ($passwordOffered): ?>
    <div class="gateadmin">
      <button type="button" id="adminToggle" class="linkish" aria-expanded="false"
              aria-controls="adminForm">Admin login</button>
      <form method="post" id="adminForm" class="adminform" hidden>
        <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
        <?php if (ghostd_secret_required()): ?>
          <label for="secret">Secret</label>
          <input type="password" id="secret" name="secret" autocomplete="off" required>
        <?php endif; ?>
        <label for="password">Password <span class="dim">read-only access</span></label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <button type="submit">Log in</button>
      </form>
    </div>
  <?php endif; ?>
</div>
<script>
(function () {
  var t = document.getElementById('adminToggle');
  var f = document.getElementById('adminForm');
  if (!t || !f) { return; }
  t.addEventListener('click', function () {
    var open = f.hidden;
    f.hidden = !open;
    t.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) { var i = f.querySelector('input'); if (i) { i.focus(); } }
  });
})();
</script>
<?php
ghostd_foot();
