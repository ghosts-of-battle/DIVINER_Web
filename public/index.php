<?php
/**
 * Front controller. Point the web server's root at this folder; everything
 * else lives one level up in src/ where the server cannot serve it directly.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/render.php';
require_once __DIR__ . '/../src/db.php';

$page = (string) ($_GET['page'] ?? 'dashboard');

// A deployment with no password is not open to the world.
if (!ghostd_configured()) {
    ghostd_head('Not configured');
    ghostd_flash('bad', 'No password is set, so the site is closed.');
    echo '<p>Set <code>GHOSTD_WEB_PASSWORD_HASH</code> in the web server environment, '
       . 'or copy <code>src/config.local.example.php</code> to <code>src/config.local.php</code>. '
       . 'Generate the hash with:</p>'
       . '<pre>php -r "echo password_hash(\'your password\', PASSWORD_DEFAULT), PHP_EOL;"</pre>';
    ghostd_foot();
    exit;
}

if ($page === 'login') {
    require __DIR__ . '/../src/pages/login.php';
    exit;
}

if ($page === 'logout') {
    ghostd_logout();
    header('Location: ?page=login');
    exit;
}

ghostd_require_login();

$pages = ['dashboard', 'roster', 'templates', 'template_edit', 'documents', 'document'];
if (!in_array($page, $pages, true)) {
    $page = 'dashboard';
}

try {
    require __DIR__ . '/../src/pages/' . $page . '.php';
} catch (Throwable $e) {
    ghostd_head('Error');
    ghostd_flash('bad', $e->getMessage());
    echo '<p class="dim">If this is a connection error: check the Atlas Network Access allowlist '
       . 'includes this web server\'s public IP, and that the connection string is right.</p>';
    ghostd_foot();
}
