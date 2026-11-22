const { chromium } = require('playwright-core');
(async () => { const b = await chromium.launch({ executablePath: '/usr/bin/google-chrome', args: ['--no-sandbox'] });
 for (const [w,h,pre] of [[1440,900,'d'],[390,844,'m']]) { const p = await (await b.newContext({ viewport: { width: w, height: h }, reducedMotion:'reduce' })).newPage();
  for (const u of ['/ar/projects','/ar/contact','/en/contact']) { await p.goto('http://127.0.0.1:8830'+u); await p.waitForTimeout(300);
   await p.evaluate(()=>document.querySelectorAll('.reveal').forEach(e=>e.classList.add('in')));
   await p.screenshot({ path: `/tmp/shots/v2-${pre}${u.replace(/\//g,'_')}.png`, fullPage: true }); } }
 await b.close(); })();
