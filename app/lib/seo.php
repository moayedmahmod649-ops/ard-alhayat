<?php
declare(strict_types=1);
function seo_noindex(): bool { return setting('noindex', '1') === '1'; }
function ga_id(): string { $v = setting('ga_id'); return preg_match('/^G-[A-Z0-9]{4,12}$/', $v) ? $v : ''; }
function csp_header(): string {
    $img = ["'self'", 'data:', 'https://i.ytimg.com'];
    $script = ["'self'"]; $connect = ["'self'"];
    if (ga_id()) { $script[] = 'https://www.googletagmanager.com'; $connect[] = 'https://*.google-analytics.com'; $connect[] = 'https://*.analytics.google.com'; $img[] = 'https://*.google-analytics.com'; }
    return "default-src 'self'; script-src " . implode(' ', $script) . "; style-src 'self'; style-src-attr 'unsafe-inline'; img-src " . implode(' ', $img)
        . "; font-src 'self'; connect-src " . implode(' ', $connect) . "; frame-src https://www.youtube-nocookie.com https://player.vimeo.com https://www.google.com https://maps.google.com"
        . "; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'" . (is_https() ? '; upgrade-insecure-requests' : '');
}
function security_headers(): void {
    header('Content-Security-Policy: ' . csp_header());
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()');
    if (is_https()) header('Strict-Transport-Security: max-age=31536000');
}
/** Organization JSON-LD on every page. */
function org_jsonld(): array {
    $same = array_values(array_filter(array_map(fn($k) => safe_url(setting('social_' . $k), false), ['facebook', 'instagram', 'x', 'linkedin', 'youtube'])));
    $o = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => setting('company_name_ar', t('company')), 'alternateName' => setting('company_name_en', 'Ard Al-Hayat'),
          'url' => base_url() . '/', 'logo' => base_url() . '/assets/img/logo.png'];
    if ($same) $o['sameAs'] = $same;
    if (($p = setting('phone')) !== '') $o['telephone'] = $p;
    if (($m = setting('email')) !== '') $o['email'] = $m;
    return $o;
}
function breadcrumb_jsonld(array $crumbs): array {
    $items = []; $i = 1;
    foreach ($crumbs as [$label, $href]) $items[] = ['@type' => 'ListItem', 'position' => $i++, 'name' => $label, 'item' => base_url() . $href];
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
}
function sitemap_xml(): string {
    $paths = ['', 'about', 'activities', 'projects', 'news', 'notices', 'gallery', 'careers', 'contact'];
    foreach (qall('SELECT slug, updated_at FROM activities WHERE published = 1') as $r) $paths[] = ['activities/' . $r['slug'], $r['updated_at']];
    foreach (qall('SELECT slug, updated_at FROM projects WHERE published = 1') as $r) $paths[] = ['projects/' . $r['slug'], $r['updated_at']];
    foreach (qall('SELECT slug, updated_at FROM news WHERE ' . news_live_sql(), [now_sql()]) as $r) $paths[] = ['news/' . $r['slug'], $r['updated_at']];
    foreach (qall('SELECT slug, updated_at FROM notices WHERE ' . notice_live_sql(), [today(), today()]) as $r) $paths[] = ['notices/' . $r['slug'], $r['updated_at']];
    foreach (qall('SELECT slug, updated_at FROM albums WHERE published = 1') as $r) $paths[] = ['gallery/' . $r['slug'], $r['updated_at']];
    foreach (jobs_open() as $r) $paths[] = ['careers/' . $r['slug'], $r['updated_at']];
    foreach (qall('SELECT slug, updated_at FROM pages WHERE published = 1 AND in_footer = 1') as $r) $paths[] = ['p/' . $r['slug'], $r['updated_at']];
    $b = base_url();
    $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
    foreach ($paths as $p) {
        [$path, $mod] = is_array($p) ? $p : [$p, null];
        foreach (LANGS as $lg) {
            $x .= '<url><loc>' . e("$b/$lg/$path") . '</loc>';
            if ($mod) $x .= '<lastmod>' . e(substr($mod, 0, 10)) . '</lastmod>';
            foreach (LANGS as $alt) $x .= '<xhtml:link rel="alternate" hreflang="' . $alt . '" href="' . e("$b/$alt/$path") . '"/>';
            $x .= '<xhtml:link rel="alternate" hreflang="x-default" href="' . e("$b/ar/$path") . '"/></url>' . "\n";
        }
    }
    return $x . '</urlset>';
}
