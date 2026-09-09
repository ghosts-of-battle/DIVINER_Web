<?php
/**
 * How the site looks for this unit: its name, its logo, its colours, and the
 * picture behind the login page.
 *
 * IN THE DATABASE, NOT ON DISK. The docroot is read-only to the web server on
 * purpose (nginx serves it, php-fpm runs as another user, and neither should
 * be able to write into a folder it also serves). Putting branding in Mongo
 * means an admin can change it from the site with no shell, no upload folder,
 * and no permissions to get wrong - and it survives a redeploy of the code.
 *
 * TWO DOCUMENTS, DELIBERATELY. <unit>.web is small - names, colours, flags -
 * and is read on every page. <unit>.web.assets holds the logo and background
 * as data, and is read only by the asset route. A megabyte of picture must not
 * be fetched to draw a table.
 *
 * NOTHING HERE IS REQUIRED. With no document at all the site looks exactly as
 * it did before any of this existed.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/** The colour tokens an admin may override, and what to call them. */
const GHOSTD_BRAND_COLOURS = [
    'accent' => 'Accent',
    'ground' => 'Page background',
    'panel'  => 'Panels',
    'ink'    => 'Text',
    'line'   => 'Borders',
    'hot'    => 'Warnings',
];

const GHOSTD_BRAND_DEFAULTS = [
    'unitName'    => '',
    'tagline'     => '',
    'theme'       => '',       // a preset to base the custom colours on
    'colours'     => [],       // token => #rrggbb
    'hidePassword' => false,   // the shared password stops being offered
    'loginFit'    => 'cover',  // cover | contain
    'loginDim'    => 55,       // percent of black over the background picture

    // How big the logo is drawn, as a percentage of its natural place. Two
    // scales because the two places are not alike: the login card is the only
    // thing on the page and can carry a big mark, while the bar is 40px of
    // furniture above every table and a logo that fights it is a nuisance.
    'logoLoginScale' => 100,   // 96px tall at 100%
    'logoBarScale'   => 100,   // 26px tall at 100%
    'assetsAt'    => '',       // stamp, so a changed picture busts the cache
];

/**
 * The small document. Read once per request, and never fatal: if the database
 * is unreachable the site must still draw, just without the branding.
 */
function ghostd_branding(): array
{
    static $b = null;
    if ($b !== null) {
        return $b;
    }
    $b = GHOSTD_BRAND_DEFAULTS;
    try {
        $doc = ghostd_get(ghostd_config()['unit'] . '.web');
        if (is_array($doc)) {
            foreach (GHOSTD_BRAND_DEFAULTS as $k => $default) {
                if (array_key_exists($k, $doc)) {
                    $b[$k] = $doc[$k];
                }
            }
        }
    } catch (Throwable $e) {
        // Keep the defaults. A branding lookup is never worth an error page.
    }
    $b['colours'] = is_array($b['colours'] ?? null) ? $b['colours'] : [];
    return $b;
}

/** True when the shared-password form should not be offered at all. */
function ghostd_password_hidden(): bool
{
    return (bool) ghostd_branding()['hidePassword'];
}

function ghostd_unit_name(): string
{
    $n = trim((string) ghostd_branding()['unitName']);
    return $n !== '' ? $n : 'TAC//PAC';
}

/** #rrggbb only - anything else is not going into a stylesheet. */
function ghostd_is_hex(string $v): bool
{
    return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $v);
}

/**
 * The custom palette as a <style> block, or ''.
 *
 * Only the six named tokens, only #rrggbb, and the derived tokens
 * (--accent-dim and friends) are computed here rather than stored, so an admin
 * picking one colour still gets a coherent set.
 */
function ghostd_branding_css(): string
{
    $b = ghostd_branding();
    $out = [];

    // The logo scales are always emitted: they apply whether or not the unit
    // has picked any colours, so this block cannot return early on colours.
    $login = max(20, min(400, (int) $b['logoLoginScale']));
    $bar   = max(20, min(400, (int) $b['logoBarScale']));
    $out[] = '  --logo-login: ' . round(96 * $login / 100) . 'px;';
    $out[] = '  --logo-bar: ' . round(26 * $bar / 100) . 'px;';

    foreach (GHOSTD_BRAND_COLOURS as $token => $_label) {
        $v = (string) ($b['colours'][$token] ?? '');
        if ($v === '' || !ghostd_is_hex($v)) {
            continue;
        }
        $out[] = "  --$token: $v;";

        if ($token === 'accent') {
            [$r, $g, $bl] = ghostd_hex_rgb($v);
            $out[] = "  --accent-dim: rgba($r, $g, $bl, .14);";
            // Black or white on the accent, whichever can actually be read.
            $out[] = '  --on-accent: ' . (ghostd_luma($v) > 0.55 ? '#0b0e11' : '#ffffff') . ';';
        }
        if ($token === 'hot') {
            [$r, $g, $bl] = ghostd_hex_rgb($v);
            $out[] = "  --hot-dim: rgba($r, $g, $bl, .13);";
        }
        if ($token === 'ground') {
            $out[] = "  --well: " . ghostd_shade($v, -0.35) . ';';
        }
        if ($token === 'panel') {
            $out[] = "  --raise: " . ghostd_shade($v, 0.12) . ';';
        }
    }

    // [data-theme] wins over :root, so the override has to be at least as
    // specific or picking a preset would silently undo the unit's colours.
    return "<style>:root, :root[data-theme] {\n" . implode("\n", $out) . "\n}</style>\n";
}

