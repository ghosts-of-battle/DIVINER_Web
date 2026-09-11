<?php
/**
 * Arma structured text <-> the HTML a browser editor writes.
 *
 * The welcome screen is one block of Arma structured text: the game's own
 * <t size color align font underline>, <br/>, <img image> and <a href> tags,
 * with the writer's returns as line breaks (see ghostD_pac_fnc_welcomeShow).
 * The editor on the web is Wysi, which writes ordinary HTML - <h2>, <p
 * style="text-align:center">, <strong>, <span style="color:#...">. These two
 * functions carry a briefing between the two spellings, so an admin edits
 * it in a real editor and the game reads what it always read.
 *
 * ONE LINE PER BLOCK. A paragraph or heading in the editor is one line in
 * the document, and a line in the document is one paragraph in the editor.
 * A break inside a paragraph is a literal <br/>, never a newline, so the
 * line stays one line and its wrapping <t> stays whole.
 *
 * WHAT DOES NOT CROSS. Arma has no italic, no lists and no indent, so the
 * welcome editor is not offered them; a list pasted in becomes lines with
 * a bullet character. Anything else unknown is dropped and its text kept.
 */

declare(strict_types=1);

/** Heading level => the <t size> it stands for. h3 is the old "Heading" button, h1 the old "Bigger". */
const GHOSTD_ARMA_HEADINGS = ['h1' => '1.4', 'h2' => '1.25', 'h3' => '1.15', 'h4' => '1.05'];

/** The attribute pairs of a tag: name => value, names lower-cased, values unquoted. */
function ghostd_tag_attrs(string $raw): array
{
    $out = [];
    if (preg_match_all('/([a-zA-Z-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>\/]+))/', $raw, $m, PREG_SET_ORDER)) {
        foreach ($m as $a) {
            $v = ($a[2] ?? '') !== '' ? $a[2] : (($a[3] ?? '') !== '' ? $a[3] : ($a[4] ?? ''));
            $out[strtolower($a[1])] = html_entity_decode($v, ENT_QUOTES, 'UTF-8');
        }
    }
    return $out;
}

/** '#rrggbb' from #rgb, #rrggbb or rgb(r, g, b); null for anything else. */
function ghostd_css_colour(string $v): ?string
{
    $v = trim($v);
    if (preg_match('/^#([0-9a-f]{6})$/i', $v, $m)) {
        return '#' . strtolower($m[1]);
    }
    if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $v, $m)) {
        return strtolower('#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3]);
    }
    if (preg_match('/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i', $v, $m)) {
        return sprintf('#%02x%02x%02x', min(255, (int) $m[1]), min(255, (int) $m[2]), min(255, (int) $m[3]));
    }
    return null;
}

/** The properties of a style attribute: name => value, lower-cased names. */
function ghostd_css_props(string $style): array
{
    $out = [];
    foreach (explode(';', $style) as $decl) {
        $p = explode(':', $decl, 2);
        if (count($p) === 2) {
            $out[strtolower(trim($p[0]))] = trim($p[1]);
        }
    }
    return $out;
}

/** An Arma <t size> from a CSS font-size: em and rem as they are, px against 16. */
function ghostd_css_size(string $v): ?string
{
    if (preg_match('/^([0-9.]+)\s*(em|rem|px)?$/i', trim($v), $m)) {
        $n = (float) $m[1];
        if (($m[2] ?? '') !== '' && strtolower($m[2]) === 'px') {
            $n = $n / 16;
        }
        if ($n > 0.2 && $n < 5) {
            return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
        }
    }
    return null;
}

/**
 * Browser HTML, as Wysi writes it, as one block of Arma structured text with
 * one line per paragraph. Only what the game can show is written; the rest
 * is dropped and its text kept.
 */
