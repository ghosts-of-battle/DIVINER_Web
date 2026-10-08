<?php
/**
 * The nightly copy of the database, kept on the web server.
 *
 * WHY A FILE AND NOT A DOCUMENT: this exists for the day Mongo is not there
 * (user, 2026-09-09: "every 24 hours the mongo needs saved to the web site ...
 * so if mongo is down a admin can import this in to a mission"). A backup that
 * lives in the thing being backed up is no backup at all, so it is written to
 * disk, outside public/, and read from disk - the page that shows it never
 * touches the database.
 *
 * ONE BIG DOCUMENT: {unit, takenAt, count, docs: {id: {...}}} - every document
 * the unit has, in one JSON object, which is what an admin pastes into a
 * mission to run without the service.
 *
 * Written by tools/backup.php from cron. Nothing here writes; the site only
 * ever reads what that put down.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** Where the backups live - NOT under public/, so nothing serves them. */
function ghostd_backup_dir(): string
{
    return dirname(__DIR__) . '/backups';
}

function ghostd_backup_latest(): string
{
    return ghostd_backup_dir() . '/latest.json';
}

/**
 * What is on disk, without reading the whole file in: [takenAt, count, bytes].
 *
 * Returns null when there has never been one - a fresh install, or cron not
 * set up, and the page says so rather than looking broken.
 */
function ghostd_backup_meta(): ?array
{
    $p = ghostd_backup_latest();
    if (!is_readable($p)) {
        return null;
    }
    $size = (int) filesize($p);

    // The header is the first few hundred bytes; the documents follow it, so
    // a 20 MB file does not have to be parsed to say when it was taken.
    $head = (string) file_get_contents($p, false, null, 0, 400);
    $when = '';
    $n    = 0;
    if (preg_match('/"takenAt"\s*:\s*"([^"]*)"/', $head, $m)) { $when = $m[1]; }
    if (preg_match('/"count"\s*:\s*(\d+)/', $head, $m))       { $n = (int) $m[1]; }

    return [
        'takenAt' => $when,
        'count'   => $n,
        'bytes'   => $size,
        'age'     => time() - (int) filemtime($p),
        'path'    => $p,
    ];
}

/** Every backup on disk, newest first: [name, bytes, mtime]. */
function ghostd_backup_list(): array
{
    $out = [];
    foreach ((array) glob(ghostd_backup_dir() . '/*.json') as $p) {
        if (basename($p) === 'latest.json') {
            continue;
        }
        $out[] = ['name' => basename($p), 'bytes' => (int) filesize($p), 'mtime' => (int) filemtime($p)];
    }
    usort($out, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $out;
}

/** A size a person reads. */
function ghostd_bytes(int $n): string
{
    if ($n >= 1048576) { return round($n / 1048576, 1) . ' MB'; }
    if ($n >= 1024)    { return round($n / 1024) . ' KB'; }
    return $n . ' B';
}

/** The document ids in the latest backup, from the small index beside it. */
function ghostd_backup_ids(): array
{
    $p = ghostd_backup_dir() . '/latest.index.json';
    if (!is_readable($p)) {
        return [];
    }
    $j = json_decode((string) file_get_contents($p), true);
    $ids = is_array($j['ids'] ?? null) ? $j['ids'] : [];
    sort($ids);
    return array_map('strval', $ids);
}

/**
 * ONE document out of the backup, as pretty JSON.
 *
 * The whole file is parsed to get it, which is why the page asks for one at a
 * time rather than drawing six megabytes into a textarea nobody can scroll.
 */
function ghostd_backup_doc(string $id): ?string
{
    $p = ghostd_backup_latest();
    if (!is_readable($p)) {
        return null;
    }
    $j = json_decode((string) file_get_contents($p), true);
    $d = $j['docs'][$id] ?? null;
    if ($d === null) {
        return null;
    }
    return (string) json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
