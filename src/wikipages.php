<?php
/**
 * The unit's own wiki: SOPs, guides, the radio plan, whatever the unit writes
 * down for its members. Not to be confused with src/wiki.php, which is the
 * help panel showing DIVINER's published documentation.
 *
 * ONE DOCUMENT PER PAGE, <unit>.wiki.<slug>, so a page saves and backs up on
 * its own and a long SOP does not ride inside a document every request
 * reads. A page is a title, a piece of HTML written in the same Wysi editor
 * as the home page, a public flag, and an order for the list.
 *
 * THREE AUDIENCES, ADMINS BY DEFAULT (user, 2026-10-08: "by default make all
 * docs admin only"). A new page is read by admins alone. `members` opens it
 * to a signed-in member; `public` opens it to anyone and offers it to the feed,
 * which is how the unit's website shows its SOP - and public takes a second
 * tick in the editor, because public is the internet. The HTML is cleaned on save by
 * ghostd_html_clean(), for the same reason the home page's is: the page is
 * served to people who are not admins.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/home.php';      // ghostd_html_clean()
require_once __DIR__ . '/roles.php';     // ghostd_doc_delete()

use MongoDB\Driver\Query;
use MongoDB\BSON\Regex;

/** Longest HTML one page may hold. An SOP, not a book. */
const GHOSTD_WIKI_MAX = 400000;

function ghostd_wiki_prefix(): string
{
    return ghostd_config()['unit'] . '.wiki.';
}

function ghostd_wiki_doc_id(string $slug): string
{
    return ghostd_wiki_prefix() . $slug;
}

/** A slug is what goes in the address: lower case, digits, dash, underscore. */
function ghostd_wiki_slug_ok(string $slug): bool
{
    return (bool) preg_match('/^[a-z0-9][a-z0-9_-]{0,60}$/', $slug);
}

/** A title turned into a slug: "Radio plan (2026)" becomes "radio-plan-2026". */
function ghostd_wiki_slugify(string $title): string
{
    $s = strtolower(trim($title));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    return substr($s, 0, 61);
}

/**
 * Every page's slug, title, public flag and order, in ONE query - the list
 * must not cost a round-trip per page. [slug => [...]], by order then title.
 */
/** Who is reading: 'public' (no session), 'member' (signed in), 'admin' (may edit). */
function ghostd_wiki_index(string $who = 'public'): array
{
    $prefix = ghostd_wiki_prefix();
    $out = [];
    try {
        $cur = ghostd_manager()->executeQuery(
            ghostd_ns(),
            new Query(
                ['_id' => new Regex('^' . preg_quote($prefix, '/'), '')],
                ['projection' => ['_id' => 1, 'title' => 1, 'public' => 1, 'members' => 1, 'order' => 1, 'updatedAt' => 1]]
            )
        );
        $cur->setTypeMap(GHOSTD_TYPEMAP);
        foreach ($cur as $doc) {
            $slug = substr((string) $doc['_id'], strlen($prefix));
            if (!ghostd_wiki_slug_ok($slug)) {
                continue;
            }
            $public = !empty($doc['public']);
            $members = $public || !empty($doc['members']);
            if ($who === 'public' && !$public) {
                continue;
            }
            if ($who === 'member' && !$members) {
                continue;
            }
            $out[$slug] = [
                'slug'      => $slug,
                'title'     => trim((string) ($doc['title'] ?? '')) ?: $slug,
                'public'    => $public,
                'members'   => $members,
                'order'     => (int) ($doc['order'] ?? 0),
                'updatedAt' => (string) ($doc['updatedAt'] ?? ''),
            ];
        }
    } catch (Throwable $e) {
        return [];
    }
    uasort($out, static fn($a, $b) => ($a['order'] <=> $b['order']) ?: strcasecmp($a['title'], $b['title']));
    return $out;
}

/** One page, every field present, or null when there is no such page. */
function ghostd_wiki_page(string $slug): ?array
{
    if (!ghostd_wiki_slug_ok($slug)) {
        return null;
    }
    try {
        $doc = ghostd_get(ghostd_wiki_doc_id($slug));
    } catch (Throwable $e) {
        return null;
    }
    if ($doc === null) {
        return null;
    }
    return [
        'slug'      => $slug,
        'title'     => trim((string) ($doc['title'] ?? '')) ?: $slug,
        // Cleaned again on the way out: a document written by an older copy
        // of this site, or by hand in Mongo, gets the same treatment.
        'html'      => ghostd_html_clean((string) ($doc['html'] ?? '')),
        'public'    => !empty($doc['public']),
        'members'   => !empty($doc['public']) || !empty($doc['members']),
        'order'     => (int) ($doc['order'] ?? 0),
        'updatedAt' => (string) ($doc['updatedAt'] ?? ''),
        'updatedBy' => (string) ($doc['updatedBy'] ?? ''),
    ];
}

/** Write a page whole. ghostd_put backs the previous version up first. */
function ghostd_wiki_save(string $slug, string $title, string $html, bool $public, int $order, string $by, bool $members = false): void
{
    if (!ghostd_wiki_slug_ok($slug)) {
        throw new RuntimeException('A page address is lower-case letters, digits, dash and underscore - "radio-plan", not "Radio Plan".');
    }
    $title = trim($title);
    if ($title === '') {
        throw new RuntimeException('A page needs a title.');
    }
    if (strlen($html) > GHOSTD_WIKI_MAX) {
        throw new RuntimeException('That page is too long for one document. Split it in two.');
    }
    ghostd_put(ghostd_wiki_doc_id($slug), [
        'section'   => 'wiki',
        'id'        => $slug,
        'title'     => mb_substr($title, 0, 120),
        'html'      => ghostd_html_clean($html),
        'public'    => $public,
        'members'   => $members || $public,
        'order'     => $order,
        'updatedBy' => $by,
    ]);
}

function ghostd_wiki_delete(string $slug): bool
{
    if (!ghostd_wiki_slug_ok($slug)) {
        return false;
    }
    return ghostd_doc_delete(ghostd_wiki_doc_id($slug));
}
