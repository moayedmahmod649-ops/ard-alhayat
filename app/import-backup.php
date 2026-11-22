<?php
declare(strict_types=1);
/**
 * Move a site to a new server (CLI only):
 *   php app/import-backup.php /path/backup-....zip [--images-from=https://old-site] [--force]
 *
 * Imports a backup made by «النسخ الاحتياطي» on ANOTHER installation of this site, e.g. the Vercel preview.
 * The admin-panel restore only accepts backups signed by the same installation; this command is the supported
 * way to migrate, because only the hosting owner (shell / cron access) can run it.
 *  - checks the manifest, the database checksum, SQLite integrity, schema version and that an active admin exists
 *  - copies the images and documents that are inside the backup (local-hosting backups include them)
 *  - --images-from: downloads every /uploads/… image the content refers to from the old site
 *    (Vercel backups contain the database only; their images stay on the old site until copied)
 *  - keeps users and passwords from the backup, signs everyone out, clears the SMTP password and the page cache
 *  - if this server already has a site, it is kept as storage/backups/pre-import-*.sqlite; --force is required
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/bootstrap.php';
if (cloud()) { fwrite(STDERR, "Cloud mode is on (BLOB_READ_WRITE_TOKEN / AH_DATA_KEY are set). Use the admin panel restore there.\n"); exit(1); }

$args = array_slice($argv, 1); $zipPath = ''; $from = ''; $force = false;
foreach ($args as $a) {
    if (str_starts_with($a, '--images-from=')) $from = rtrim(substr($a, 14), '/');
    elseif ($a === '--force') $force = true;
    elseif ($a !== '' && $a[0] !== '-') $zipPath = $a;
    else { fwrite(STDERR, "Unknown option $a\n"); exit(2); }
}
$say = function (string $m) { fwrite(STDOUT, $m . "\n"); };
$fail = function (string $m) { fwrite(STDERR, "ERROR: $m\n"); exit(1); };
if ($zipPath === '' || !is_file($zipPath)) $fail('usage: php app/import-backup.php backup.zip [--images-from=https://old-site] [--force]');
if ($from !== '' && !preg_match('#^https://[A-Za-z0-9.\-]+(:\d+)?$#', $from)) $fail('--images-from must be an https:// site address without a path');
if (!class_exists('ZipArchive')) $fail('PHP zip extension is missing (cPanel → Select PHP Version → zip)');

$z = new ZipArchive(); if ($z->open($zipPath) !== true) $fail('not a zip file');
$man = json_decode((string)$z->getFromName('manifest.json'), true);
if (!is_array($man) || ($man['app'] ?? '') !== 'ardalhayat') $fail('not an Ard Al-Hayat backup (manifest.json missing)');
$st = $z->statName('db.sqlite');
if (!$st || $st['size'] > 200 * 1048576) $fail('db.sqlite missing or too large');
$dbBytes = (string)$z->getFromName('db.sqlite');
if (!str_starts_with($dbBytes, "SQLite format 3\0") || !hash_equals((string)($man['db'] ?? ''), hash('sha256', $dbBytes))) $fail('database checksum does not match the manifest (damaged or edited file)');

$liveDb = $CONFIG['db_path'];
$hasSite = is_file($liveDb) && filesize($liveDb) > 0 && (int)(new PDO('sqlite:' . $liveDb))->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'users'")->fetchColumn() > 0
    && (int)(new PDO('sqlite:' . $liveDb))->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
if ($hasSite && !$force) $fail("this server already has a site at $liveDb. Add --force to replace it (a copy is kept in storage/backups).");

foreach ([dirname($liveDb), $CONFIG['files_dir'], $CONFIG['uploads_dir'], $CONFIG['backup_dir'], $CONFIG['cache_dir']] as $d) {
    if (!is_dir($d) && !@mkdir($d, 0750, true)) $fail("cannot create $d");
    if (!is_writable($d)) $fail("$d is not writable by PHP (set the folder to 755, owned by your cPanel user)");
}
$stage = dirname($liveDb) . '/.import-' . bin2hex(random_bytes(6));
mkdir($stage, 0750);
$cleanup = function () use ($stage) { foreach (glob("$stage/*") ?: [] as $f) @unlink($f); @rmdir($stage); };
register_shutdown_function($cleanup);
file_put_contents("$stage/db.sqlite", $dbBytes);
$t = new PDO('sqlite:' . "$stage/db.sqlite", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ($t->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') $fail('SQLite integrity check failed');
if ((int)$t->query('PRAGMA user_version')->fetchColumn() !== 1) $fail('backup schema version is not supported by this code version');
if ((int)$t->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1")->fetchColumn() < 1) $fail('the backup has no active system administrator');
$t->exec("UPDATE settings SET v = '' WHERE k = 'smtp_pass'");          // re-enter it in the admin panel on this server
$t->exec('UPDATE users SET session_ver = session_ver + 1');            // sign everybody out
foreach (['login_attempts', 'form_hits'] as $tb) $t->exec("DELETE FROM $tb");
$t->exec("INSERT INTO audit_log (user_id, username, action, entity, label, ip, at) VALUES (NULL, 'cli', 'import', 'backup', " . $t->quote((string)($man['created'] ?? '')) . ", 'cli', " . $t->quote(now_sql()) . ")");

// images referenced by the content
$names = [];
foreach ($t->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN) as $tb) {
    $cols = array_column($t->query('PRAGMA table_info(' . $t->quote($tb) . ')')->fetchAll(PDO::FETCH_ASSOC), 'name');
    foreach ($t->query('SELECT * FROM "' . str_replace('"', '""', $tb) . '"')->fetchAll(PDO::FETCH_NUM) as $row)
        foreach ($row as $v) if (is_string($v) && preg_match_all('#/uploads/([a-z]+-[a-f0-9]{16})(?:-[mt])?\.webp#', $v, $m)) foreach ($m[1] as $n) $names[$n] = true;
}
$docs = $t->query('SELECT stored FROM files')->fetchAll(PDO::FETCH_COLUMN);
$t->exec('PRAGMA journal_mode = DELETE'); $t = null;

// files inside the zip (local-hosting backups carry them; Vercel backups do not)
$inImg = 0; $inDoc = 0;
for ($i = 0; $i < $z->numFiles; $i++) {
    $n = (string)$z->getNameIndex($i); $sz = (int)($z->statIndex($i)['size'] ?? 0);
    if ($sz > 20 * 1048576) continue;
    if (preg_match('#^uploads/([a-z]+-[a-f0-9]{16}(-[mt])?\.webp)$#', $n, $m)) {
        $b = (string)$z->getFromIndex($i); $info = @getimagesizefromstring($b);
        if ($info && $info['mime'] === 'image/webp') { file_put_contents($CONFIG['uploads_dir'] . '/' . $m[1], $b); @chmod($CONFIG['uploads_dir'] . '/' . $m[1], 0644); $inImg++; }
    } elseif (preg_match('#^files/([a-f0-9]{32})$#', $n, $m)) {
        $b = (string)$z->getFromIndex($i);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($b);
        if (in_array($mime, ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream', 'application/CDFV2'], true)) {
            file_put_contents($CONFIG['files_dir'] . '/' . $m[1], $b); @chmod($CONFIG['files_dir'] . '/' . $m[1], 0640); $inDoc++;
        }
    }
}
$z->close();

// images from the old site
$got = 0; $missing = [];
if ($from !== '') {
    $fetch = function (string $url): ?string {
        if (function_exists('curl_init')) {
            $c = curl_init($url);
            curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 60, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
            $b = curl_exec($c); $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
            return ($code === 200 && is_string($b)) ? $b : null;
        }
        $b = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 60, 'follow_location' => 0]]));
        $h = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);
        return ($b !== false && str_contains((string)($h[0] ?? ''), ' 200')) ? $b : null;
    };
    foreach (array_keys($names) as $base) foreach (['', '-m', '-t'] as $suf) {
        $file = "$base$suf.webp"; $dest = $CONFIG['uploads_dir'] . '/' . $file;
        if (is_file($dest)) continue;
        $b = $fetch("$from/uploads/$file");
        $info = $b !== null ? @getimagesizefromstring($b) : false;
        if ($b !== null && $info && $info['mime'] === 'image/webp' && strlen($b) <= 20 * 1048576) { file_put_contents($dest, $b); @chmod($dest, 0644); $got++; }
        elseif ($suf === '') $missing[] = $file; // -m / -t sizes only exist for some images
    }
}

// switch
if ($hasSite) {
    $keep = $CONFIG['backup_dir'] . '/pre-import-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.sqlite';
    $old = new PDO('sqlite:' . $liveDb); $old->exec('VACUUM INTO ' . $old->quote($keep)); $old = null; @chmod($keep, 0640);
    $say("Previous site database kept at $keep");
}
foreach (['-wal', '-shm'] as $x) @unlink($liveDb . $x);
if (!rename("$stage/db.sqlite", $liveDb)) $fail('could not move the database into place');
@chmod($liveDb, 0640);
page_cache_clear();

$docMissing = 0; foreach ($docs as $s) if (!is_file($CONFIG['files_dir'] . '/' . $s)) $docMissing++;
$say('Imported backup from ' . ($man['created'] ?? '?') . " into $liveDb");
$say("Images: $inImg from the zip, $got downloaded" . ($from !== '' ? " from $from" : '') . ', ' . count($names) . ' referenced by the content');
if ($missing) $say('Not found (re-upload in the admin media library): ' . implode(', ', $missing));
if ($from === '' && $names && !$inImg) $say('Tip: add --images-from=https://<old-site> to copy the images too.');
$say("Documents (CVs, tender files): $inDoc from the zip" . ($docMissing ? ", $docMissing referenced but missing (re-attach them in the admin panel)" : ''));
$say('Done. Everyone was signed out: log in at /admin/ with the same username and password as on the old site. Re-enter the SMTP password in Settings.');
