<?php
declare(strict_types=1);
// Vercel entry point (vercel-php runtime). The edge terminates TLS and passes the visitor address in x-real-ip.
$_SERVER['HTTPS'] = 'on';
$ip = (string)($_SERVER['HTTP_X_REAL_IP'] ?? '');
if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) $_SERVER['REMOTE_ADDR'] = $ip;
require __DIR__ . '/../app/bootstrap.php';
require APP_DIR . '/router.php';
