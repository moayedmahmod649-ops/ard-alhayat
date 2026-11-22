"""Static preview export of the PHP site (public pages AR+EN + read-only admin snapshots) for Vercel."""
import re, os, sys, shutil, json, time, urllib.request, urllib.parse, http.cookiejar, html
B = 'http://127.0.0.1:8832'
OUT = '/workspace/ard-alhayat/preview'
CRED = sys.argv[1]
shutil.rmtree(OUT, ignore_errors=True); os.makedirs(OUT)
cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
anon = urllib.request.build_opener()
def fetch(u, opener=anon, data=None):
    r = opener.open(urllib.request.Request(B + u, data=urllib.parse.urlencode(data).encode() if data else None))
    return r.read().decode('utf-8')

def qmap(u):
    """URL (path+query) -> static clean path."""
    p = urllib.parse.urlsplit(u); path = p.path.rstrip('/') or '/'
    if p.query:
        q = urllib.parse.parse_qsl(p.query)
        if any(k not in ('cat', 'status', 'type', 'tag', 'page', 'topic') for k, _ in q): return None
        if any(k == 'tag' for k, _ in q) and any(k == 'cat' for k, _ in q): return None
        suffix = '--' + '-'.join(f'{k}-' + urllib.parse.quote(v, safe='').replace('%', '').lower() for k, v in q)
        path = path + suffix
    return path

def outfile(path):
    if path in ('/ar', '/en'): return path + '/index.html'
    return path + '.html'

def static_path(path):  # file-system safe (tags are Arabic → keep as UTF-8 names, Vercel serves them)
    return path

pages = {}; queue = ['/ar/', '/en/']; seen = set()
PREVIEW_JS = '<script src="/assets/js/preview.js" defer></script>'
BANNER_PUB = ''
while queue:
    u = queue.pop(0)
    if u in seen: continue
    seen.add(u)
    try: h = fetch(u)
    except Exception as ex: print('ERR', u, ex); continue
    pages[u] = h
    for m in re.finditer(r'href="(/(?:ar|en)/[^"#]*)', h):
        l = html.unescape(m.group(1))
        if qmap(l) and l not in seen: queue.append(l)
print('public pages', len(pages))

def rewrite(h, admin_map=None):
    def rep(m):
        attr, l, frag = m.group(1), html.unescape(m.group(2)), m.group(3) or ''
        if re.match(r'^/(ar|en)(/|$)', l):
            qp = qmap(l)
            if qp is None:
                p = urllib.parse.urlsplit(l); qp = p.path.rstrip('/') or '/'
            return f'{attr}="{html.escape(qp + ("/" if qp in ("/ar", "/en") else ""), quote=True)}{frag}"'
        if admin_map is not None and l.startswith('/admin/'):
            t = admin_map.get(l)
            return f'{attr}="{t if t else "#preview-only"}{frag}"'
        return m.group(0)
    h = re.sub(r'(href)="(/[^"#]*)(#[^"]*)?"', rep, h)
    h = re.sub(r'name="_csrf" value="[^"]*"', 'name="_csrf" value=""', h)
    h = re.sub(r'name="_ft" value="[^"]*"', 'name="_ft" value=""', h)
    if '<meta name="robots"' not in h: h = h.replace('<head>', '<head><meta name="robots" content="noindex, nofollow">', 1)
    h = h.replace('</body>', PREVIEW_JS + '</body>', 1)
    return h

for u, h in pages.items():
    qp = qmap(u) or u.rstrip('/')
    f = OUT + outfile(qp)
    os.makedirs(os.path.dirname(f), exist_ok=True)
    open(f, 'w', encoding='utf-8').write(rewrite(h))

# 404 page
try:
    anon.open(B + '/ar/does-not-exist')
except urllib.error.HTTPError as ex:
    open(OUT + '/404.html', 'w', encoding='utf-8').write(rewrite(ex.read().decode('utf-8')))

# static assets
shutil.copytree('/tmp/ahprev/public/assets', OUT + '/assets')
shutil.copy('/tmp/ahprev/public/favicon.ico', OUT + '/favicon.ico')
up = '/tmp/ahprev/public/uploads'
if os.path.isdir(up):
    os.makedirs(OUT + '/uploads', exist_ok=True)
    for n in os.listdir(up):
        if n.endswith('.webp'): shutil.copy(os.path.join(up, n), OUT + '/uploads/' + n)
