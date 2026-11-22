const { chromium } = require('playwright-core');
(async()=>{const b=await chromium.launch({executablePath:'/usr/bin/google-chrome',args:['--no-sandbox']});
for (const w of [1440,1920]){const p=await (await b.newContext({viewport:{width:w,height:900}})).newPage();await p.goto('http://127.0.0.1:8830/en/');
console.log(w, JSON.stringify(await p.evaluate(()=>[...document.querySelector('.head-in').children].map(e=>[e.className,Math.round(e.getBoundingClientRect().width),getComputedStyle(e).fontSize]).concat([[getComputedStyle(document.documentElement).fontSize]]))));}
await b.close();})();
