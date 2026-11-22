<?php
declare(strict_types=1);
function icon(string $n, string $cls = ''): string { return '<svg class="ic ' . e($cls) . '" aria-hidden="true" focusable="false"><use href="#i-' . e($n) . '"/></svg>'; }
/** Responsive picture for our 3-size images (x.webp 1600 · x-m.webp 800x960 · x-t.webp 720x480). */
function pic(string $src, string $alt, string $class = '', string $mode = 'card', bool $eager = false): string {
    if ($src === '') return '<div class="pic-empty ' . e($class) . '" role="img" aria-label="' . e($alt) . '"></div>';
    $t = img_variant($src, 't'); $m = img_variant($src, 'm');
    $lazy = $eager ? ' fetchpriority="high"' : ' loading="lazy" decoding="async"';
    if ($mode === 'hero') {
        return '<picture>' . ($m !== $src ? '<source media="(max-width: 700px)" srcset="' . e($m) . '">' : '') .
            '<img class="' . e($class) . '" src="' . e($src) . '" alt="' . e($alt) . '" width="1600" height="900"' . $lazy . '></picture>';
    }
    $sizes = $mode === 'wide' ? '(max-width: 900px) 100vw, 60vw' : '(max-width: 700px) 100vw, (max-width: 1100px) 50vw, 33vw';
    $srcset = $t !== $src ? ' srcset="' . e($t) . ' 720w, ' . e($src) . ' 1600w" sizes="' . $sizes . '"' : '';
    return '<img class="' . e($class) . '" src="' . e($t) . '"' . $srcset . ' alt="' . e($alt) . '" width="720" height="480"' . $lazy . '>';
}
function is_draft_asset(string $src): bool { return str_starts_with($src, '/assets/img/'); }
function draft_tag(string $src): string { return ''; } // image labels are never shown publicly
function furrows(string $cls = ''): string {
    // the ploughed-field curves from the logo, as a section divider motif
    $p = '';
    for ($i = 0; $i < 7; $i++) { $y = 20 + $i * 13; $p .= '<path pathLength="1" d="M0 ' . ($y + 40) . ' C 360 ' . ($y - 10) . ', 900 ' . ($y + 70) . ', 1440 ' . ($y + 6) . '"/>'; }
    return '<svg class="furrows ' . e($cls) . '" viewBox="0 0 1440 140" preserveAspectRatio="none" aria-hidden="true" focusable="false">' . $p . '</svg>';
}
function crumbs(array $c): string {
    $h = '<nav class="crumbs" aria-label="' . e(t('breadcrumb')) . '"><ol>';
    $n = count($c);
    foreach ($c as $i => [$label, $href]) $h .= '<li>' . ($i < $n - 1 ? '<a href="' . e($href) . '">' . bidi($label) . '</a>' . icon('chev', 'flip') : '<span aria-current="page">' . bidi($label) . '</span>') . '</li>';
    return $h . '</ol></nav>';
}
/** Inner page header: photo, dark gradient, kicker, title, breadcrumb, furrow edge. */
function page_hero(string $title, string $kicker, string $img, array $crumbs, string $lead = ''): string {
    return '<header class="phero"><div class="phero-media">' . pic($img, '', 'phero-img', 'hero', true) . '</div><div class="phero-shade"></div><div class="grain"></div>'
        . '<div class="wrap phero-in"><h1 class="phero-title">' . bidi($title) . '</h1>'
        . ($lead !== '' ? '<p class="phero-lead">' . bidi($lead) . '</p>' : '') . crumbs($crumbs) . '</div>' . draft_tag($img) . furrows('phero-furrows') . '</header>';
}
function pagination(int $page, int $pages, callable $href): string {
    if ($pages <= 1) return '';
    $h = '<nav class="pager" aria-label="' . e(t('pagination')) . '"><ul>';
    for ($i = 1; $i <= $pages; $i++) $h .= '<li><a href="' . e($href($i)) . '"' . ($i === $page ? ' aria-current="page"' : '') . '><span class="sr">' . e(t('page')) . ' </span>' . $i . '</a></li>';
    return $h . '</ul></nav>';
}
function share_links(string $absUrl, string $title): string {
    $u = rawurlencode($absUrl); $tt = rawurlencode($title);
    $L = [['fb', 'Facebook', "https://www.facebook.com/sharer/sharer.php?u=$u"], ['x', 'X', "https://twitter.com/intent/tweet?url=$u&text=$tt"],
          ['wa', 'WhatsApp', "https://wa.me/?text=$tt%20$u"], ['in', 'LinkedIn', "https://www.linkedin.com/sharing/share-offsite/?url=$u"], ['tg', 'Telegram', "https://t.me/share/url?url=$u&text=$tt"]];
    $h = '<div class="share"><span class="share-l">' . icon('link') . e(t('share')) . '</span><ul>';
    foreach ($L as [$i, $n, $href]) $h .= '<li><a href="' . e($href) . '" target="_blank" rel="noopener noreferrer" aria-label="' . e(t('share') . ': ' . $n) . '">' . icon($i) . '</a></li>';
    return $h . '<li><button type="button" class="copy-link" data-url="' . e($absUrl) . '" data-done="' . e(t('copied')) . '" aria-label="' . e(t('copy_link')) . '">' . icon('link') . '</button></li></ul><span class="copy-status" role="status" aria-live="polite"></span></div>';
}
function social_links(): string {
    $map = ['facebook' => ['fb', 'Facebook'], 'instagram' => ['ig', 'Instagram'], 'x' => ['x', 'X'], 'linkedin' => ['in', 'LinkedIn'], 'youtube' => ['yt', 'YouTube']];
    $h = '';
    foreach ($map as $k => [$i, $n]) { $u = safe_url(setting('social_' . $k), false); if ($u) $h .= '<li><a href="' . e($u) . '" target="_blank" rel="noopener" aria-label="' . e($n) . '">' . icon($i) . '</a></li>'; }
    return $h ? '<ul class="social">' . $h . '</ul>' : '';
}
function sector_label(string $s): string { return $s === 'livestock' ? t('livestock_one') : t('agri_one'); }
function notice_kind_label(string $k): string { return ['tender' => t('tender'), 'job' => t('job_notice')][$k] ?? t('announcement'); }
function job_type_label(string $k): string { return ['part' => t('part'), 'contract' => t('contract')][$k] ?? t('full'); }
function days_left(string $end): ?int { if ($end === '') return null; return (int)floor((strtotime($end . ' 23:59:59') - time()) / 86400); }

