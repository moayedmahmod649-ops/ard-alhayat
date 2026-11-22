<?php
declare(strict_types=1);
function db(): PDO {
    if (isset($GLOBALS['__PDO'])) return $GLOBALS['__PDO'];
    global $CONFIG;
    if (!is_dir(dirname($CONFIG['db_path']))) mkdir(dirname($CONFIG['db_path']), 0750, true);
    if (cloud()) cloud_db_prepare($CONFIG['db_path']);
    $pdo = new PDO('sqlite:' . $CONFIG['db_path'], null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    // engine-specific setup lives here and in migrate() only
    // cloud mode keeps a single self-contained file (no WAL) so it can be uploaded as-is after each write
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000; PRAGMA journal_mode = ' . (cloud() ? 'DELETE' : 'WAL') . ';');
    if (cloud()) cloud_attach_local($pdo);
    migrate($pdo);
    return $GLOBALS['__PDO'] = $pdo;
}
function db_close(): void { $GLOBALS['__PDO'] = null; unset($GLOBALS['__PDO']); if (function_exists('setting')) setting('__reset'); }
function q(string $sql, array $p = []): PDOStatement { if (cloud()) cloud_track($sql, $p); $st = db()->prepare($sql); $st->execute($p); return $st; }
function q1(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function qv(string $sql, array $p = []): mixed { return q($sql, $p)->fetchColumn(); }
function qall(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }

/** Bilingual content columns shared by most tables. */
function migrate(PDO $pdo): void {
    $v = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    if ($v >= 1) return;
    $t = "created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP";
    $seo = "meta_title_ar TEXT NOT NULL DEFAULT '', meta_title_en TEXT NOT NULL DEFAULT '', meta_desc_ar TEXT NOT NULL DEFAULT '', meta_desc_en TEXT NOT NULL DEFAULT ''";
    $pdo->exec(<<<SQL
CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT NOT NULL UNIQUE, email TEXT NOT NULL DEFAULT '', display_name TEXT NOT NULL DEFAULT '',
  role TEXT NOT NULL DEFAULT 'editor', perms TEXT NOT NULL DEFAULT '', active INTEGER NOT NULL DEFAULT 1, pass_hash TEXT NOT NULL,
  must_change INTEGER NOT NULL DEFAULT 1, session_ver INTEGER NOT NULL DEFAULT 1, admin_lang TEXT NOT NULL DEFAULT 'ar', last_login TEXT, $t);
CREATE TABLE login_attempts (id INTEGER PRIMARY KEY, ip TEXT NOT NULL, username TEXT NOT NULL, at INTEGER NOT NULL);
CREATE INDEX la_ip ON login_attempts(ip, at);
CREATE TABLE form_hits (id INTEGER PRIMARY KEY, ip TEXT NOT NULL, form TEXT NOT NULL, at INTEGER NOT NULL);
CREATE INDEX fh_ip ON form_hits(ip, form, at);
CREATE TABLE password_resets (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, token_hash TEXT NOT NULL,
  expires_at INTEGER NOT NULL, used INTEGER NOT NULL DEFAULT 0, issued_by INTEGER, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE audit_log (id INTEGER PRIMARY KEY, user_id INTEGER, username TEXT NOT NULL DEFAULT '', action TEXT NOT NULL, entity TEXT NOT NULL DEFAULT '',
  entity_id INTEGER, label TEXT NOT NULL DEFAULT '', ip TEXT NOT NULL DEFAULT '', at TEXT NOT NULL);
CREATE INDEX audit_at ON audit_log(at);
CREATE TABLE settings (k TEXT PRIMARY KEY, v TEXT NOT NULL DEFAULT '');
CREATE TABLE visits (day TEXT NOT NULL, path TEXT NOT NULL, hits INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (day, path));

CREATE TABLE pages (id INTEGER PRIMARY KEY, slug TEXT NOT NULL UNIQUE, title_ar TEXT NOT NULL, title_en TEXT NOT NULL DEFAULT '',
  body_ar TEXT NOT NULL DEFAULT '', body_en TEXT NOT NULL DEFAULT '', in_footer INTEGER NOT NULL DEFAULT 0, published INTEGER NOT NULL DEFAULT 1, sort INTEGER NOT NULL DEFAULT 0, $seo, $t);
CREATE TABLE slides (id INTEGER PRIMARY KEY, title_ar TEXT NOT NULL, title_en TEXT NOT NULL DEFAULT '', sub_ar TEXT NOT NULL DEFAULT '', sub_en TEXT NOT NULL DEFAULT '',
  cta_ar TEXT NOT NULL DEFAULT '', cta_en TEXT NOT NULL DEFAULT '', link TEXT NOT NULL DEFAULT '', image TEXT NOT NULL DEFAULT '', sort INTEGER NOT NULL DEFAULT 0, published INTEGER NOT NULL DEFAULT 1, $t);
CREATE TABLE stats (id INTEGER PRIMARY KEY, label_ar TEXT NOT NULL, label_en TEXT NOT NULL DEFAULT '', value TEXT NOT NULL DEFAULT '0', suffix_ar TEXT NOT NULL DEFAULT '', suffix_en TEXT NOT NULL DEFAULT '',
  icon TEXT NOT NULL DEFAULT 'leaf', is_sample INTEGER NOT NULL DEFAULT 1, sort INTEGER NOT NULL DEFAULT 0, published INTEGER NOT NULL DEFAULT 1, $t);
CREATE TABLE company_values (id INTEGER PRIMARY KEY, title_ar TEXT NOT NULL, title_en TEXT NOT NULL DEFAULT '', body_ar TEXT NOT NULL DEFAULT '', body_en TEXT NOT NULL DEFAULT '',
  icon TEXT NOT NULL DEFAULT 'leaf', sort INTEGER NOT NULL DEFAULT 0, published INTEGER NOT NULL DEFAULT 1, $t);
CREATE TABLE org_units (id INTEGER PRIMARY KEY, name_ar TEXT NOT NULL, name_en TEXT NOT NULL DEFAULT '', parent_id INTEGER REFERENCES org_units(id) ON DELETE SET NULL,
  sort INTEGER NOT NULL DEFAULT 0, published INTEGER NOT NULL DEFAULT 1, $t);
CREATE TABLE team (id INTEGER PRIMARY KEY, name_ar TEXT NOT NULL, name_en TEXT NOT NULL DEFAULT '', role_ar TEXT NOT NULL DEFAULT '', role_en TEXT NOT NULL DEFAULT '',
  bio_ar TEXT NOT NULL DEFAULT '', bio_en TEXT NOT NULL DEFAULT '', image TEXT NOT NULL DEFAULT '', is_sample INTEGER NOT NULL DEFAULT 0, sort INTEGER NOT NULL DEFAULT 0, published INTEGER NOT NULL DEFAULT 1, $t);
CREATE TABLE activities (id INTEGER PRIMARY KEY, slug TEXT NOT NULL UNIQUE, sector TEXT NOT NULL DEFAULT 'agri', title_ar TEXT NOT NULL, title_en TEXT NOT NULL DEFAULT '',
  summary_ar TEXT NOT NULL DEFAULT '', summary_en TEXT NOT NULL DEFAULT '', body_ar TEXT NOT NULL DEFAULT '', body_en TEXT NOT NULL DEFAULT '',
  image TEXT NOT NULL DEFAULT '', gallery TEXT NOT NULL DEFAULT '', icon TEXT NOT NULL DEFAULT 'leaf', featured INTEGER NOT NULL DEFAULT 0, sort INTEGER NOT NULL DEFAULT 0, published INTEGER NOT NULL DEFAULT 1, $seo, $t);
CREATE TABLE projects (id INTEGER PRIMARY KEY, slug TEXT NOT NULL UNIQUE, status TEXT NOT NULL DEFAULT 'ongoing', sector TEXT NOT NULL DEFAULT 'agri',
  title_ar TEXT NOT NULL, title_en TEXT NOT NULL DEFAULT '', summary_ar TEXT NOT NULL DEFAULT '', summary_en TEXT NOT NULL DEFAULT '',
  body_ar TEXT NOT NULL DEFAULT '', body_en TEXT NOT NULL DEFAULT '', location_ar TEXT NOT NULL DEFAULT '', location_en TEXT NOT NULL DEFAULT '',
  map_url TEXT NOT NULL DEFAULT '', year TEXT NOT NULL DEFAULT '', image TEXT NOT NULL DEFAULT '', gallery TEXT NOT NULL DEFAULT '', is_sample INTEGER NOT NULL DEFAULT 0,
  featured INTEGER NOT NULL DEFAULT 0, sort INTEGER NOT NULL DEFAULT 0, published INTEGER NOT NULL DEFAULT 1, $seo, $t);
CREATE TABLE news_categories (id INTEGER PRIMARY KEY, slug TEXT NOT NULL UNIQUE, name_ar TEXT NOT NULL, name_en TEXT NOT NULL DEFAULT '', sort INTEGER NOT NULL DEFAULT 0, $t);
CREATE TABLE news (id INTEGER PRIMARY KEY, slug TEXT NOT NULL UNIQUE, category_id INTEGER REFERENCES news_categories(id) ON DELETE SET NULL,
  title_ar TEXT NOT NULL, title_en TEXT NOT NULL DEFAULT '', excerpt_ar TEXT NOT NULL DEFAULT '', excerpt_en TEXT NOT NULL DEFAULT '',
  body_ar TEXT NOT NULL DEFAULT '', body_en TEXT NOT NULL DEFAULT '', tags TEXT NOT NULL DEFAULT '', image TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL DEFAULT 'draft', publish_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, is_sample INTEGER NOT NULL DEFAULT 0, featured INTEGER NOT NULL DEFAULT 0, $seo, $t);
CREATE INDEX news_pub ON news(status, publish_at);
CREATE TABLE notices (id INTEGER PRIMARY KEY, slug TEXT NOT NULL UNIQUE, kind TEXT NOT NULL DEFAULT 'announcement', ref_no TEXT NOT NULL DEFAULT '',
  title_ar TEXT NOT NULL, title_en TEXT NOT NULL DEFAULT '', body_ar TEXT NOT NULL DEFAULT '', body_en TEXT NOT NULL DEFAULT '',
  start_date TEXT NOT NULL DEFAULT '', end_date TEXT NOT NULL DEFAULT '', attachments TEXT NOT NULL DEFAULT '', is_sample INTEGER NOT NULL DEFAULT 0, published INTEGER NOT NULL DEFAULT 1, $seo, $t);
CREATE TABLE albums (id INTEGER PRIMARY KEY, slug TEXT NOT NULL UNIQUE, title_ar TEXT NOT NULL, title_en TEXT NOT NULL DEFAULT '', desc_ar TEXT NOT NULL DEFAULT '', desc_en TEXT NOT NULL DEFAULT '',
  image TEXT NOT NULL DEFAULT '', gallery TEXT NOT NULL DEFAULT '', videos TEXT NOT NULL DEFAULT '', sort INTEGER NOT NULL DEFAULT 0, published INTEGER NOT NULL DEFAULT 1, $t);
CREATE TABLE jobs (id INTEGER PRIMARY KEY, slug TEXT NOT NULL UNIQUE, title_ar TEXT NOT NULL, title_en TEXT NOT NULL DEFAULT '', dept_ar TEXT NOT NULL DEFAULT '', dept_en TEXT NOT NULL DEFAULT '',
  location_ar TEXT NOT NULL DEFAULT '', location_en TEXT NOT NULL DEFAULT '', jtype TEXT NOT NULL DEFAULT 'full', body_ar TEXT NOT NULL DEFAULT '', body_en TEXT NOT NULL DEFAULT '',
  deadline TEXT NOT NULL DEFAULT '', is_sample INTEGER NOT NULL DEFAULT 0, published INTEGER NOT NULL DEFAULT 1, $t);
CREATE TABLE applications (id INTEGER PRIMARY KEY, job_id INTEGER REFERENCES jobs(id) ON DELETE SET NULL, job_title TEXT NOT NULL DEFAULT '', name TEXT NOT NULL, email TEXT NOT NULL, phone TEXT NOT NULL DEFAULT '',
  city TEXT NOT NULL DEFAULT '', message TEXT NOT NULL DEFAULT '', cv_file INTEGER REFERENCES files(id) ON DELETE SET NULL, status TEXT NOT NULL DEFAULT 'new', lang TEXT NOT NULL DEFAULT 'ar', $t);
CREATE TABLE partners (id INTEGER PRIMARY KEY, name_ar TEXT NOT NULL, name_en TEXT NOT NULL DEFAULT '', url TEXT NOT NULL DEFAULT '', image TEXT NOT NULL DEFAULT '',
  is_sample INTEGER NOT NULL DEFAULT 0, sort INTEGER NOT NULL DEFAULT 0, published INTEGER NOT NULL DEFAULT 1, $t);
CREATE TABLE messages (id INTEGER PRIMARY KEY, topic TEXT NOT NULL DEFAULT 'general', name TEXT NOT NULL, email TEXT NOT NULL, phone TEXT NOT NULL DEFAULT '', organisation TEXT NOT NULL DEFAULT '',
  subject TEXT NOT NULL DEFAULT '', message TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'new', note TEXT NOT NULL DEFAULT '', lang TEXT NOT NULL DEFAULT 'ar', $t);
CREATE TABLE subscribers (id INTEGER PRIMARY KEY, email TEXT NOT NULL UNIQUE, token TEXT NOT NULL, confirmed INTEGER NOT NULL DEFAULT 1, active INTEGER NOT NULL DEFAULT 1, lang TEXT NOT NULL DEFAULT 'ar', $t);
CREATE TABLE menu_items (id INTEGER PRIMARY KEY, location TEXT NOT NULL DEFAULT 'header', label_ar TEXT NOT NULL, label_en TEXT NOT NULL DEFAULT '', url TEXT NOT NULL, sort INTEGER NOT NULL DEFAULT 0, published INTEGER NOT NULL DEFAULT 1, $t);
CREATE TABLE media (id INTEGER PRIMARY KEY, kind TEXT NOT NULL DEFAULT 'image', path TEXT NOT NULL DEFAULT '', file_id INTEGER, title TEXT NOT NULL DEFAULT '', alt_ar TEXT NOT NULL DEFAULT '', alt_en TEXT NOT NULL DEFAULT '', bytes INTEGER NOT NULL DEFAULT 0, $t);
CREATE TABLE files (id INTEGER PRIMARY KEY, token TEXT NOT NULL UNIQUE, stored TEXT NOT NULL, orig_name TEXT NOT NULL, mime TEXT NOT NULL, bytes INTEGER NOT NULL,
  scope TEXT NOT NULL DEFAULT 'private', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
PRAGMA user_version = 1;
SQL);
}
