<?php
/**
 * One record picture, straight out of the document.
 *
 * The upload half of an image field - see src/records.php. The game path is a
 * .paa the browser cannot draw, so the site draws this instead.
 */

declare(strict_types=1);

require_once __DIR__ . '/../records.php';

$img = ghostd_record_image(trim((string) ($_GET['k'] ?? '')));
if ($img === null) {
    http_response_code(404);
    exit;
}
$bytes = base64_decode((string) $img['data'], true);
if ($bytes === false) {
    http_response_code(404);
    exit;
}

$etag = '"' . md5($bytes) . '"';
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Type: ' . (string) $img['mime']);
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: private, max-age=86400');
header('ETag: ' . $etag);
echo $bytes;
exit;
