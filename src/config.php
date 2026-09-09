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

        'session_name' => 'ghostd_web',
    ];

    return $cfg;
}
