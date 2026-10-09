<?php
/**
 * Members' photos: the one picture a player adds themselves, for the roster
 * (user, 2026-10-08: "add a place for members to add an avatar image in the
 * pac web").
 *
 * ONE DOCUMENT PER MEMBER, <unit>.web.photo.<steam id>. Not in the store,
 * which the game rewrites whole at every SAVE; and not one document for the
 * whole unit, which a Mongo document's 16 MB would fill after a dozen
 * pictures. A member writes only their own, through the same narrow door as
 * their other details (ghostd_write_scope); an admin may remove anyone's.
 *
 * THE BROWSER SHRINKS IT FIRST. There is no GD on the server, so My details
 * resizes the picture to a passport-sized JPEG before it is sent; the server
 * still checks the bytes and caps the size, since the script is optional.
 *
 * PUBLIC BY CHOICE. A photo goes out on the roster feed, which the unit's
 * website shows to anyone, under an opaque token rather than the Steam id.
 * The form says so before the button.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/roles.php';     // ghostd_doc_delete()

use MongoDB\Driver\Query;
use MongoDB\BSON\Regex;

const GHOSTD_PHOTO_MAX = 1572864;   // 1.5 MB - a head shot, not a wallpaper
const GHOSTD_PHOTO_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

function ghostd_photo_prefix(): string
{
    return ghostd_config()['unit'] . '.web.photo.';
}

function ghostd_photo_doc_id(string $uid): string
{
    return ghostd_photo_prefix() . $uid;
}

/**
 * Who has a photo, in ONE query and without the pictures: uid => when it was
 * added. The roster feed asks this for every player, so it must not read
 * every picture to answer.
 */
function ghostd_photo_ids(): array
{
    $prefix = ghostd_photo_prefix();
    $out = [];
    try {
        $cur = ghostd_manager()->executeQuery(
            ghostd_ns(),
            new Query(
                ['_id' => new Regex('^' . preg_quote($prefix, '/'), '')],
                ['projection' => ['_id' => 1, 'at' => 1, 'mime' => 1]]
            )
        );
        $cur->setTypeMap(GHOSTD_TYPEMAP);
        foreach ($cur as $doc) {
            $uid = substr((string) $doc['_id'], strlen($prefix));
            if (preg_match('/^\d{5,25}$/', $uid) && isset(GHOSTD_PHOTO_TYPES[(string) ($doc['mime'] ?? '')])) {
                $out[$uid] = (string) ($doc['at'] ?? '');
            }
        }
    } catch (Throwable $e) {
        return [];
    }
    return $out;
}

/** ['mime', 'data' (base64), 'at'] for one member, or null. */
function ghostd_photo(string $uid): ?array
{
    if (!preg_match('/^\d{5,25}$/', $uid)) {
        return null;
    }
    try {
        $doc = ghostd_get(ghostd_photo_doc_id($uid));
    } catch (Throwable $e) {
        return null;
    }
    if (!is_array($doc) || !isset(GHOSTD_PHOTO_TYPES[(string) ($doc['mime'] ?? '')]) || (string) ($doc['data'] ?? '') === '') {
        return null;
    }
    return ['mime' => (string) $doc['mime'], 'data' => (string) $doc['data'], 'at' => (string) ($doc['at'] ?? '')];
}

/**
 * The name a photo is served under on the public feed. Derived from the id,
 * never reversible to it: the feed must not hand out Steam ids.
 */
function ghostd_photo_token(string $uid): string
{
    return substr(hash('sha256', ghostd_config()['unit'] . '|photo|' . $uid), 0, 20);
}

function ghostd_photo_by_token(string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{20}$/', $token)) {
        return null;
    }
    // PHP turns a Steam id used as an array key into an int: cast it back.
    foreach (array_keys(ghostd_photo_ids()) as $uid) {
        $uid = (string) $uid;
        if (hash_equals(ghostd_photo_token($uid), $token)) {
            return ghostd_photo($uid);
        }
    }
    return null;
}

/** A data: address for the member's own page - no route, no cache to bust. */
function ghostd_photo_data_url(array $p): string
{
    return 'data:' . $p['mime'] . ';base64,' . $p['data'];
}

/**
 * The signed-in member's own photo, from a $_FILES entry. Checked here, not in
 * the page: the type is read from the bytes, not the name.
 */
function ghostd_photo_put_self(array $file): string
{
    if (!ghostd_is_member()) {
        throw new RuntimeException('Sign in through Steam to add a photo.');
    }
    $uid = ghostd_self_uid();
    $errno = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errno !== UPLOAD_ERR_OK) {
        throw new RuntimeException($errno === UPLOAD_ERR_INI_SIZE || $errno === UPLOAD_ERR_FORM_SIZE
            ? 'That picture is too big. Keep it under 1.5 MB.'
            : 'No picture arrived. Pick a file and try again.');
    }
    if ((int) ($file['size'] ?? 0) > GHOSTD_PHOTO_MAX) {
        throw new RuntimeException('That picture is too big. Keep it under 1.5 MB.');
    }
    $info = @getimagesize((string) $file['tmp_name']);
    $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
    if (!isset(GHOSTD_PHOTO_TYPES[$mime])) {
        throw new RuntimeException('That is not a picture this site can use. PNG, JPEG or WebP.');
    }
    $bytes = file_get_contents((string) $file['tmp_name']);
    if ($bytes === false || strlen($bytes) > GHOSTD_PHOTO_MAX) {
        throw new RuntimeException('The upload could not be read.');
    }
    $store = ghostd_get(ghostd_config()['unit']);
    if (!isset($store['players'][$uid])) {
        throw new RuntimeException('You are not on the roster yet.');
    }

    ghostd_write_scope(true);
    try {
        ghostd_put(ghostd_photo_doc_id($uid), [
            'section' => 'web',
            'uid'     => $uid,
            'mime'    => $mime,
            'width'   => (int) ($info[0] ?? 0),
            'height'  => (int) ($info[1] ?? 0),
            'data'    => base64_encode($bytes),
            'at'      => gmdate('Y-m-d H:i:s'),
        ]);
    } finally {
        ghostd_write_scope(false);
    }
    return 'Photo saved (' . round(strlen($bytes) / 1024) . ' KB). It is on the roster now.';
}

function ghostd_photo_remove_self(): void
{
    if (!ghostd_is_member()) {
        throw new RuntimeException('Sign in through Steam to change your photo.');
    }
    ghostd_write_scope(true);
    try {
        ghostd_doc_delete(ghostd_photo_doc_id(ghostd_self_uid()));
    } finally {
        ghostd_write_scope(false);
    }
}

/** An admin taking a photo down. Guarded like every other admin write. */
function ghostd_photo_remove(string $uid): void
{
    ghostd_doc_delete(ghostd_photo_doc_id($uid));
}
