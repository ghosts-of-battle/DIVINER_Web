<?php
/**
 * The unit's public home page: what a visitor sees before signing in.
 *
 * IN THE DATABASE, LIKE BRANDING. The content lives in <unit>.web.home so an
 * admin writes it from the site, and it follows the database rather than
 * this server. It is its own document - <unit>.web is read on every page
 * and must stay small; a page of prose does not belong in it.
 *
 * BLOCKS, NOT A FREE PAGE. The page is the unit's logo and name from Branding
 * over a fixed list of sections - today one, About - each a heading and a
 * piece of HTML written in the editor on the Web settings page. An empty
 * block is simply not drawn. Another section is one more line in the list.
 *
 * THE HTML IS CLEANED, on the way in and on the way out. The editor is used
 * by admins, but "admin" is a shared password on some deployments, and the
 * page is public: a pasted <script> must never reach a visitor's browser.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/** The sections of the page, in the order they are drawn: key => label. */
const GHOSTD_HOME_BLOCKS = [
    'about' => 'About',
];

/** The layouts an admin may pick: key => [label, what it looks like]. The key is the card's l-* class. */
const GHOSTD_HOME_LAYOUTS = [
    'stack'  => ['Stacked', 'the logo and name on top, the words under them, the buttons at the bottom'],
    'split'     => ['Split, words right', 'the logo, name and buttons in a column on the left, the words in a box on the right'],
    'splitleft' => ['Split, words left', 'the words in a box on the left, the logo, name and buttons in a column on the right'],
    'banner' => ['Banner', 'the logo beside the name across the top, the words below, the buttons at the bottom'],
    'cards'  => ['Cards', 'the logo and name in one panel, the words in a second, the buttons in a third'],
];

/** Where the card sits across the window, for any layout: key => label. */
const GHOSTD_HOME_ALIGNS = ['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'];

/** Width of the page as a percentage of the window, and its height. */
const GHOSTD_HOME_WIDTH  = ['min' => 30, 'max' => 100, 'default' => 60];
const GHOSTD_HOME_HEIGHT = ['min' => 0,  'max' => 100, 'default' => 0];   // 0: as tall as the words

/** Longest HTML one block may hold; a page, not a wiki. */
const GHOSTD_HOME_BLOCK_MAX = 20000;

/** Tags a block may contain. Anything else is stripped, its text kept. */
const GHOSTD_HTML_TAGS = [
    'p', 'br', 'hr', 'b', 'strong', 'i', 'em', 'u', 's', 'sup', 'sub',
    'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'a', 'img', 'blockquote',
    'span', 'div', 'code', 'pre', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
];

/** Tags that have no closing partner. */
const GHOSTD_HTML_VOID = ['br', 'hr', 'img'];

/**
 * The home document with defaults filled in: ['enabled' => bool,
 * 'layout' => key, 'align' => left|center|right, 'width' => %, 'height' => %, 'headings' => [key =>
 * string], 'blocks' => [key => html]].
 * Never fatal - a visitor must reach the sign-in page even if the database
 * is down, so a failed read is an empty, disabled page.
 */
function ghostd_home(): array
{
    static $home = null;
    if ($home !== null) {
        return $home;
    }
    $home = [
        'enabled'  => false,
        'layout'   => 'stack',
        'align'    => 'center',
        'width'    => GHOSTD_HOME_WIDTH['default'],
        'height'   => GHOSTD_HOME_HEIGHT['default'],
        'headings' => [],
        'blocks'   => [],
    ];
    foreach (GHOSTD_HOME_BLOCKS as $key => $label) {
        $home['headings'][$key] = $label;
        $home['blocks'][$key]   = '';
    }
    try {
        $doc = ghostd_get(ghostd_config()['unit'] . '.web.home');
    } catch (Throwable $e) {
        return $home;
    }
    if (!is_array($doc)) {
        return $home;
    }
    $home['enabled'] = (bool) ($doc['enabled'] ?? false);
    if (isset(GHOSTD_HOME_LAYOUTS[(string) ($doc['layout'] ?? '')])) {
        $home['layout'] = (string) $doc['layout'];
    }
    if (isset(GHOSTD_HOME_ALIGNS[(string) ($doc['align'] ?? '')])) {
        $home['align'] = (string) $doc['align'];
    }
    $home['width']  = max(GHOSTD_HOME_WIDTH['min'],  min(GHOSTD_HOME_WIDTH['max'],  (int) ($doc['width']  ?? $home['width'])));
    $home['height'] = max(GHOSTD_HOME_HEIGHT['min'], min(GHOSTD_HOME_HEIGHT['max'], (int) ($doc['height'] ?? $home['height'])));
    foreach (GHOSTD_HOME_BLOCKS as $key => $label) {
        if (isset($doc['headings'][$key]) && is_string($doc['headings'][$key])) {
            $home['headings'][$key] = $doc['headings'][$key];
        }
        if (isset($doc['blocks'][$key]) && is_string($doc['blocks'][$key])) {
            $home['blocks'][$key] = ghostd_html_clean($doc['blocks'][$key]);
        }
    }
    return $home;
}

