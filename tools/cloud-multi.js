// Two "instances" (A, B) sharing one Blob store: edits on A must show on B; concurrent writes must merge.
const { chromium } = require('playwright-core');
const A = process.env.A, Bh = process.env.B, PW = process.env.PW; let pass = 0, fail = 0;
const ok = (c, n, x = '') => { c ? pass++ : fail++; console.log((c ? 'PASS ' : 'FAIL ') + n + (c ? '' : ' — ' + x)); };
(async () => {
  const b = await chromium.launch({ executablePath: '/usr/bin/google-chrome', args: ['--no-sandbox'] });
  const login = async (ctx, base) => { const p = await ctx.newPage(); await p.goto(base + '/admin/'); await p.fill('#u', 'admin'); await p.fill('#pw', PW); await p.click('button[type=submit].btn-block'); return p; };
  const c1 = await b.newContext(); const p = await login(c1, A);
  ok((await p.content()).includes('stat-card'), 'login on A');
  // warm B with the current copy
  const pubB = await c1.newPage(); await pubB.goto(Bh + '/ar/news');
  // edit the launch news title on A
  await p.goto(A + '/admin/?m=news'); await p.locator('.tbl a.strong').first().click();
  const t = 'عنوان معدّل ' + Date.now(); await p.fill('#f_title_ar', t); await p.click('.form-actions button[type=submit]');
  ok((await p.content()).includes('alert-ok'), 'save on A');
  await pubB.goto(Bh + '/ar/news'); ok((await pubB.content()).includes(t), 'B shows A\'s edit immediately (signed version hint)');
  // concurrent write: a second editor session logs in on B while B is behind
  await p.goto(A + '/admin/?m=news'); await p.locator('.tbl a.strong').first().click();
  const t2 = 'تعديل ثانٍ ' + Date.now(); await p.fill('#f_title_ar', t2); await p.click('.form-actions button[type=submit]');
  const c2 = await b.newContext(); const q = await login(c2, Bh);
  ok((await q.content()).includes('stat-card'), 'login on B while B is stale (write conflict → merge)');
  await q.goto(Bh + '/admin/?p=audit'); const audit = await q.content();
  ok(audit.includes('login'), 'B audit has its own login');
  await q.goto(Bh + '/admin/?m=news'); ok((await q.content()).includes(t2), 'B has A\'s latest edit after merge');
  await p.goto(A + '/admin/?p=audit'); ok((await p.locator('.tbl tbody tr').count()) > 0, 'A still healthy');
  console.log(`${pass} passed, ${fail} failed`); await b.close();
})().catch(e => { console.log('CRASH', e.message); process.exit(1); });
