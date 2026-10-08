<?php
/**
 * ONE order of battle drawn as a chart: the force at the top, its platoons in
 * a row under it, each platoon's squads hanging below it - the wiring diagram
 * an army draws of a division (user, 2026-10-03: "add a view button to show an
 * orbat like" a US division's chart).
 *
 * READ ONLY. Nothing here edits; Edit is the page for that.
 *
 * THE SYMBOLS ARE THE SQUAD'S OWN TYPE - the same word the blue force tracker
 * draws it with (inf, mech_inf, air ...) - in a NATO frame, filled in the
 * side's colour. A platoon takes the symbol most of its squads have.
 */

declare(strict_types=1);

$cur   = ghostd_orbat($variant);
$sideC = ['WEST' => '#80e0ff', 'EAST' => '#ff8080', 'GUER' => '#aaffaa', 'CIV' => '#e0a0ff'][$cur['side']] ?? '#80e0ff';

/** A NATO unit symbol: the frame in the side's colour, the type's glyph, the echelon dots over it. */
$symbol = static function (string $type, string $echelon) use ($sideC): string {
    $g = '';
    $ln = static fn($x1, $y1, $x2, $y2) => sprintf('<line x1="%s" y1="%s" x2="%s" y2="%s"/>', $x1, $y1, $x2, $y2);
    switch ($type) {
        case 'armor':        $g = '<ellipse cx="22" cy="17" rx="13" ry="6" fill="none"/>'; break;
        case 'mech_inf':     $g = $ln(4, 6, 40, 28) . $ln(40, 6, 4, 28) . '<ellipse cx="22" cy="17" rx="13" ry="6" fill="none"/>'; break;
        case 'motor_inf':    $g = $ln(4, 6, 40, 28) . $ln(40, 6, 4, 28) . $ln(22, 6, 22, 28); break;
        case 'recon':        $g = $ln(4, 28, 40, 6); break;
        case 'air':          $g = '<path d="M10 11 L22 17 L10 23 Z M34 11 L22 17 L34 23 Z" fill="none"/>'; break;
        case 'plane':        $g = '<path d="M8 17 Q22 6 36 17 Q22 28 8 17 Z" fill="none"/>'; break;
        case 'uav':          $g = '<path d="M8 12 L22 20 L36 12" fill="none"/>'; break;
        case 'antiair':      $g = '<path d="M6 28 Q22 4 38 28" fill="none"/>'; break;
        case 'art':          $g = '<circle cx="22" cy="17" r="4" fill="#000"/>'; break;
        case 'mortar':       $g = '<circle cx="22" cy="22" r="3" fill="none"/>' . $ln(22, 19, 22, 8) . '<path d="M18 12 L22 8 L26 12" fill="none"/>'; break;
        case 'med':          $g = $ln(22, 8, 22, 26) . $ln(13, 17, 31, 17); break;
        case 'hq':           $g = '<text x="22" y="22" text-anchor="middle" font-size="12" font-weight="700" stroke="none" fill="#000">HQ</text>'; break;
        case 'maint':        $g = '<path d="M10 17 L34 17 M13 13 Q9 17 13 21 M31 13 Q35 17 31 21" fill="none"/>'; break;
        case 'naval':        $g = '<path d="M22 8 L22 26 M16 12 L28 12 M14 22 Q22 30 30 22" fill="none"/>'; break;
        case 'support':
        case 'service':      $g = $ln(4, 24, 40, 24); break;
        case 'ordnance':     $g = '<circle cx="22" cy="20" r="5" fill="none"/>' . $ln(18, 11, 26, 11); break;
        case 'installation': $g = '<rect x="16" y="2" width="12" height="4" fill="#000"/>'; break;
        case 'unknown':      $g = '<text x="22" y="23" text-anchor="middle" font-size="14" stroke="none" fill="#000">?</text>'; break;
        default:             $g = $ln(4, 6, 40, 28) . $ln(40, 6, 4, 28);   // infantry, and an empty type
    }
    $dots = ['squad' => 1, 'section' => 2, 'platoon' => 3][$echelon] ?? 0;
    $ech = '';
    for ($i = 0; $i < $dots; $i++) {
        $ech .= sprintf('<circle cx="%s" cy="-4" r="2" fill="currentColor" stroke="none"/>', 22 + ($i - ($dots - 1) / 2) * 6);
    }
    if ($echelon === 'company') {
        $ech = '<line x1="22" y1="-8" x2="22" y2="-1" stroke="currentColor"/>';
    }
    return '<svg class="obsym" viewBox="0 -10 44 44" width="44" height="44" aria-hidden="true">' . $ech
        . '<g stroke="#000" stroke-width="1.6"><rect x="1" y="2" width="42" height="30" fill="' . $sideC . '"/>'
        . $g . '</g></svg>';
};

