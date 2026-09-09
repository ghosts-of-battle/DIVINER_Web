<?php
/**
 * The password gate and the CSRF token.
 *
 * ONE SHARED PASSWORD, NOT ACCOUNTS. This site manages one unit's roster and
 * is used by the handful of people who already administer the server. Per-user
 * accounts would mean a user store, a reset flow and a second thing to keep in
 * step with PAC's own admin list - for an audience that fits in a squad.
 *
 * NO PASSWORD SET MEANS CLOSED, NOT OPEN. An unconfigured deployment refuses
 * every request rather than serving the roster to the internet.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function ghostd_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $cfg = ghostd_config();
    session_name($cfg['session_name']);
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        // Set only when the request actually arrived over TLS, so this works
        // on a plain-HTTP LAN box during setup without silently dropping the
        // cookie and looping the login page.
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

function ghostd_configured(): bool
{
    // Either gate counts. Steam alone is a complete configuration; so is a
    // password alone. Neither means the site stays closed.
    $cfg = ghostd_config();
    return $cfg['password_hash'] !== '' || $cfg['steam_login'];
}

/** True when the password form should be drawn at all. */
function ghostd_password_enabled(): bool
{
    return ghostd_config()['password_hash'] !== '';
}

/**
 * Mark the session signed in as a verified Steam id.
 *
 * Called only from the OpenID return in public/index.php, and only after
 * ghostd_steam_validate() and ghostd_steam_allowed() have both passed.
 */
function ghostd_login_steam(string $steamid, ?string $name): void
{
    ghostd_session_start();
    session_regenerate_id(true);
    $_SESSION['ghostd_auth']    = true;
    $_SESSION['ghostd_steamid'] = $steamid;
    $_SESSION['ghostd_name']    = $name;
}

/** Who is signed in, for the header bar: kind, steamid, name - or null. */
function ghostd_identity(): ?array
{
    ghostd_session_start();
    if (empty($_SESSION['ghostd_auth'])) {
        return null;
    }
    $sid = $_SESSION['ghostd_steamid'] ?? null;
    return [
        'kind'    => $sid !== null ? 'steam' : 'password',
        'steamid' => $sid,
        'name'    => $_SESSION['ghostd_name'] ?? null,
    ];
}

function ghostd_logged_in(): bool
{
    ghostd_session_start();
    return !empty($_SESSION['ghostd_auth']);
}

function ghostd_login(string $password): bool
{
    ghostd_session_start();
    $hash = ghostd_config()['password_hash'];
    if ($hash === '' || !password_verify($password, $hash)) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['ghostd_auth'] = true;
    return true;
}

function ghostd_logout(): void
{
    ghostd_session_start();
    $_SESSION = [];
    session_destroy();
}

function ghostd_require_login(): void
{
    if (!ghostd_logged_in()) {
        header('Location: ?page=login');
        exit;
    }
}

function ghostd_csrf_token(): string
{
    ghostd_session_start();
    if (empty($_SESSION['ghostd_csrf'])) {
        $_SESSION['ghostd_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['ghostd_csrf'];
}

function ghostd_csrf_check(): void
{
    ghostd_session_start();
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || $sent === '' || empty($_SESSION['ghostd_csrf'])
        || !hash_equals($_SESSION['ghostd_csrf'], $sent)) {
        http_response_code(400);
        exit('Bad CSRF token. Go back, reload the page and try again.');
    }
}
