<?php
/**
 * The nightly database dump. Run from cron, not from the site.
 *
 *   php tools/backup.php            write backups/latest.json and a dated copy
 *   php tools/backup.php --keep 30  and prune anything older than 30 days
 *
 * IT NEEDS THE SAME ENVIRONMENT PHP-FPM HAS - GHOSTD_MONGO and GHOSTD_UNIT are
 * env[] lines in /etc/php-fpm.d/*.conf, not in src/config.local.php, so cron
 * must export them (see the crontab line in DEPLOY.md). Without them this
 * exits 2 rather than writing an empty backup over a good one.
 *
 * ONE DOCUMENT OUT: {unit, takenAt, count, docs: {id: {...}}}. Written to a
 * temporary file and renamed, so a run that dies half way leaves the previous
 * backup intact - a half-written backup is worse than an old one.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/backup.php';

$keep = 14;
foreach ($argv as $i => $a) {
    if ($a === '--keep' && isset($argv[$i + 1])) {
        $keep = max(1, (int) $argv[$i + 1]);
    }
}

$unit = ghostd_config()['unit'];
$dir  = ghostd_backup_dir();

if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
    fwrite(STDERR, "cannot make $dir\n");
    exit(2);
}

try {
    $keys = ghostd_keys();
} catch (Throwable $e) {
    fwrite(STDERR, 'cannot read the database: ' . $e->getMessage() . "\n");
    exit(2);
}
if ($keys === []) {
    fwrite(STDERR, "the database answered with no documents - refusing to write an empty backup\n");
    exit(2);
}

$docs   = [];
$failed = [];
foreach ($keys as $id) {
    try {
        $d = ghostd_get((string) $id);
        if (is_array($d)) {
            unset($d['_id']);
            $docs[(string) $id] = $d;
        }
    } catch (Throwable $e) {
        $failed[] = (string) $id;
    }
}

$payload = [
    'unit'    => $unit,
    'takenAt' => gmdate('Y-m-d H:i:s'),
    'count'   => count($docs),
    'failed'  => $failed,
    'docs'    => $docs,
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    fwrite(STDERR, 'cannot encode: ' . json_last_error_msg() . "\n");
    exit(2);
}

$tmp = $dir . '/.writing.json';
if (file_put_contents($tmp, $json) === false) {
    fwrite(STDERR, "cannot write $tmp\n");
    exit(2);
}
@chmod($tmp, 0640);

// A SMALL INDEX BESIDE THE BIG FILE. The page lists what is in the backup
// without parsing six megabytes to draw a dropdown.
$index = ['unit' => $unit, 'takenAt' => $payload['takenAt'], 'ids' => array_keys($docs)];
file_put_contents($dir . '/latest.index.json', json_encode($index));
@chmod($dir . '/latest.index.json', 0640);

$dated = $dir . '/' . $unit . '-' . gmdate('Y-m-d') . '.json';
copy($tmp, $dated);
@chmod($dated, 0640);
rename($tmp, ghostd_backup_latest());

// Prune. The dated copies are the history; latest.json is never pruned.
$cut = time() - ($keep * 86400);
foreach (ghostd_backup_list() as $b) {
    if ($b['mtime'] < $cut) {
        @unlink($dir . '/' . $b['name']);
    }
}

printf("%s: %d documents, %s%s\n", $payload['takenAt'], count($docs),
    ghostd_bytes(strlen($json)),
    $failed === [] ? '' : ' (' . count($failed) . ' unreadable)');
