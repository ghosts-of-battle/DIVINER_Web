<?php
/**
 * The site's colour schemes come from the unit's TAC//PAD schemes.
 *
 * THE DOCUMENT IS THE LIST. <unit>.schemes is what the TAC//PAD is painted
 * with in game and what the site is painted with here. There is no list in this
 * file: a scheme the site knows about and the document does not is exactly the
 * drift this is meant to remove.
 *
 * A scheme stores ground, ink and accent. The site needs a few more - panel,
 * line, dim - so those are shaded from the three rather than stored.
 *
 * The branding override still wins: it is emitted after these and is at least
 * as specific.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/branding.php';

// ---- the mod's own six ----------------------------------------------------
// THE SIX THE MOD SHIPS, and they are NOT editable - here or in game (user,
// 2026-09-09: "6 default colors schemes that can not be edited for game and
// web"). They are hard-coded in ghostD_tacpad_fnc_theme and drawn as preset
// cards on the TAC//PAD's settings screen; this is the same six so the site
// paints with them too. They are deliberately NOT in <unit>.schemes: putting
// them there would show every one of them twice in game.
//
// If a scheme is ever changed in the mod, it changes here - the file to look
// at is addons/tacpad/functions/fnc_theme.sqf.
const GHOSTD_MOD_SCHEMES = [
    'light'      => ['name' => 'FIELD GREY',  'ground' => '#f3f2f2', 'ink' => '#201e1d', 'accent' => '#ec3013'],
    'olive'      => ['name' => 'OLIVE',       'ground' => '#e8e7e2', 'ink' => '#16281d', 'accent' => '#b5cc4a'],
    'sand'       => ['name' => 'SAND',        'ground' => '#efece4', 'ink' => '#2b2119', 'accent' => '#d99427'],
    'dark'       => ['name' => 'NIGHT / RED', 'ground' => '#141514', 'ink' => '#e6e5e2', 'accent' => '#ff563c'],
    'nightOlive' => ['name' => 'NIGHT OLIVE', 'ground' => '#101411', 'ink' => '#d9e0d4', 'accent' => '#9cb43c'],
    'nightSand'  => ['name' => 'NIGHT SAND',  'ground' => '#161310', 'ink' => '#e5e0d6', 'accent' => '#c78221'],
];

/**
 * The unit's schemes, as [id => [name, ground, ink, accent, locked]].
 *
 * Empty when the unit has none - the caller then uses the site's presets.
 */
function ghostd_schemes(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    // The mod's six first, so a unit's own scheme of the same id would sit on
    // top of it rather than the other way round.
    $cache = [];
    foreach (GHOSTD_MOD_SCHEMES as $id => $s) {
        $cache[(string) $id] = $s + ['locked' => true];
    }

    try {
        $doc = ghostd_get(ghostd_config()['unit'] . '.schemes');
    } catch (Throwable $e) {
        return $cache;
    }

    foreach ((array) ($doc['items'] ?? []) as $id => $s) {
        if (!is_array($s)) {
            continue;
        }
        $ground = (string) ($s['ground'] ?? '');
        $ink    = (string) ($s['ink'] ?? '');
        $accent = (string) ($s['accent'] ?? '');
        // A scheme missing any of the three cannot be drawn, and half a
        // repaint is worse than none.
        if (!ghostd_is_hex($ground) || !ghostd_is_hex($ink) || !ghostd_is_hex($accent)) {
            continue;
        }
        $cache[(string) $id] = [
            'name'   => (string) ($s['name'] ?? $id),
            'ground' => $ground,
            'ink'    => $ink,
            'accent' => $accent,
            'locked' => false,
        ];
    }
    return $cache;
}

/** What the picker in the bar offers: id => label. */
function ghostd_themes(): array
{
    $schemes = ghostd_schemes();
    $out = [];
    foreach ($schemes as $id => $s) {
        $out[$id] = $s['name'];
    }
    return $out;
}

/** The one a page starts on: the configured theme if it exists, else the first. */
function ghostd_default_theme(): string
{
    $themes = ghostd_themes();
    $want = (string) ghostd_config()['theme'];
    return isset($themes[$want]) ? $want : (string) array_key_first($themes);
}

/**
 * A <style> block defining every scheme as a [data-theme] rule.
 *
 * The three stored colours, plus the six the site needs derived from them.
 * Emitted BEFORE the branding override, which is at least as specific and so
 * still wins.
 */
function ghostd_schemes_css(): string
{
    $schemes = ghostd_schemes();
    $out = [];
    foreach ($schemes as $id => $s) {
        $ground = $s['ground'];
        $ink    = $s['ink'];
        $accent = $s['accent'];
        $light  = ghostd_luma($ground) > 0.5;

        // Panels sit just off the ground, lines just off the panels - lighter
        // on a dark scheme, darker on a light one, so the same three colours
        // work either way round.
        $panel = ghostd_shade($ground, $light ? -0.05 : 0.06);
        $raise = ghostd_shade($ground, $light ? -0.10 : 0.12);
        $well  = ghostd_shade($ground, $light ? -0.03 : -0.35);
        $line  = ghostd_shade($ground, $light ? -0.20 : 0.20);
        $lined = ghostd_shade($ground, $light ? -0.12 : 0.12);

        [$ar, $ag, $ab] = ghostd_hex_rgb($accent);
        $onAccent = ghostd_luma($accent) > 0.55 ? '#0b0e11' : '#ffffff';

        // Dim and faint text are the ink at lower contrast, not a grey guess:
        // on a light scheme they go towards the ground, on a dark one likewise.
        $dim   = ghostd_shade($ink, $light ? 0.35 : -0.30);
        $faint = ghostd_shade($ink, $light ? 0.55 : -0.50);

        $css = [
            "--ground: $ground;",
            "--panel: $panel;",
            "--raise: $raise;",
            "--well: $well;",
            "--ink: $ink;",
            "--dim: $dim;",
            "--faint: $faint;",
            "--line: $line;",
            "--line-dim: $lined;",
            "--accent: $accent;",
            "--accent-dim: rgba($ar, $ag, $ab, .14);",
            "--on-accent: $onAccent;",
        ];

        $sel = '[data-theme="' . htmlspecialchars($id, ENT_QUOTES) . '"]';
        $out[] = $sel . ' {' . implode(' ', $css) . '}';
    }

    return "<style>\n" . implode("\n", $out) . "\n</style>\n";
}