/**
 * HTML reduced to the tags in GHOSTD_HTML_TAGS, with only href, target, src,
 * alt, colspan, rowspan and a text-align style kept (what the Wysi editor
 * writes), no javascript: or data: addresses, and every tag closed - an unclosed <div> in a block must not swallow the footer.
 *
 * Regular expressions rather than DOMDocument because the dom extension is a
 * separate package on the Red Hat family and DEPLOY.md does not install it;
 * a home page must not be the one thing that needs another yum.
 */
function ghostd_html_clean(string $html): string
{
    // Whole elements whose content is never wanted, and comments.
    $html = (string) preg_replace('/<(script|style|iframe|object|embed|noscript|template)\b[^>]*>.*?<\/\1\s*>/is', '', $html);
    $html = (string) preg_replace('/<!--.*?-->/s', '', $html);

    $open = [];   // the tags still open, innermost last
    $out = (string) preg_replace_callback(
        '/<\s*(\/?)\s*([a-zA-Z][a-zA-Z0-9]*)\b([^>]*?)\/?\s*>/s',
        static function (array $m) use (&$open): string {
            $tag = strtolower($m[2]);
            if (!in_array($tag, GHOSTD_HTML_TAGS, true)) {
                return '';
            }
            $void = in_array($tag, GHOSTD_HTML_VOID, true);
            if ($m[1] === '/') {
                if ($void || !in_array($tag, $open, true)) {
                    return '';                      // closing nothing, or something never opened
                }
                $close = '';
                while (($last = array_pop($open)) !== null) {
                    $close .= '</' . $last . '>';   // closes anything left open inside it
                    if ($last === $tag) {
                        break;
                    }
                }
                return $close;
            }

            $keep = '';
            if (preg_match_all('/([a-zA-Z-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/', $m[3], $attrs, PREG_SET_ORDER)) {
                foreach ($attrs as $a) {
                    $name  = strtolower($a[1]);
                    $value = html_entity_decode(($a[2] ?? '') !== '' ? $a[2] : (($a[3] ?? '') !== '' ? $a[3] : ($a[4] ?? '')), ENT_QUOTES, 'UTF-8');
                    $ok = ($tag === 'a' && ($name === 'href' || $name === 'target'))
                       || ($tag === 'img' && ($name === 'src' || $name === 'alt'))
                       || (($tag === 'td' || $tag === 'th') && ($name === 'colspan' || $name === 'rowspan'))
                       || ($name === 'style' && in_array($tag, ['p', 'h1', 'h2', 'h3', 'h4', 'div', 'li', 'ul', 'ol', 'blockquote'], true));
                    if (!$ok) {
                        continue;
                    }
                    if ($name === 'style') {
                        if (!preg_match('/^\s*text-align\s*:\s*(left|center|right|justify)\s*;?\s*$/i', $value, $al)) {
                            continue;               // the one style a block may carry
                        }
                        $value = 'text-align: ' . strtolower($al[1]);
                    }
                    if ($name === 'target' && $value !== '_blank') {
                        continue;
                    }
                    if ($name === 'href' || $name === 'src') {
                        $value = trim($value);
                        if (!preg_match('#^(https?://|mailto:|/|\?|\#)#i', $value)) {
                            continue;               // javascript:, data:, vbscript: and friends
                        }
                    }
                    $keep .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
                }
            }
            if ($tag === 'a' && $keep !== '') {
                $keep .= ' rel="noopener"';
            }
            if ($tag === 'img' && strpos($keep, ' src=') === false) {
                return '';                          // a picture with no address is nothing
            }
            if (!$void) {
                $open[] = $tag;
            }
            return '<' . $tag . $keep . '>';
        },
        $html
    );
    while (($last = array_pop($open)) !== null) {
        $out .= '</' . $last . '>';
    }
    return trim($out);
}
