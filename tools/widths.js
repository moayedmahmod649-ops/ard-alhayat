const { chromium } = require('playwright-core');
(async()=>{const b=await chromium.launch({executablePath:'/usr/bin/google-chrome',args:['--no-sandbox']});
for(const w of [390,768,1061,1100,1200,1280,1366,1440,1920]) for(const u of ['/ar/','/en/','/ar/about','/en/about','/en/notices']){
const c=await b.newContext({viewport:{width:w,height:900}});const p=await c.newPage();await p.goto('http://127.0.0.1:8830'+u,{waitUntil:'networkidle'});
const o=await p.evaluate(()=>{const ov=document.documentElement.scrollWidth-innerWidth;const h=document.querySelector('.head-in');const hr=h.getBoundingClientRect();const bad=[...h.querySelectorAll('*')].filter(e=>{const r=e.getBoundingClientRect();return r.width&&(r.right>hr.right+1||r.left<hr.left-1)}).length;const nh=document.querySelector('.site-head').getBoundingClientRect().height;return {ov,bad,nh}});
if(o.ov>0||o.bad||o.nh>100)console.log(w,u,JSON.stringify(o));await c.close();}
console.log('done');await b.close();})();
