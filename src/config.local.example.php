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

    // Replaced documents are copied here before every write. '' turns the
    // safety net off; do not.
    'backup_collection' => 'pac_backups',
];
