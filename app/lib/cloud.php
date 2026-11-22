<?php
declare(strict_types=1);
/**
 * Cloud mode (serverless hosting, e.g. Vercel): the filesystem is ephemeral and instances do not share disk.
 *  - The SQLite database lives as ONE encrypted object (AES-256-GCM) in Vercel Blob; every instance keeps a copy
 *    in /tmp, revalidates it (ETag) on a time budget, and writes it back with optimistic concurrency (If-Match).
 *    A conflicting write (412) re-downloads the newest copy, replays this request's statements and retries.
 *  - Short-lived tables (login_attempts, form_hits, visits) live in a per-instance side database and never sync.
 *  - Sessions are stored client-side in an encrypted, authenticated cookie (no server session files).
 *  - Uploaded images are re-encoded by GD as before, then pushed to Blob (served at /uploads/* via a rewrite).
 *  - Documents (CVs, tender files) are encrypted before upload and only ever served through PHP.
 * Enabled when BLOB_READ_WRITE_TOKEN and AH_DATA_KEY are present in the environment.
 */
const CLOUD_DB_PATH = 'state/site-db.bin';
const CLOUD_EPHEMERAL = ['login_attempts', 'form_hits', 'visits'];
define('CLOUD_TMP', getenv('AH_CLOUD_TMP') ?: '/tmp/ah');

function cloud(): bool {
    static $on = null;
    return $on ??= (getenv('BLOB_READ_WRITE_TOKEN') ?: '') !== '' && (getenv('AH_DATA_KEY') ?: '') !== '';
}
function blob_token(): string { return (string)getenv('BLOB_READ_WRITE_TOKEN'); }
function blob_base(): string { return getenv('AH_BLOB_BASE') ?: 'https://' . strtolower(explode('_', blob_token())[3] ?? '') . '.public.blob.vercel-storage.com'; }
function blob_api_url(): string { return getenv('AH_BLOB_API') ?: 'https://vercel.com/api/blob'; } // overrides exist only for the local test double
function data_key(string $purpose): string {
    $raw = base64_decode((string)getenv('AH_DATA_KEY'), true);
    if ($raw === false || strlen($raw) < 32) throw new RuntimeException('AH_DATA_KEY must be 32+ random bytes, base64');
    return hash_hmac('sha256', $purpose, $raw, true);
}
/** AES-256-GCM: "AH1" | nonce(12) | tag(16) | ciphertext. $aad binds a blob to its purpose. */
function seal(string $plain, string $purpose): string {
    $iv = random_bytes(12); $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', data_key($purpose), OPENSSL_RAW_DATA, $iv, $tag, $purpose, 16);
    if ($ct === false) throw new RuntimeException('encrypt');
    return 'AH1' . $iv . $tag . $ct;
}
function unseal(string $box, string $purpose): ?string {
    if (strlen($box) < 31 || !str_starts_with($box, 'AH1')) return null;
    $p = openssl_decrypt(substr($box, 31), 'aes-256-gcm', data_key($purpose), OPENSSL_RAW_DATA, substr($box, 3, 12), substr($box, 15, 16), $purpose);
    return $p === false ? null : $p;
}
function b64u(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function b64u_dec(string $s): string|false { return base64_decode(strtr($s, '-_', '+/'), true); }

/* ---------------- Blob REST ---------------- */
function blob_ops(string $kind): void { $GLOBALS['BLOB_OPS'][$kind] = ($GLOBALS['BLOB_OPS'][$kind] ?? 0) + 1; }
/** @return array{0:int,1:?array,2:string} status, decoded json, raw */
function blob_api(string $method, string $path, ?string $body, array $headers = []): array {
    blob_ops('advanced');
    $h = ['authorization: Bearer ' . blob_token(), 'x-api-version: 13'];
    foreach ($headers as $k => $v) $h[] = "$k: $v";
    $c = curl_init(blob_api_url() . $path);
    curl_setopt_array($c, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $h,
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 25]);
    if ($body !== null) curl_setopt($c, CURLOPT_POSTFIELDS, $body);
    $raw = (string)curl_exec($c); $st = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
    $j = json_decode($raw, true);
    return [$st, is_array($j) ? $j : null, $raw];
}
/** Upload (public delivery; secrets are sealed before they get here). Returns the new ETag or throws. */
function blob_put(string $pathname, string $body, string $type, ?string $ifMatch = null, bool $overwrite = true): string {
    $hd = ['x-vercel-blob-access' => 'public', 'x-add-random-suffix' => '0', 'x-allow-overwrite' => $overwrite ? '1' : '0', 'x-content-type' => $type];
    if ($ifMatch !== null) $hd['x-if-match'] = $ifMatch;
    [$st, $j, $raw] = blob_api('PUT', '/?pathname=' . rawurlencode($pathname), $body, $hd);
    if ($st === 412 || ($j['error']['code'] ?? '') === 'precondition_failed') throw new BlobConflict();
    if ($st !== 200 || empty($j['etag'])) { error_log("blob put $pathname failed: $st " . substr($raw, 0, 300)); throw new RuntimeException('blob put'); }
    return (string)$j['etag'];
}
function blob_delete(array $pathnames): void {
    $urls = array_values(array_map(fn($p) => blob_base() . '/' . ltrim($p, '/'), $pathnames));
    if (!$urls) return;
    [$st] = blob_api('POST', '/delete', json_encode(['urls' => $urls]), ['content-type' => 'application/json']);
    if ($st !== 200) error_log("blob delete failed: $st");
}
/** @return array{0:int,1:string,2:string} status, body, etag. Every call from a function counts as one Blob read. */
function blob_get(string $pathname, ?string $etag = null): array {
    blob_ops('simple');
    $hd = []; $out = ['etag' => ''];
    $c = curl_init(blob_base() . '/' . ltrim($pathname, '/') . '?r=' . bin2hex(random_bytes(4)));
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $etag ? ['If-None-Match: ' . $etag, 'Cache-Control: no-cache'] : ['Cache-Control: no-cache'],
        CURLOPT_HEADERFUNCTION => function ($c, $l) use (&$out) { if (stripos($l, 'etag:') === 0) $out['etag'] = trim(substr($l, 5)); return strlen($l); }]);
    $body = (string)curl_exec($c); $st = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
    if ($st === 0) error_log('blob get transport error: ' . curl_error($c));
    return [$st, $body, $out['etag'] ?: (string)$etag];
}
final class BlobConflict extends RuntimeException {}

