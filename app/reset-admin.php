<?php
declare(strict_types=1);
/**
 * One-time admin reset (CLI): php app/reset-admin.php
 * Sets username admin to the password recorded in SETUP-ADMIN.txt (repo root, outside the web root)
 * and forces a change at the next login. Existing sessions are revoked.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/bootstrap.php';

// An empty database file is migrated to the current schema by db(). If users is still missing, seed first.
db();
if (!q1("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'users'")) {
    fwrite(STDERR, "No tables yet. Run app/seed.php first.\n");
    exit(1);
}

$pw = 'YUIisA55GLfRkQWn';
$hash = password_hash($pw, PASSWORD_DEFAULT);
$ins = function (string $table, array $row) { $k = array_keys($row); q("INSERT INTO $table (" . implode(',', $k) . ') VALUES (' . implode(',', array_fill(0, count($k), '?')) . ')', array_values($row)); return (int)db()->lastInsertId(); };

$user = q1('SELECT * FROM users WHERE username = ?', ['admin']);
if ($user) {
    $sets = ['pass_hash = ?', 'must_change = 1', 'role = ?'];
    $args = [$hash, 'admin'];
    $cols = array_column(qall('PRAGMA table_info(users)'), 'name');
    if (in_array('session_ver', $cols, true)) $sets[] = 'session_ver = session_ver + 1';
    $args[] = $user['id'];
    q('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?', $args);
    $uid = (int)$user['id'];
} else {
    $uid = $ins('users', ['username' => 'admin', 'email' => '', 'display_name' => 'مدير النظام', 'role' => 'admin', 'pass_hash' => $hash, 'must_change' => 1]);
}
if (q1("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'sessions'")) {
    $scols = array_column(qall('PRAGMA table_info(sessions)'), 'name');
    if (in_array('user_id', $scols, true)) q('DELETE FROM sessions WHERE user_id = ?', [$uid]);
}

echo "Done. Log in at /admin/ as admin with the password in SETUP-ADMIN.txt; you will be asked to set a new one.\n";
