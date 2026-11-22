<?php
declare(strict_types=1);
/** Public read queries. $preview = logged-in admin may see unpublished items by direct URL. */
function pub(string $extra = ''): string { return 'published = 1' . ($extra ? " AND $extra" : ''); }
function slides(): array { return qall('SELECT * FROM slides WHERE published = 1 ORDER BY sort, id'); }
function stats_list(): array { return qall('SELECT * FROM stats WHERE published = 1 ORDER BY sort, id'); }
function values_list(): array { return qall('SELECT * FROM company_values WHERE published = 1 ORDER BY sort, id'); }
function team_list(): array { return qall('SELECT * FROM team WHERE published = 1 ORDER BY sort, id'); }
function partners_list(): array { return qall('SELECT * FROM partners WHERE published = 1 ORDER BY sort, id'); }
function page_by_slug(string $slug, bool $preview = false): ?array {
    return q1('SELECT * FROM pages WHERE slug = ?' . ($preview ? '' : ' AND published = 1'), [$slug]);
}
function footer_pages(): array { return qall('SELECT slug, title_ar, title_en FROM pages WHERE published = 1 AND in_footer = 1 ORDER BY sort, id'); }
function org_tree(): array {
    $rows = qall('SELECT * FROM org_units WHERE published = 1 ORDER BY sort, id');
    $by = []; foreach ($rows as $r) $by[(int)($r['parent_id'] ?? 0)][] = $r;
    return $by;
}
function activities(?string $sector = null, bool $featuredOnly = false, int $limit = 100): array {
    $w = ['published = 1']; $p = [];
    if ($sector) { $w[] = 'sector = ?'; $p[] = $sector; }
    if ($featuredOnly) $w[] = 'featured = 1';
    return qall('SELECT * FROM activities WHERE ' . implode(' AND ', $w) . ' ORDER BY featured DESC, sort, id LIMIT ' . (int)$limit, $p);
}
function activity_by_slug(string $slug, bool $preview = false): ?array { return q1('SELECT * FROM activities WHERE slug = ?' . ($preview ? '' : ' AND published = 1'), [$slug]); }
function projects(?string $status = null, ?string $sector = null, int $limit = 100): array {
    $w = ['published = 1']; $p = [];
    if ($status) { $w[] = 'status = ?'; $p[] = $status; }
    if ($sector) { $w[] = 'sector = ?'; $p[] = $sector; }
    return qall('SELECT * FROM projects WHERE ' . implode(' AND ', $w) . ' ORDER BY featured DESC, sort, id DESC LIMIT ' . (int)$limit, $p);
}
function project_by_slug(string $slug, bool $preview = false): ?array { return q1('SELECT * FROM projects WHERE slug = ?' . ($preview ? '' : ' AND published = 1'), [$slug]); }

