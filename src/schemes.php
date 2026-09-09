<?php
/**
 * The site's colour schemes come from the unit's TAC//PAD schemes.
 *
 * ONE SET OF COLOURS FOR THE UNIT (user, 2026-09-09). <unit>.schemes is what
 * the TAC//PAD is painted with in game; the site had five presets of its own
 * with no relation to it, so the same unit looked like two different products.
 * The document is the list now, and the site's own presets are only the
 * fallback for a unit that has not written one.
 *
 * A SCHEME IS THREE COLOURS - ground, ink, accent - because that is what the
 * game stores. The site needs six, so panel, line and hot are derived from
 * those three the same way the branding override derives them: shade the
 * ground for panels and lines, and keep the site's warning red, which is a
 * signal rather than a style.
 *
 * THE BRANDING OVERRIDE STILL WINS. ghostd_branding_css() is emitted after
 * these and is at least as specific, so a unit that has set its own colours
 * keeps them whatever scheme is picked.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/branding.php';

/**
 * THE GAME'S OWN SIX, for a unit with no schemes document.
 *
 * Taken from ghostD_tacpad_fnc_theme - the same ids, the same names and the
 * same colours the TAC//PAD paints itself with, converted from its 0-1 RGB to
 * hex. The site had five presets of its own invention; a unit looking at the
 * pad and the site should not be looking at two different products.
 *
 * If a colour changes in fnc_theme, change it here. Nothing checks that they
 * agree, which is exactly why the unit's <unit>.schemes document beats both.
 */
const GHOSTD_GAME_SCHEMES = [
    'light'      => ['name' => 'LIGHT',       'ground' => '#f3f2f2', 'ink' => '#201e1d', 'accent' => '#ec3013'],
    'olive'      => ['name' => 'OLIVE',       'ground' => '#e8e7e2', 'ink' => '#16281d', 'accent' => '#b5cc4a'],
    'sand'       => ['name' => 'SAND',        'ground' => '#efece4', 'ink' => '#2b2119', 'accent' => '#d99427'],
    'dark'       => ['name' => 'DARK',        'ground' => '#141514', 'ink' => '#e6e5e2', 'accent' => '#ff563c'],
    'nightOlive' => ['name' => 'NIGHT OLIVE', 'ground' => '#101411', 'ink' => '#d9e0d4', 'accent' => '#9cb43c'],
    'nightSand'  => ['name' => 'NIGHT SAND',  'ground' => '#161310', 'ink' => '#e5e0d6', 'accent' => '#c78221'],
];

/**
 * The unit's schemes, as [id => [name, ground, ink, accent]].
 *
 * Empty when the unit has none - the caller then uses the site's presets.
 */
function ghostd_schemes(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    // THE GAME'S SIX ARE ALWAYS THERE. The unit's document adds its own and
    // replaces any that share an id, which is the same order the pad uses.
    $cache = GHOSTD_GAME_SCHEMES;
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
