<?php
/**
 * The media folder - files people share, kept where only this app can reach.
 *
 * WHY NOT public/. Anything under public/ is handed out by nginx to anyone who
 * guesses the URL, and half of what a unit shares is not for the world. So the
 * bytes live in <repo>/media/, which nginx cannot read (root:apache, 750), and
 * every read goes through ?page=file - which checks the file's visibility
 * first. "Write only from the php app" is the same rule from the other side:
 * nothing but this code puts a byte in that folder.
 *
 * TWO PERMISSIONS, because two is what anyone can hold in their head:
 *
 *   site     signed in - the unit only. The default.
 *   public   anyone with the link, no sign-in.
 *
 * The INDEX is a Mongo document (<unit>.media) so it backs up with everything
 * else and the roster can be joined against it; the FILE is on disk, because a
 * 40MB video does not belong in a document.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/** Where the bytes live. Outside public/ on purpose - see the note above. */
function ghostd_media_dir(): string
{
    return dirname(__DIR__) . '/media';
}

function ghostd_media_doc_id(): string
{
    return ghostd_config()['unit'] . '.media';
}

/** What may be uploaded, extension => content type. */
const GHOSTD_MEDIA_TYPES = [
    'png'  => 'image/png',    'jpg' => 'image/jpeg',  'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',    'webp' => 'image/webp', 'svg'  => 'image/svg+xml',
    'paa'  => 'application/octet-stream',
    'pdf'  => 'application/pdf',
    'txt'  => 'text/plain',   'md'  => 'text/markdown', 'csv' => 'text/csv',
    'json' => 'application/json',
    'sqf'  => 'text/plain',   'hpp' => 'text/plain',  'ext'  => 'text/plain',
    'zip'  => 'application/zip',
    'mp4'  => 'video/mp4',    'webm' => 'video/webm', 'ogg'  => 'audio/ogg',
    'mp3'  => 'audio/mpeg',   'wav' => 'audio/wav',
];

/** 64 MB. A share, not a file server. */
const GHOSTD_MEDIA_MAX = 67108864;

/** Every file, newest first. */
function ghostd_media_all(): array
{
    try {
        $doc = ghostd_get(ghostd_media_doc_id());
    } catch (Throwable $e) {
        return [];
    }
    $out = [];
    foreach ((array) ($doc['items'] ?? []) as $id => $f) {
        if (!is_array($f)) {
            continue;
        }
        $out[(string) $id] = [
            'id'         => (string) $id,
            'name'       => (string) ($f['name'] ?? $id),
            'ext'        => (string) ($f['ext'] ?? ''),
            'mime'       => (string) ($f['mime'] ?? 'application/octet-stream'),
            'size'       => (int) ($f['size'] ?? 0),
            'visibility' => ($f['visibility'] ?? 'site') === 'public' ? 'public' : 'site',
            'by'         => (string) ($f['by'] ?? ''),
            'at'         => (string) ($f['at'] ?? ''),
            'note'       => (string) ($f['note'] ?? ''),
        ];
    }
    uasort($out, static fn($a, $b) => strcmp($b['at'], $a['at']));
    return $out;
}

function ghostd_media_one(string $id): ?array
{
    return ghostd_media_all()[$id] ?? null;
}

/** The file on disk for a record. */
function ghostd_media_path(array $f): string
{
    return ghostd_media_dir() . '/' . $f['id'] . ($f['ext'] !== '' ? '.' . $f['ext'] : '');
}

/** Write the index back, whole. */
function ghostd_media_save(array $items): void
{
    $doc = ghostd_get(ghostd_media_doc_id());
    $doc = is_array($doc) ? $doc : [];
    unset($doc['_id']);
    $doc['section']   = 'media';
    $doc['items']     = $items;
    $doc['from']      = 'DIVINER_Web';
    $doc['updatedAt'] = gmdate('Y-m-d H:i:s');
    ghostd_put(ghostd_media_doc_id(), $doc);
}

/**
 * Take one uploaded file. Returns its id.
 *
 * $who is what goes in the "shared by" column - a name, not an id, because the
 * column is read by people.
 */
