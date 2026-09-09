<?php
/**
 * Sign in through Steam.
 *
 * OPENID 2.0, NOT OAUTH. Steam has never published an OAuth or OIDC endpoint;
 * "Sign in through Steam" is OpenID 2.0 and nothing else. It is a dead
 * standard everywhere but here, which is why this is written out rather than
 * pulled from a library - the whole exchange is one redirect and one POST.
 *
 * NO LIBRARY, SAME REASON AS db.php. A Composer dependency for sixty lines of
 * query string would be the only thing on this site needing an install step.
 *
 * WHO MAY IN. The mod already keeps the list: <unit>.admins is keyed by Steam
 * id - the same digits getPlayerUID returns in game - so the people who
 * administer PAC in the mission are exactly the people who can sign in here,
 * with no second list to maintain. config.local.php can name extra ids for
 * someone who needs the site but not the in-game console.
 *
 * STEAM PROVES WHO, NOT WHETHER. The assertion is an identity, not a
 * permission - so signing in and being allowed to do something are two
 * separate questions, answered separately:
 *
 *     admin   on <unit>.admins (or steam_admins) - the whole site, and writes
 *     member  has a record in the store - their own details, nothing else
 *     visitor neither - may apply to join, and nothing else
 *
 * Anyone with a Steam account may therefore sign IN. That is deliberate: an
 * application form nobody can reach is not an application form. What they can
 * SEE is decided per tier in public/index.php, and what they can WRITE at the
 * choke point in db.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

const GHOSTD_STEAM_ENDPOINT = 'https://steamcommunity.com/openid/login';

function ghostd_steam_enabled(): bool
{
    return (bool) ghostd_config()['steam_login'];
}

/**
 * This site's own URL, as Steam must see it.
 *
 * The realm and return_to sent to Steam have to match the URL the browser
 * actually came from, or Steam returns to the wrong host and the assertion
 * fails verification. Behind a TLS-terminating proxy the scheme is the part
 * that gets this wrong, so config.local.php can set base_url outright.
 */
