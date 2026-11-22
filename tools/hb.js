const { chromium } = require('playwright-core');
(async()=>{const b=await chromium.launch({executablePath:'/usr/bin/google-chrome',args:['--no-sandbox']});
for (const u of ['/ar/','/en/']){const p=await (await b.newContext({viewport:{width:1920,height:900}})).newPage();await p.goto('http://127.0.0.1:8830'+u);
console.log(u, JSON.stringify(await p.evaluate(()=>{const h=document.querySelector('.head-in');const hr=h.getBoundingClientRect();return [hr.left,hr.right,[...h.querySelectorAll('*')].filter(e=>{const r=e.getBoundingClientRect();return r.width&&(r.right>hr.right+1||r.left<hr.left-1)}).map(e=>[e.tagName,e.className,Math.round(e.getBoundingClientRect().left),Math.round(e.getBoundingClientRect().right),getComputedStyle(e).visibility])]})));}
await b.close();})();
