<?php
declare(strict_types=1);
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'); }
/** Escape, then isolate Latin runs inside Arabic text so RTL ordering stays correct. */
function bidi(?string $s): string {
    $h = e($s);
    if (LANG !== 'ar') return $h;
    return preg_replace('/(?<![\w&#;])([A-Za-z][A-Za-z0-9.+&;#\/-]*(?:\s+[A-Za-z0-9][A-Za-z0-9.+&;#\/-]*)*)/u', '<bdi lang="en">$1</bdi>', $h) ?? $h;
}
function slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim(substr($s, 0, 70), '-');
}
function valid_slug(string $s): bool { return (bool)preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $s) && strlen($s) <= 80; }
function now_sql(): string { return date('Y-m-d H:i:s'); }
function today(): string { return date('Y-m-d'); }
function post(string $k, string $d = ''): string { $v = $_POST[$k] ?? $d; return is_string($v) ? trim($v) : $d; }
function get(string $k, string $d = ''): string { $v = $_GET[$k] ?? $d; return is_string($v) ? trim($v) : $d; }
function post_bool(string $k): int { return isset($_POST[$k]) && $_POST[$k] !== '0' ? 1 : 0; }
function clamp_int($v, int $min, int $max, int $def): int { if (!is_numeric($v)) return $def; return max($min, min($max, (int)$v)); }
function redirect(string $url): never { header('Location: ' . $url, true, 303); exit; }
function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0'), 0, 64); }
function is_https(): bool { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'); }
function base_url(): string {
    global $CONFIG;
    if ($CONFIG['base_url'] !== '') return rtrim($CONFIG['base_url'], '/');
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    return (is_https() ? 'https' : 'http') . '://' . $host;
}
const AR_MONTHS = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
function fdate(?string $d, ?string $lang = null): string {
    $t = $d ? strtotime($d) : false; if (!$t) return '';
    $lang ??= defined('LANG') ? LANG : 'ar';
    return $lang === 'ar' ? (int)date('j', $t) . ' ' . AR_MONTHS[(int)date('n', $t) - 1] . ' ' . date('Y', $t) : date('j M Y', $t);
}
function valid_date(string $d): bool { return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false; }
function safe_url(string $u, bool $allowRelative = true): string {
    $u = trim($u);
    if ($u === '') return '';
    $clean = preg_replace('/[\x00-\x20\x7F]+/', '', $u) ?? '';
    if (preg_match('#^(https?://|mailto:|tel:)#i', $clean)) return $clean;
    if ($allowRelative && preg_match('~^/(?!/)[A-Za-z0-9/_.?=&%#-]*$~', $clean)) return $clean;
    return '';
}
function valid_hex(string $c): bool { return (bool)preg_match('/^#[0-9a-fA-F]{6}$/', $c); }
function lines(string $s): array { return array_values(array_filter(array_map('trim', preg_split('/\R/u', $s) ?: []), fn($x) => $x !== '')); }
function excerpt(string $html, int $n = 160): string {
    $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    return mb_strlen($t) > $n ? rtrim(mb_substr($t, 0, $n)) . '…' : $t;
}
function setting(string $k, string $d = ''): string {
    static $all = null;
    if ($all === null) { $all = []; foreach (qall('SELECT k, v FROM settings') as $r) $all[$r['k']] = $r['v']; }
    if ($k === '__reset') { $all = null; return ''; }
    return $all[$k] ?? $d;
}
function set_setting(string $k, string $v): void {
    q('DELETE FROM settings WHERE k = ?', [$k]);
    q('INSERT INTO settings (k, v) VALUES (?, ?)', [$k, $v]);
    setting('__reset');
}
/** Throttle anonymous form submissions per IP. */
function form_throttled(string $form, int $max = 5, int $window = 600): bool {
    $n = (int)qv('SELECT COUNT(*) FROM form_hits WHERE ip = ? AND form = ? AND at > ?', [client_ip(), $form, time() - $window]);
    // serverless storage has a monthly write allowance: also cap total submissions per instance and hour
    if (cloud() && (int)qv('SELECT COUNT(*) FROM form_hits WHERE at > ?', [time() - 3600]) >= 40) return true;
    return $n >= $max;
}
function form_hit(string $form): void {
    q('INSERT INTO form_hits (ip, form, at) VALUES (?,?,?)', [client_ip(), $form, time()]);
    if (random_int(1, 50) === 1) q('DELETE FROM form_hits WHERE at < ?', [time() - 86400]);
}
function valid_email(string $e): bool { return strlen($e) <= 190 && filter_var($e, FILTER_VALIDATE_EMAIL) !== false; }
function json_list(string $s): array { $a = json_decode($s, true); return is_array($a) ? $a : []; }
function human_bytes(int $b): string { return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int)round($b / 1024)) . ' KB'; }
