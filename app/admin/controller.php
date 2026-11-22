<?php
declare(strict_types=1);
/* Admin panel front controller: /admin/?m=<module>&a=<action> · /admin/?p=<page> */
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; style-src-attr 'unsafe-inline'; img-src 'self' data: https://i.ytimg.com; font-src 'self'; connect-src 'self'; frame-src 'none'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

session_boot();
$u = current_user();
if ($u) $_SESSION['seen'] = time();
define('ADMIN_LANG', $u ? ($u['admin_lang'] === 'en' ? 'en' : 'ar') : (($_COOKIE['ah_alang'] ?? '') === 'en' ? 'en' : 'ar'));
if (!defined('LANG')) define('LANG', ADMIN_LANG);
require __DIR__ . '/ui.php';
require __DIR__ . '/modules.php';
require __DIR__ . '/crud.php';
require __DIR__ . '/auth_pages.php';
require __DIR__ . '/sections.php';
require __DIR__ . '/system.php';

$a = get('a'); $p = get('p'); $mk = get('m');
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

/* ---- unauthenticated routes ---- */
if ($a === 'forgot') page_forgot($isPost);
if ($a === 'reset') page_reset($isPost);
if ($a === 'login' && $isPost) do_login();
if ($a === 'alang' && $isPost) { csrf_check(); setcookie('ah_alang', ADMIN_LANG === 'ar' ? 'en' : 'ar', ['expires' => time() + 31536000, 'path' => '/admin', 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https()]); redirect(A()); }
if (!$u) page_login();

/* ---- authenticated ---- */
if ($a === 'logout' && $isPost) { csrf_check(); audit('logout', 'users', (int)$u['id'], $u['username']); logout(); redirect(A()); }
if ($a === 'lang' && $isPost) { csrf_check(); q('UPDATE users SET admin_lang = ? WHERE id = ?', [ADMIN_LANG === 'ar' ? 'en' : 'ar', $u['id']]); redirect(A()); }
if ((int)$u['must_change'] === 1 && $p !== 'profile') redirect(A('p=profile'));
if ($a === 'file') admin_file_download((int)get('id'));
if ($a === 'clear_cache' && $isPost) { csrf_check(); page_cache_clear(); audit('cache_clear'); aflash(ta('cache_cleared')); redirect(A()); }

if ($mk !== '') {
    $M = modules();
    if (!isset($M[$mk])) { http_response_code(404); admin_page('404', '<div class="empty">404</div>'); }
    $m = $M[$mk];
    if ($a === 'save' && $isPost) crud_save($mk, $m);
    if ($a === 'delete' && $isPost) crud_delete($mk, $m);
    if ($a === 'edit') crud_edit($mk, $m);
    crud_list($mk, $m);
}
switch ($p) {
    case 'profile': page_profile($isPost);
    case 'messages': page_messages($isPost);
    case 'applications': page_applications($isPost);
    case 'subscribers': page_subscribers($isPost);
    case 'users': page_users($isPost);
    case 'audit': page_audit();
    case 'settings': page_settings($isPost);
    case 'backup': page_backup($isPost);
    case 'media': page_media($isPost);
    case 'help': page_help();
}
page_dashboard();
