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

// A deployment with neither gate configured is not open to the world.
if (!ghostd_configured()) {
    ghostd_head('Not configured');
    ghostd_flash('bad', 'Neither a password nor Steam sign-in is set up, so the site is closed.');
    echo '<p>Set <code>GHOSTD_WEB_PASSWORD_HASH</code> in the web server environment, '
       . 'or copy <code>src/config.local.example.php</code> to <code>src/config.local.php</code>. '
       . 'Generate the hash with:</p>'
       . '<pre>php -r "echo password_hash(\'your password\', PASSWORD_DEFAULT), PHP_EOL;"</pre>'
       . '<p>Or turn on Steam sign-in - <code>GHOSTD_STEAM=1</code>, or '
       . '<code>steam_login</code> in the file - which admits the Steam ids '
       . 'already in the <code>admins</code> document for this unit.</p>';
    ghostd_foot();
    exit;
}

if ($page === 'steam') {
    require_once __DIR__ . '/../src/steam.php';

    if (!ghostd_steam_enabled()) {
        header('Location: ?page=login');
        exit;
    }

    if (($_GET['action'] ?? '') !== 'return') {
        ghostd_steam_redirect();      // never returns
    }

    $steamid = ghostd_steam_validate($_GET);
    if ($steamid === null) {
        // Cancelled at Steam, a stale nonce, or an assertion that did not
        // verify. All three are the same to a user: try again.
        header('Location: ?page=login&error=steam');
        exit;
    }

    [$allowed, $why] = ghostd_steam_allowed($steamid);
    if (!$allowed) {
        ghostd_head('Not on the list');
        ghostd_flash('bad', 'Steam id ' . h($steamid) . ' may not use this site - ' . h($why) . '.');
        echo '<p>Steam confirmed who you are; the unit has not said you may in. '
           . 'Add this id in game - admin console, <strong>STRUCTURE > ADMINS > ADD ME</strong> - '
           . 'or name it in the site configuration.</p>'
           . '<p><a href="?page=login">Back to the login page</a></p>';
        ghostd_foot();
        exit;
    }

    ghostd_login_steam($steamid, ghostd_steam_pac_name($steamid) ?? ghostd_steam_name($steamid));
    header('Location: ?page=dashboard');
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