// Platoons in order, each with its squads; the force's own slot count.
$cols = [];
$total = 0;
foreach ($cur['platoons'] as $prow) {
    $pid = (string) ($prow[0] ?? '');
    $p = ghostd_platoon($variant, $pid) ?? ['id' => $pid, 'name' => $pid, 'callsign' => '', 'net' => '', 'squads' => []];
    $squads = [];
    $types = [];
    foreach ($p['squads'] as $sname) {
        $s = ghostd_squad($variant, (string) $sname);
        $slots = $s !== null ? count($s['roles']) : 0;
        $type = $s !== null && $s['type'] !== '' ? $s['type'] : 'inf';
        $types[$type] = ($types[$type] ?? 0) + 1;
        $total += $slots;
        $squads[] = ['name' => (string) $sname, 'slots' => $slots, 'type' => $type, 'missing' => $s === null];
    }
    arsort($types);
    $cols[] = ['p' => $p, 'squads' => $squads, 'type' => (string) (array_key_first($types) ?? 'inf')];
}
$vq = $variant !== '' ? '&amp;v=' . urlencode($variant) : '';
?>
<p><a href="?page=orbat&amp;s=versions">&larr; Orders of battle</a>
   &middot; <a href="?page=orbat&amp;s=one<?= $vq ?>">Edit this one</a></p>

<h2><code><?= h(ghostd_orbat_doc_id($variant)) ?></code></h2>

<?php if ($cols === []): ?>
  <p class="dim">This order of battle has no platoons yet - pick them on its
  <a href="?page=orbat&amp;s=one<?= $vq ?>">Edit</a> page.</p>
<?php else: ?>
<div class="obchart">
  <div class="obtop">
    <div class="obnode">
      <?= $symbol('hq', 'company') ?>
      <div class="obtxt"><strong><?= h($cur['faction'] !== '' ? $cur['faction'] : 'Order of battle') ?></strong>
        <span class="dim"><?= h($cur['side']) ?> &middot; <?= count($cols) ?> platoons &middot; <?= $total ?> slots</span></div>
    </div>
  </div>
  <div class="obcols">
    <?php foreach ($cols as $c): $p = $c['p']; ?>
      <div class="obcol">
        <div class="obnode obplt">
          <?= $symbol($c['type'], 'platoon') ?>
          <div class="obtxt"><strong><?= h($p['name'] !== '' ? $p['name'] : $p['id']) ?></strong>
            <span class="dim"><?= h(trim(($p['callsign'] !== '' ? $p['callsign'] : $p['id'])
                . ($p['net'] !== '' ? ' · ' . $p['net'] : ''))) ?></span></div>
        </div>
        <ul class="obsq">
          <?php foreach ($c['squads'] as $s): ?>
            <li class="obnode">
              <?= $symbol($s['type'], 'squad') ?>
              <div class="obtxt"><?= h($s['name']) ?>
                <span class="dim"><?= $s['missing'] ? 'no such squad' : $s['slots'] . ' slots' ?></span></div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