/* ---------------- database sync ---------------- */
function cloud_meta(?array $set = null): array {
    $f = CLOUD_TMP . '/db.meta.json';
    if ($set !== null) { file_put_contents("$f.tmp", json_encode($set)); rename("$f.tmp", $f); return $set; }
    $m = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    return is_array($m) ? $m : ['etag' => '', 'checked' => 0, 'hint' => ''];
}
/** Signed "you just saved version X" hint cookie → the instance serving the next request refreshes at once. */
function cloud_hint_cookie(string $etag): void {
    $v = b64u(substr(md5($etag), 0, 12) . '.' . time());
    $v .= '.' . b64u(substr(hash_hmac('sha256', $v, data_key('hint'), true), 0, 16));
    if (!headers_sent()) setcookie('ah_v', $v, ['expires' => time() + 900, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
}
function cloud_hint(): ?string {
    $c = (string)($_COOKIE['ah_v'] ?? '');
    if (!preg_match('/^([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)$/', $c, $m)) return null;
    if (!hash_equals(b64u(substr(hash_hmac('sha256', $m[1], data_key('hint'), true), 0, 16)), $m[2])) return null;
    [$h, $ts] = array_pad(explode('.', (string)b64u_dec($m[1])), 2, '0');
    return (int)$ts > time() - 900 ? $h : null; // only recent saves; replaying an old cookie cannot force reads
}
/**
 * Make sure /tmp holds a current copy of the database before PDO opens it.
 * Reads are budgeted: anonymous traffic revalidates at most every 10 min per instance, admin pages every 60 s,
 * and immediately when the signed hint cookie names a newer version.
 */
function cloud_db_prepare(string $dbPath): void {
    if (!is_dir(CLOUD_TMP)) mkdir(CLOUD_TMP, 0700, true);
    $m = cloud_meta(); $have = is_file($dbPath) && $m['etag'] !== '';
    $isAdmin = str_starts_with((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/admin');
    $hint = cloud_hint(); $hintNew = $hint !== null && $hint !== substr(md5($m['etag']), 0, 12) && $hint !== ($m['hint'] ?? '');
    $age = time() - (int)$m['checked'];
    if ($have && !$hintNew && $age < ($isAdmin ? 60 : 600)) return;
    if ($hintNew) $m['hint'] = $hint;
    [$st, $body, $etag] = blob_get(CLOUD_DB_PATH, $have ? $m['etag'] : null);
    if ($st === 304) { $m['checked'] = time(); cloud_meta($m); return; }
    if ($st === 200 && ($plain = unseal($body, 'db')) !== null && str_starts_with($plain, 'SQLite format 3')) {
        cloud_install_db($dbPath, $plain); cloud_meta(['etag' => $etag, 'checked' => time(), 'hint' => $m['hint'] ?? '']); return;
    }
    if ($st === 404 && !$have) { cloud_first_boot($dbPath); return; }
    error_log("cloud db fetch failed: $st");
    if ($have) { $m['checked'] = time() - 540; cloud_meta($m); return; } // keep serving the local copy; retry soon
    http_response_code(503); header('Retry-After: 30'); exit('Temporarily unavailable. Please retry in a moment.');
}
function cloud_install_db(string $dbPath, string $plain): void {
    $tmp = $dbPath . '.' . bin2hex(random_bytes(4));
    file_put_contents($tmp, $plain); chmod($tmp, 0600);
    foreach (['-journal', '-wal', '-shm'] as $x) @unlink($dbPath . $x);
    rename($tmp, $dbPath);
}
/** No database in Blob yet: start from the sealed seed that ships with the deployment and publish it. */
function cloud_first_boot(string $dbPath): void {
    $seed = APP_DIR . '/storage/seed.sealed';
    $plain = is_file($seed) ? unseal((string)file_get_contents($seed), 'seed') : null;
    if ($plain === null) { http_response_code(503); exit('Not initialised.'); }
    try { $etag = blob_put(CLOUD_DB_PATH, seal($plain, 'db'), 'application/octet-stream', null, false); try { blob_delete(['diag/keep.bin']); } catch (Throwable) {} }
    catch (Throwable) { // another instance won the race — use its copy
        [$st, $body, $etag] = blob_get(CLOUD_DB_PATH);
        if ($st !== 200 || ($plain = unseal($body, 'db')) === null) { http_response_code(503); exit('Not initialised.'); }
    }
    cloud_install_db($dbPath, $plain); cloud_meta(['etag' => $etag, 'checked' => time(), 'hint' => '']);
}
/** Per-instance side database for counters and throttles (unqualified table names resolve here). */
function cloud_attach_local(PDO $pdo): void {
    $pdo->exec('ATTACH DATABASE ' . $pdo->quote(CLOUD_TMP . '/local.sqlite') . ' AS loc');
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS loc.login_attempts (id INTEGER PRIMARY KEY, ip TEXT NOT NULL, username TEXT NOT NULL, at INTEGER NOT NULL);
CREATE INDEX IF NOT EXISTS loc.la_ip ON login_attempts(ip, at);
CREATE TABLE IF NOT EXISTS loc.form_hits (id INTEGER PRIMARY KEY, ip TEXT NOT NULL, form TEXT NOT NULL, at INTEGER NOT NULL);
CREATE INDEX IF NOT EXISTS loc.fh_ip ON form_hits(ip, form, at);
CREATE TABLE IF NOT EXISTS loc.visits (day TEXT NOT NULL, path TEXT NOT NULL, hits INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (day, path));
SQL);
}
/** Called by q() for every statement: remember writes to durable tables so they can be saved and replayed. */
function cloud_track(string $sql, array $p): void {
    if (!preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b(?:\s+OR\s+\w+)?\s+(?:INTO\s+|FROM\s+)?["`]?(\w+)/i', $sql, $m)) {
        if (preg_match('/^\s*(CREATE|DROP|ALTER)\b/i', $sql)) { $GLOBALS['CLOUD_DIRTY'] = true; $GLOBALS['CLOUD_JOURNAL'][] = [$sql, $p]; }
        return;
    }
    if (in_array(strtolower($m[2]), CLOUD_EPHEMERAL, true)) return;
    $GLOBALS['CLOUD_DIRTY'] = true;
    $GLOBALS['CLOUD_JOURNAL'][] = [$sql, $p];
}
function cloud_mark_dirty(): void { $GLOBALS['CLOUD_DIRTY'] = true; $GLOBALS['CLOUD_JOURNAL_BROKEN'] = true; }
/** Write the database back to Blob. Returns true when saved (or nothing to save). */
function cloud_persist(): bool {
    global $CONFIG;
    if (empty($GLOBALS['CLOUD_DIRTY'])) return true;
    $pdo = db();
    if ($pdo->inTransaction()) $pdo->exec('COMMIT');
    $m = cloud_meta();
    for ($try = 0; $try < 3; $try++) {
        $plain = (string)file_get_contents($CONFIG['db_path']);
        try {
            $etag = blob_put(CLOUD_DB_PATH, seal($plain, 'db'), 'application/octet-stream', $m['etag'] ?: null);
            cloud_meta($m = ['etag' => $etag, 'checked' => time(), 'hint' => substr(md5($etag), 0, 12)]);
            cloud_hint_cookie($etag); $GLOBALS['CLOUD_DIRTY'] = false;
            return true;
        } catch (BlobConflict) {
            // someone saved in between: take their copy, re-apply our statements, try again
            if (!empty($GLOBALS['CLOUD_JOURNAL_BROKEN'])) return false;
            [$st, $body, $etag] = blob_get(CLOUD_DB_PATH);
            if ($st !== 200 || ($fresh = unseal($body, 'db')) === null) return false;
            $tmp = CLOUD_TMP . '/replay.sqlite'; file_put_contents($tmp, $fresh);
            try {
                $r = new PDO('sqlite:' . $tmp, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $r->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = DELETE');
                $r->beginTransaction();
                foreach ($GLOBALS['CLOUD_JOURNAL'] ?? [] as [$sql, $p]) { $s = $r->prepare($sql); $s->execute($p); }
                $r->commit(); $r = null;
            } catch (Throwable $e) { error_log('cloud replay failed: ' . $e->getMessage()); return false; }
            db_close(); cloud_install_db($CONFIG['db_path'], (string)file_get_contents($tmp)); @unlink($tmp);
            $m = ['etag' => $etag, 'checked' => time(), 'hint' => ''];
            cloud_meta($m);
        } catch (Throwable $e) { error_log('cloud persist failed: ' . $e->getMessage()); return false; }
    }
    return false;
}
/** End of every request: save, then emit the session cookie while output is still buffered. */
function cloud_shutdown(): void {
    $ok = true;
    try { $ok = cloud_persist(); } catch (Throwable $e) { error_log('cloud shutdown: ' . $e->getMessage()); $ok = false; }
    if (!$ok) {
        // the change did not reach storage — never pretend it did
        cloud_reset_local();
        if (!headers_sent()) {
            header_remove('Location'); http_response_code(503); header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store');
            while (ob_get_level() > 1) ob_end_clean();
            if (ob_get_level()) ob_clean();
            echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Not saved</title>'
               . '<div style="font:16px/1.7 system-ui,sans-serif;max-width:560px;margin:12vh auto;padding:24px">'
               . '<h1 style="font-size:22px">لم يُحفظ التغيير · Change not saved</h1>'
               . '<p dir="rtl">تعذّر حفظ التعديل في وحدة التخزين الآن. ارجع وحاول مرة أخرى بعد لحظات.</p>'
               . '<p>Storage could not be updated just now. Go back and try again in a moment.</p>'
               . '<p><a href="javascript:history.back()">← Back</a></p></div>';
        }
    }
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
}
/** Throw away a local copy that may contain unsaved changes; the next request re-downloads. */
function cloud_reset_local(): void {
    global $CONFIG;
    db_close();
    foreach (['', '-journal', '-wal', '-shm'] as $x) @unlink($CONFIG['db_path'] . $x);
    cloud_meta(['etag' => '', 'checked' => 0, 'hint' => '']);
}

/* ---------------- files ---------------- */
/** Push a freshly re-encoded image (already on local disk) to Blob under uploads/. */
function cloud_push_image(string $localPath): void {
    $name = basename($localPath);
    if (!preg_match('/^[a-z]+-[a-f0-9]{16}(-m|-t)?\.webp$/', $name)) throw new RuntimeException('bad name');
    blob_put('uploads/' . $name, (string)file_get_contents($localPath), 'image/webp', null, false);
    @unlink($localPath);
}
function cloud_put_doc(string $stored, string $bytes): void { blob_put("docs/$stored.bin", seal($bytes, "doc:$stored"), 'application/octet-stream', null, false); }
function cloud_get_doc(string $stored): ?string {
    [$st, $body] = blob_get("docs/$stored.bin");
    return $st === 200 ? unseal($body, "doc:$stored") : null;
}

/* ---------------- sessions in an encrypted cookie ---------------- */
final class CookieSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {
    private const C = 'ah_sd';
    private function payload(): ?array {
        $c = (string)($_COOKIE[self::C] ?? ''); if ($c === '' || strlen($c) > 4000) return null;
        $raw = b64u_dec($c); if ($raw === false) return null;
        $j = json_decode((string)unseal($raw, 'session'), true);
        return is_array($j) && ($j['exp'] ?? 0) > time() ? $j : null;
    }
    private function emit(string $id, string $data): bool {
        global $CONFIG;
        if (headers_sent()) return true;
        $v = b64u(seal(json_encode(['id' => $id, 'd' => $data, 'exp' => time() + $CONFIG['session_idle']]), 'session'));
        if (strlen($v) > 3900) { error_log('session cookie too large'); return false; }
        setcookie(self::C, $v, ['expires' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
        $_COOKIE[self::C] = $v;
        return true;
    }
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string|false { $p = $this->payload(); return $p && hash_equals((string)$p['id'], $id) ? (string)$p['d'] : ''; }
    public function write(string $id, string $data): bool { return $this->emit($id, $data); }
    public function destroy(string $id): bool {
        if (!headers_sent()) setcookie(self::C, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
        unset($_COOKIE[self::C]); return true;
    }
    public function gc(int $max): int|false { return 0; }
    public function validateId(string $id): bool { $p = $this->payload(); return $p !== null && hash_equals((string)$p['id'], $id); }
    public function updateTimestamp(string $id, string $data): bool { return $this->emit($id, $data); }
}
