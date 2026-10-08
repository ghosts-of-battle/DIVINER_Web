<?php
/**
 * The public feed's front door: ?page=feed&what=roster|orbat|events|wiki,
 * ?page=feed&what=page&slug=<page>, ?page=feed&what=image&k=<key>.
 *
 * index.php sends this here before the login gate; there is no session and
 * no cookie, and nothing is written. Every answer is JSON except an image.
 */

declare(strict_types=1);

require_once __DIR__ . '/../feed.php';

$what = (string) ($_GET['what'] ?? '');
$set  = ghostd_feed_settings();

if (!$set['enabled']) {
    ghostd_feed_out(['error' => 'The public feed is off. An admin turns it on under Web settings.'], 403, 0);
}

switch ($what) {
    case 'roster':
    case 'orbat':
    case 'events':
    case 'wiki':
        if (empty($set[$what])) {
            ghostd_feed_out(['error' => 'That feed is off.'], 403, 0);
        }
        $fn = 'ghostd_feed_' . $what;
        ghostd_feed_out($fn());
        // no break: ghostd_feed_out() exits

    case 'page':
        if (empty($set['wiki'])) {
            ghostd_feed_out(['error' => 'That feed is off.'], 403, 0);
        }
        $page = ghostd_feed_page((string) ($_GET['slug'] ?? ''));
        if ($page === null) {
            ghostd_feed_out(['error' => 'No public page by that name.'], 404, 60);
        }
        ghostd_feed_out($page);
        // no break

    case 'image':
        $key = (string) ($_GET['k'] ?? '');
        $img = ghostd_feed_image_key_ok($key) ? ghostd_record_image($key) : null;
        $bytes = $img === null ? false : base64_decode((string) $img['data'], true);
        if ($bytes === false) {
            http_response_code(404);
            exit;
        }
        $etag = '"' . md5($bytes) . '"';
        header('Access-Control-Allow-Origin: *');
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }
        header('Content-Type: ' . (string) $img['mime']);
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        header('ETag: ' . $etag);
        echo $bytes;
        exit;

    default:
        ghostd_feed_out([
            'unit'  => ghostd_unit_name(),
            'feeds' => array_values(array_filter(
                array_keys(GHOSTD_FEEDS),
                static fn($k) => !empty($set[$k])
            )),
            'help'  => '?page=feed&what=<roster|orbat|events|wiki>, &what=page&slug=<page>, &what=image&k=<key>',
        ], 200, 300);
}
