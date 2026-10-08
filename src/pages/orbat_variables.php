<?php
/**
 * The ORBAT's Variables tab - the setVariable names this unit invented.
 *
 * IT IS THE CONFIGS GRID, DRAWN HERE. A variable is what a ROLE puts on a man
 * and roles live under ORBAT, so this is where it is edited (user, 2026-09-09:
 * "this should be part of the orbat"). The editor itself is
 * src/pages/record.php - embedding it rather than copying it means one grid,
 * one save, and no second copy to drift.
 *
 * The forms post to ?page=record, which owns the save, and it sends you back
 * here afterwards - see $recordBack there.
 */

declare(strict_types=1);

$sec          = 'traits';
$recordEmbed  = true;
$recordFrom   = 'orbat';

require __DIR__ . '/record.php';
