// Stitched full-page screenshot (viewport tiles) — reliable with fixed headers & 100svh heroes.
// usage: node full.js out.png path   env MOB=1, W, RM=1
const { chromium } = require('playwright-core');
const { execFileSync } = require('child_process');
(async () => {
  const [out, path] = process.argv.slice(2); const mob = process.env.MOB === '1';
  const W = +(process.env.W || (mob ? 390 : 1440)), H = mob ? 844 : 900;
  const b = await chromium.launch({ executablePath: '/usr/bin/google-chrome', args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: W, height: H }, isMobile: mob, hasTouch: mob, deviceScaleFactor: 1, reducedMotion: process.env.RM === '1' ? 'reduce' : 'no-preference' });
  const pg = await ctx.newPage(); const errs = [];
  pg.on('console', m => m.type() === 'error' && errs.push(m.text()));
  pg.on('response', r => r.status() >= 400 && errs.push(r.status() + ' ' + r.url()));
  pg.on('pageerror', e => errs.push('JS ' + e.message));
  await pg.goto('http://127.0.0.1:8830' + path, { waitUntil: 'networkidle' });
  await pg.evaluate(() => document.documentElement.style.scrollBehavior = 'auto');
  const total = await pg.evaluate(() => document.documentElement.scrollHeight);
  const tiles = [];
  for (let y = 0, i = 0; y < total; y += H, i++) {
    await pg.evaluate(y => scrollTo(0, y), y); await pg.waitForTimeout(i === 0 ? 1500 : 900);
    if (i === 1) await pg.evaluate(() => { const h = document.querySelector('.site-head'); if (h) h.style.visibility = 'hidden'; });
    const f = `/tmp/tile-${process.pid}-${i}.png`; await pg.screenshot({ path: f }); tiles.push([f, await pg.evaluate(() => scrollY)]);
  }
  const ov = await pg.evaluate(() => document.documentElement.scrollWidth - innerWidth);
  await b.close();
  execFileSync('python3', ['-c', `
import sys,json
from PIL import Image
t=json.loads(sys.argv[1]); W=${W}; H=${H}; total=${total}
c=Image.new('RGB',(W,total))
for f,y in t: c.paste(Image.open(f).convert('RGB'),(0,y))
c.save(sys.argv[2])`, JSON.stringify(tiles), out]);
  console.log(out, 'h', total, 'overflow', ov, errs.length ? 'ERR ' + errs.join(' | ') : '');
})();
