const { chromium } = require('playwright-core');
(async () => {
  const b = await chromium.launch({ executablePath: '/usr/bin/google-chrome', args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: process.env.RM==='1'?'reduce':'no-preference' });
  const pg = await ctx.newPage();
  pg.on('console', m => console.log('console', m.type(), m.text()));
  await pg.goto('http://127.0.0.1:8830' + process.argv[2], { waitUntil: 'networkidle' });
  const r = await pg.evaluate(new Function(process.argv[3]));
  console.log(JSON.stringify(r, null, 1));
  if (process.argv[4]) { await pg.screenshot({ path: process.argv[4] }); }
  await b.close();
})();
