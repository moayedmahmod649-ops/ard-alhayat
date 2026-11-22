<?php
declare(strict_types=1);
const LANGS = ['ar', 'en'];
/** Localised field with Arabic fallback (EN visitors still see content if a translation is missing). */
function L(?array $row, string $f): string {
    if (!$row) return '';
    $v = (string)($row[$f . '_' . LANG] ?? '');
    return $v !== '' ? $v : (string)($row[$f . '_ar'] ?? '');
}
function t(string $k, ...$args): string {
    static $S = null;
    $S ??= require APP_DIR . '/lib/strings.php';
    $s = $S[$k][LANG === 'en' ? 1 : 0] ?? $S[$k][0] ?? $k;
    return $args ? vsprintf($s, $args) : $s;
}
function url(string $path = ''): string { return '/' . LANG . '/' . ltrim($path, '/'); }
function url_lang(string $lang, string $path = ''): string { return '/' . $lang . '/' . ltrim($path, '/'); }
function dir_attr(): string { return LANG === 'ar' ? 'rtl' : 'ltr'; }
/** Admin-panel strings (bilingual admin UI). */
function ta(string $k, ...$args): string {
    static $S = null;
    $S ??= require APP_DIR . '/lib/admin_strings.php';
    $lang = defined('ADMIN_LANG') ? ADMIN_LANG : (defined('LANG') ? LANG : 'ar');
    $s = $S[$k][$lang === 'en' ? 1 : 0] ?? $S[$k][0] ?? $k;
    return $args ? vsprintf($s, $args) : $s;
}
