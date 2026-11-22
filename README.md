# أرض الحياة — Ard Al-Hayat website + admin panel (draft for review)

Bilingual (Arabic RTL first + English LTR) company website with a full admin panel, built from Tawasot's technical proposal for
**شركة أرض الحياة للتنمية الزراعية والإنتاج الحيواني المساهمة** — **without the المنتجات (Products) section** (removed everywhere: pages, home highlights, quote requests, admin, menus, sitemap).

- Stack: **PHP 8.1+ (8.4 tested) + SQLite (PDO)**, no framework, no Composer, no CDNs, self-hosted Expo Arabic (Book + Bold) font — 29LT, needs a webfont licence before launch. Server-rendered HTML.
- ⚠️ The proposal text says "Laravel or equivalent" and "MySQL or PostgreSQL". This build uses the agency's proven PHP + SQLite stack with portable SQL.
  **Get the client's written approval** for this, and for the removal of المنتجات, before launch.
- Site is hidden from search engines until launch (`noindex` setting, admin → الإعدادات العامة).

## Install / run locally
```bash
php app/seed.php            # first install: creates DB, admin user + random password → ../CREDENTIALS.txt (outside web root)
php -S 127.0.0.1:8830 -t public app/dev-router.php
# site  http://127.0.0.1:8830/ar/   ·   English  /en/   ·   admin  /admin/
```
Change the password at first login (forced), then **delete CREDENTIALS.txt**. `php app/seed.php --reset` wipes and re-seeds.
Required extensions: pdo_sqlite, gd (WebP), fileinfo, mbstring, dom, zip (backups), openssl (SMTP TLS).

## Deploy (cPanel / shared hosting)
Full Arabic step-by-step guide (Libyan Spider cPanel): `deploy/LIBYAN-SPIDER-GUIDE.md` / `.pdf` in the agency workspace.
1. Easiest: upload `ard-alhayat-cpanel-public_html.zip` to your home folder (`/home/USER/`) and extract it there → `app/` lands next to `public_html/`.
   Or by hand: the **contents of `public/`** go into `public_html/` (including the hidden `.htaccess`), and **`app/` one level above** it.
   If the domain's document root can be pointed at a folder, use this zip as-is and point the docroot at `ard-alhayat/public`.
2. PHP 8.2+ (8.4 recommended) with pdo_sqlite, gd (WebP), fileinfo, mbstring, dom/xml, zip, openssl, exif (optional).
   upload_max_filesize ≥ 16M, post_max_size ≥ 32M, memory_limit ≥ 256M.
3. Make `app/storage/*` and `public_html/uploads/` writable by PHP (755 folders / 644 files when PHP runs as your user; never 777).
4. Fresh install: `php app/seed.php` (Terminal/SSH, or a one-off cron job) → admin password in `CREDENTIALS.txt` beside `app/` (forced change at first login; then delete the file).
   **Moving from another installation** (e.g. the Vercel preview): download a backup there, then
   `php app/import-backup.php backup-….zip --images-from=https://old-site --force` (the admin-panel restore only accepts backups made by the same installation).
5. Enable SSL, then uncomment the HTTPS redirect in `.htaccess`; copy `config.example.php` to `app/config.php` and set `'base_url' => 'https://…'` and `'force_https_cookie' => true`.
6. Admin → الإعدادات العامة: company details, contact, social links, SMTP (send a test email), GA4 id; turn off «إخفاء الموقع» at launch.
7. Never set `BLOB_READ_WRITE_TOKEN` / `AH_DATA_KEY` on normal hosting (they switch on the Vercel cloud mode).
8. Verify `https://site/app/storage/data/ardalhayat.sqlite`, `https://site/CREDENTIALS.txt` and `https://site/uploads/x.php` return 403/404.

**Nginx:** `root …/public; index index.php; location / { try_files $uri /index.php?$query_string; }`
`location ^~ /uploads/ { location ~ \.php$ { deny all; } }` and pass `\.php$` to PHP-FPM only for `/index.php`.

## Folder layout
```
public/     web root: index.php (front controller), assets/ (css, js, fonts, draft images), uploads/ (generated WebP only)
app/        OUTSIDE the web root: bootstrap, router, lib/ (db, auth, perms, sanitize, uploads, files, mailer, xlsx, seo, cache…),
            admin/ (controller, modules, crud, sections, system), views/ (public templates), storage/ (db, CVs + attachments, cache, backups, mail log)
```

## Public pages (AR `/ar/…` and EN `/en/…`, hreflang + canonical)
Home (slider, about, highlighted activities, latest news, stats, شركاء النجاح, projects) · من نحن (intro, vision/mission/values, org structure, management team) ·
الأنشطة والخدمات (agricultural + livestock detail pages) · المشاريع (ongoing/completed, gallery, location) · الأخبار والمقالات (categories, tags, search, share) ·
الإعلانات والمناقصات (types, start/end dates, auto-hide, attachments, "closing soon") · معرض الصور والفيديو (albums + YouTube/Vimeo) ·
الوظائف (openings + application with CV upload, PDF/DOC/DOCX ≤5 MB) · اتصل بنا (form, details, map, social) · static pages (privacy…) · newsletter · 404 · XML sitemap · robots.txt.

## Admin panel (`/admin/`, Arabic/English UI)
Content: slider, static pages, about (values, org units, team), stats, activities, projects, news (+categories, tags, scheduling, draft/published/archived),
notices & tenders, gallery, partners, media library, menus & footer · Interaction: contact messages (new/processed, email alert), job applications (filter, CV download, Excel export),
newsletter (Excel export) · System: users (4 roles: مدير النظام / مدير المحتوى / محرر / مسؤول الموارد البشرية + per-section overrides), audit log, settings
(company, logo, colours, SEO, SMTP, tracking), backup & restore, dashboard with visits/new messages/latest content, user guide (Arabic).

Security: CSRF on every POST, password_hash, login throttling, session revocation on password change, password reset by email (hashed single-use 30-min token) or
admin-issued link, allowlist HTML sanitizer, images re-encoded to WebP under random names, documents MIME-sniffed and stored outside the web root and served
through a controlled download, CSP + security headers, prepared statements everywhere.

## Before launch — content to fill in from the admin panel
No sample labels are shown anywhere. Starting content is neutral and editable; items that need real facts are saved as unpublished drafts
(stats, team, projects, tenders/notices, jobs, most news) and stay hidden until you fill them in and publish. Empty contact rows (address, phone,
email, hours, map, social links) are hidden automatically. Search engines are blocked (Settings → noindex) until launch is approved.

## Vercel (live preview) — cloud mode
Runs on Vercel Hobby with the community `vercel-php@0.8.0` runtime (`api/index.php`, `vercel.json`, output dir `public`).
Cloud mode turns on when `BLOB_READ_WRITE_TOKEN` and `AH_DATA_KEY` are set: the SQLite DB is kept encrypted (AES-256-GCM) in Vercel Blob
(`state/site-db.bin`), each function instance works on a copy in /tmp and saves back with an ETag check (conflicts are merged by replaying the request).
Images go to Blob `uploads/` and are served same-origin via `/uploads/…`; documents are stored encrypted under `docs/` and served only through PHP.
First boot loads `app/storage/seed.sealed` (encrypted, not in this zip). Limits: ~4 MB per upload request (large photos are shrunk in the browser),
Blob Hobby quota 1 GB storage / 10k simple + 2k advanced ops / 10 GB transfer per month; visitors may see edits up to ~10 min late; visit counter and
login throttling are per instance. For the real launch, standard PHP hosting (this zip as-is) or Vercel Pro is recommended.