function ghostd_base_url(): string
{
    $cfg = ghostd_config();
    if ($cfg['base_url'] !== '') {
        return rtrim((string) $cfg['base_url'], '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

    // A request made straight to /index.php must produce the same base as one
    // made to /, or the return_to sent to Steam will not match the return_to
    // checked on the way back and every login fails verification.
    $path = preg_replace('#/index\.php/?$#', '', $path) ?? $path;

    return ($https ? 'https://' : 'http://') . $host . rtrim($path, '/');
}

function ghostd_steam_return_url(string $state): string
{
    return ghostd_base_url() . '/?page=steam&action=return&state=' . urlencode($state);
}

/** Send the browser to Steam. Never returns. */
function ghostd_steam_redirect(): void
{
    require_once __DIR__ . '/auth.php';
    ghostd_session_start();

    // A nonce carried in return_to and compared on the way back, so a login
    // cannot be finished in a session that never started one. It survives the
    // round trip because Steam returns by top-level GET, which SameSite=Lax
    // allows the session cookie on - a POST return would arrive cookie-less.
    $state = bin2hex(random_bytes(16));
    $_SESSION['ghostd_steam_state'] = $state;

    $params = [
        'openid.ns'         => 'http://specs.openid.net/auth/2.0',
        'openid.mode'       => 'checkid_setup',
        'openid.return_to'  => ghostd_steam_return_url($state),
        'openid.realm'      => ghostd_base_url() . '/',
        'openid.identity'   => 'http://specs.openid.net/auth/2.0/identifier_select',
        'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
    ];

    header('Location: ' . GHOSTD_STEAM_ENDPOINT . '?' . http_build_query($params));
    exit;
}

/**
 * Check what came back from Steam. Returns the 17-digit Steam id, or null.
 *
 * THE POST BACK IS THE WHOLE POINT. Everything in $_GET arrived through the
 * user's browser and can be typed by hand; only Steam answering
 * check_authentication with is_valid:true proves any of it. Skipping that step
 * turns this login into "enter any Steam id you like".
 */
function ghostd_steam_validate(array $q): ?string
{
    require_once __DIR__ . '/auth.php';
    ghostd_session_start();

    // The nonce, before anything else touches the network.
    $state = (string) ($q['state'] ?? '');
    $expect = (string) ($_SESSION['ghostd_steam_state'] ?? '');
    unset($_SESSION['ghostd_steam_state']);
    if ($state === '' || $expect === '' || !hash_equals($expect, $state)) {
        return null;
    }

    if (($q['openid_mode'] ?? $q['openid.mode'] ?? '') === 'cancel') {
        return null;
    }

    // PHP turns dots into underscores in $_GET keys, so openid.mode arrives as
    // openid_mode. The POST back has to use the real dotted names again.
    $params = [];
    foreach ($q as $k => $v) {
        if (str_starts_with($k, 'openid_') && is_string($v)) {
            $params['openid.' . substr($k, 7)] = $v;
        }
    }
    if ($params === []) {
        return null;
    }

    // Only Steam's own endpoint, never one named in the response.
    $op = $params['openid.op_endpoint'] ?? GHOSTD_STEAM_ENDPOINT;
    if ($op !== GHOSTD_STEAM_ENDPOINT) {
        return null;
    }

    // return_to must be the URL we sent, or the assertion belongs to another site.
    if (($params['openid.return_to'] ?? '') !== ghostd_steam_return_url($state)) {
        return null;
    }

    $claimed = (string) ($params['openid.claimed_id'] ?? '');
    if (!preg_match('#^https://steamcommunity\.com/openid/id/(\d{17})$#', $claimed, $m)) {
        return null;
    }

    $params['openid.mode'] = 'check_authentication';
    $body = ghostd_http_post(GHOSTD_STEAM_ENDPOINT, $params);
    if ($body === null) {
        return null;
    }
    foreach (preg_split('/\r?\n/', $body) as $line) {
        if (trim($line) === 'is_valid:true') {
            return $m[1];
        }
    }
    return null;
}

/**
 * Is this Steam id an admin?
 *
 * Two sources: the mod's own <unit>.admins document, and any extra ids named
 * in the configuration. Returns [isAdmin, reason] - the reason is shown to
 * somebody who expected to be one, because "you are not on the list" with the
 * id visible is what lets them be added.
 *
 * A DATABASE THAT CANNOT BE READ MEANS NOT AN ADMIN, never "assume yes". The
 * steam_admins list in the configuration is the way back in when the store is
 * unreachable, which is exactly when the admin list cannot be consulted.
 */
function ghostd_steam_is_admin(string $steamid): array
{
    $cfg = ghostd_config();

    foreach ($cfg['steam_admins'] as $id) {
        if (hash_equals((string) $id, $steamid)) {
            return [true, 'configuration'];
        }
    }

    if (!$cfg['steam_use_pac_admins']) {
        return [false, 'not in the configured list of Steam ids'];
    }

    try {
        $doc = ghostd_get($cfg['unit'] . '.admins');
    } catch (Throwable $e) {
        // A database that cannot be read must not become "everyone is allowed".
        return [false, 'the admin list could not be read: ' . $e->getMessage()];
    }

    if ($doc === null) {
        return [false, 'this unit has no ' . $cfg['unit'] . '.admins document yet'];
    }

    // The mod writes both shapes: a sorted "ids" array and the keyed "items".
    $ids = [];
    if (isset($doc['ids']) && is_array($doc['ids'])) {
        $ids = $doc['ids'];
    } elseif (isset($doc['items']) && is_array($doc['items'])) {
        $ids = array_keys($doc['items']);
    }

    foreach ($ids as $id) {
        if (hash_equals((string) $id, $steamid)) {
            return [true, 'PAC admin list'];
        }
    }

    return [false, 'not in ' . $cfg['unit'] . '.admins'];
}

/** The persona name, if an API key is set. Cosmetic - never used to decide anything. */
function ghostd_steam_name(string $steamid): ?string
{
    $key = ghostd_config()['steam_api_key'];
    if ($key === '') {
        return null;
    }
    $url = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?'
         . http_build_query(['key' => $key, 'steamids' => $steamid]);
    $body = ghostd_http_get($url);
    if ($body === null) {
        return null;
    }
    $j = json_decode($body, true);
    $p = $j['response']['players'][0]['personaname'] ?? null;
    return is_string($p) && $p !== '' ? $p : null;
}

/**
 * The name this Steam id has in the PAC admin list, if any.
 *
 * Preferred over the Steam persona name: it is what the unit calls them, and
 * it needs no API key.
 */
function ghostd_steam_pac_name(string $steamid): ?string
{
    try {
        $doc = ghostd_get(ghostd_config()['unit'] . '.admins');
    } catch (Throwable $e) {
        return null;
    }
    $n = $doc['items'][$steamid]['name'] ?? null;
    return is_string($n) && $n !== '' ? $n : null;
}

// ---- the two HTTP calls ----------------------------------------------------
// curl when it is there, streams when it is not. Both time out: a Steam
// outage should fail the login, not hold a PHP worker until the request dies.

function ghostd_http_post(string $url, array $fields): ?string
{
    $data = http_build_query($fields);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return is_string($body) ? $body : null;
    }

    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $data,
        'timeout' => 10,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    return is_string($body) ? $body : null;
}

function ghostd_http_get(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return is_string($body) ? $body : null;
    }

    $ctx = stream_context_create(['http' => ['timeout' => 10]]);
    $body = @file_get_contents($url, false, $ctx);
    return is_string($body) ? $body : null;
}
