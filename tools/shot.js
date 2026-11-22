// usage: node shot.js out-prefix path [path...]  (env W,H,MOB,FULL)
const { chromium } = require('playwright-core');
(async () => {
  const [prefix, ...paths] = process.argv.slice(2);
  const mob = process.env.MOB === '1';
  const b = await chromium.launch({ executablePath: '/usr/bin/google-chrome', args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: +(process.env.W || (mob ? 390 : 1440)), height: +(process.env.H || (mob ? 844 : 900)) }, isMobile: mob, hasTouch: mob, deviceScaleFactor: mob ? 2 : 1, reducedMotion: process.env.RM === '1' ? 'reduce' : 'no-preference' });
  for (const p of paths) {
    const pg = await ctx.newPage(); const errs = [];
    pg.on('console', m => m.type() === 'error' && errs.push(m.text()));
    pg.on('response', r => r.status() >= 400 && errs.push(r.status() + ' ' + r.url()));
    pg.on('pageerror', e => errs.push('JS ' + e.message));
    await pg.goto('http://127.0.0.1:8830' + p, { waitUntil: 'networkidle' });
    // reveal everything for full-page shots
    await pg.evaluate(async () => { document.documentElement.style.scrollBehavior='auto'; for (let y = 0; y < document.body.scrollHeight; y += 500) { scrollTo(0, y); await new Promise(r => setTimeout(r, 60)); } scrollTo(0, 0); });
    await pg.waitForTimeout(+(process.env.WAIT || 1800));
    const name = prefix + '-' + (p.replace(/[\/?=&]+/g, '_').replace(/^_|_$/g, '') || 'root') + '.png';
    await pg.screenshot({ path: name, fullPage: process.env.FULL !== '0' });
    const ov = await pg.evaluate(() => document.documentElement.scrollWidth - innerWidth);
    console.log(name, 'overflow', ov, errs.length ? 'ERR ' + errs.join(' | ') : '');
    await pg.close();
  }
  await b.close();
})();
