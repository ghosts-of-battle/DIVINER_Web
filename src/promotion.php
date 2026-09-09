<?php
/**
 * Promotion points, the same sum the game does.
 *
 * A MIRROR OF ghostD_pac_fnc_promotionPoints, deliberately: the formula is the
 * unit's data (<unit>.promotion) and the quantities are the store's, so both
 * sides can add them up and must agree. Change the weights and both change.
 *
 *   hour          points per hour on the server (all sessions)
 *   op            points per op attended (a closed window since enlistment)
 *   serviceMonth  points per 30 days since enlistedAt
 *   gradeMonth    points per 30 days since promotedAt (enlistedAt if never)
 *   training      points per training entry
 *   award         points per award
 *   rank_<id>     points required to hold rank <id> - edited on the RANK
 *
 * THE RUNGS ARE EDITED ON THE RANK (user, 2026-09-09: "points required need to
 * be in here"), which is why they are pulled out of the promotion list when it
 * is drawn - that page is "how to calculate" and nothing else. They still live
 * in the promotion document, because that is where the mod reads them.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/records.php';

/** One weight out of the promotion document. Anything unset is 0. */
function ghostd_promotion_weight(string $key): float
{
    static $items = null;
    if ($items === null) {
        $items = ghostd_record_items('promotion');
    }
    $v = $items[$key]['value'] ?? 0;
    return is_numeric($v) ? (float) $v : 0.0;
}

/** rankId => points required, for every rung the promotion document holds. */
function ghostd_rank_thresholds(): array
{
    $out = [];
    foreach (ghostd_record_items('promotion') as $id => $it) {
        if (str_starts_with((string) $id, 'rank_')) {
            $v = $it['value'] ?? 0;
            $out[substr((string) $id, 5)] = is_numeric($v) ? (float) $v : 0.0;
        }
    }
    return $out;
}

/**
 * Write the rungs back, leaving the weights alone.
 *
 * $byRank is rankId => points, and a rank missing from it loses its rung -
 * which is what clearing the box on the ranks page means.
 */
function ghostd_rank_thresholds_save(array $byRank): void
{
    $items = ghostd_record_items('promotion');
    foreach (array_keys($items) as $id) {
        if (str_starts_with((string) $id, 'rank_')) {
            unset($items[$id]);
        }
    }
    foreach ($byRank as $rankId => $points) {
        $items['rank_' . $rankId] = [
            'name'  => 'Points required to hold ' . $rankId,
            'value' => $points + 0,
        ];
    }
    ghostd_record_save('promotion', $items);
}

/** Is the unit promoting by itself? <unit>.settings, autoPromote. */
function ghostd_auto_promote(): bool
{
    try {
        $doc = ghostd_get(ghostd_config()['unit'] . '.settings');
    } catch (Throwable $e) {
        return false;
    }
    $v = ($doc['items'] ?? [])['autoPromote'] ?? false;
    return $v === true || $v === 1 || $v === '1' || $v === 'true';
}

function ghostd_auto_promote_save(bool $on): void
{
    ghostd_set_path(ghostd_config()['unit'] . '.settings', 'items.autoPromote', $on);
}

/** "2026-09-05 17:44" or "2026-09-05" to minutes, the way the mod stamps. */
function ghostd_stamp_minutes(string $s): int
{
    $s = trim($s);
    if ($s === '') {
        return 0;
    }
    $t = strtotime(strlen($s) <= 10 ? $s . ' 00:00:00' : $s . ' UTC');
    return $t === false ? 0 : (int) floor($t / 60);
}

/**
 * Everyone's points, in one pass over the store.
 *
 * uid => [points, hours, ops, nextRankId, needed, threshold]. One pass because
 * the sessions are one list for the whole unit and walking it per player is
 * the same work eighteen times.
 */
function ghostd_promotion_table(array $store): array
{
    $players  = is_array($store['players'] ?? null) ? $store['players'] : [];
    $sessions = is_array($store['sessions'] ?? null) ? $store['sessions'] : [];
    $windows  = is_array($store['windows'] ?? null) ? $store['windows'] : [];

    // Closed windows only - an op that has not finished is not an op attended.
    $closed = [];
    foreach ($windows as $w) {
        $w = (array) $w;
        if ((string) ($w[3] ?? '') !== '') {
            $closed[(string) ($w[0] ?? '')] = ghostd_stamp_minutes((string) ($w[2] ?? ''));
        }
    }

    $minutes  = [];
    $attended = [];
    foreach ($sessions as $s) {
        $s   = (array) $s;
        $uid = (string) ($s[0] ?? '');
        if ($uid === '') {
            continue;
        }
        $end = (string) ($s[4] ?? '') !== '' ? (string) $s[4] : (string) ($s[3] ?? '');
        $m   = ghostd_stamp_minutes($end) - ghostd_stamp_minutes((string) ($s[2] ?? ''));
        $minutes[$uid] = ($minutes[$uid] ?? 0) + max(0, $m);

        $win = (string) ($s[5] ?? '');
        if ($win !== '' && isset($closed[$win])) {
            $attended[$uid][$win] = true;
        }
    }

    $now  = (int) floor(time() / 60);
    $th   = ghostd_rank_thresholds();
    $out  = [];

    foreach ($players as $uid => $rec) {
        $uid = (string) $uid;
        $rec = (array) $rec;

        $enlisted = (string) ($rec['enlistedAt'] ?? '');
        $promoted = (string) ($rec['promotedAt'] ?? '');
        if (strlen($promoted) < 10) {
            $promoted = $enlisted;
        }
        $days = static fn(string $from): float =>
            $from === '' ? 0.0 : max(0.0, floor(($now - ghostd_stamp_minutes($from)) / 1440));

        // Only ops held since this player enlisted count as scheduled for them.
        $since = ghostd_stamp_minutes($enlisted);
        $ops = 0;
        foreach ((array) ($attended[$uid] ?? []) as $win => $_) {
            if (($closed[$win] ?? 0) >= $since) {
                $ops++;
            }
        }

        $hours = ($minutes[$uid] ?? 0) / 60;
        $points = round(
            $hours * ghostd_promotion_weight('hour')
            + $ops * ghostd_promotion_weight('op')
            + ($days($enlisted) / 30) * ghostd_promotion_weight('serviceMonth')
            + ($days($promoted) / 30) * ghostd_promotion_weight('gradeMonth')
            + count((array) ($rec['training'] ?? [])) * ghostd_promotion_weight('training')
            + count((array) ($rec['awards'] ?? [])) * ghostd_promotion_weight('award')
        );

        // The next rung is the lowest threshold strictly above the one the
        // player's current rank sits on.
        $current = (float) ($th[(string) ($rec['rankId'] ?? '')] ?? 0);
        $next    = '';
        $nextAt  = -1.0;
        foreach ($th as $rid => $t) {
            if ($t > $current && ($nextAt < 0 || $t < $nextAt)) {
                $nextAt = $t;
                $next   = (string) $rid;
            }
        }

        $out[$uid] = [
            'name'      => (string) ($rec['name'] ?? $uid),
            'rankId'    => (string) ($rec['rankId'] ?? ''),
            'points'    => (int) $points,
            'hours'     => round($hours, 1),
            'ops'       => $ops,
            'next'      => $next,
            'needed'    => $nextAt < 0 ? 0 : max(0, (int) ($nextAt - $points)),
            'nextAt'    => $nextAt < 0 ? 0 : (int) $nextAt,
            'threshold' => (int) $current,
        ];
    }

    return $out;
}
