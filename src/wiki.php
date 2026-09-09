<?php
/**
 * The wiki panel: the page of the published documentation that explains
 * whatever the admin is currently looking at.
 *
 * FETCHED, NOT FRAMED. GitHub serves the wiki with `X-Frame-Options: deny`
 * and `frame-ancestors 'none'`, so an <iframe> shows nothing at all. The raw
 * markdown, however, is public at raw.githubusercontent.com/wiki/... - so the
 * server fetches that and renders it here.
 *
 * CACHED ON DISK, because a panel that costs an HTTPS round trip on every page
 * view makes the whole site feel slow, and the wiki changes a few times a
 * month. A stale copy is served when GitHub cannot be reached: out-of-date
 * help beats an error box.
 *
 * A SMALL MARKDOWN SUBSET, not a library. Same reason as db.php and steam.php:
 * no Composer. Everything is escaped before any markup is put back, so nothing
 * the wiki says can inject HTML into this page.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** Which wiki page explains which screen. */
const GHOSTD_WIKI_PAGES = [
    'dashboard'     => 'TAC-PAC',
    'roster'        => 'TAC-PAC',
    'templates'     => 'Messaging-Deck',
    'template_edit' => 'Messaging-Deck',
    'documents'     => 'Quick-Start-Database',
    'document'      => 'Quick-Start-Database',
];

function ghostd_wiki_enabled(): bool
{
    return (bool) ghostd_config()['wiki_panel'];
}

/** The wiki page for a screen, honouring an explicit ?wiki= override. */
function ghostd_wiki_page_for(string $active): ?string
{
    $override = (string) ($_GET['wiki'] ?? '');
    if ($override !== '') {
        // Page names only - no slashes, no traversal, no absolute URLs.
        return preg_match('/^[A-Za-z0-9._-]{1,80}$/', $override) ? $override : null;
    }
    return GHOSTD_WIKI_PAGES[$active] ?? null;
}

function ghostd_wiki_url(string $page): string
{
    return 'https://raw.githubusercontent.com/wiki/' . ghostd_config()['wiki_repo'] . '/' . rawurlencode($page) . '.md';
}

function ghostd_wiki_web_url(string $page): string
{
    return 'https://github.com/' . ghostd_config()['wiki_repo'] . '/wiki/' . rawurlencode($page);
}

/**
 * The page's markdown, from cache when it is fresh.
 *
 * Returns [markdown, whether it came from a stale cache], or [null, false].
 */
function ghostd_wiki_fetch(string $page): array
{
    $cfg = ghostd_config();
    $dir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'ghostd-wiki';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $file = $dir . DIRECTORY_SEPARATOR . preg_replace('/[^A-Za-z0-9._-]/', '_', $page) . '.md';
    $ttl  = (int) $cfg['wiki_ttl'];

    if (is_file($file) && (time() - (int) filemtime($file)) < $ttl) {
        $hit = file_get_contents($file);
        if (is_string($hit)) {
            return [$hit, false];
        }
    }

    require_once __DIR__ . '/steam.php';   // ghostd_http_get(), curl or streams
    $body = ghostd_http_get(ghostd_wiki_url($page));

    if (is_string($body) && $body !== '' && !str_starts_with($body, '404')) {
        @file_put_contents($file, $body);
        return [$body, false];
    }

    // GitHub unreachable or the page is gone: anything cached still helps.
    if (is_file($file)) {
        $stale = file_get_contents($file);
        if (is_string($stale)) {
            return [$stale, true];
        }
    }
    return [null, false];
}

/**
 * A markdown subset to HTML: headings, fenced code, tables, lists, quotes,
 * rules, links, bold, italic, inline code.
 *
 * The input is escaped FIRST and markup is only ever added afterwards, so a
 * wiki page containing <script> renders as the text "<script>".
 */
