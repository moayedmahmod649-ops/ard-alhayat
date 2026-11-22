const { chromium } = require('playwright-core'); const fs = require('fs');
const B = process.env.BASE, pw0 = fs.readFileSync(process.env.CRED, 'utf8').match(/Password: (\S+)/)[1], NP = 'Tmp-Shot-pass-2026';
(async () => {
  const b = await chromium.launch({ executablePath: '/usr/bin/google-chrome', args: ['--no-sandbox'] });
  const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  await p.goto(B + '/admin/'); await p.screenshot({ path: '/tmp/shots/adm-login.png' });
  await p.fill('#u', 'admin'); await p.fill('#pw', pw0); await p.click('button[type=submit].btn-block');
  if (p.url().includes('p=profile')) { await p.fill('#pw', NP); await p.fill('#pw2', NP); await p.fill('#cur', pw0); await p.click('.form-actions button'); }
  for (const [n, u] of [['dash', '/admin/'], ['news', '/admin/?m=news'], ['edit', '/admin/?m=news&a=edit&id=1'], ['settings', '/admin/?p=settings']]) { await p.goto(B + u); await p.waitForTimeout(400); await p.screenshot({ path: `/tmp/shots/adm-${n}.png` }); }
  await b.close();
})();
