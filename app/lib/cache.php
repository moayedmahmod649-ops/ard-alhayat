<?php
declare(strict_types=1);
/** Tiny full-page cache for anonymous GET requests. Cleared on every admin write. */
function page_cache_key(): string { return hash('sha256', ($_SERVER['REQUEST_URI'] ?? '/') . (cloud() ? '|' . cloud_meta()['etag'] : '')); }
function page_cache_get(): ?string {
    global $CONFIG;
    $f = $CONFIG['cache_dir'] . '/' . page_cache_key() . '.html';
    if (is_file($f) && filemtime($f) > time() - $CONFIG['page_cache_ttl']) return (string)file_get_contents($f);
    return null;
}
function page_cache_put(string $html): void {
    global $CONFIG;
    if (!is_dir($CONFIG['cache_dir'])) @mkdir($CONFIG['cache_dir'], 0750, true);
    @file_put_contents($CONFIG['cache_dir'] . '/' . page_cache_key() . '.html', $html, LOCK_EX);
}
function page_cache_clear(): void {
    global $CONFIG;
    foreach (glob($CONFIG['cache_dir'] . '/*.html') ?: [] as $f) @unlink($f);
}
function count_visit(string $path): void {
    try {
        $path = mb_substr($path, 0, 190);
        $n = q('UPDATE visits SET hits = hits + 1 WHERE day = ? AND path = ?', [today(), $path])->rowCount();
        if ($n === 0) q('INSERT INTO visits (day, path, hits) VALUES (?,?,1)', [today(), $path]);
    } catch (Throwable $e) { /* never break a page for analytics */ }
}
