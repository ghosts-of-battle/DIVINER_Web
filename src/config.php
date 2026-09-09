<?php
/**
 * Configuration.
 *
 * NOTHING SECRET LIVES IN THIS FILE. It reads the environment first, then an
 * untracked config.local.php beside it. That order is deliberate: a box that
 * sets GHOSTD_MONGO in the web server's environment needs no file at all, and
 * a file that is never committed cannot be committed by accident.
 *
 * Copy config.local.example.php to config.local.php to use the file form.
 */

declare(strict_types=1);

/** "1", "true", "yes", "on" - the shapes a setting arrives in from an environment. */
function ghostd_truthy($v): bool
{
    if (is_bool($v)) {
        return $v;
    }
    return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'on'], true);
}

/** A list of Steam ids from either an array or a comma/space separated string. */
function ghostd_idlist($v): array
{
    if (!is_array($v)) {
        $v = preg_split('/[\s,]+/', (string) $v, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
    return array_values(array_filter(array_map(
        static fn($x) => trim((string) $x),
        $v
    ), static fn($x) => $x !== ''));
}

function ghostd_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $local = [];
    $localFile = __DIR__ . '/config.local.php';
    if (is_file($localFile)) {
        /** @noinspection PhpIncludeInspection */
        $local = require $localFile;
        if (!is_array($local)) {
            $local = [];
        }
    }

    $pick = static function (string $env, string $key, $default) use ($local) {
        $v = getenv($env);
        if ($v !== false && $v !== '') {
            return $v;
        }
        return $local[$key] ?? $default;
    };

    $cfg = [
        // The Atlas connection string. Database user should be scoped to this
        // database alone - see tools/pacdb/README.md in the DIVINER repo.
        'mongo_uri'   => (string) $pick('GHOSTD_MONGO', 'mongo_uri', ''),
        'database'    => (string) $pick('GHOSTD_DB', 'database', 'ghostd'),
        'collection'  => (string) $pick('GHOSTD_COLLECTION', 'collection', 'pac'),

        // Every document for one unit is prefixed with its id: the store is
        // "<unit>", its config documents "<unit>.ranks" and so on.
        'unit'        => (string) $pick('GHOSTD_UNIT', 'unit', 'framework'),

        // password_hash() output. Empty disables the site rather than opening
        // it - a management page with no password is not a management page.
        'password_hash' => (string) $pick('GHOSTD_WEB_PASSWORD_HASH', 'password_hash', ''),

        // Where replaced documents are copied before a write. Set to '' to
        // turn the safety net off, which you should not do.
        'backup_collection' => (string) $pick('GHOSTD_BACKUP_COLLECTION', 'backup_collection', 'pac_backups'),


        // ---- Sign in through Steam (src/steam.php) -----------------------
        // With this on, the login page offers Steam and authorises against
        // the mod's own <unit>.admins document. The password stays available
        // alongside it - a way in when the admin list cannot be read.
        'steam_login'   => ghostd_truthy($pick('GHOSTD_STEAM', 'steam_login', false)),

        // Extra Steam ids allowed in beyond <unit>.admins. Comma-separated in
        // the environment, an array in the file.
        'steam_admins'  => ghostd_idlist($pick('GHOSTD_STEAM_ADMINS', 'steam_admins', [])),

        // Off to ignore <unit>.admins and use steam_admins alone.
        'steam_use_pac_admins' => ghostd_truthy($pick('GHOSTD_STEAM_PAC_ADMINS', 'steam_use_pac_admins', true)),

        // Optional, for persona names on the login banner. Never used to
        // decide anything: https://steamcommunity.com/dev/apikey
        'steam_api_key' => (string) $pick('GHOSTD_STEAM_API_KEY', 'steam_api_key', ''),

        // The site's own URL, when it cannot work it out - behind a proxy
        // that terminates TLS, say. Steam must return to exactly this host.
        'base_url'      => (string) $pick('GHOSTD_BASE_URL', 'base_url', ''),

        'session_name' => 'ghostd_web',
    ];

    return $cfg;
}
