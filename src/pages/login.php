<?php
declare(strict_types=1);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

ghostd_head('Log in');
if ($error !== '') {
    ghostd_flash('bad', $error);
}
?>
<form method="post" class="card narrow">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <label for="password">Password</label>
  <input type="password" id="password" name="password" autocomplete="current-password" autofocus required>
  <button type="submit">Log in</button>
</form>
<?php
ghostd_foot();
