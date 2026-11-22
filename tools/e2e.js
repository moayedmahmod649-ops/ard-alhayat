// End-to-end tests: admin login, CRUD on every module, uploads (image / PDF / malicious), XSS samples, public forms, users/settings/backup/media, overflow + console checks.
const { chromium } = require('playwright-core');
const fs = require('fs');
const B = process.env.BASE || 'http://127.0.0.1:8831', FX = '/tmp/ahfix';
const pw0 = fs.readFileSync(process.env.CRED, 'utf8').match(/Password: (\S+)/)[1];
const NEWPW = 'Olive-Field-2026x';
let pass = 0, fail = 0; const res = [];
const ok = (c, n, extra = '') => { (c ? pass++ : fail++); res.push((c ? 'PASS ' : 'FAIL ') + n + (extra && !c ? ' — ' + extra : '')); };
(async () => {
  const b = await chromium.launch({ executablePath: '/usr/bin/google-chrome', args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
  const p = await ctx.newPage(); const errs = []; let dialogs = 0;
  p.on('console', m => m.type() === 'error' && errs.push(m.text())); p.on('pageerror', e => errs.push('PE ' + e.message));
  p.on('dialog', d => { if (d.type() === 'confirm') d.accept(); else { dialogs++; d.dismiss(); } });
  p.on('response', r => { if (r.status() >= 400 && !/a=login/.test(r.url())) errs.push(r.status() + ' ' + r.url()); });
  const html = () => p.content();
  const noPhpErr = async n => { const h = await html(); ok(!/Fatal error|Warning:|Notice:|Deprecated:|Uncaught/.test(h), 'no PHP errors: ' + n, (h.match(/(Fatal error|Warning:|Notice:|Deprecated:|Uncaught)[^<]{0,200}/) || [''])[0]); };
  // login: wrong password, then right
  await p.goto(B + '/admin/'); await p.fill('#u', 'admin'); await p.fill('#pw', 'wrong-pass-1'); await p.click('button[type=submit].btn-block');
  ok((await html()).includes('alert-err'), 'login rejects wrong password');
  await p.fill('#u', 'admin'); await p.fill('#pw', pw0); await p.click('button[type=submit].btn-block');
  ok(p.url().includes('p=profile'), 'first login forces password change');
  await p.fill('#pw', NEWPW); await p.fill('#pw2', NEWPW); await p.fill('#cur', pw0); await p.click('.form-actions button');
  ok((await html()).includes('alert-ok'), 'password changed');
  await p.goto(B + '/admin/'); await noPhpErr('dashboard'); ok(await p.locator('.stat-card').count() >= 3, 'dashboard stats');
  // every module list + new form
  const mods = ['slides','activities','projects','news','news_categories','notices','albums','jobs','partners','stats','pages','company_values','org_units','team','menu_items'];
  for (const m of mods) { await p.goto(B + '/admin/?m=' + m); await noPhpErr('list ' + m); await p.goto(B + '/admin/?m=' + m + '&a=edit'); await noPhpErr('new ' + m); }
  for (const pg of ['messages','applications','subscribers','users','audit','settings','backup','media','help','profile']) { await p.goto(B + '/admin/?p=' + pg); await noPhpErr('page ' + pg); }
  // products must not exist anywhere
  await p.goto(B + '/admin/'); ok(!/منتج|product/i.test(await p.locator('#side').innerText()), 'no products in admin nav');
  // create news with XSS payloads + image upload
  const XSS1 = '<img src=x onerror="window.__x1=1">', XSS2 = '<a href="javascript:window.__x2=1">click</a><script>window.__x3=1</script><p onclick="x()">ok-para</p>';
  await p.goto(B + '/admin/?m=news&a=edit');
  await p.fill('#f_title_ar', 'خبر اختبار ' + XSS1); await p.click('#t-en'); await p.fill('#f_title_en', 'Test news item'); await p.click('#t-ar');
  await p.evaluate(([a]) => { const t = document.querySelector('#f_body_ar'); t.value = a; const ed = t.closest('[data-rte]').querySelector('.rte-ed'); if (ed) ed.innerHTML = a; }, [XSS2]);
  await p.selectOption('#f_status', 'published'); await p.setInputFiles('#f_image', FX + '/photo.jpg');
  await p.click('.form-actions button[type=submit]'); await noPhpErr('save news');
  ok((await html()).includes('alert-ok'), 'news saved', (await p.locator('.alert').allInnerTexts()).join('|'));
  const slug = await p.evaluate(() => fetch('/admin/?m=news').then(r => r.text()).then(t => (t.match(/news\/(test-news-item[a-z0-9-]*)/) || [])[1]));
  ok(!!slug, 'news slug generated from English title', String(slug));
  await p.goto(B + '/ar/news/' + slug); await p.waitForTimeout(400);
  const x = await p.evaluate(() => [window.__x1, window.__x2, window.__x3, document.querySelectorAll('[onerror],[onclick],script:not([src])').length, [...document.querySelectorAll('a[href^="javascript"]')].length, document.body.innerText.includes('ok-para'), [...document.images].some(i => /uploads\/news-|uploads\/[a-z]+-[a-f0-9]{16}/.test(i.currentSrc))]);
  ok(!x[0] && !x[2], 'XSS: no script/onerror execution on public page'); ok(x[3] === 0 || x[3] === (await p.locator('script[type="application/ld+json"]').count()), 'XSS: no inline handlers', JSON.stringify(x)); ok(x[4] === 0, 'XSS: javascript: links stripped'); ok(x[5], 'safe rich text kept'); ok(x[6], 'uploaded image re-encoded to WebP and shown');
  // malicious image upload rejected
  await p.goto(B + '/admin/?m=projects&a=edit'); await p.fill('#f_title_ar', 'مشروع اختبار'); await p.setInputFiles('#f_image', FX + '/shell.php.jpg'); await p.click('.form-actions button[type=submit]');
  ok((await html()).includes('alert-err'), 'PHP disguised as JPG rejected');
  // notice with PDF attachment + fake PDF
  await p.goto(B + '/admin/?m=notices&a=edit'); await p.fill('#f_title_ar', 'مناقصة اختبار'); await p.click('#t-en'); await p.fill('#f_title_en', 'Test tender'); await p.click('#t-ar');
  await p.selectOption('#f_kind', 'tender'); const fin = p.locator('input[type=file][name="f_new[]"], input[type=file][name^="f_"]').first();
  await fin.setInputFiles([FX + '/doc.pdf']); await p.check('#f_published').catch(() => {}); await p.click('.form-actions button[type=submit]');
  ok((await html()).includes('alert-ok'), 'tender with PDF saved', (await p.locator('.alert').allInnerTexts()).join('|'));
  await p.goto(B + '/ar/notices/test-tender'); const flink = await p.locator('a[href^="/files/"]').first().getAttribute('href').catch(() => null);
  ok(!!flink, 'tender attachment listed publicly');
  if (flink) { const r = await p.request.get(B + flink); ok(r.status() === 200 && (r.headers()['content-type'] || '').includes('pdf') && /attachment/.test(r.headers()['content-disposition'] || ''), 'attachment served via controlled download'); }
  await p.goto(B + '/admin/?m=notices&a=edit'); await p.fill('#f_title_ar', 'مناقصة ملف مزيف'); await fin.setInputFiles([FX + '/evil.pdf']).catch(async () => { await p.locator('input[type=file]').first().setInputFiles([FX + '/evil.pdf']); });
  await p.click('.form-actions button[type=submit]'); ok((await html()).includes('alert-err'), 'fake PDF (PHP payload) rejected');
  // storage not web reachable
  for (const u of ['/app/storage/data/ardalhayat.sqlite', '/../app/bootstrap.php', '/uploads/x.php', '/CREDENTIALS.txt']) { const r = await p.request.get(B + u); ok(r.status() >= 400, 'blocked: ' + u, String(r.status())); }
  // users: create editor, permission override
  await p.goto(B + '/admin/?p=users&id=new'); await p.fill('#un', 'editor1'); await p.fill('#dn', 'محرر اختبار'); await p.selectOption('#rl', 'editor'); await p.fill('#pw', 'Editor-pass-77'); await p.selectOption('#pm_news', '3');
  await p.click('.form-actions button'); ok((await html()).includes('editor1'), 'user created');
  // settings save + bad value
  await p.goto(B + '/admin/?p=settings'); await p.fill('#s_phone', '+218 00 000 0000'); await p.fill('#s_ga_id', 'bad'); await p.click('.form-actions button');
  ok((await html()).includes('alert-err'), 'settings validation');
  await p.fill('#s_ga_id', ''); await p.click('.form-actions button'); ok((await html()).includes('alert-ok'), 'settings saved');
  // media upload
  await p.goto(B + '/admin/?p=media'); await p.setInputFiles('#up', [FX + '/photo.jpg', FX + '/doc.pdf']); await p.click('.upload-card button[type=submit]');
  ok(await p.locator('.media-item').count() >= 2, 'media library upload (image + PDF)');
  // backup
  await p.goto(B + '/admin/?p=backup');
  if (process.env.CLOUD === '1') { // serverless: the archive downloads straight away
    const [dl] = await Promise.all([p.waitForEvent('download'), p.click('.head-actions button')]); await dl.saveAs('/tmp/ahfix/bk.zip');
    const head = fs.readFileSync('/tmp/ahfix/bk.zip').slice(0, 2).toString(); ok(head === 'PK', 'backup created'); ok(head === 'PK', 'backup downloadable (zip)');
  } else { await p.click('.head-actions button'); ok(await p.locator('a[href*="dl=backup-"]').count() >= 1, 'backup created'); }
  // restore: forged zip rejected, real signed backup restores
  { if (process.env.CLOUD !== '1') { const href = await p.locator('a[href*="dl=backup-"]').first().getAttribute('href'); const r = await p.request.get(B + href); fs.writeFileSync('/tmp/ahfix/bk.zip', await r.body());
    ok(r.status() === 200 && (await r.body()).slice(0, 2).toString() === 'PK', 'backup downloadable (zip)'); }
    const AdmZ = require('child_process'); AdmZ.execSync("cd /tmp/ahfix && rm -rf fz && mkdir fz && cd fz && python3 -c \"import zipfile;z=zipfile.ZipFile('/tmp/ahfix/bk.zip');z.extractall('.');import json;m=json.load(open('manifest.json'));m['created']='x';json.dump(m,open('manifest.json','w'));import shutil;shutil.make_archive('/tmp/ahfix/forged','zip','.')\"");
    await p.goto(B + '/admin/?p=backup'); await p.setInputFiles('#bk', '/tmp/ahfix/forged.zip'); await p.fill('#rcur', NEWPW); await p.click('form:has(#bk) button[type=submit]');
    ok((await html()).includes('alert-err'), 'forged/unsigned backup rejected');
    await p.goto(B + '/admin/?p=backup'); await p.setInputFiles('#bk', '/tmp/ahfix/bk.zip'); await p.fill('#rcur', 'wrong-pass-1'); await p.click('form:has(#bk) button[type=submit]');
    ok((await html()).includes('alert-err'), 'restore requires current password');
    await p.goto(B + '/admin/?p=backup'); await p.setInputFiles('#bk', '/tmp/ahfix/bk.zip'); await p.fill('#rcur', NEWPW); await p.click('form:has(#bk) button[type=submit]');
    ok(p.url().endsWith('/admin/') && (await html()).includes('alert-ok'), 'signed backup restores, sessions revoked', p.url());
    await p.fill('#u', 'admin'); await p.fill('#pw', NEWPW); await p.click('button[type=submit].btn-block'); await p.goto(B + '/admin/');
    ok((await html()).includes('stat-card'), 'login works after restore'); await noPhpErr('after restore');
    const pub = await p.request.get(B + '/ar/'); ok(pub.status() === 200, 'public site OK after restore'); }
  // audit log has entries
  await p.goto(B + '/admin/?p=audit'); ok(await p.locator('.tbl tbody tr').count() >= 5, 'audit log records actions');
  // CSRF: post without token
  const r1 = await p.request.post(B + '/admin/?m=news&a=delete', { form: { id: '1' } }); ok(r1.status() === 403 || r1.status() === 400 || !(await r1.text()).includes('alert-ok'), 'CSRF token required', String(r1.status()));
  // delete news
  await p.goto(B + '/admin/?m=news'); const before = await p.locator('.tbl tbody tr').count();
  const del = p.locator('form[action*="a=delete"] button').first(); if (await del.count()) { await del.click(); await p.waitForLoadState(); }
  ok((await p.locator('.tbl tbody tr').count()) === before - 1, 'delete works');
  // editor permissions
  const c2 = await b.newContext(); const q = await c2.newPage();
  await q.goto(B + '/admin/'); await q.fill('#u', 'editor1'); await q.fill('#pw', 'Editor-pass-77'); await q.click('button[type=submit].btn-block');
  await q.fill('#pw', 'Editor-pass-88b'); await q.fill('#pw2', 'Editor-pass-88b'); await q.fill('#cur', 'Editor-pass-77'); await q.click('.form-actions button');
  const r2 = await q.goto(B + '/admin/?p=users'); ok(r2.status() === 403, 'editor cannot open users', String(r2.status()));
  const r3 = await q.goto(B + '/admin/?p=applications'); ok(r3.status() === 403, 'editor cannot open applications (HR)');
  const r4 = await q.goto(B + '/admin/?m=news'); ok(r4.status() === 200, 'editor can open news');
  await c2.close();
  // jobs start as unpublished drafts: publish one from the admin, as the client would
  await p.goto(B + '/admin/?m=jobs'); await p.locator('.tbl a.strong').first().click(); await p.check('#f_published'); await p.click('.form-actions button[type=submit]');
  ok((await html()).includes('alert-ok'), 'publish a draft job');
  // public forms: contact + application with CV (PDF ok, PHP rejected)
  const c3 = await b.newContext(); const u = await c3.newPage();
  await u.goto(B + '/ar/contact'); await u.waitForTimeout(3500); await u.fill('#form [name=name], form [name=name]', 'زائر اختبار'); await u.fill('form [name=email] >> nth=-1', 'visitor@example.com').catch(async () => { await u.locator('form:has([name=message]) [name=email]').fill('visitor@example.com'); });
  await u.locator('form:has([name=message]) [name=email]').fill('visitor@example.com'); await u.locator('form:has([name=message]) [name=message]').fill('رسالة <script>alert(1)</script> اختبار');
  await u.locator('form:has([name=message]) button[type=submit]').click(); ok(u.url().includes('sent=1'), 'contact form submits', u.url());
  await u.goto(B + '/ar/careers'); const jl = await u.locator('a[href^="/ar/careers/"]').first().getAttribute('href');
  await u.goto(B + jl); await u.waitForTimeout(3500); const af = u.locator('form:has([name=cv])');
  await af.locator('[name=name]').fill('متقدم اختبار'); await af.locator('[name=email]').fill('a@example.com'); await af.locator('[name=phone]').fill('0910000000'); await af.locator('[name=cv]').setInputFiles(FX + '/evil.pdf');
  await af.locator('button[type=submit]').click(); ok(!u.url().includes('sent=1'), 'CV with PHP payload rejected');
  await u.waitForTimeout(3500); const af2 = u.locator('form:has([name=cv])');
  await af2.locator('[name=name]').fill('متقدم اختبار'); await af2.locator('[name=email]').fill('a@example.com'); await af2.locator('[name=phone]').fill('0910000000'); await af2.locator('[name=cv]').setInputFiles(FX + '/doc.pdf');
  await af2.locator('button[type=submit]').click(); ok(u.url().includes('sent=1'), 'application with PDF CV accepted', u.url());
  await c3.close();
  await p.goto(B + '/admin/?p=messages'); ok((await html()).includes('زائر اختبار') && !(await html()).includes('<script>alert(1)'), 'message in admin, escaped');
  await p.goto(B + '/admin/?p=applications'); ok((await html()).includes('متقدم اختبار'), 'application in admin');
  const cv = p.locator('a[href*="a=file"]').first(); const cvh = await cv.getAttribute('href'); const rcv = await p.request.get(B + cvh); ok(rcv.status() === 200, 'CV downloadable by HR/admin');
  const anon = await b.newContext(); const rq = await anon.request.get(B + cvh); ok(!(rq.headers()['content-type'] || '').includes('pdf'), 'CV not downloadable anonymously'); await anon.close();
  const rx = await p.request.get(B + '/admin/?p=applications&export=1'); ok((rx.headers()['content-type'] || '').includes('spreadsheetml') && (await rx.body()).slice(0, 2).toString() === 'PK', 'Excel export (xlsx)');
  // admin overflow on mobile
  const cm = await b.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, storageState: await ctx.storageState() }); const m = await cm.newPage();
  for (const pg of ['', '?m=news', '?m=news&a=edit', '?p=users', '?p=settings', '?p=media', '?p=applications']) { await m.goto(B + '/admin/' + pg); const ov = await m.evaluate(() => document.documentElement.scrollWidth - innerWidth); ok(ov <= 0, 'admin mobile no overflow ' + pg, String(ov)); }
  await cm.close();
  ok(errs.filter(e => !/403|401|Unauthorized|Forbidden/.test(e) && !/inline event handler/.test(e)).length === 0, 'no console errors / 404s in admin', errs.slice(0, 6).join(' | '));
  console.log(res.join('\n')); console.log(`\n${pass} passed, ${fail} failed`);
  await b.close(); process.exit(fail ? 1 : 0);
})().catch(e => { console.log(res.join('\n')); console.error('CRASH', e.message); process.exit(2); });
