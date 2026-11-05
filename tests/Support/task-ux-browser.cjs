// Stage 2: immutable fixtures and snapshot-specific assets; never contacts the app.
// node tests/Support/task-ux-browser.cjs /existing/playwright-core /tmp/rb-task-ux
const {chromium}=require(process.argv[2]);
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const root=path.resolve(process.argv[3]);
const sizes=[[1438,638],[1440,900],[1366,768],[1280,720],[1024,600],[768,1024],[390,844],[375,812],[320,640],[1438,400],[1438,631],[1438,632],[1023,800],[1024,800],[1025,800],[1279,720],[1281,720]];
(async()=>{
 const browser=await chromium.launch({headless:true});const measurements=[],errors=[];let responsive=0;
 const serve=version=>async route=>{
  const u=new URL(route.request().url());assert.equal(route.request().method(),'GET');assert.ok(['reservebot.test','before.test','after.test'].includes(u.hostname));
  const rel=decodeURIComponent(u.pathname).slice(1),asset=/^(css|js|fonts)\//.test(rel);
  const base=asset?path.join(root,version,'public'):path.join(root,version+'-'+rel.split('/')[0]);
  const file=path.resolve(base,asset?rel:rel.split('/').slice(1).join('/'));assert.ok(file.startsWith(base+path.sep));
  await route.fulfill({body:fs.readFileSync(file),contentType:file.endsWith('.css')?'text/css':file.endsWith('.js')?'text/javascript':file.endsWith('.woff2')?'font/woff2':'text/html'});
 };
 try{
 const context=await browser.newContext({deviceScaleFactor:2});await context.route('**/*',serve('after'));
 const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
 const settle=()=>page.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));
 const visit=async fixture=>{await page.goto('http://after.test/'+fixture+'.html');await page.evaluate(()=>document.fonts.ready);await page.evaluate(()=>scrollTo(0,0));await settle();};
 const geometry=()=>page.evaluate(()=>{
  const box=s=>{const e=document.querySelector(s);if(!e)return null;const r=e.getBoundingClientRect();return {x:r.x,y:r.y,width:r.width,height:r.height,bottom:r.bottom,scroll:e.scrollHeight,client:e.clientHeight,scrollTop:e.scrollTop};};
  return {width:innerWidth,height:innerHeight,dpr:devicePixelRatio,documentHeight:document.documentElement.scrollHeight,documentWidth:document.documentElement.scrollWidth,scrollY,main:box('main'),panel:box('.rb-shell__content'),email:box('#email'),password:box('#password'),login:box('main button[type=submit]'),recovery:box('.rb-auth-card__footer a'),calendar:box('.rb-availability__cal-card'),agenda:box('#day'),results:box('.rb-availability__results-section')};
 });
 for(const [width,height]of sizes)for(const text of [100,125,200])for(const fixture of ['auth/login','auth/login-error','availability/home-rep-priority','availability/home-rep-long','availability/rooms-rep-normal','availability/rooms-rep-long']){
  await page.setViewportSize({width,height});await visit(fixture);await page.evaluate(n=>document.documentElement.style.fontSize=n+'%',text);await settle();
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`${fixture} ${width}x${height} text${text} horizontal overflow`);
  if(fixture==='auth/login'&&text===100&&[[1438,638],[1366,768],[1280,720]].some(s=>s[0]===width&&s[1]===height)){
   for(const selector of ['#email','#password','main button[type=submit]','.rb-auth-card__footer a']){const b=await page.locator(selector).boundingBox();assert.ok(b.y>=0&&b.y+b.height<=height,selector+' initially visible '+JSON.stringify({width,height,b}));}
   assert.equal(await page.locator('.rb-auth-login__art').isVisible(),false,'short laptop artwork hidden');
  }
  if(fixture.includes('login')){
   await page.locator('.rb-auth-card__footer a').focus();const b=await page.locator(':focus').boundingBox();assert.ok(b.y>=0&&b.y+b.height<=height+1,'recovery focus reachable');
   assert.equal(await page.locator('.rb-auth-login__card').evaluate(e=>getComputedStyle(e).overflowY),'visible','no inner form scrollbar');
  }else if(fixture.includes('priority')){
   assert.equal(await page.locator('[data-meetings=attention] > li').count(),7);assert.equal(await page.locator('[data-meetings=other] > li').count(),3);
   assert.equal(await page.locator('main form[action*="/classes/"]').count(),8);
   assert.equal(await page.locator('[data-meetings=attention]').evaluate(e=>!!e.closest('details')),false);
   assert.equal(await page.locator('[data-meetings=other]').isVisible(),false);
   const codes=await page.locator('[data-meetings=attention] > li .mlist-what').allTextContents();
   assert.deepEqual(codes.map(s=>s.match(/SAMPLE \d+/)[0]),['SAMPLE 0','SAMPLE 1','SAMPLE 2','SAMPLE 3','SAMPLE 4','SAMPLE 5','SAMPLE 6']);
   const summaries=page.locator('.rb-dashboard__other > summary,.rb-dashboard__timetable > summary');
   for(let i=0;i<await summaries.count();i++){await summaries.nth(i).focus();await page.keyboard.press('Enter');assert.ok(await summaries.nth(i).evaluate(e=>e.parentElement.open));assert.equal(await summaries.nth(i).evaluate(e=>getComputedStyle(e).outlineStyle),'solid');}
   assert.equal(await page.locator('.rb-dashboard__classes tbody tr').count(),10);
   assert.ok(await page.locator('.rb-dashboard__classes tbody tr').last().isVisible());
   assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'expanded timetable reflow');
  }else if(fixture.endsWith('rooms-rep-normal')){
   const r=await page.locator('.rb-availability__results-section').boundingBox(),outer=await page.locator('.rb-availability').boundingBox();assert.ok(Math.abs(r.width-outer.width)<=1,'full-width results');
   assert.equal(await page.locator('.rb-availability__room-row').count(),4);assert.equal(await page.locator('.rb-availability__free-chip:visible').count(),4);
   await page.locator('.rb-availability__event-count').filter({hasText:'4 events'}).first().click();const summary=page.locator('.rb-availability__calendar-events:not([hidden]) > summary');assert.equal(await summary.locator('..').locator('li').count(),4);assert.ok(await summary.locator('..').locator('li').last().isVisible());await page.keyboard.press('Space');assert.equal(await summary.evaluate(e=>e.parentElement.open),false);
  }
  if(width===1438&&height===638){await visit(fixture);await page.evaluate(n=>document.documentElement.style.fontSize=n+'%',text);await settle();measurements.push({fixture,text,...await geometry()});}
  responsive++;
 }
 // Native no-JS disclosure operation keeps all rows, answers and calendar overflow reachable.
 const nojs=await browser.newContext({javaScriptEnabled:false,viewport:{width:390,height:844}});await nojs.route('**/*',serve('after'));const n=await nojs.newPage();
 await n.goto('http://after.test/availability/home-rep-priority.html');
 for(const selector of ['.rb-dashboard__other > summary','.rb-dashboard__timetable > summary']){await n.locator(selector).focus();await n.keyboard.press('Enter');assert.ok(await n.locator(selector).evaluate(e=>e.parentElement.open));}
 assert.equal(await n.locator('main form[action*="/classes/"]:visible').count(),8);assert.equal(await n.locator('.rb-dashboard__classes tbody tr:visible').count(),10);
 await n.goto('http://after.test/availability/rooms-rep-normal.html');const detail=n.locator('.rb-availability__calendar-events').filter({hasText:'4 events'}).first();assert.ok(await detail.evaluate(e=>e.open));assert.equal(await detail.locator('li:visible').count(),4);await nojs.close();
 // Identical fixture/viewport screenshots, with each version's own CSS, JS and fonts.
 for(const version of ['before','after']){
  const c=await browser.newContext({deviceScaleFactor:2});await c.route('**/*',serve(version));const p=await c.newPage();
  for(const [width,height]of [[1438,638],[1440,900],[390,844]])for(const fixture of ['auth/login','availability/home-rep-normal','availability/rooms-rep-normal']){
   await p.setViewportSize({width,height});await p.goto(`http://${version}.test/${fixture}.html`);await p.evaluate(()=>document.fonts.ready);await p.evaluate(()=>{scrollTo(0,0);document.querySelector('.rb-shell__content').scrollTop=0;});await p.waitForTimeout(100);
   const name=fixture.replace('/','-');await p.screenshot({path:path.join(root,`stage2-${name}-${width}x${height}-${version}.png`)});
   if(width===1438)measurements.push({version,fixture,screenshot:true,...await p.evaluate(()=>({documentHeight:document.documentElement.scrollHeight,contentHeight:document.querySelector('.rb-shell__content').scrollHeight,mainHeight:document.querySelector('main').getBoundingClientRect().height}))});
  }await c.close();
 }
 assert.deepEqual(errors,[]);
 const result={result:'PASS',responsive,deviceScaleFactor:2,sizes,measurements,checks:['initial laptop login visibility','all today and future waiting/freed meetings visible; chronological groups; ended suppression','every meeting, form and timetable row exactly once','native keyboard and no-JS disclosures','full-width room results and visible free intervals','calendar overflow details without hover','125/200% root text and horizontal overflow','snapshot-specific before/after screenshots'],limits:['isolated supplied-data rendering, not authenticated HTTP or service privacy proof','root text enlargement, not browser-toolbar zoom','Chromium only; no physical device or screen reader']};
 fs.writeFileSync(path.join(root,'task-ux-results.json'),JSON.stringify(result,null,2));console.log(JSON.stringify({result:result.result,responsive,checks:result.checks}));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
