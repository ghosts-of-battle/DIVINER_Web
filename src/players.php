<?php
/**
 * Editing a player's record the way the PAC's admin page does.
 *
 * THE GAME UI AND THE WEB UI DO THE SAME THING (user, 2026-09-09). Every shape
 * here is ghostD_pac_fnc_adminSet's, by the same names and in the same order,
 * because the two write the same store document:
 *
 *   skillIds        [skillId, ...]
 *   qualifications  [[skillId, name, YYYY-MM-DD], ...]  - written when a skill
 *                   is granted for the FIRST time, and never removed after
 *   awards          [[awardId, "YYYY-MM-DD HH:MM", by, citation], ...]
 *   training        [[when, by, text, courseId], ...]
 *   notes           [[when, by, text], ...]
 *
 * AND EVERY WRITE IS LOGGED, dated and signed, on the record and in the store's
 * log - the same two rows the game writes, so the dashboard's admin log does
 * not care which side the change came from.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/** Who is making the change, for the signature on the log row. */
function ghostd_actor(): array
{
    $name = trim((string) ($_SESSION['ghostd_name'] ?? ''));
    $uid  = trim((string) ($_SESSION['ghostd_steamid'] ?? ''));
    if ($name === '') {
        $name = $uid !== '' ? $uid : 'web';
    }
    return [$uid, $name];
}

/**
 * Write one field of a record and log it, exactly as the game does.
 *
 * $value is written whole - a list field is the list, not an append - and the
 * log line is what the game's own would say.
 */
function ghostd_player_write(array $store, string $uid, string $field, $value, string $type, string $detail): void
{
    $storeId = ghostd_config()['unit'];
    $now     = gmdate('Y-m-d H:i');
    [$byUid, $byName] = ghostd_actor();

    ghostd_set_path($storeId, 'players.' . $uid . '.' . $field, $value);
    ghostd_set_path($storeId, 'players.' . $uid . '.updatedAt', gmdate('Y-m-d H:i:s'));

    // LOG-n, counting off what is there - the game's own numbering.
    $log = is_array($store['log'] ?? null) ? $store['log'] : [];
    $id  = 'LOG-' . (count($log) + 1);

    $rec        = (array) ($store['players'][$uid] ?? []);
    $targetName = (string) ($rec['name'] ?? $uid);
    $actions    = is_array($rec['adminActions'] ?? null) ? $rec['adminActions'] : [];

    $actions[] = [$id, $type, $now, $byUid, $byName, $detail];
    $log[]     = [$id, $now, $byUid, $byName, $type, $uid, $targetName, $detail];

    ghostd_set_path($storeId, 'players.' . $uid . '.adminActions', $actions);
    ghostd_set_path($storeId, 'log', $log);
}

/** id => name for a records section, for the dropdowns. */
function ghostd_record_labels(string $section): array
{
    try {
        $doc = ghostd_get(ghostd_config()['unit'] . '.' . $section);
    } catch (Throwable $e) {
        return [];
    }
    $out = [];
    foreach ((array) ($doc['items'] ?? []) as $id => $it) {
        $out[(string) $id] = is_array($it)
            ? (string) ($it['name'] ?? $it['title'] ?? $id)
            : (string) $it;
    }
    asort($out);
    return $out;
}

/**
 * Text that may start with a date, split into [when, text].
 *
 * "2026-08-01 finished the course" back-dates the entry, which is the same
 * rule the game's training box follows.
 */
function ghostd_dated_text(string $text, string $now): array
{
    $text = trim($text);
    if (strlen($text) >= 10 && preg_match('/^(\d{4}-\d{2}-\d{2})\s*(.*)$/s', $text, $m)) {
        return [$m[1], trim($m[2])];
    }
    return [$now, $text];
}
