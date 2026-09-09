<?php
declare(strict_types=1);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ghostd_password_enabled()) {
    // The token is issued on the GET that drew this form.
    ghostd_csrf_check();
    if (ghostd_login((string) ($_POST['password'] ?? ''))) {
        header('Location: ?page=dashboard');
        exit;
    }
    // Slow a guesser down without holding a worker for long.
    usleep(400000);
    $error = 'Wrong password.';
}

if (ghostd_logged_in()) {
    header('Location: ?page=dashboard');
    exit;
}

if (($_GET['error'] ?? '') === 'steam') {
    $error = 'Steam did not confirm that sign-in. Try again.';
}

ghostd_head('Log in');
if ($error !== '') {
    ghostd_flash('bad', $error);
}

require_once __DIR__ . '/../steam.php';
if (ghostd_steam_enabled()):
?>
<div class="card narrow">
  <p>Sign in with the Steam account that plays on this server. Access is the
     unit's own admin list - the same Steam ids as the in-game console.</p>
  <a class="steam" href="?page=steam">Sign in through Steam</a>
</div>
<?php endif; ?>
<?php if (ghostd_steam_enabled() && ghostd_password_enabled()): ?>
<p class="dim narrow">or</p>
<?php endif; ?>
<?php if (ghostd_password_enabled()): ?>
<form method="post" class="card narrow">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <label for="password">Password</label>
  <input type="password" id="password" name="password" autocomplete="current-password" autofocus required>
  <button type="submit">Log in</button>
</form>
<?php endif; ?>
<?php
ghostd_foot();
