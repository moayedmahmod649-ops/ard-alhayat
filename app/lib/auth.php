<?php
declare(strict_types=1);
function session_boot(bool $readOnly = false): void {
    global $CONFIG;
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = $CONFIG['force_https_cookie'] ?? is_https();
    session_name($CONFIG['session_name']);
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => (bool)$secure, 'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $readOnly ? session_start(['read_and_close' => true]) : session_start();
}
function current_user(): ?array {
    global $CONFIG;
    static $u = false;
    if ($u !== false) return $u;
    if (empty($_SESSION['uid']) || ($_SESSION['seen'] ?? 0) < time() - $CONFIG['session_idle']) return $u = null;
    $row = q1('SELECT * FROM users WHERE id = ?', [$_SESSION['uid']]);
    // disabled users and sessions revoked by a password reset are rejected
    if (!$row || !$row['active'] || (int)$row['session_ver'] !== (int)($_SESSION['sv'] ?? -1)) return $u = null;
    return $u = $row;
}
function admin_preview_allowed(): bool {
    global $CONFIG;
    if (empty($_COOKIE[$CONFIG['session_name']])) return false;
    session_boot(true);
    if (empty($_SESSION['uid']) || ($_SESSION['seen'] ?? 0) < time() - $CONFIG['session_idle']) return false;
    $row = q1('SELECT active, session_ver FROM users WHERE id = ?', [$_SESSION['uid']]);
    return $row && $row['active'] && (int)$row['session_ver'] === (int)($_SESSION['sv'] ?? -1);
}
function login_throttled(string $username): bool {
    $st = q('SELECT COUNT(*) FROM login_attempts WHERE (ip = ? OR username = ?) AND at > ?', [client_ip(), mb_strtolower($username), time() - 900]);
    return (int)$st->fetchColumn() >= 5;
}
function attempt_login(string $username, string $password): bool {
    $username = mb_strtolower(trim($username));
    $row = q1('SELECT id, pass_hash, active, session_ver FROM users WHERE username = ? OR (email <> \'\' AND lower(email) = ?)', [$username, $username]);
    $hash = $row['pass_hash'] ?? password_hash(random_bytes(12), PASSWORD_DEFAULT); // dummy keeps timing similar
    $ok = password_verify($password, $hash) && $row && $row['active'];
    if (!$ok) { q('INSERT INTO login_attempts (ip, username, at) VALUES (?,?,?)', [client_ip(), $username, time()]); return false; }
    if (password_needs_rehash($row['pass_hash'], PASSWORD_DEFAULT)) q('UPDATE users SET pass_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    q('DELETE FROM login_attempts WHERE username = ? OR ip = ?', [$username, client_ip()]);
    session_regenerate_id(true);
    $_SESSION = ['uid' => (int)$row['id'], 'sv' => (int)$row['session_ver'], 'seen' => time(), 'csrf' => bin2hex(random_bytes(32))];
    q('UPDATE users SET last_login = ? WHERE id = ?', [now_sql(), $row['id']]);
    return true;
}
function logout(): void {
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
}
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function same_origin_ok(): bool {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '' || $origin === 'null') return $origin === '';
    return parse_url($origin, PHP_URL_HOST) === parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
}
function csrf_check(): void {
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t) || !same_origin_ok()) {
        http_response_code(400);
        exit('Invalid request (CSRF). Reload the page and try again. / طلب غير صالح، أعد تحميل الصفحة.');
    }
}
/** Stateless anti-forgery/anti-bot token for anonymous public forms (works with the page cache). */
function app_secret(): string {
    $s = setting('app_secret');
    if ($s === '') { $s = bin2hex(random_bytes(32)); set_setting('app_secret', $s); }
    return $s;
}
function form_token(string $form): string { $ts = (string)time(); return $ts . '.' . hash_hmac('sha256', $form . '|' . $ts, app_secret()); }
function form_token_ok(string $form): bool {
    $t = $_POST['_ft'] ?? '';
    if (!is_string($t) || !preg_match('/^(\d{10})\.([a-f0-9]{64})$/', $t, $m)) return false;
    $age = time() - (int)$m[1];
    if ($age < 2 || $age > 86400) return false; // bots post instantly; cached pages stay valid for a day
    return hash_equals(hash_hmac('sha256', $form . '|' . $m[1], app_secret()), $m[2]) && same_origin_ok();
}
function audit(string $action, string $entity = '', ?int $id = null, string $label = ''): void {
    $u = current_user();
    q('INSERT INTO audit_log (user_id, username, action, entity, entity_id, label, ip, at) VALUES (?,?,?,?,?,?,?,?)',
      [$u['id'] ?? null, $u['username'] ?? '', $action, $entity, $id, mb_substr($label, 0, 200), client_ip(), now_sql()]);
}
/** Password reset tokens: random, stored hashed, single-use, 30-minute expiry. */
function create_reset_token(int $uid, ?int $issuedBy = null, int $ttl = 1800): string {
    $tok = bin2hex(random_bytes(24));
    q('UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0', [$uid]);
    q('INSERT INTO password_resets (user_id, token_hash, expires_at, issued_by) VALUES (?,?,?,?)', [$uid, hash('sha256', $tok), time() + $ttl, $issuedBy]);
    return $tok;
}
function find_reset(string $tok): ?array {
    if (!preg_match('/^[a-f0-9]{48}$/', $tok)) return null;
    return q1('SELECT * FROM password_resets WHERE token_hash = ? AND used = 0 AND expires_at > ?', [hash('sha256', $tok), time()]);
}
function set_password(int $uid, string $pw): void {
    // bumping session_ver revokes every existing session of that user
    q('UPDATE users SET pass_hash = ?, must_change = 0, session_ver = session_ver + 1, updated_at = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), now_sql(), $uid]);
}
function password_problem(string $pw, string $username = ''): ?string {
    if (mb_strlen($pw) < 10) return 'pw_short';
    if ($username !== '' && stripos($pw, $username) !== false) return 'pw_user';
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) return 'pw_mix';
    return null;
}
