<?php
/**
 * The supply catalogue: crates, and what is in each.
 *
 * The document is a block of SQF - the same literal a mission's
 * config_logistics.sqf returns - and it has no logic in it at all:
 *
 *   { {"crate_medical", { {"ACE_fieldDressing", 80}, ... }}, ... }
 *
 * so there was never a reason to make somebody edit brackets (user,
 * 2026-09-09: "Logistics needs a simple editor like other pages"). This reads
 * it into crates and writes the same shape back, exactly as the pylon editor
 * does.
 */

declare(strict_types=1);

require_once __DIR__ . '/templates.php';
require_once __DIR__ . '/roles.php';        // ghostd_sqf_encode / _decode

/** [crate => [[classname, count], ...]], in the order the document holds. */
function ghostd_logistics_tree(string $variant = ''): array
{
    $code = ghostd_template_code('logistics', $variant);
    if (trim($code) === '') {
        return [];
    }
    try {
        $tree = ghostd_sqf_decode($code);
    } catch (Throwable $e) {
        return [];
    }

    $out = [];
    foreach ($tree as $crate) {
        if (!is_array($crate) || count($crate) < 2) {
            continue;
        }
        $name = (string) $crate[0];
        $rows = [];
        foreach ((array) $crate[1] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = [(string) ($row[0] ?? ''), (string) ($row[1] ?? '')];
        }
        $out[$name] = $rows;
    }
    return $out;
}

/** Back into the shape the mod reads. A crate with nothing in it is dropped. */
function ghostd_logistics_save(array $tree, string $variant = ''): void
{
    $out = [];
    foreach ($tree as $name => $rows) {
        $items = [];
        foreach ((array) $rows as $r) {
            $cls = trim((string) ($r[0] ?? ''));
            if ($cls === '') {
                continue;
            }
            $n = trim((string) ($r[1] ?? ''));
            $items[] = [$cls, is_numeric($n) ? $n + 0 : 1];
        }
        if ($items === []) {
            continue;
        }
        $out[] = [(string) $name, $items];
    }

    ghostd_template_code_save('logistics', $out === [] ? '' : ghostd_sqf_encode($out, 0, false), $variant);
}

/** How many of everything a crate holds, for the card summary. */
function ghostd_logistics_count(array $rows): int
{
    $n = 0;
    foreach ($rows as $r) {
        $n += is_numeric($r[1] ?? null) ? (int) $r[1] : 0;
    }
    return $n;
}
