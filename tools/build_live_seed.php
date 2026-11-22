<?php
// Build the sealed first-boot database for the cloud deployment from the freshly seeded local DB.
// usage: AH_DATA_KEY=... php tools/build_live_seed.php
declare(strict_types=1);
$site = dirname(__DIR__) . '/site';
define('APP_DIR', $site . '/app');
require APP_DIR . '/lib/cloud.php';
if (!getenv('AH_DATA_KEY')) { fwrite(STDERR, "AH_DATA_KEY missing\n"); exit(1); }
$src = $site . '/app/storage/data/ardalhayat.sqlite';
$tmp = sys_get_temp_dir() . '/live-seed-' . bin2hex(random_bytes(4)) . '.sqlite';
$pdo = new PDO('sqlite:' . $src); $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)'); $pdo->exec('VACUUM INTO ' . $pdo->quote($tmp)); $pdo = null;
$db = new PDO('sqlite:' . $tmp, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA journal_mode = DELETE');
// fresh admin password, forced change at first login
$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
$pw = ''; for ($i = 0; $i < 18; $i++) $pw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
$pw .= '-' . random_int(10, 99);
$st = $db->prepare("UPDATE users SET pass_hash = ?, must_change = 1, session_ver = session_ver + 1, last_login = NULL WHERE username = 'admin'");
$st->execute([password_hash($pw, PASSWORD_DEFAULT)]);
if ($st->rowCount() !== 1) { fwrite(STDERR, "admin user missing\n"); exit(1); }
$db->exec("DELETE FROM users WHERE username <> 'admin'");
// new signing secret; no SMTP secret; hidden from search engines until launch
$db->prepare("INSERT OR REPLACE INTO settings (k, v) VALUES ('app_secret', ?)")->execute([bin2hex(random_bytes(32))]);
$db->exec("INSERT OR REPLACE INTO settings (k, v) VALUES ('noindex', '1')");
$db->exec("UPDATE settings SET v = '' WHERE k = 'smtp_pass'");
$db->exec('DELETE FROM audit_log'); $db->exec('DELETE FROM password_resets');
// per-instance tables live in a side database on the server
foreach (CLOUD_EPHEMERAL as $t) $db->exec("DROP TABLE IF EXISTS $t");
$n = (int)$db->query("SELECT COUNT(*) FROM files")->fetchColumn();
if ($n) fwrite(STDERR, "warning: $n file rows reference local documents\n");
$db->exec('VACUUM'); $db = null;
file_put_contents($site . '/app/storage/seed.sealed', seal((string)file_get_contents($tmp), 'seed'));
unlink($tmp);
$cred = $site . '/CREDENTIALS-LIVE.txt';
file_put_contents($cred, "Ard Al-Hayat — live admin (Vercel)\n\nAdmin URL: https://ard-alhayat-preview.vercel.app/admin/\nUsername: admin\nPassword: $pw\n\nYou must choose a new password at first login.\nKeep this file private; delete it after the first login. It is never deployed or zipped.\n");
chmod($cred, 0600);
echo "seed.sealed written (" . filesize($site . '/app/storage/seed.sealed') . " bytes); credentials -> $cred\n";
