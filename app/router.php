<?php
declare(strict_types=1);
const ASSET_V = '1.3.1';
$uri = rawurldecode((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'));
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ---------- non-page endpoints ---------- */
if ($uri === '/' ) {
    // language negotiation: Arabic is the default; English browsers are offered /en/ only via the switch (no auto-redirect for bots)
    header('Location: /ar/', true, 302); exit;
}
if ($uri === '/admin' || str_starts_with($uri, '/admin/')) { require APP_DIR . '/admin/controller.php'; exit; }
if ($uri === '/robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo seo_noindex() ? "User-agent: *\nDisallow: /\n" : "User-agent: *\nDisallow: /admin/\nDisallow: /files/\nSitemap: " . base_url() . "/sitemap.xml\n";
    exit;
}
if ($uri === '/sitemap.xml') { header('Content-Type: application/xml; charset=utf-8'); echo sitemap_xml(); exit; }
if ($uri === '/site.webmanifest') {
    header('Content-Type: application/manifest+json');
    echo json_encode(['name' => 'أرض الحياة', 'short_name' => 'أرض الحياة', 'start_url' => '/ar/', 'display' => 'browser', 'background_color' => '#F7F3E8', 'theme_color' => '#1E2410',
        'icons' => [['src' => '/assets/img/icon-v2-192.png', 'sizes' => '192x192', 'type' => 'image/png'], ['src' => '/assets/img/icon-v2-512.png', 'sizes' => '512x512', 'type' => 'image/png']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($uri === '/theme.css') {
    header('Content-Type: text/css; charset=utf-8'); header('Cache-Control: public, max-age=86400');
    $p = setting('brand_primary'); $a = setting('brand_accent');
    echo ':root{' . (valid_hex($p) ? "--forest:$p;" : '') . (valid_hex($a) ? "--gold:$a;" : '') . '}';
    exit;
}
if (preg_match('#^/files/([a-f0-9]{24})$#', $uri, $m)) {
    $f = q1('SELECT * FROM files WHERE token = ?', [$m[1]]);
    if (!$f || !public_file_allowed($f)) { http_response_code(404); exit('Not found'); }
    download_file($f, isset($_GET['view']));
}

/* ---------- language + path ---------- */
if (!preg_match('#^/(ar|en)(/.*)?$#', $uri, $m)) { define('LANG', 'ar'); $ROUTE_PATH = ''; not_found(); }
define('LANG', $m[1]);
$ROUTE_PATH = trim($m[2] ?? '', '/');
if (($m[2] ?? '') === '') { header('Location: /' . LANG . '/', true, 301); exit; }
$seg = $ROUTE_PATH === '' ? [] : explode('/', $ROUTE_PATH);
$preview = isset($_GET['preview']) && admin_preview_allowed();
if ($preview) { $GLOBALS['PREVIEW_BANNER'] = true; }

/* ---------- page cache (anonymous GETs only) ---------- */
// only cache known, bounded query shapes (prevents cache flooding with random query strings)
$qkeys = array_keys($_GET);
$cacheable = $method === 'GET' && !$preview && empty($_COOKIE[$CONFIG['session_name']])
    && !array_diff($qkeys, ['page', 'cat', 'status', 'type']) && strlen((string)($_SERVER['QUERY_STRING'] ?? '')) <= 60;
security_headers();
if ($method === 'GET') count_visit('/' . LANG . '/' . $ROUTE_PATH);
if ($cacheable && ($html = page_cache_get()) !== null) { header('X-Cache: HIT'); echo $html; exit; }

function not_found(): never {
    global $ROUTE_PATH;
    http_response_code(404);
    echo render_page('404', [], ['title' => t('not_found'), 'noindex' => true]);
    exit;
}
function out(string $view, array $vars, array $meta): never {
    global $cacheable;
    $html = render_page($view, $vars, $meta);
    if ($cacheable && http_response_code() === 200) page_cache_put($html);
    echo $html; exit;
}
function meta_for(array $row, string $fallbackTitle, string $fallbackDesc = ''): array {
    $t = L($row, 'meta_title') ?: $fallbackTitle;
    $d = L($row, 'meta_desc') ?: $fallbackDesc;
    return ['title' => $t, 'desc' => excerpt($d, 160)];
}
function home_crumb(): array { return [t('home'), url()]; }

require APP_DIR . '/public_forms.php';
$s0 = $seg[0] ?? '';
$s1 = $seg[1] ?? null;
if (count($seg) > 2) not_found();
if ($s1 !== null && !valid_slug($s1)) not_found();

switch ($s0) {
    case '':
        out('home', [], ['title' => '', 'section' => 'home', 'body_class' => 'is-home']);
    case 'about':
        if ($s1) not_found();
        $pg = page_by_slug('about');
        out('about', ['pg' => $pg], meta_for($pg ?? [], t('about'), L($pg, 'body')) + ['section' => 'about', 'jsonld' => [breadcrumb_jsonld([home_crumb(), [t('about'), url('about')]])]]);
    case 'activities':
        if ($s1) {
            $a = activity_by_slug($s1, $preview) ?? not_found();
            out('activity', ['a' => $a], meta_for($a, L($a, 'title'), L($a, 'summary')) + ['section' => 'activities', 'image' => $a['image'] ?: '/assets/img/hay.webp',
                'jsonld' => [breadcrumb_jsonld([home_crumb(), [t('activities'), url('activities')], [L($a, 'title'), url('activities/' . $a['slug'])]])]]);
        }
        out('activities', [], ['title' => t('activities'), 'section' => 'activities', 'desc' => LANG === 'ar' ? 'الأنشطة الزراعية وأنشطة الإنتاج الحيواني لشركة أرض الحياة.' : 'Agricultural and livestock activities of Ard Al-Hayat.']);
    case 'projects':
        if ($s1) {
            $p = project_by_slug($s1, $preview) ?? not_found();
            out('project', ['p' => $p], meta_for($p, L($p, 'title'), L($p, 'summary')) + ['section' => 'projects', 'image' => $p['image'] ?: '/assets/img/irrigation.webp',
                'jsonld' => [breadcrumb_jsonld([home_crumb(), [t('projects'), url('projects')], [L($p, 'title'), url('projects/' . $p['slug'])]])]]);
        }
        out('projects', [], ['title' => t('projects'), 'section' => 'projects']);
    case 'news':
        if ($s1) {
            $n = news_by_slug($s1, $preview) ?? not_found();
            $abs = base_url() . url('news/' . $n['slug']);
            $ld = ['@context' => 'https://schema.org', '@type' => 'NewsArticle', 'headline' => L($n, 'title'), 'datePublished' => date('c', strtotime($n['publish_at'])), 'dateModified' => date('c', strtotime($n['updated_at'])),
                   'inLanguage' => LANG, 'mainEntityOfPage' => $abs, 'publisher' => ['@type' => 'Organization', 'name' => t('company'), 'logo' => ['@type' => 'ImageObject', 'url' => base_url() . '/assets/img/logo.png']]];
            if ($n['image']) $ld['image'] = [base_url() . $n['image']];
            out('news-item', ['n' => $n], meta_for($n, L($n, 'title'), L($n, 'excerpt') ?: L($n, 'body')) + ['section' => 'news', 'type' => 'article', 'image' => $n['image'] ?: '/assets/img/harvest.webp',
                'jsonld' => [$ld, breadcrumb_jsonld([home_crumb(), [t('news'), url('news')], [L($n, 'title'), url('news/' . $n['slug'])]])]]);
        }
        out('news', [], ['title' => t('news'), 'section' => 'news', 'noindex' => get('q') !== '']);
    case 'notices':
        if ($s1) {
            $n = notice_by_slug($s1, $preview) ?? not_found();
            out('notice', ['n' => $n], meta_for($n, L($n, 'title'), L($n, 'body')) + ['section' => 'notices', 'jsonld' => [breadcrumb_jsonld([home_crumb(), [t('notices'), url('notices')], [L($n, 'title'), url('notices/' . $n['slug'])]])]]);
        }
        out('notices', [], ['title' => t('notices'), 'section' => 'notices']);
    case 'gallery':
        if ($s1) { $a = album_by_slug($s1, $preview) ?? not_found(); out('album', ['al' => $a], ['title' => L($a, 'title'), 'desc' => excerpt(L($a, 'desc')), 'section' => 'gallery', 'image' => $a['image'] ?: '/assets/img/harvest.webp']); }
        out('gallery', [], ['title' => t('gallery'), 'section' => 'gallery']);
    case 'careers':
        if ($s1) {
            $j = job_by_slug($s1, $preview) ?? not_found();
            if ($method === 'POST') handle_application($j);
            $ld = ['@context' => 'https://schema.org', '@type' => 'JobPosting', 'title' => L($j, 'title'), 'description' => L($j, 'body') ?: L($j, 'title'), 'datePosted' => substr($j['created_at'], 0, 10),
                   'hiringOrganization' => ['@type' => 'Organization', 'name' => t('company')], 'employmentType' => ['full' => 'FULL_TIME', 'part' => 'PART_TIME', 'contract' => 'CONTRACTOR'][$j['jtype']] ?? 'FULL_TIME',
                   'jobLocation' => ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressCountry' => 'LY', 'addressLocality' => L($j, 'location')]]];
            if ($j['deadline']) $ld['validThrough'] = $j['deadline'] . 'T23:59:59';
            out('job', ['j' => $j], ['title' => L($j, 'title'), 'desc' => excerpt(L($j, 'body')), 'section' => 'careers', 'jsonld' => $ld ? [$ld] : []]);
        }
        if ($method === 'POST') handle_application(null);
        out('careers', [], ['title' => t('careers'), 'section' => 'careers']);
    case 'contact':
        if ($s1) not_found();
        if ($method === 'POST') handle_contact();
        out('contact', [], ['title' => t('contact'), 'section' => 'contact']);
    case 'newsletter':
        if ($method === 'POST') handle_newsletter();
        if ($s1 === 'unsubscribe' || $s1 === 'confirm') handle_newsletter_link($s1);
        not_found();
    case 'p':
        $pg = $s1 ? page_by_slug($s1, $preview) : null;
        if (!$pg || in_array($s1, ['about', 'vision', 'mission'], true)) not_found();
        out('page', ['pg' => $pg], meta_for($pg, L($pg, 'title'), L($pg, 'body')));
    default:
        not_found();
}
