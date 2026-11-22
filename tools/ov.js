const { chromium } = require('playwright-core');
(async()=>{const b=await chromium.launch({executablePath:'/usr/bin/google-chrome',args:['--no-sandbox']});const p=await (await b.newContext({viewport:{width:390,height:844}})).newPage();
await p.goto('http://127.0.0.1:8830/admin/');await p.fill('#u','admin');await p.fill('#pw','Tmp-Shot-pass-2026');await p.click('button[type=submit].btn-block');await p.waitForLoadState();
await p.goto('http://127.0.0.1:8830/admin/?m=news');
console.log(JSON.stringify(await p.evaluate(()=>[...document.querySelectorAll('body > *, .main > *, .main > * > *')].filter(e=>{const r=e.getBoundingClientRect();return r.width&&!e.closest('.side')&&(r.left<-1||r.right>innerWidth+1||e.scrollWidth>innerWidth)}).slice(0,6).map(e=>e.tagName+'.'+e.className+' '+Math.round(e.getBoundingClientRect().left)+'/'+Math.round(e.getBoundingClientRect().right)))));await b.close();})();