function ghostd_hex_rgb(string $hex): array
{
    return [
        (int) hexdec(substr($hex, 1, 2)),
        (int) hexdec(substr($hex, 3, 2)),
        (int) hexdec(substr($hex, 5, 2)),
    ];
}

/** Perceived brightness, 0..1 - for deciding what colour text sits on top. */
function ghostd_luma(string $hex): float
{
    [$r, $g, $b] = ghostd_hex_rgb($hex);
    return (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
}

/** Lighten (positive) or darken (negative) a hex colour. */
function ghostd_shade(string $hex, float $by): string
{
    [$r, $g, $b] = ghostd_hex_rgb($hex);
    $f = static function (int $c) use ($by): int {
        $c = $by >= 0 ? $c + (255 - $c) * $by : $c * (1 + $by);
        return max(0, min(255, (int) round($c)));
    };
    return sprintf('#%02x%02x%02x', $f($r), $f($g), $f($b));
}

// ---- the pictures ---------------------------------------------------------

/**
 * The pictures a unit can store.
 *
 * THE BAR LOGO AND THE LOGIN LOGO ARE SEPARATE. They are asked to do different
 * jobs: the bar wants a compact mark that reads at 26px beside text, the login
 * card wants the full lockup with wordmark. One image doing both is either
 * unreadable in the bar or underwhelming on the door. The login card falls
 * back to the bar logo when only one has been uploaded.
 */
const GHOSTD_ASSET_SLOTS = ['logo', 'loginLogo', 'background'];

const GHOSTD_ASSET_TYPES = [
    'image/png'  => 'png',
    'image/jpeg' => 'jpg',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    'image/svg+xml' => 'svg',
];

/** One stored picture: ['mime' => ..., 'data' => base64], or null. */
function ghostd_brand_asset(string $which): ?array
{
    if (!in_array($which, GHOSTD_ASSET_SLOTS, true)) {
        return null;
    }
    try {
        $doc = ghostd_get(ghostd_config()['unit'] . '.web.assets');
    } catch (Throwable $e) {
        return null;
    }
    $a = $doc[$which] ?? null;
    if (!is_array($a) || !isset($a['mime'], $a['data'])) {
        return null;
    }
    if (!isset(GHOSTD_ASSET_TYPES[(string) $a['mime']])) {
        return null;
    }
    return ['mime' => (string) $a['mime'], 'data' => (string) $a['data']];
}

/** Whether a picture exists, without fetching it. */
function ghostd_has_asset(string $which): bool
{
    $b = ghostd_branding();
    return in_array($which, (array) ($b['assets'] ?? []), true)
        || ghostd_brand_asset($which) !== null;
}

/**
 * The URL of a stored picture, stamped so a change is not served from cache.
 *
 * RAW AMPERSANDS. This is used both in HTML attributes and inside a CSS
 * url() - and CSS does not decode HTML entities, so an &amp; here becomes a
 * literal parameter named "amp;which" and the image 404s. Callers putting it
 * in an attribute escape it themselves.
 */
function ghostd_asset_url(string $which): string
{
    $stamp = (string) ghostd_branding()['assetsAt'];
    return '?page=asset&which=' . rawurlencode($which)
         . ($stamp !== '' ? '&v=' . rawurlencode($stamp) : '');
}

/** The login card's picture: its own if set, otherwise the bar logo. */
function ghostd_login_logo(): ?string
{
    foreach (['loginLogo', 'logo'] as $which) {
        if (ghostd_brand_asset($which) !== null) {
            return $which;
        }
    }
    return null;
}

/** The brand block for the bar: the logo if there is one, else the name. */
function ghostd_brand_html(): string
{
    $name = ghostd_unit_name();
    if (ghostd_brand_asset('logo') !== null) {
        return '<img class="logo" src="' . h(ghostd_asset_url('logo')) . '" alt="' . h($name) . '">';
    }
    // The house style: the unit's name, with the second word dimmed.
    $parts = explode(' ', $name, 2);
    return h($parts[0]) . (isset($parts[1]) ? ' <span class="dim">' . h($parts[1]) . '</span>' : '');
}
