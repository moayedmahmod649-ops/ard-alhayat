<?php
declare(strict_types=1);
/* Ard Al-Hayat CMS — bootstrap. app/ must live OUTSIDE the web root. */
define('APP_DIR', __DIR__);
// The front controller defines PUBLIC_DIR. CLI scripts (seed, import) find the web root next to app/: public/ or cPanel's public_html/.
if (!defined('PUBLIC_DIR')) {
    $pub = __DIR__ . '/../public';
    foreach ([__DIR__ . '/../public', __DIR__ . '/../public_html'] as $cand) if (is_file($cand . '/index.php')) { $pub = $cand; break; }
    define('PUBLIC_DIR', realpath($pub) ?: $pub);
    unset($pub, $cand);
}
$cfgFile = APP_DIR . '/config.php';
$CONFIG = array_merge([
    'db_path'       => APP_DIR . '/storage/data/ardalhayat.sqlite',
    'files_dir'     => APP_DIR . '/storage/files',     // CVs + tender attachments (never web-reachable)
    'cache_dir'     => APP_DIR . '/storage/cache',
    'mail_log'      => APP_DIR . '/storage/mail/outbox.log', // used when SMTP is not configured
    'backup_dir'    => APP_DIR . '/storage/backups',
    'uploads_dir'   => PUBLIC_DIR . '/uploads',
    'uploads_url'   => '/uploads',
    'max_upload_mb' => 6,
    'max_doc_mb'    => 4, // also fits serverless request limits (4.5 MB)
    'max_pixels'    => 30_000_000,
    'session_name'  => 'ah_admin',
    'session_idle'  => 7200,
    'force_https_cookie' => null,
    'page_cache_ttl'=> 600,
    'base_url'      => '', // e.g. https://ardalhayat.ly (used in sitemap/canonical/emails). Empty = auto-detect.
], is_file($cfgFile) ? (require $cfgFile) : []);

error_reporting(E_ALL);
ini_set('display_errors', getenv('AH_DEBUG') ? '1' : '0');
ini_set('log_errors', '1');
mb_internal_encoding('UTF-8');
date_default_timezone_set('Africa/Tripoli');

require APP_DIR . '/lib/cloud.php';
if (cloud()) {
    // serverless: only /tmp is writable and it is private to this instance
    $CONFIG = array_merge($CONFIG, ['db_path' => CLOUD_TMP . '/site.sqlite', 'cache_dir' => CLOUD_TMP . '/cache', 'mail_log' => CLOUD_TMP . '/mail/outbox.log',
        'backup_dir' => CLOUD_TMP . '/backups', 'uploads_dir' => CLOUD_TMP . '/uploads', 'force_https_cookie' => true,
        'max_upload_mb' => 4]); // request bodies are capped at 4.5 MB; the admin shrinks large photos in the browser first
    ob_start();                                   // keep headers open until the save has succeeded
    session_set_save_handler(new CookieSessionHandler(), false);
    register_shutdown_function('cloud_shutdown');
}
foreach (['db','helpers','i18n','sanitize','auth','perms','uploads','files','mailer','xlsx','cache','repo','seo','render'] as $lib) require APP_DIR . "/lib/$lib.php";
