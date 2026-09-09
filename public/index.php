<?php
/**
 * Front controller. Point the web server's root at this folder; everything
 * else lives one level up in src/ where the server cannot serve it directly.
 *
 * WHO SEES WHAT. Signing in and being allowed something are separate (see
 * src/steam.php). This file is where the second question is answered:
 *
 *     admin    every page
 *     member   their own details, and nothing else
 *     visitor  the application form, and nothing else
 *     password the admin pages, read-only (src/db.php enforces the "read")
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/render.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/branding.php';

$page = (string) ($_GET['page'] ?? 'dashboard');

// ---- the unit's logo and login background --------------------------------
// Before the login gate on purpose: the login page needs both, and neither is
// a secret - they are the pictures every visitor is meant to see.
if ($page === 'asset') {
    $asset = ghostd_brand_asset((string) ($_GET['which'] ?? ''));
    if ($asset === null) {
        http_response_code(404);
        exit;
    }
    $bytes = base64_decode($asset['data'], true);
    if ($bytes === false) {
        http_response_code(404);
        exit;
    }
    $etag = '"' . md5($bytes) . '"';
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }
    header('Content-Type: ' . $asset['mime']);
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: public, max-age=31536000, immutable');
    header('ETag: ' . $etag);
    echo $bytes;
    exit;
}

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

    // Everyone with a Steam account may sign in; what they get is decided by
    // whether they are an admin, and whether they are on the roster.
    [$isAdmin] = ghostd_steam_is_admin($steamid);
    ghostd_login_steam(
        $steamid,
        ghostd_steam_pac_name($steamid) ?? ghostd_steam_name($steamid),
        $isAdmin
    );
    header('Location: ' . ($isAdmin ? '?page=dashboard' : '?page=me'));
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

// ---- what this session may open ------------------------------------------
$adminPages  = ['dashboard', 'roster', 'player', 'templates', 'template_edit', 'documents', 'document',
                'branding', 'applications', 'questions', 'tickets', 'ticket', 'opords', 'opord',
                'config', 'configedit', 'orbat', 'role', 'squad', 'platoon', 'arsenal', 'schemes',
                'opord_section'];
$memberPages = ['me', 'apply', 'tickets', 'ticket'];

if (ghostd_is_admin() || !ghostd_is_member()) {
    // Admins get everything; a password session gets the admin pages read-only.
    $allowed = array_merge($adminPages, ['me', 'apply']);
    $fallback = 'dashboard';
} else {
    $allowed = $memberPages;
    $fallback = 'me';
}

if (!in_array($page, $allowed, true)) {
    $page = $fallback;
}

try {
    require __DIR__ . '/../src/pages/' . $page . '.php';
} catch (Throwable $e) {
    // Half a page may already be on the wire. Finish that one rather than
    // opening a second document inside it.
    if (!ghostd_head_sent()) {
        ghostd_head('Error', 'error');
    }
    ghostd_flash('bad', $e->getMessage());
    echo '<p class="dim">If this is a connection error: check the Atlas Network Access allowlist '
       . 'includes this web server\'s public IP, and that the connection string is right.</p>';
    ghostd_foot();
}