/* ------------------------- cards ------------------------- */
function card_activity(array $a, int $n = 0): string {
    return '<article class="card act-card reveal"><a class="card-link" href="' . e(url('activities/' . $a['slug'])) . '"><div class="card-media">' . pic($a['image'], '', 'card-img') . '</div>'
        . '<div class="card-body">' . ($n ? '<span class="idx" aria-hidden="true">' . sprintf('%02d', $n) . '</span>' : '') . '<h3>' . bidi(L($a, 'title')) . '</h3><p>' . bidi(L($a, 'summary')) . '</p></div></a></article>';
}
function card_project(array $p, bool $big = false): string {
    $meta = array_filter([$p['status'] === 'completed' ? t('completed') : t('ongoing'), L($p, 'location'), $p['year'] ? (string)$p['year'] : '']);
    return '<article class="card proj-card reveal' . ($big ? ' big' : '') . '"><a class="card-link" href="' . e(url('projects/' . $p['slug'])) . '"><div class="card-media">' . pic($p['image'], '', 'card-img', $big ? 'wide' : 'card') . '</div>'
        . '<div class="card-body"><p class="meta">' . implode(' · ', array_map('bidi', $meta)) . '</p><h3>' . bidi(L($p, 'title')) . '</h3><p>' . bidi(L($p, 'summary')) . '</p></div></a></article>';
}
function card_news(array $n): string {
    $cat = $n['cat_ar'] ?? null ? (LANG === 'en' && ($n['cat_en'] ?? '') !== '' ? $n['cat_en'] : $n['cat_ar']) : '';
    return '<article class="card news-card reveal"><a class="card-link" href="' . e(url('news/' . $n['slug'])) . '"><div class="card-media">' . pic($n['image'], '', 'card-img') . '</div>'
        . '<div class="card-body"><p class="meta"><time datetime="' . e(substr($n['publish_at'], 0, 10)) . '">' . e(fdate($n['publish_at'])) . '</time>' . ($cat ? ' · <span>' . bidi($cat) . '</span>' : '') . '</p>'
        . '<h3><span class="ul">' . bidi(L($n, 'title')) . '</span></h3><p>' . bidi(L($n, 'excerpt') ?: excerpt(L($n, 'body'))) . '</p></div></a></article>';
}
function notice_row(array $n): string {
    $d = days_left($n['end_date']);
    $soon = $d !== null && $d <= 7;
    return '<article class="notice reveal kind-' . e($n['kind']) . '">'
        . '<div class="notice-main"><p class="meta"><span class="kind">' . e(notice_kind_label($n['kind'])) . '</span>' . ($n['ref_no'] ? ' · <bdi>' . e($n['ref_no']) . '</bdi>' : '') . '</p>'
        . '<h3><a href="' . e(url('notices/' . $n['slug'])) . '">' . bidi(L($n, 'title')) . '</a></h3></div>'
        . '<div class="notice-dates">' . ($n['end_date'] ? '<p>' . e(t('ends')) . ': <time datetime="' . e($n['end_date']) . '">' . e(fdate($n['end_date'])) . '</time></p>' : '')
        . ($soon ? '<p class="soon">' . e(t('closing_soon')) . ' · ' . e(t('days_left', max(0, $d))) . '</p>' : '') . '</div></article>';
}

/* ------------------------- layout ------------------------- */
function nav_items(): array {
    $items = menu('header');
    if ($items) return array_map(fn($m) => [L($m, 'label'), safe_url($m['url']) ?: '#'], $items);
    return [];
}
/** Header menu URLs are stored lang-neutral ("/activities") and prefixed with the current language. */
function menu_href(string $u): string {
    if (preg_match('#^https?://#', $u)) return $u;
    return url(ltrim($u, '/'));
}
function render_page(string $view, array $vars, array $meta): string {
    global $ROUTE_PATH;
    $meta += ['title' => '', 'desc' => '', 'image' => '/assets/img/hero-olive.webp', 'type' => 'website', 'jsonld' => [], 'noindex' => false, 'section' => '', 'body_class' => ''];
    extract($vars, EXTR_SKIP);
    ob_start();
    require APP_DIR . '/views/' . $view . '.php';
    $content = ob_get_clean();
    ob_start();
    require APP_DIR . '/views/_layout.php';
    return (string)ob_get_clean();
}
