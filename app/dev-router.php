<?php
// Router for PHP's built-in server (local testing only): php -S 127.0.0.1:8830 -t public app/dev-router.php — mirrors public/.htaccess.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if (preg_match('#^/uploads/#', $path)) {
    if (!preg_match('#^/uploads/[a-z]+-[a-f0-9]{16}(-[mt])?\.webp$#', $path)) { http_response_code(403); exit('Forbidden'); }
    return false;
}
if (preg_match('#^/assets/#', $path) || $path === '/favicon.ico') {
    $f = $_SERVER['DOCUMENT_ROOT'] . $path;
    if (is_file($f) && !preg_match('/\.php$/i', $path)) return false;
    http_response_code(404); exit('Not found');
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
