<?php
declare(strict_types=1);
// Front controller. app/ sits one level above the web root (works for both public/ and public_html/).
define('PUBLIC_DIR', __DIR__);
require __DIR__ . '/../app/bootstrap.php';
require APP_DIR . '/router.php';