function ghostd_media_add(array $upload, string $who, string $visibility = 'site', string $note = ''): string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The upload did not arrive - error ' . (int) ($upload['error'] ?? -1)
            . '. A file bigger than the server\'s upload_max_filesize fails this way.');
    }
    $name = trim((string) ($upload['name'] ?? ''));
    if ($name === '') {
        throw new RuntimeException('That upload had no filename.');
    }
    $size = (int) ($upload['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('That file is empty.');
    }
    if ($size > GHOSTD_MEDIA_MAX) {
        throw new RuntimeException('That file is ' . ghostd_media_size($size) . '. The limit is '
            . ghostd_media_size(GHOSTD_MEDIA_MAX) . '.');
    }
    $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    if (!isset(GHOSTD_MEDIA_TYPES[$ext])) {
        throw new RuntimeException('"' . $ext . '" is not a kind this folder takes. It takes: '
            . implode(', ', array_keys(GHOSTD_MEDIA_TYPES)) . '.');
    }

    $dir = ghostd_media_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('The media folder does not exist and could not be made: ' . $dir);
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('The media folder is not writable by the web user: ' . $dir);
    }

    // A NAME NOBODY CHOSE. The file on disk is named by a random id, never by
    // what the browser sent - an uploaded name is attacker-controlled, and a
    // folder full of them is a folder full of surprises. The real name is in
    // the index and is what the download is called.
    $id   = bin2hex(random_bytes(8));
    $dest = $dir . '/' . $id . '.' . $ext;
    if (!move_uploaded_file((string) $upload['tmp_name'], $dest)) {
        // The probe scripts and the tests do not go through a real upload.
        if (!@rename((string) $upload['tmp_name'], $dest)) {
            throw new RuntimeException('The file could not be moved into the media folder.');
        }
    }
    @chmod($dest, 0640);

    $items = [];
    foreach (ghostd_media_all() as $fid => $f) {
        $items[$fid] = $f;
    }
    $items[$id] = [
        'id'         => $id,
        'name'       => $name,
        'ext'        => $ext,
        'mime'       => GHOSTD_MEDIA_TYPES[$ext],
        'size'       => $size,
        'visibility' => $visibility === 'public' ? 'public' : 'site',
        'by'         => $who,
        'at'         => gmdate('Y-m-d H:i:s'),
        'note'       => $note,
    ];
    ghostd_media_save($items);
    return $id;
}

/** Remove one, index and bytes. */
function ghostd_media_delete(string $id): void
{
    $items = ghostd_media_all();
    if (!isset($items[$id])) {
        throw new RuntimeException('There is no file with that id.');
    }
    $path = ghostd_media_path($items[$id]);
    unset($items[$id]);
    ghostd_media_save($items);
    // The index first: a row pointing at nothing is worse than a byte nobody
    // can reach, and this order means a failed unlink cannot resurrect it.
    if (is_file($path)) {
        @unlink($path);
    }
}

/** Change the name, the note or who may read it. */
function ghostd_media_update(string $id, array $fields): void
{
    $items = ghostd_media_all();
    if (!isset($items[$id])) {
        throw new RuntimeException('There is no file with that id.');
    }
    foreach (['name', 'note'] as $k) {
        if (isset($fields[$k])) {
            $items[$id][$k] = trim((string) $fields[$k]);
        }
    }
    if (isset($fields['visibility'])) {
        $items[$id]['visibility'] = $fields['visibility'] === 'public' ? 'public' : 'site';
    }
    if (trim((string) $items[$id]['name']) === '') {
        throw new RuntimeException('A file needs a name.');
    }
    ghostd_media_save($items);
}

/** "1.4 MB" - for a column people read, not a byte count. */
function ghostd_media_size(int $n): string
{
    foreach ([['GB', 1073741824], ['MB', 1048576], ['KB', 1024]] as [$u, $d]) {
        if ($n >= $d) {
            return rtrim(rtrim(number_format($n / $d, 1), '0'), '.') . ' ' . $u;
        }
    }
    return $n . ' B';
}
