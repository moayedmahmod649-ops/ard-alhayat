<?php
// Minimal local stand-in for the Vercel Blob API + CDN, for testing cloud mode offline.
// php -S 127.0.0.1:8899 tools/blobmock.php   (data in /tmp/blobmock)
$dir = '/tmp/blobmock'; @mkdir($dir, 0700, true);
$u = parse_url($_SERVER['REQUEST_URI']); $path = $u['path']; parse_str($u['query'] ?? '', $q);
$h = fn($n) => $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $n))] ?? null;
$file = fn($p) => $dir . '/' . str_replace('/', '__', ltrim($p, '/'));
$etag = fn($f) => '"' . md5_file($f) . '"';
file_put_contents("$dir/ops.log", $_SERVER['REQUEST_METHOD'] . " $path\n", FILE_APPEND);
if ($path === '/api/blob/' || $path === '/api/blob') {
    if (($h('authorization') ?? '') !== 'Bearer vercel_blob_rw_mockstore_secret') { http_response_code(403); exit('{"error":{"code":"forbidden"}}'); }
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $p = $q['pathname']; $f = $file($p);
        if (is_file($f) && $h('x-allow-overwrite') !== '1') { http_response_code(400); exit('{"error":{"code":"bad_request","message":"exists"}}'); }
        if ($h('x-if-match') !== null && (!is_file($f) || $etag($f) !== $h('x-if-match'))) { http_response_code(412); exit('{"error":{"code":"precondition_failed"}}'); }
        file_put_contents($f, file_get_contents('php://input'));
        header('Content-Type: application/json'); echo json_encode(['url' => 'http://127.0.0.1:8899/' . $p, 'pathname' => $p, 'etag' => $etag($f)]); exit;
    }
}
if ($path === '/api/blob/delete') { foreach (json_decode(file_get_contents('php://input'), true)['urls'] ?? [] as $url) @unlink($file(parse_url($url, PHP_URL_PATH))); echo 'null'; exit; }
$f = $file($path);
if (!is_file($f)) { http_response_code(404); exit('not found'); }
header('ETag: ' . $etag($f));
if ($h('if-none-match') === $etag($f)) { http_response_code(304); exit; }
header('Content-Type: ' . (str_ends_with($path, '.webp') ? 'image/webp' : 'application/octet-stream'));
readfile($f);
