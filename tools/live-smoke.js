// Live smoke test on the deployed site. Never prints passwords.
const { chromium } = require('playwright-core'); const fs = require('fs'); const crypto = require('crypto');
const B = process.env.BASE, CRED = process.env.CRED;
const credTxt = fs.readFileSync(CRED, 'utf8'); const pw0 = credTxt.match(/Password: (\S+)/)[1];
const gen = () => 'Ah-' + crypto.randomBytes(15).toString('base64url') + '-7q';
const P1 = gen(), P2 = gen();
let pass = 0, fail = 0; const ok = (c, n, x = '') => { c ? pass++ : fail++; console.log((c ? 'PASS ' : 'FAIL ') + n + (!c && x ? ' — ' + x : '')); };
const MARK = ' (QA-' + Date.now().toString(36) + ')';
(async () => {
  const b = await chromium.launch({ executablePath: '/usr/bin/google-chrome', args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: 1366, height: 900 } }); const p = await ctx.newPage(); const errs = [];
  p.on('pageerror', e => errs.push(e.message)); p.on('console', m => m.type() === 'error' && errs.push(m.text()));
  p.on('dialog', d => d.accept());
  const html = () => p.content();
  try {
  await p.goto(B + '/admin/'); await p.fill('#u', 'admin'); await p.fill('#pw', 'wrong-pass-1'); await p.click('button[type=submit].btn-block');
  ok((await html()).includes('alert-err'), 'wrong password rejected');
  await p.fill('#u', 'admin'); await p.fill('#pw', pw0); await p.click('button[type=submit].btn-block'); await p.waitForLoadState();
  ok(p.url().includes('p=profile') && (await html()).includes('alert-warn'), 'first login forces password change', p.url());
  await p.goto(B + '/admin/?m=news'); ok(p.url().includes('p=profile'), 'other admin pages blocked until password changed');
  await p.fill('#pw', P1); await p.fill('#pw2', P1); await p.fill('#cur', pw0); await p.click('.form-actions button[type=submit]'); await p.waitForLoadState();
  await p.goto(B + '/admin/'); ok(!p.url().includes('p=profile') && (await html()).includes('dash'), 'password changed, dashboard opens', p.url());
  const r = await p.request.post(B + '/admin/?m=news&a=delete', { form: { id: '1' } }); ok(!(await r.text()).includes('alert-ok'), 'POST without CSRF token refused', String(r.status()));
  // find the published news item
  await p.goto(B + '/admin/?m=news');
  const editHref = await p.evaluate(() => { const row = [...document.querySelectorAll('.tbl tbody tr')].find(tr => tr.innerHTML.includes('news/new-website')) || document.querySelector('.tbl tbody tr'); return row && row.querySelector('a[href*="a=edit"]')?.getAttribute('href'); });
  ok(!!editHref, 'news list shows the published item', String(editHref));
  await p.goto(new URL(editHref, B + '/admin/').href);
  const orig = await p.inputValue('#f_title_ar');
  await p.fill('#f_title_ar', orig + MARK); await p.setInputFiles('#f_image', '/tmp/ahfix/photo.jpg');
  await p.click('.form-actions button[type=submit]'); await p.waitForLoadState();
  ok((await html()).includes('alert-ok'), 'edit + image upload saved', (await p.locator('.alert').allInnerTexts()).join('|'));
  await p.goto(B + '/ar/news/new-website'); let h = await html();
  ok(h.includes(MARK.trim()), 'public page shows the edit immediately (same browser)');
  const img = (h.match(/\/uploads\/news-[a-f0-9]{16}(?:-[mt])?\.webp/) || [])[0];
  ok(!!img, 'public page shows the uploaded image', String(img));
  if (img) { const ir = await p.request.get(B + img); ok(ir.status() === 200 && /image\/webp/.test(ir.headers()['content-type'] || ''), 'uploaded image served from /uploads (Blob)', ir.status() + ' ' + ir.headers()['content-type']); }
  const ctx2 = await b.newContext(); const p2 = await ctx2.newPage(); await p2.goto(B + '/ar/news/new-website');
  console.log('INFO fresh visitor sees edit right away: ' + (await p2.content()).includes(MARK.trim()));
  // revert
  await p.goto(new URL(editHref, B + '/admin/').href); await p.fill('#f_title_ar', orig);
  await p.check('#f_image_rm'); await p.click('.form-actions button[type=submit]'); await p.waitForLoadState();
  ok((await html()).includes('alert-ok'), 'test edit reverted (title restored, image removed)');
  await p.goto(B + '/ar/news/new-website'); h = await html();
  ok(!h.includes(MARK.trim()) && !/\/uploads\/news-/.test(h), 'public page back to original');
  if (img) { await new Promise(r => setTimeout(r, 1500)); const g = await fetch('https://ix0isq9brczxkolz.public.blob.vercel-storage.com' + img + '?x=' + Date.now()); ok(g.status !== 200, 'removed image deleted from Blob', String(g.status)); }
  // restore the "change password at first login" state with a fresh password
  await p.goto(B + '/admin/?p=users'); const uHref = await p.evaluate(() => [...document.querySelectorAll('a[href*="p=users&id="]')].map(a => a.getAttribute('href')).find(h => !/id=new/.test(h)));
  await p.goto(new URL(uHref, B + '/admin/').href); await p.fill('#pw', P2); await p.click('.form-actions button[type=submit]'); await p.waitForLoadState();
  await p.goto(B + '/admin/'); ok(/a=login|<input[^>]+id="u"/.test(await html()) || p.url().includes('login'), 'session ended after password reset');
  fs.writeFileSync(CRED, credTxt.replace(/Password: \S+/, 'Password: ' + P2), { mode: 0o600 });
  const p3 = await (await b.newContext()).newPage(); await p3.goto(B + '/admin/'); await p3.fill('#u', 'admin'); await p3.fill('#pw', P2); await p3.click('button[type=submit].btn-block'); await p3.waitForLoadState();
  ok(p3.url().includes('p=profile'), 'new password works and forces a change at first login');
  await p3.goto(B + '/admin/?p=profile'); const lo = p3.locator('form[action*="a=logout"] button'); if (await lo.count()) await lo.first().click();
  ok(errs.length === 0, 'no console errors', errs.slice(0, 3).join(' | '));
  } catch (e) { fail++; console.log('FAIL exception ' + e.message.split('\n')[0]); }
  console.log(`\n${pass} passed, ${fail} failed`); await b.close();
})();
