<?php
/**
 * Copy to config.local.php and fill in. config.local.php is gitignored.
 *
 * The environment wins over this file, so a deployment that sets GHOSTD_MONGO
 * and GHOSTD_WEB_PASSWORD_HASH in the web server's environment needs no file.
 */
return [
    // Atlas connection string. Use a database user scoped to this database.
    'mongo_uri' => 'mongodb+srv://user:password@cluster.example.mongodb.net/?retryWrites=true&w=majority',

    'database'   => 'ghostd',
    'collection' => 'pac',

    // The unit id the documents are filed under - "framework" for
    // frameworkmongo.Stratis.
    'unit' => 'framework',

    // php -r "echo password_hash('your password', PASSWORD_DEFAULT), PHP_EOL;"
    'password_hash' => '',

    // ---- Sign in through Steam ------------------------------------------
    // On, and the login page offers Steam instead of (or beside) the
    // password. Who may in: the mod's own <unit>.admins document, which is
    // keyed by Steam id - so the in-game admins are the site's users.
    'steam_login' => false,

    // Extra Steam ids, for someone who needs the site but not the console.
    'steam_admins' => [],

    // false to ignore <unit>.admins and trust steam_admins alone.
    'steam_use_pac_admins' => true,

    // Optional, persona names only: https://steamcommunity.com/dev/apikey
    'steam_api_key' => '',

    // Only when the site cannot see its own URL - behind a TLS proxy, say.
    // Steam returns the browser to exactly this address.
    'base_url' => '',

    // Replaced documents are copied here before every write. '' turns the
    // safety net off; do not.
    'backup_collection' => 'pac_backups',
];
