const { chromium } = require('playwright-core'); const fs=require('fs');
const axe=fs.readFileSync('/tmp/axe/node_modules/axe-core/axe.min.js','utf8');
(async()=>{const b=await chromium.launch({executablePath:'/usr/bin/google-chrome',args:['--no-sandbox']});let tot=0;
for (const [w,h] of [[1440,900],[390,844]]){const c=await b.newContext({viewport:{width:w,height:h},bypassCSP:true});const p=await c.newPage();
await p.goto('http://127.0.0.1:8830/admin/');await p.fill('#u','admin');await p.fill('#pw','Tmp-Shot-pass-2026');await p.click('button[type=submit].btn-block');await p.waitForLoadState();
for (const u of ['/admin/','/admin/?m=news','/admin/?m=news&a=edit&id=1','/admin/?m=projects&a=new','/admin/?p=settings','/admin/?p=media','/admin/?p=users','/admin/?p=messages','/admin/?p=backup','/admin/?p=profile','/admin/?p=help','/admin/?p=stats']){
await p.goto('http://127.0.0.1:8830'+u);await p.addScriptTag({content:axe});
const r=await p.evaluate(async()=>{const res=await axe.run(document,{runOnly:['wcag2a','wcag2aa']});
const tap=[...document.querySelectorAll('a,button,select,input:not([type=hidden]):not([type=checkbox]):not([type=radio])')].filter(e=>{const s=getComputedStyle(e);if(s.visibility==='hidden'||s.display==='none')return false;const r=e.getBoundingClientRect();if(!r.width)return false;if(e.closest('p,td,.prose,.hp,.ql-editor'))return false;return r.height<44&&r.width<44||r.height<24});
const small=[...document.querySelectorAll('body *')].filter(e=>[...e.childNodes].some(n=>n.nodeType===3&&n.textContent.trim())&&parseFloat(getComputedStyle(e).fontSize)<14&&e.getBoundingClientRect().width>0&&!e.closest('.sr'));
return {v:res.violations.map(v=>v.id+':'+v.nodes.length+' '+v.nodes[0].target.join(' ')),tap:tap.slice(0,3).map(e=>e.tagName+'.'+e.className+' '+Math.round(e.getBoundingClientRect().width)+'x'+Math.round(e.getBoundingClientRect().height)),small:small.slice(0,3).map(e=>e.tagName+'.'+e.className+' '+getComputedStyle(e).fontSize),ov:document.documentElement.scrollWidth-innerWidth}});
if(r.v.length||r.tap.length||r.small.length||r.ov>0){tot++;console.log(w,u,JSON.stringify(r));}}}
console.log('admin issue pages',tot);await b.close();})();
