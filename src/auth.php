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
function ghostd_login_steam(string $steamid, ?string $name, bool $isAdmin): void
{
    ghostd_session_start();
    session_regenerate_id(true);
    $_SESSION['ghostd_auth']    = true;
    $_SESSION['ghostd_steamid'] = $steamid;
    $_SESSION['ghostd_name']    = $name;
    // Settled at sign-in rather than re-read per request: the admin list is a
    // database round trip, and a change to it takes effect at their next
    // sign-in. Removing somebody urgently means removing them AND telling them
    // to sign out - or restarting php-fpm, which drops every session.
    $_SESSION['ghostd_admin']   = $isAdmin;
}

/** The Steam id of whoever is signed in, or '' for a password session. */
function ghostd_self_uid(): string
{
    ghostd_session_start();
    return (string) ($_SESSION['ghostd_steamid'] ?? '');
}

/** An admin: the whole site, and writes to anything. */
function ghostd_is_admin(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    return !empty($_SESSION['ghostd_auth']) && !empty($_SESSION['ghostd_admin']);
}

/**
 * Signed in as a person, admin or not - so they may edit their OWN details.
 *
 * A password session is excluded on purpose: it proves somebody knows a shared
 * secret, so there is no "own" record for it to edit.
 */
function ghostd_is_member(): bool
{
    return ghostd_self_uid() !== '';
}

/**
 * May this session change anything?
 *
 * ONLY A STEAM SESSION, AND ONLY AN ADMIN. The shared password proves
 * somebody knows a secret;
 * it does not say who they are, and a document written by "whoever had the
 * password" cannot be traced to a person. A Steam sign-in has been checked
 * against the unit's own admin list, so a write can be attributed - which is
 * the same standard the game holds admins to.
 *
 * The password therefore signs in to a READ-ONLY site: useful for looking
 * something up, and for reaching the site at all when the database is down and
 * the admin list cannot be read.
 */
function ghostd_can_edit(): bool
{
    return ghostd_is_admin();
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

/** True when a secret is configured, so the form must ask for one. */
function ghostd_secret_required(): bool
{
    return ghostd_config()['admin_secret_hash'] !== '';
}

/**
 * The shared credential.
 *
 * TWO SECRETS, NOT A NAME AND A PASSWORD. A username is usually public - it
 * is on Discord, in the credits, on the website - so it adds nothing an
 * attacker has to guess. Two independent secrets, each hashed, means guessing
 * has to succeed twice.
 */
function ghostd_login(string $secret, string $password): bool
{
    ghostd_session_start();
    $cfg  = ghostd_config();
    $hash = $cfg['password_hash'];
    $shash = $cfg['admin_secret_hash'];

    // BOTH ARE ALWAYS VERIFIED. Returning as soon as the secret is wrong would
    // make a wrong secret faster to reject than a wrong password, and that
    // difference alone tells somebody they have found the secret.
    $secretOk = $shash === '' ? true : password_verify($secret, $shash);
    $passOk   = $hash !== '' && password_verify($password, $hash);

    if (!$secretOk || !$passOk) {
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
