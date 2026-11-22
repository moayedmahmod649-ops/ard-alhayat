<?php
declare(strict_types=1);
function A(string $q = ''): string { return '/admin/' . ($q !== '' ? '?' . $q : ''); }
function al(array $pair): string { return ADMIN_LANG === 'en' ? ($pair[1] ?? $pair[0]) : $pair[0]; }
function aflash(?string $m = null, string $t = 'ok'): ?array {
    if ($m !== null) { $_SESSION['flash'] = ['m' => $m, 't' => $t]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
function aicon(string $n): string { return '<svg class="ic" aria-hidden="true" focusable="false"><use href="#i-' . e($n) . '"/></svg>'; }
function nav_groups(): array {
    $M = modules();
    $g = [
      'content' => [['dashboard', ta('dashboard'), 'chart', A(), null], ['slides', al($M['slides']['title']), 'image', A('m=slides'), 'slides'], ['pages', al($M['pages']['title']), 'file', A('m=pages'), 'pages'],
        ['about', ta('about_section'), 'users', A('m=team'), 'about'], ['stats', al($M['stats']['title']), 'chart', A('m=stats'), 'stats'], ['activities', al($M['activities']['title']), 'sprout', A('m=activities'), 'activities'],
        ['projects', al($M['projects']['title']), 'pin', A('m=projects'), 'projects'], ['news', al($M['news']['title']), 'megaphone', A('m=news'), 'news'], ['notices', al($M['notices']['title']), 'file', A('m=notices'), 'notices'],
        ['albums', al($M['albums']['title']), 'image', A('m=albums'), 'gallery'], ['partners', al($M['partners']['title']), 'hand', A('m=partners'), 'partners'], ['media', ta('media'), 'image', A('p=media'), 'media'], ['menu_items', ta('menus_footer'), 'menu', A('m=menu_items'), 'menus']],
      'interaction' => [['messages', ta('messages'), 'mail', A('p=messages'), 'messages'], ['subscribers', ta('subscribers'), 'users', A('p=subscribers'), 'subscribers']],
      'hr' => [['jobs', al($M['jobs']['title']), 'briefcase', A('m=jobs'), 'jobs'], ['applications', ta('applications'), 'file', A('p=applications'), 'applications']],
      'system' => [['users', ta('users'), 'shield', A('p=users'), 'users'], ['audit', ta('audit'), 'eye', A('p=audit'), 'audit'], ['settings', ta('settings'), 'sun', A('p=settings'), 'settings'], ['backup', ta('backup'), 'download', A('p=backup'), 'backup']],
    ];
    return $g;
}
function admin_page(string $title, string $body, string $active = 'dashboard'): never {
    $u = current_user(); $f = aflash();
    $newMsgs = $u && can('messages') ? (int)qv("SELECT COUNT(*) FROM messages WHERE status = 'new'") : 0;
    $newApps = $u && can('applications') ? (int)qv("SELECT COUNT(*) FROM applications WHERE status = 'new'") : 0;
    header('Cache-Control: no-store');
    ?><!doctype html>
<html lang="<?= ADMIN_LANG ?>" dir="<?= ADMIN_LANG === 'ar' ? 'rtl' : 'ltr' ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> — <?= e(ta('admin')) ?></title>
<link rel="icon" href="/favicon.ico"><link rel="stylesheet" href="/assets/admin/admin.css?v=<?= ASSET_V ?>"><script src="/assets/admin/admin.js?v=<?= ASSET_V ?>" defer></script></head>
<body class="<?= $u ? 'has-side' : 'bare' ?>"<?= cloud() ? ' data-max-req="4300000"' : '' ?><?= $u ? ' data-u="' . e(substr(hash_hmac('sha256', (string)$u['id'], app_secret()), 0, 12)) . '"' : '' ?>>
<?php readfile(APP_DIR . '/views/_icons.svg') ?>
<a class="skip" href="#main"><?= e(ta('skip')) ?></a>
<?php if ($u): ?>
<header class="top">
  <button type="button" class="menu-toggle" aria-expanded="false" aria-controls="side"><?= aicon('menu') ?><span class="sr"><?= e(ta('menu')) ?></span></button>
  <a class="top-brand" href="<?= e(A()) ?>" aria-label="<?= e(ta('admin') . ' · ' . t('company_short')) ?>"><img src="/assets/img/brand-mark-light.webp" alt="" width="40" height="40"><span><?= e(ta('admin')) ?> · <?= e(t('company_short')) ?></span></a>
  <div class="top-actions">
    <a class="tbtn" href="/ar/" target="_blank" rel="noopener" aria-label="<?= e(ta('view_site')) ?>"><?= aicon('globe') ?><span><?= e(ta('view_site')) ?></span></a>
    <form method="post" action="<?= e(A('a=lang')) ?>"><?= csrf_field() ?><button class="tbtn" type="submit" lang="<?= ADMIN_LANG === 'ar' ? 'en' : 'ar' ?>"><?= e(ta('switch_lang')) ?></button></form>
    <a class="tbtn" href="<?= e(A('p=profile')) ?>" aria-label="<?= e($u['display_name'] ?: $u['username']) ?>"><?= aicon('users') ?><span><?= e($u['display_name'] ?: $u['username']) ?></span></a>
    <form method="post" action="<?= e(A('a=logout')) ?>"><?= csrf_field() ?><button class="tbtn" type="submit" aria-label="<?= e(ta('logout')) ?>"><?= aicon('logout') ?><span><?= e(ta('logout')) ?></span></button></form>
  </div>
</header>
<nav class="side" id="side" aria-label="<?= e(ta('menu')) ?>">
<?php foreach (nav_groups() as $gk => $items): $vis = array_filter($items, fn($i) => $i[4] === null || can($i[4])); if (!$vis) continue; ?>
  <p class="side-h"><?= e(ta('g_' . $gk)) ?></p>
  <ul>
<?php foreach ($vis as [$key, $label, $ic, $href]): $badge = $key === 'messages' ? $newMsgs : ($key === 'applications' ? $newApps : 0); ?>
    <li><a href="<?= e($href) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= aicon($ic) ?><span><?= e($label) ?></span><?php if ($badge): ?><b class="badge" aria-label="<?= $badge ?> <?= e(ta('new')) ?>"><?= $badge ?></b><?php endif ?></a></li>
<?php endforeach ?>
  </ul>
<?php endforeach ?>
</nav>
<div class="side-backdrop" hidden></div>
<?php endif ?>
<main id="main" class="main" tabindex="-1">
<?php if ($f): ?><div class="alert alert-<?= e($f['t']) ?>" role="status"><?= e($f['m']) ?></div><?php endif ?>
<?= $body ?>
</main>
</body></html>
<?php exit;
}
function deny(): never { http_response_code(403); admin_page(ta('no_perm'), '<div class="alert alert-err">' . e(ta('no_perm')) . '</div>'); }
function need(string $section, int $level = PERM_VIEW): void { if (!can($section, $level)) deny(); }
function pill(string $label, string $kind): string { return '<span class="pill pill-' . e($kind) . '">' . e($label) . '</span>'; }
function apager(int $page, int $pages, string $base): string {
    if ($pages <= 1) return '';
    $h = '<nav class="pager" aria-label="pages"><ul>';
    for ($i = max(1, $page - 5); $i <= min($pages, $page + 5); $i++) $h .= '<li><a href="' . e($base . '&pg=' . $i) . '"' . ($i === $page ? ' aria-current="page"' : '') . '>' . $i . '</a></li>';
    return $h . '</ul></nav>';
}
function adate(?string $d): string { if (!$d) return '—'; $t = strtotime($d); return $t ? date('Y-m-d H:i', $t) : '—'; }
