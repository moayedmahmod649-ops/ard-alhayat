const { chromium } = require('playwright-core'); const fs=require('fs');
const axe=fs.readFileSync('/tmp/axe/node_modules/axe-core/axe.min.js','utf8');
const pages=['/ar/','/en/','/ar/about','/en/about','/ar/activities','/ar/activities/greenhouses','/ar/projects','/en/projects','/ar/news','/ar/news/new-website','/ar/notices','/ar/gallery','/ar/gallery/livestock','/ar/careers','/en/careers','/ar/contact','/en/contact','/ar/p/privacy'];
(async()=>{const b=await chromium.launch({executablePath:'/usr/bin/google-chrome',args:['--no-sandbox']}); let tot=0;
for (const [w,h,mob] of [[1440,900,false],[390,844,true]]){const c=await b.newContext({viewport:{width:w,height:h},isMobile:mob,hasTouch:mob,reducedMotion:'reduce',bypassCSP:true});
for (const u of pages){const p=await c.newPage();await p.goto('http://127.0.0.1:8830'+u,{waitUntil:'networkidle'});
await p.evaluate(()=>document.querySelectorAll('.reveal').forEach(e=>e.classList.add('in')));await p.addScriptTag({content:axe});
const r=await p.evaluate(async()=>{const res=await axe.run(document,{runOnly:['wcag2a','wcag2aa']});
 const tap=[...document.querySelectorAll('a,button,input,select,textarea,[role=button]')].filter(e=>{const s=getComputedStyle(e);if(s.visibility==='hidden'||s.display==='none')return false;const r=e.getBoundingClientRect();if(!r.width||!r.height)return false;if(e.closest('.prose,p,li.crumb,.crumbs,figcaption')&&e.tagName==='A')return false;if(e.closest('.hp'))return false;return (r.height<44&&r.width<44)||r.height<24});
 const small=[...document.querySelectorAll('body *')].filter(e=>{if(!e.childNodes.length)return false;const t=[...e.childNodes].some(n=>n.nodeType===3&&n.textContent.trim());if(!t)return false;const s=getComputedStyle(e);return s.display!=='none'&&s.visibility!=='hidden'&&parseFloat(s.fontSize)<14&&e.getBoundingClientRect().width>0&&!e.closest('.sr,.skip')});
 return {v:res.violations.map(v=>v.id+':'+v.nodes.length+' '+v.nodes.slice(0,2).map(n=>n.target.join(' ')).join(' | ')),tap:tap.slice(0,4).map(e=>e.tagName+'.'+e.className+' '+Math.round(e.getBoundingClientRect().width)+'x'+Math.round(e.getBoundingClientRect().height)),small:small.slice(0,4).map(e=>e.tagName+'.'+e.className+' '+getComputedStyle(e).fontSize+' '+e.textContent.trim().slice(0,20)),ov:document.documentElement.scrollWidth-innerWidth}});
if(r.v.length||r.tap.length||r.small.length||r.ov>0){tot++;console.log(w,u,JSON.stringify(r));} await p.close();}}
console.log('issues pages:',tot);await b.close();})();
