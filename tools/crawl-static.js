const { chromium } = require('playwright-core');
const base=process.env.BASE||'http://127.0.0.1:8830';
(async()=>{
 const b=await chromium.launch({executablePath:'/usr/bin/google-chrome',args:['--no-sandbox']});
 const seen=new Set(), q=['/ar/','/en/','/admin/dashboard','/admin/login'], out=[];
 const ctxD=await b.newContext({viewport:{width:1440,height:900}});
 const ctxM=await b.newContext({viewport:{width:390,height:844},isMobile:true,hasTouch:true});
 while(q.length && seen.size<200){
  const u=q.shift(); if(seen.has(u))continue; seen.add(u);
  for (const [nm,ctx] of [['d',ctxD],['m',ctxM]]){
   const p=await ctx.newPage(); const errs=[];
   p.on('console',m=>{if(m.type()==='error')errs.push(m.text())}); p.on('pageerror',e=>errs.push('PE '+e.message));
   p.on('response',r=>{if(r.status()>=400 && r.url().startsWith(base))errs.push(r.status()+' '+r.url())});
   let st=0; try{const r=await p.goto(base+u,{waitUntil:'networkidle'});st=r.status();}catch(e){errs.push('NAV '+e.message)}
   const info=await p.evaluate(()=>({ov:document.documentElement.scrollWidth-innerWidth,dir:document.documentElement.dir,links:[...document.querySelectorAll('a[href]')].map(a=>a.getAttribute('href'))})).catch(()=>({ov:0,links:[]}));
   if(nm==='d') for(const h of info.links){ if(h.startsWith('/')&&(h.startsWith('/ar')||h.startsWith('/en')||h.startsWith('/admin'))){const c=h.split('#')[0]; if(c && !(c.includes('tag=')&&c.includes('cat=')) && !seen.has(c)&&!c.includes('preview')) q.push(c);} }
   if(st!==200||errs.length||info.ov>0) out.push({u,nm,st,ov:info.ov,errs:[...new Set(errs)].slice(0,5)});
   await p.close();
  }
 }
 console.log('pages',seen.size); console.log(JSON.stringify(out,null,1)); console.log(JSON.stringify([...seen]));
 await b.close();
})();