function ghostd_html_to_arma(string $html): string
{
    $html  = str_replace(["\r\n", "\r"], "\n", $html);
    $lines = [];
    $cur   = '';        // the line being built
    $attr  = [];        // the <t> that wraps the whole line: size, align
    $stack = [];        // closers for the inline tags open in $cur
    $inBlock = false;
    $lists = [];        // open lists: ['ul'|'ol', count]

    $flush = static function () use (&$lines, &$cur, &$attr, &$stack, &$inBlock): void {
        while ($stack !== []) {
            $cur .= array_pop($stack);
        }
        $line = trim($cur);
        if ($line !== '' && $attr !== []) {
            $line = '<t ' . implode(' ', $attr) . '>' . $line . '</t>';
        }
        $lines[] = $line;
        $cur = '';
        $attr = [];
        $inBlock = false;
    };

    $blocks = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'blockquote', 'li', 'pre'];
    $tokens = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

    foreach ($tokens as $tok) {
        if ($tok[0] !== '<') {
            $text = html_entity_decode($tok, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = str_replace("\xC2\xA0", ' ', $text);
            if (!$inBlock && trim($text) === '') {
                continue;                       // the pretty newlines between blocks
            }
            $text = str_replace("\n", ' ', $text);
            $cur .= str_replace(['<', '>'], ['&lt;', '&gt;'], $text);
            $inBlock = true;
            continue;
        }
        if (!preg_match('/^<\s*(\/?)\s*([a-zA-Z][a-zA-Z0-9]*)\b([^>]*?)\/?\s*>$/s', $tok, $m)) {
            continue;
        }
        $close = $m[1] === '/';
        $tag   = strtolower($m[2]);
        $a     = $close ? [] : ghostd_tag_attrs($m[3]);
        $css   = isset($a['style']) ? ghostd_css_props($a['style']) : [];

        if (in_array($tag, $blocks, true)) {
            if ($close) {
                if ($inBlock) {
                    $flush();
                }
                continue;
            }
            if ($inBlock) {
                $flush();                       // a block inside a block: a new line
            }
            $inBlock = true;
            if (isset(GHOSTD_ARMA_HEADINGS[$tag])) {
                $attr[] = "size='" . GHOSTD_ARMA_HEADINGS[$tag] . "'";
            }
            $al = strtolower($css['text-align'] ?? '');
            if ($al === 'center' || $al === 'right') {
                $attr[] = "align='" . $al . "'";
            }
            if ($tag === 'li' && $lists !== []) {
                $i = count($lists) - 1;
                $lists[$i][1]++;
                $cur .= $lists[$i][0] === 'ol' ? $lists[$i][1] . '. ' : "\xE2\x80\xA2 ";
            }
            continue;
        }

        switch ($tag) {
            case 'ul':
            case 'ol':
                if ($close) {
                    array_pop($lists);
                } else {
                    if ($inBlock) {
                        $flush();
                    }
                    $lists[] = [$tag, 0];
                }
                break;
            case 'br':
                // Wysi spells an empty paragraph <p><br></p>: a break with
                // nothing before it is a blank line, not a break.
                if ($inBlock && trim($cur) !== '') {
                    $cur .= '<br/>';
                }
                break;
            case 'hr':
                if ($inBlock) {
                    $flush();
                }
                $lines[] = '';
                break;
            case 'img':
                $src = trim((string) ($a['src'] ?? ''));
                if (!$close && $src !== '') {
                    $cur .= "<img image='" . str_replace("'", '', $src) . "'/>";
                    $inBlock = true;
                }
                break;
            case 'a':
                if ($close) {
                    $cur .= array_pop($stack) ?? '';
                } else {
                    $href = trim((string) ($a['href'] ?? ''));
                    $cur .= $href !== '' ? "<a href='" . str_replace("'", '', $href) . "'>" : '';
                    $stack[] = $href !== '' ? '</a>' : '';
                    $inBlock = true;
                }
                break;
            case 'strong':
            case 'b':
                if ($close) {
                    $cur .= array_pop($stack) ?? '';
                } else {
                    $cur .= "<t font='PuristaBold'>";
                    $stack[] = '</t>';
                }
                break;
            case 'u':
                if ($close) {
                    $cur .= array_pop($stack) ?? '';
                } else {
                    $cur .= "<t underline='1'>";
                    $stack[] = '</t>';
                }
                break;
            default:
                // span, font, em, i, s and anything unknown: only a colour or a
                // size on it means anything to the game.
                if ($close) {
                    $cur .= array_pop($stack) ?? '';
                    break;
                }
                $t = [];
                $colour = ghostd_css_colour((string) ($css['color'] ?? $a['color'] ?? ''));
                if ($colour !== null) {
                    $t[] = "color='" . $colour . "'";
                }
                $size = ghostd_css_size((string) ($css['font-size'] ?? ''));
                if ($size !== null) {
                    $t[] = "size='" . $size . "'";
                }
                if (($css['font-weight'] ?? '') === 'bold') {
                    $t[] = "font='PuristaBold'";
                }
                $cur .= $t === [] ? '' : '<t ' . implode(' ', $t) . '>';
                $stack[] = $t === [] ? '' : '</t>';
        }
    }
    if ($inBlock || trim($cur) !== '') {
        $flush();
    }
    return rtrim(implode("\n", $lines));
}