function ghostd_wiki_render(string $md, string $active): string
{
    $lines = preg_split('/\r?\n/', $md) ?: [];
    $out = [];
    $inCode = false;
    $listType = null;      // 'ul' | 'ol' | null
    $inTable = false;
    $para = [];

    $flushPara = static function () use (&$para, &$out): void {
        if ($para !== []) {
            $out[] = '<p>' . implode(' ', $para) . '</p>';
            $para = [];
        }
    };
    $closeList = static function () use (&$listType, &$out): void {
        if ($listType !== null) {
            $out[] = '</' . $listType . '>';
            $listType = null;
        }
    };
    $closeTable = static function () use (&$inTable, &$out): void {
        if ($inTable) {
            $out[] = '</tbody></table>';
            $inTable = false;
        }
    };

    foreach ($lines as $raw) {
        $line = rtrim($raw);

        // Fenced code: everything inside is literal.
        if (preg_match('/^\s*```/', $line)) {
            $flushPara(); $closeList(); $closeTable();
            $out[] = $inCode ? '</code></pre>' : '<pre><code>';
            $inCode = !$inCode;
            continue;
        }
        if ($inCode) {
            $out[] = h($line);
            continue;
        }

        if (trim($line) === '') { $flushPara(); $closeList(); $closeTable(); continue; }

        // Tables: | a | b |  with a |---|---| separator row.
        if (preg_match('/^\s*\|(.+)\|\s*$/', $line, $m)) {
            $cells = array_map('trim', explode('|', trim($m[1])));
            if (preg_match('/^[\s|:-]+$/', $line)) { continue; }   // the separator
            $flushPara(); $closeList();
            if (!$inTable) {
                $out[] = '<table class="wikitable"><thead><tr>';
                foreach ($cells as $c) { $out[] = '<th>' . ghostd_wiki_inline($c, $active) . '</th>'; }
                $out[] = '</tr></thead><tbody>';
                $inTable = true;
                continue;
            }
            $out[] = '<tr>';
            foreach ($cells as $c) { $out[] = '<td>' . ghostd_wiki_inline($c, $active) . '</td>'; }
            $out[] = '</tr>';
            continue;
        }
        $closeTable();

        if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
            $flushPara(); $closeList();
            // The wiki's h1 is the page title, which the panel header already
            // shows; everything shifts down one so the panel has one heading level.
            $lvl = min(6, strlen($m[1]) + 1);
            $out[] = "<h$lvl>" . ghostd_wiki_inline($m[2], $active) . "</h$lvl>";
            continue;
        }

        if (preg_match('/^\s*([-*_])\1{2,}\s*$/', $line)) {
            $flushPara(); $closeList();
            $out[] = '<hr>';
            continue;
        }

        if (preg_match('/^\s*>\s?(.*)$/', $line, $m)) {
            $flushPara(); $closeList();
            $out[] = '<blockquote>' . ghostd_wiki_inline($m[1], $active) . '</blockquote>';
            continue;
        }

        if (preg_match('/^\s*[-*+]\s+(.*)$/', $line, $m)) {
            $flushPara();
            if ($listType !== 'ul') { $closeList(); $out[] = '<ul>'; $listType = 'ul'; }
            $out[] = '<li>' . ghostd_wiki_inline($m[1], $active) . '</li>';
            continue;
        }
        if (preg_match('/^\s*\d+[.)]\s+(.*)$/', $line, $m)) {
            $flushPara();
            if ($listType !== 'ol') { $closeList(); $out[] = '<ol>'; $listType = 'ol'; }
            $out[] = '<li>' . ghostd_wiki_inline($m[1], $active) . '</li>';
            continue;
        }

        $closeList();
        $para[] = ghostd_wiki_inline($line, $active);
    }

    $flushPara();
    if ($listType !== null) { $out[] = '</' . $listType . '>'; }
    if ($inTable) { $out[] = '</tbody></table>'; }
    if ($inCode) { $out[] = '</code></pre>'; }

    return implode("\n", $out);
}

/**
 * Inline markdown inside one line. Escaped first, then marked up.
 *
 * Links to other wiki pages stay in the panel (?wiki=Page); anything with a
 * scheme opens in a new tab, since it is leaving the site.
 */
function ghostd_wiki_inline(string $s, string $active): string
{
    $s = h($s);

    // Inline code first, so ** inside a code span is not read as bold.
    $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s) ?? $s;

    $s = preg_replace_callback(
        '/!?\[([^\]]*)\]\(([^)\s]+)\)/',
        static function (array $m) use ($active): string {
            $text = $m[1];
            $href = $m[2];
            if (preg_match('#^https?://#i', $href)) {
                return '<a href="' . $href . '" target="_blank" rel="noopener noreferrer">' . $text . '</a>';
            }
            // A bare wiki page name, possibly with an anchor.
            $page = preg_replace('/\.md$/i', '', explode('#', $href)[0]);
            if ($page === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $page)) {
                return $text;
            }
            return '<a href="?page=' . rawurlencode($active) . '&amp;wiki=' . rawurlencode($page) . '">' . $text . '</a>';
        },
        $s
    ) ?? $s;

    $s = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $s) ?? $s;
    $s = preg_replace('/(?<![\w*])\*([^*]+)\*(?![\w*])/', '<em>$1</em>', $s) ?? $s;

    return $s;
}

/** The whole panel, ready to echo. Returns '' when there is nothing to show. */
function ghostd_wiki_panel(string $active): string
{
    if (!ghostd_wiki_enabled()) {
        return '';
    }
    $page = ghostd_wiki_page_for($active);
    if ($page === null) {
        return '';
    }

    [$md, $stale] = ghostd_wiki_fetch($page);

    $head = '<div class="wikihead">'
          . '<span class="wikimark">WIKI</span>'
          . '<strong>' . h(str_replace('-', ' ', $page)) . '</strong>'
          . '<a class="wikiout" href="' . h(ghostd_wiki_web_url($page)) . '" target="_blank" rel="noopener noreferrer" title="Open on GitHub">open</a>'
          . '<button type="button" class="wikitoggle" id="wikitoggle" title="Hide this panel">hide</button>'
          . '</div>';

    if ($md === null) {
        return '<aside class="wiki" id="wiki">' . $head
             . '<div class="wikibody"><p class="dim">The wiki could not be reached, and nothing is cached yet.</p></div></aside>';
    }

    $note = $stale ? '<p class="dim wikistale">Showing a cached copy - GitHub could not be reached.</p>' : '';

    return '<aside class="wiki" id="wiki">' . $head
         . '<div class="wikibody">' . $note . ghostd_wiki_render($md, $active) . '</div></aside>';
}
