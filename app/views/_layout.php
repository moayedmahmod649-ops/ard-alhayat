<?php
/** @var string $content @var array $meta */
$siteName = LANG === 'ar' ? setting('company_name_ar', t('company_short')) : setting('company_name_en', t('company_short'));
$title = $meta['title'] !== '' ? $meta['title'] . ' | ' . t('company_short') : (LANG === 'ar' ? setting('seo_title_ar', t('company')) : setting('seo_title_en', t('company')));
$desc = $meta['desc'] !== '' ? $meta['desc'] : (LANG === 'ar' ? setting('seo_desc_ar') : setting('seo_desc_en'));
$path = $ROUTE_PATH; // path without language prefix
$canon = base_url() . url_lang(LANG, $path);
$other = LANG === 'ar' ? 'en' : 'ar';
$img = preg_match('#^https?://#', $meta['image']) ? $meta['image'] : base_url() . $meta['image'];
$ld = array_merge([org_jsonld()], $meta['jsonld']);
$cur = $meta['section'];
$nav = [['', t('home'), 'home'], ['about', t('about'), 'about'], ['activities', t('activities'), 'activities'], ['projects', t('projects'), 'projects'], ['news', t('news_short'), 'news'],
        ['notices', t('notices'), 'notices'], ['gallery', t('gallery_short'), 'gallery'], ['careers', t('careers'), 'careers'], ['contact', t('contact'), 'contact']];
