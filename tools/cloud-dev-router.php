<?php
// Local emulation of the Vercel routing in vercel.json (static files, /uploads proxy, everything else -> api/index.php).
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pub = dirname(__DIR__) . '/site/public';
if ($p !== '/' && is_file($pub . $p) && !str_ends_with($p, '.php')) return false;
if (preg_match('#^/uploads/([a-z]+-[a-f0-9]{16}(?:-[mt])?\.webp)$#', $p, $m)) {
    $b = @file_get_contents(getenv('AH_BLOB_BASE') . '/uploads/' . $m[1]);
    if ($b === false) { http_response_code(404); exit; }
    header('Content-Type: image/webp'); echo $b; exit;
}
$_SERVER['SCRIPT_NAME'] = '/api/index.php';
require dirname(__DIR__) . '/site/api/index.php';