/**
 * Arma structured text as the HTML the editor shows: one paragraph per line,
 * a line wrapped whole in a heading-sized <t> as that heading, its align as
 * the paragraph's, and the inline tags as the spans Wysi is told to keep.
 */
function ghostd_arma_to_html(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $out  = [];
    $sizes = array_flip(GHOSTD_ARMA_HEADINGS);       // '1.15' => 'h3'

    foreach (explode("\n", $text) as $line) {
        $line = trim($line);
        if ($line === '') {
            $out[] = '<p><br></p>';
            continue;
        }
        $block = 'p';
        $style = '';
        // A <t> around the whole line carries the line's own size and align.
        if (preg_match('/^<t\s+([^>]*)>(.*)<\/t>$/is', $line, $m)
            && substr_count($m[2], '<t') === substr_count($m[2], '</t>')) {
            $a = ghostd_tag_attrs($m[1]);
            $rest = [];
            foreach ($a as $k => $v) {
                if ($k === 'size' && isset($sizes[$v])) {
                    $block = $sizes[$v];
                } elseif ($k === 'align' && ($v === 'center' || $v === 'right')) {
                    $style = ' style="text-align: ' . $v . ';"';
                } elseif ($k === 'align') {
                    // left: the default, nothing to say
                } else {
                    $rest[] = $k . "='" . str_replace("'", '', $v) . "'";
                }
            }
            $line = $rest === [] ? $m[2] : '<t ' . implode(' ', $rest) . '>' . $m[2] . '</t>';
        }
        $out[] = '<' . $block . $style . '>' . ghostd_arma_inline_to_html($line) . '</' . $block . '>';
    }
    return implode("\n", $out);
}

/** The inline tags of one line - <t>, <br/>, <img>, <a> - as HTML, text escaped. */
function ghostd_arma_inline_to_html(string $line): string
{
    $out   = '';
    $stack = [];
    $tokens = preg_split('/(<[^>]+>)/', $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
    foreach ($tokens as $tok) {
        if ($tok[0] !== '<') {
            $out .= htmlspecialchars($tok, ENT_NOQUOTES, 'UTF-8', false);
            continue;
        }
        if (!preg_match('/^<\s*(\/?)\s*([a-zA-Z][a-zA-Z0-9]*)\b([^>]*?)\/?\s*>$/s', $tok, $m)) {
            $out .= htmlspecialchars($tok, ENT_NOQUOTES, 'UTF-8', false);
            continue;
        }
        $close = $m[1] === '/';
        $tag   = strtolower($m[2]);
        $a     = $close ? [] : ghostd_tag_attrs($m[3]);
        switch ($tag) {
            case 'br':
                $out .= '<br>';
                break;
            case 'img':
                $src = str_replace('"', '', (string) ($a['image'] ?? $a['src'] ?? ''));
                if ($src !== '') {
                    $out .= '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="">';
                }
                break;
            case 'a':
                if ($close) {
                    $out .= array_pop($stack) ?? '';
                } else {
                    $href = (string) ($a['href'] ?? '');
                    $out .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">';
                    $stack[] = '</a>';
                }
                break;
            case 't':
                if ($close) {
                    $out .= array_pop($stack) ?? '';
                    break;
                }
                $open = '';
                $shut = '';
                $css  = [];
                $colour = ghostd_css_colour((string) ($a['color'] ?? ''));
                if ($colour !== null) {
                    $css[] = 'color: ' . $colour;
                }
                if (isset($a['size']) && is_numeric($a['size'])) {
                    $css[] = 'font-size: ' . rtrim(rtrim(number_format((float) $a['size'], 2, '.', ''), '0'), '.') . 'em';
                }
                if ($css !== []) {
                    $open .= '<span style="' . implode('; ', $css) . ';">';
                    $shut  = '</span>' . $shut;
                }
                if (isset($a['font']) && stripos($a['font'], 'bold') !== false) {
                    $open .= '<strong>';
                    $shut  = '</strong>' . $shut;
                }
                if (($a['underline'] ?? '') === '1' || ($a['underline'] ?? '') === 'true') {
                    $open .= '<u>';
                    $shut  = '</u>' . $shut;
                }
                $out .= $open;
                $stack[] = $shut;
                break;
            default:
                // Not a tag the game reads: show it as text rather than lose it.
                $out .= htmlspecialchars($tok, ENT_NOQUOTES, 'UTF-8', false);
        }
    }
    while ($stack !== []) {
        $out .= array_pop($stack);
    }
    return $out;
}