$custom = menu('header');
?><!doctype html>
<html lang="<?= LANG ?>" dir="<?= dir_attr() ?>" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<?php if (seo_noindex() || $meta['noindex']): ?><meta name="robots" content="noindex, nofollow"><?php endif ?>
<link rel="canonical" href="<?= e($canon) ?>">
<link rel="alternate" hreflang="ar" href="<?= e(base_url() . url_lang('ar', $path)) ?>">
<link rel="alternate" hreflang="en" href="<?= e(base_url() . url_lang('en', $path)) ?>">
<link rel="alternate" hreflang="x-default" href="<?= e(base_url() . url_lang('ar', $path)) ?>">
<meta property="og:type" content="<?= e($meta['type']) ?>">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($meta['title'] ?: $title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($canon) ?>">
<meta property="og:image" content="<?= e($img) ?>">
<meta property="og:locale" content="<?= LANG === 'ar' ? 'ar_LY' : 'en_US' ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#1E2410">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-v2-32.png">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon-v2.png">
<link rel="manifest" href="/site.webmanifest">
<link rel="preload" href="/assets/fonts/expo-arabic-bold.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/expo-arabic-book.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/assets/css/site.css?v=<?= ASSET_V ?>">
<link rel="stylesheet" href="/theme.css?v=<?= e(substr(md5(setting('brand_primary') . setting('brand_accent')), 0, 8)) ?>">
<script src="/assets/js/boot.js?v=<?= ASSET_V ?>"></script>
<?php foreach ($ld as $j): ?><script type="application/ld+json"><?= json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endforeach ?>
<?php if (ga_id()): ?><script async src="https://www.googletagmanager.com/gtag/js?id=<?= e(ga_id()) ?>"></script><script src="/assets/js/track.js" data-ga="<?= e(ga_id()) ?>"></script><?php endif ?>
</head>
<body class="<?= e($meta['body_class']) ?>">
<?php readfile(APP_DIR . '/views/_icons.svg') ?>
<a class="skip" href="#main"><?= e(t('skip')) ?></a>
<?php if (!empty($GLOBALS['PREVIEW_BANNER'])): ?><div class="preview-banner" role="status"><?= e(t('preview_banner')) ?></div><?php endif ?>
<header class="site-head<?= $cur === 'home' ? ' over-hero' : '' ?>" id="top">
  <div class="wrap head-in">
    <a class="brand" href="<?= e(url()) ?>" aria-label="<?= e($siteName) ?> — <?= e(t('home')) ?>">
      <img class="brand-emblem" src="<?= e(setting('logo_header') ?: '/assets/img/brand-mark.webp') ?>" alt="" width="60" height="60">
      <span class="brand-text"><span class="brand-name"><?= e(t('company_short')) ?></span><span class="brand-tag"><?= e(t('tagline')) ?></span></span>
    </a>
    <nav class="main-nav" id="main-nav" aria-label="<?= e(t('menu')) ?>">
      <ul class="nav-list">
<?php if ($custom): if (!array_filter($custom, fn($m) => rtrim(menu_href(safe_url($m['url']) ?: '/'), '/') === rtrim(url(), '/'))): ?>
        <li><a href="<?= e(url()) ?>"<?= $cur === 'home' ? ' aria-current="page"' : '' ?>><?= e(t('home')) ?></a></li>
<?php endif; foreach ($custom as $m): $href = menu_href(safe_url($m['url']) ?: '/'); ?>
        <li><a href="<?= e($href) ?>"<?= rtrim($href, '/') === rtrim(url($path), '/') ? ' aria-current="page"' : '' ?>><?= e(L($m, 'label')) ?></a></li>
<?php endforeach; else: foreach ($nav as [$p, $label, $key]): ?>
        <li<?= $key === 'home' ? ' class="nav-home"' : '' ?>><a href="<?= e(url($p)) ?>"<?= $cur === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
<?php endforeach; endif ?>
      </ul>
      <a class="lang-switch mobile-only" href="<?= e(url_lang($other, $path)) ?>" hreflang="<?= $other ?>" lang="<?= $other ?>"><?= icon('globe') ?><?= e(t('lang_other')) ?></a>
    </nav>
    <div class="head-actions">
      <a class="lang-switch desktop-only" href="<?= e(url_lang($other, $path)) ?>" hreflang="<?= $other ?>" lang="<?= $other ?>" aria-label="<?= e(t('lang_other')) ?>"><?= icon('globe') ?><span><?= e(t('lang_other_code')) ?></span></a>
      <a class="btn btn-gold head-cta desktop-only" href="<?= e(url('contact?topic=partner')) ?>"><?= e(t('partner_req')) ?></a>
      <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-nav"><?= icon('menu', 'open') ?><?= icon('close', 'shut') ?><span class="sr"><?= e(t('menu')) ?></span></button>
    </div>
  </div>
</header>
<main id="main" tabindex="-1">
<?= $content ?>
</main>
<footer class="site-foot">
  <div class="foot-top">
    <div class="wrap foot-grid">
      <div class="foot-brand">
        <img src="<?= e(setting('logo_footer') ?: '/assets/img/footer-logo-light.webp') ?>" alt="<?= e(t('company')) ?>" width="220" height="185" loading="lazy">
        <p><?= bidi(LANG === 'ar' ? setting('footer_about_ar') : setting('footer_about_en')) ?></p>
        <?= social_links() ?>
      </div>
      <div>
        <h2 class="foot-h"><?= e(t('quick_links')) ?></h2>
        <ul class="foot-links">
<?php $fm = menu('footer'); if ($fm): if (!array_filter($fm, fn($m) => rtrim(menu_href(safe_url($m['url']) ?: '/'), '/') === rtrim(url(), '/'))): ?>
          <li><a href="<?= e(url()) ?>"><?= e(t('home')) ?></a></li>
<?php endif; foreach ($fm as $m): ?>
          <li><a href="<?= e(menu_href(safe_url($m['url']) ?: '/')) ?>"><?= e(L($m, 'label')) ?></a></li>
<?php endforeach; else: foreach ($nav as [$p, $label]): ?>
          <li><a href="<?= e(url($p)) ?>"><?= e($label) ?></a></li>
<?php endforeach; endif ?>
<?php foreach (footer_pages() as $fp): ?>
          <li><a href="<?= e(url('p/' . $fp['slug'])) ?>"><?= e(L($fp, 'title')) ?></a></li>
<?php endforeach ?>
        </ul>
      </div>
      <div>
        <h2 class="foot-h"><?= e(t('contact_info')) ?></h2>
        <ul class="foot-contact">
          <?php $fa = LANG === 'ar' ? setting('address_ar') : setting('address_en'); $fp = setting('phone'); $fe = setting('email'); $fh = LANG === 'ar' ? setting('hours_ar') : setting('hours_en'); ?>
          <?php if ($fa): ?><li><?= icon('pin') ?><span><?= bidi($fa) ?></span></li><?php endif ?>
          <?php if ($fp): ?><li><?= icon('phone') ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $fp)) ?>" dir="ltr"><?= e($fp) ?></a></li><?php endif ?>
          <?php if ($fe): ?><li><?= icon('mail') ?><a href="mailto:<?= e($fe) ?>" dir="ltr"><?= e($fe) ?></a></li><?php endif ?>
          <?php if ($fh): ?><li><?= icon('clock') ?><span><?= bidi($fh) ?></span></li><?php endif ?>
          <?php if (!$fa && !$fp && !$fe && !$fh): ?><li><?= icon('mail') ?><a href="<?= e(url('contact')) ?>"><?= e(t('contact')) ?></a></li><?php endif ?>
        </ul>
      </div>
      <div>
        <h2 class="foot-h" id="nl-h"><?= e(t('newsletter')) ?></h2>
        <p><?= e(t('newsletter_sub')) ?></p>
        <form class="nl-form" method="post" action="<?= e(url('newsletter')) ?>" aria-labelledby="nl-h">
          <input type="hidden" name="_ft" value="<?= e(form_token('newsletter')) ?>">
          <div class="hp" aria-hidden="true"><label>website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
          <label class="sr" for="nl-email"><?= e(t('email')) ?></label>
          <input id="nl-email" type="email" name="email" required autocomplete="email" placeholder="<?= e(t('email')) ?>" dir="ltr">
          <button class="btn btn-gold" type="submit"><?= e(t('subscribe')) ?></button>
        </form>
        <?php $nl = get('nl'); if (in_array($nl, ['ok', 'confirm', 'err'], true)): ?><p class="nl-status <?= $nl === 'err' ? 'is-err' : 'is-ok' ?>" role="status" tabindex="-1" data-focus><?= e($nl === 'ok' ? t('subscribed') : ($nl === 'confirm' ? t('confirm_sent') : t('err_email'))) ?></p><?php endif ?>
      </div>
    </div>
    <?= furrows('foot-furrows') ?>
  </div>
  <div class="foot-bottom"><div class="wrap">
    <p>© <?= date('Y') ?> <?= e(t('company')) ?> — <?= e(t('rights')) ?></p>
    <p class="muted-light"><?= e(t('built_by')) ?></p>
  </div></div>
</footer>
<div class="lightbox" id="lightbox" hidden role="dialog" aria-modal="true" aria-label="<?= e(t('gallery_short')) ?>">
  <button type="button" class="lb-close" aria-label="<?= e(t('close')) ?>"><?= icon('close') ?></button>
  <button type="button" class="lb-prev" aria-label="<?= e(t('prev')) ?>"><?= icon('chev', 'flip-r') ?></button>
  <figure class="lb-stage"><div class="lb-media"></div><figcaption class="lb-cap"></figcaption></figure>
  <button type="button" class="lb-next" aria-label="<?= e(t('next')) ?>"><?= icon('chev', 'flip') ?></button>
</div>
<script src="/assets/js/site.js?v=<?= ASSET_V ?>" defer></script>
</body>
</html>