/** News visible to the public: status published and publish_at reached (scheduling). Archived items stay reachable by URL. */
function news_live_sql(): string { return "status = 'published' AND publish_at <= ?"; }
function news_categories(): array { return qall('SELECT * FROM news_categories ORDER BY sort, id'); }
function news_list(?int $cat, string $search, string $tag, int $page, int $per, ?int &$total = null): array {
    $w = [news_live_sql()]; $p = [now_sql()];
    if ($cat) { $w[] = 'category_id = ?'; $p[] = $cat; }
    if ($tag !== '') { $w[] = "(',' || tags || ',') LIKE ?"; $p[] = '%,' . str_replace(['%', '_'], '', $tag) . ',%'; }
    if ($search !== '') {
        $s = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
        $w[] = "(title_ar LIKE ? ESCAPE '\\' OR title_en LIKE ? ESCAPE '\\' OR excerpt_ar LIKE ? ESCAPE '\\' OR excerpt_en LIKE ? ESCAPE '\\' OR body_ar LIKE ? ESCAPE '\\' OR body_en LIKE ? ESCAPE '\\' OR tags LIKE ? ESCAPE '\\')";
        array_push($p, $s, $s, $s, $s, $s, $s, $s);
    }
    $where = implode(' AND ', $w);
    $total = (int)qv("SELECT COUNT(*) FROM news WHERE $where", $p);
    return qall("SELECT n.*, c.name_ar AS cat_ar, c.name_en AS cat_en, c.slug AS cat_slug FROM news n LEFT JOIN news_categories c ON c.id = n.category_id WHERE $where ORDER BY n.featured DESC, n.publish_at DESC, n.id DESC LIMIT " . (int)$per . ' OFFSET ' . (int)(($page - 1) * $per), $p);
}
function latest_news(int $n = 3): array { $t = 0; return news_list(null, '', '', 1, $n, $t); }
function news_by_slug(string $slug, bool $preview = false): ?array {
    $sql = 'SELECT n.*, c.name_ar AS cat_ar, c.name_en AS cat_en, c.slug AS cat_slug FROM news n LEFT JOIN news_categories c ON c.id = n.category_id WHERE n.slug = ?';
    if ($preview) return q1($sql, [$slug]);
    return q1($sql . " AND ((n.status = 'published' AND n.publish_at <= ?) OR n.status = 'archived')", [$slug, now_sql()]);
}
function related_news(array $n, int $k = 3): array {
    return qall("SELECT * FROM news WHERE " . news_live_sql() . " AND id <> ? AND (category_id = ? OR ? IS NULL) ORDER BY publish_at DESC LIMIT $k", [now_sql(), $n['id'], $n['category_id'], $n['category_id']]);
}
/** Notices auto-hide after end_date and before start_date. */
function notice_live_sql(): string { return "published = 1 AND (start_date = '' OR start_date <= ?) AND (end_date = '' OR end_date >= ?)"; }
function notices(?string $kind = null, int $limit = 100): array {
    $p = [today(), today()]; $w = notice_live_sql();
    if ($kind) { $w .= ' AND kind = ?'; $p[] = $kind; }
    return qall("SELECT * FROM notices WHERE $w ORDER BY CASE WHEN end_date = '' THEN 1 ELSE 0 END, end_date, id DESC LIMIT " . (int)$limit, $p);
}
function notice_by_slug(string $slug, bool $preview = false): ?array {
    if ($preview) return q1('SELECT * FROM notices WHERE slug = ?', [$slug]);
    return q1('SELECT * FROM notices WHERE slug = ? AND ' . notice_live_sql(), [$slug, today(), today()]);
}
function notice_files(array $n): array {
    $ids = array_values(array_filter(array_map('intval', json_list($n['attachments']))));
    if (!$ids) return [];
    $rows = qall('SELECT * FROM files WHERE id IN (' . implode(',', $ids) . ')');
    usort($rows, fn($a, $b) => array_search($a['id'], $ids) <=> array_search($b['id'], $ids));
    return $rows;
}
function albums(): array { return qall('SELECT * FROM albums WHERE published = 1 ORDER BY sort, id DESC'); }
function album_by_slug(string $slug, bool $preview = false): ?array { return q1('SELECT * FROM albums WHERE slug = ?' . ($preview ? '' : ' AND published = 1'), [$slug]); }
/** Jobs are open until deadline (inclusive). */
function jobs_open(): array { return qall("SELECT * FROM jobs WHERE published = 1 AND (deadline = '' OR deadline >= ?) ORDER BY id DESC", [today()]); }
function job_by_slug(string $slug, bool $preview = false): ?array {
    if ($preview) return q1('SELECT * FROM jobs WHERE slug = ?', [$slug]);
    return q1("SELECT * FROM jobs WHERE slug = ? AND published = 1 AND (deadline = '' OR deadline >= ?)", [$slug, today()]);
}
function menu(string $loc): array { return qall('SELECT * FROM menu_items WHERE location = ? AND published = 1 ORDER BY sort, id', [$loc]); }
/** Is a public-scope file currently allowed for download? (attached to a live notice, or in the media library) */
function public_file_allowed(array $f): bool {
    if ($f['scope'] !== 'public') return false;
    if (q1('SELECT id FROM media WHERE file_id = ?', [$f['id']])) return true;
    foreach (qall('SELECT attachments FROM notices WHERE ' . notice_live_sql(), [today(), today()]) as $n)
        if (in_array((int)$f['id'], array_map('intval', json_list($n['attachments'])), true)) return true;
    return false;
}
/** YouTube / Vimeo link → embed descriptor, or null. */
function video_info(string $url): ?array {
    $url = trim($url);
    if (preg_match('#^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $url, $m))
        return ['type' => 'youtube', 'id' => $m[1], 'embed' => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1&rel=0', 'thumb' => 'https://i.ytimg.com/vi/' . $m[1] . '/hqdefault.jpg', 'url' => $url];
    if (preg_match('#^https?://(?:www\.)?vimeo\.com/(\d{5,12})#', $url, $m))
        return ['type' => 'vimeo', 'id' => $m[1], 'embed' => 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1', 'thumb' => '', 'url' => $url];
    return null;
}
/** Videos field = one "url | Arabic caption | English caption" per line. */
function album_videos(array $a): array {
    $out = [];
    foreach (lines((string)$a['videos']) as $ln) {
        $parts = array_map('trim', explode('|', $ln));
        if ($v = video_info($parts[0])) { $v['cap_ar'] = $parts[1] ?? ''; $v['cap_en'] = $parts[2] ?? ($parts[1] ?? ''); $out[] = $v; }
    }
    return $out;
}
/** Gallery field = JSON list of {src, ar, en}. */
function gallery_items(string $json): array {
    $out = [];
    foreach (json_list($json) as $g) if (is_array($g) && is_string($g['src'] ?? null) && preg_match('#^/(uploads|assets/img)/[a-z0-9-]+\.webp$#', $g['src'])) $out[] = $g;
    return $out;
}