for u, n in [('/theme.css', 'theme.css'), ('/site.webmanifest', 'site.webmanifest')]:
    open(OUT + '/' + n, 'w', encoding='utf-8').write(fetch(u))
open(OUT + '/robots.txt', 'w').write('User-agent: *\nDisallow: /\n')
open(OUT + '/index.html', 'w', encoding='utf-8').write('<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex, nofollow"><meta http-equiv="refresh" content="0;url=/ar/"><title>أرض الحياة</title><a href="/ar/">أرض الحياة</a>')

# ---- admin snapshots (logged in on a throw-away install) ----
pw0 = re.search(r'Password: (\S+)', open(CRED).read()).group(1)
def csrf(h): return re.search(r'name="_csrf" value="([^"]+)"', h).group(1)
h = fetch('/admin/', op)
fetch('/admin/?a=login', op, {'_csrf': csrf(h), 'username': 'admin', 'password': pw0})
h = fetch('/admin/?p=profile', op)
NEWPW = 'Preview-' + os.urandom(6).hex() + '9'
fetch('/admin/?p=profile', op, {'_csrf': csrf(h), 'display_name': 'مدير النظام', 'email': '', 'admin_lang': 'ar', 'pw': NEWPW, 'pw2': NEWPW, 'cur': pw0})
first = lambda mod: re.search(r'href="/admin/\?m=' + mod + r'&amp;a=edit&amp;id=(\d+)"', fetch('/admin/?m=' + mod, op)).group(1)
shots = {
  '/admin/': 'dashboard', '/admin/?m=news': 'news', '/admin/?m=news&a=edit&id=' + first('news'): 'news-edit',
  '/admin/?m=projects': 'projects', '/admin/?m=projects&a=edit&id=' + first('projects'): 'project-edit',
  '/admin/?m=activities': 'activities', '/admin/?m=notices': 'notices', '/admin/?m=notices&a=edit&id=' + first('notices'): 'notice-edit',
  '/admin/?m=slides': 'slides', '/admin/?m=albums': 'gallery', '/admin/?m=partners': 'partners', '/admin/?m=jobs': 'jobs', '/admin/?m=team': 'team',
  '/admin/?p=messages': 'messages', '/admin/?p=applications': 'applications', '/admin/?p=subscribers': 'subscribers',
  '/admin/?p=users': 'users', '/admin/?p=users&id=1': 'user-edit', '/admin/?p=audit': 'audit', '/admin/?p=settings': 'settings',
  '/admin/?p=media': 'media', '/admin/?p=backup': 'backup', '/admin/?p=help': 'help',
}
admin_map = {}
for u, n in shots.items():
    admin_map[u] = f'/admin/{n}'; admin_map[u.replace('&', '&amp;')] = f'/admin/{n}'
admin_map['/admin/?p=profile'] = '/admin/users'
BANNER = '<div class="preview-banner" role="note">معاينة فقط — Preview only · لوحة التحكم تعمل على الخادم الفعلي (PHP). الأزرار والنماذج معطّلة هنا.</div>'
os.makedirs(OUT + '/admin', exist_ok=True)
for u, n in shots.items():
    h = fetch(u, op)
    h = rewrite(h, {k.replace('&amp;', '&'): v for k, v in admin_map.items()})
    h = h.replace('<main id="main" class="main" tabindex="-1">', BANNER + '<main id="main" class="main" tabindex="-1">', 1)
    h = re.sub(r'href="/ar/"', 'href="/ar/"', h)
    open(f'{OUT}/admin/{n}.html', 'w', encoding='utf-8').write(h)
# login screen (anonymous)
lh = rewrite(fetch('/admin/'), admin_map).replace('<main id="main" class="main" tabindex="-1">', BANNER + '<main id="main" class="main" tabindex="-1">', 1)
open(f'{OUT}/admin/login.html', 'w', encoding='utf-8').write(lh)
open(f'{OUT}/admin/index.html', 'w', encoding='utf-8').write('<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex"><meta http-equiv="refresh" content="0;url=/admin/dashboard"><a href="/admin/dashboard">Admin preview</a>')
json.dump({"cleanUrls": True, "trailingSlash": False,
  "headers": [{"source": "/(.*)", "headers": [{"key": "X-Robots-Tag", "value": "noindex, nofollow"}, {"key": "X-Content-Type-Options", "value": "nosniff"}, {"key": "Referrer-Policy", "value": "same-origin"}]}]},
  open(OUT + '/vercel.json', 'w'), indent=1)
print('admin shots', len(shots) + 1)
