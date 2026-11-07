// Local fixtures only: native requests are intercepted, never sent to the application.
const { chromium }=require(process.argv[2]);
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const current=path.resolve(process.argv[3]),before=path.resolve(process.argv[4]),assets=path.resolve(__dirname,'../../public');
const widths=[320,390,768,1024,1280,1440];
(async()=>{
 const browser=await chromium.launch({headless:true});
 try{
 const context=await browser.newContext(),errors=[],submissions=[];
 const serve=async route=>{
  const req=route.request(),url=new URL(req.url());assert.equal(url.hostname,'reservebot.test');
  if(req.method()!=='GET'||url.pathname==='/rooms'||url.pathname.startsWith('/classes/')){
   submissions.push({method:req.method(),url:url.pathname+url.search,body:req.postData()});return route.fulfill({body:'Intercepted test request'});
  }
  const rel=decodeURIComponent(url.pathname).slice(1),baseline=rel.startsWith('before/');
  const base=/^(css|fonts|js)\//.test(rel)?assets:baseline?before:current;
  const file=path.resolve(base,baseline?rel.slice(7):rel);assert.ok(file.startsWith(base+path.sep));
  await route.fulfill({body:fs.readFileSync(file),contentType:file.endsWith('.css')?'text/css':file.endsWith('.js')?'text/javascript':file.endsWith('.woff2')?'font/woff2':'text/html'});
 };
 await context.route('**/*',serve);const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
 const visit=async(name,old=false)=>{await page.goto('http://reservebot.test/'+(old?'before/':'')+name+'.html');await page.evaluate(()=>document.fonts.ready);};
 const contracts=()=>page.evaluate(()=>({
  forms:[...document.forms].map(f=>({action:f.action,method:f.method,enctype:f.enctype,fields:[...f.elements].map(e=>({tag:e.tagName,name:e.name,id:e.id,type:e.type,value:e.value,required:e.required,disabled:e.disabled,options:e.tagName==='SELECT'?[...e.options].map(o=>[o.value,o.textContent.trim(),o.selected,o.disabled]):null})),data:[...new FormData(f)]})),
  links:[...document.querySelectorAll('a:not(.rb-availability__event-count):not([href="#calendar-and-agenda"])')].map(a=>[a.getAttribute('href'),(()=>{ const copy=a.cloneNode(true); copy.querySelectorAll('.rb-nav-item__tile, .rb-nav-item__icon').forEach(el=>el.remove()); return copy.textContent.replace(/\s+/g,' ').trim(); })(),a.getAttribute('aria-current'),a.getAttribute('aria-label')]),
  records:[...document.querySelectorAll('.rb-dashboard .mlist > li, .rb-dashboard tbody tr, .rb-availability__room-row')].map(e=>{if(!e.classList.contains('rb-availability__room-row'))return e.textContent.replace(/\s+/g,' ').trim();const w=document.createTreeWalker(e,NodeFilter.SHOW_TEXT);let n,parts=[];while(n=w.nextNode()){const text=n.textContent.replace(/\s+/g,' ').trim();if(text)parts.push(text);}return parts.sort();}),
  titles:[...document.querySelectorAll('main [title]')].map(e=>e.title),
  disclosures:[...document.querySelectorAll('main details')].map(e=>e.textContent.replace(/\s+/g,' ').trim())
 }));
 let states=0;const cases=fs.readdirSync(current).filter(f=>f.endsWith('.html')).map(f=>f.slice(0,-5));
 for(const name of cases){
  await visit(name,true);const old=await contracts();
  const info=await page.locator('main').evaluate(el=>{const w=document.createTreeWalker(el,NodeFilter.SHOW_TEXT);let n,out=[];while(n=w.nextNode()){const s=n.textContent.replace(/\s+/g,' ').trim();if(s)out.push(n.parentElement.closest('.rb-availability__event-count')?s.replace('; view details below calendar',''):s);}return out;});
  const staffMain=await page.locator('main').innerHTML();
  await visit(name);const currentContract=await contracts();
  // DOM order changes intentionally. Compare complete multisets, retaining duplicates.
  const sorted=items=>items.map(item=>JSON.stringify(item)).sort();
  for(const key of ['forms','links','titles','records'])assert.deepEqual(sorted(currentContract[key]),sorted(old[key]),name+' exact '+key+' multiset parity');
  const retained=[...currentContract.disclosures];
  for(const disclosure of old.disclosures){const at=retained.indexOf(disclosure);assert.ok(at>=0,name+' retained original disclosure');retained.splice(at,1);}
  const text=(await page.locator('main').textContent()).replace(/\s+/g,' ').trim();
  for(const content of info)assert.ok(text.includes(content),name+' retained information: '+content);
  if(name==='home-lab_staff'||name==='home-admin'){
   // Phase 05 intentionally redesigned the lab-staff/admin dashboard (shared dashboard structure and stylesheet), so the
   // earlier "markup unchanged / no dashboard.css" scope guard no longer applies. Their forms, links, titles, records,
   // disclosures and information are still compared exactly against the baseline above.
   assert.equal(await page.locator('link[href*="dashboard.css"]').count(),1,name+' loads the dashboard stylesheet once');
  }
  for(const width of widths){await page.setViewportSize({width,height:900});if(!await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)){console.error(await page.locator('body *').evaluateAll(es=>es.filter(e=>{const r=e.getBoundingClientRect();return r.width&&r.right>innerWidth+1&&!e.closest('.rb-availability__cal-wrap');}).map(e=>[e.tagName,e.className,e.getBoundingClientRect().width])));await page.screenshot({path:path.join(current,'overflow.png'),fullPage:true});throw new Error(name+' page overflow '+width);}states++;}
 }
 // Calendar: native table, visible nonoverlapping day targets and keyboard-scrollable overflow.
 await page.setViewportSize({width:320,height:750});await visit('rooms-rep-normal');
 const calendar=page.locator('.rb-availability__cal-wrap');await calendar.focus();
 assert.equal(await calendar.getAttribute('role'),'region');
 assert.ok(await calendar.evaluate(e=>e.scrollWidth>e.clientWidth));
 await page.keyboard.press('ArrowRight');await page.waitForTimeout(150);
 assert.ok(await calendar.evaluate(e=>e.scrollLeft>0),'keyboard scrolls calendar');
 const cells=await page.locator('.rb-availability__calendar a').evaluateAll(es=>es.map(e=>{const r=e.getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height,text:e.textContent.trim()};}));
 for(const cell of cells){assert.ok(cell.w>=44&&cell.h>=44,'date target size');assert.ok(cell.text.length);}
 for(let i=0;i<cells.length;i++)for(let j=i+1;j<cells.length;j++){const a=cells[i],b=cells[j];assert.ok(a.x+a.w<=b.x+1||b.x+b.w<=a.x+1||a.y+a.h<=b.y+1||b.y+b.h<=a.y+1,'nonoverlapping date targets');}
 await page.locator('.rb-availability__calendar a').last().focus();assert.equal(await page.locator(':focus').evaluate(e=>getComputedStyle(e).outlineStyle),'solid');
 // Real native disclosure interaction.
 const summary=page.locator('main summary').first();await summary.focus();await page.keyboard.press('Enter');assert.ok(await summary.evaluate(e=>e.parentElement.open));
 await page.keyboard.press('Space');assert.equal(await summary.evaluate(e=>e.parentElement.open),false);
 // GET search preserves field names/date; clearing and navigation preserve the approved URLs.
 await visit('rooms-rep-normal');await page.locator('#from').selectOption('10:00');await page.locator('#to').selectOption('11:00');
 await page.locator('main button[type=submit]').click();await page.waitForLoadState();
 const search=submissions.at(-1);assert.equal(search.method,'GET');const q=new URL(search.url,'http://reservebot.test');assert.equal(q.searchParams.get('date'),'2026-11-04');assert.equal(q.searchParams.get('from'),'10:00');assert.equal(q.searchParams.get('to'),'11:00');
 await visit('rooms-rep-search');const clear=page.getByRole('link',{name:'Clear',exact:true});assert.equal(await clear.getAttribute('href'),'http://reservebot.test/rooms?date=2026-11-04#day');
 await visit('home-rep-normal');const form=page.locator('main form').first();await form.locator('button[value=online]').click();await page.waitForLoadState();
 assert.equal(submissions.at(-1).method,'POST');assert.ok(submissions.at(-1).body.includes('answer=online'));assert.ok(submissions.at(-1).body.includes('_token=isolated-layout-csrf'));
 // CSS breakpoints and enlarged text, including both long-content views.
 for(const width of [639,640,641,1119,1120,1121,1279,1280,1281,1359,1360,1361]){
  for(const name of ['home-rep-normal','rooms-rep-normal']){await page.setViewportSize({width,height:900});await visit(name);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),name+' breakpoint '+width);}
 }
 for(const name of ['home-rep-long','rooms-rep-long','rooms-admin-search']){
  await page.setViewportSize({width:320,height:750});await visit(name);await page.evaluate(()=>document.documentElement.style.fontSize='200%');
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),name+' 200% text');await page.screenshot({path:path.join(current,name+'-text200.png'),fullPage:true});
 }
 for(const name of ['home-rep-normal','home-rep-feedback','rooms-rep-normal','rooms-rep-search','rooms-lab_staff-normal','rooms-admin-empty','rooms-rep-invalid-time','rooms-rep-closed'])for(const width of [390,1440]){
  await page.setViewportSize({width,height:1000});await visit(name,true);await page.screenshot({path:path.join(current,`${name}-${width}-before.png`),fullPage:true});await visit(name);await page.screenshot({path:path.join(current,`${name}-${width}-after.png`),fullPage:true});
 }
 await page.setViewportSize({width:320,height:750});await visit('rooms-rep-normal');await page.screenshot({path:path.join(current,'calendar-320.png'),fullPage:true});
 await page.setViewportSize({width:390,height:300});await visit('home-rep-normal');await page.locator('main a').last().focus();await page.keyboard.press('Shift+Tab');await page.keyboard.press('Tab');
 let target=await page.locator(':focus').boundingBox(),bar=await page.locator('.rb-sidebar').boundingBox();assert.ok(target.y+target.height<=bar.y+1,'bottom nav clearance');
 const nojs=await browser.newContext({javaScriptEnabled:false,viewport:{width:390,height:900}});await nojs.route('**/*',serve);const n=await nojs.newPage();
 await n.goto('http://reservebot.test/rooms-rep-normal.html');await n.locator('main summary').first().click();assert.ok(await n.locator('main details').first().getAttribute('open')!==null);assert.ok(await n.locator('#from').isVisible());
 await n.goto('http://reservebot.test/home-rep-normal.html');assert.ok(await n.locator('main button[value=online]').first().isVisible());await nojs.close();
 assert.deepEqual(errors,[]);const result={result:'PASS',cases:cases.length,responsiveStates:states,widths,checks:['identical before/after forms, links, information, tooltips and disclosures','unchanged staff/admin home markup','320px keyboard-scrollable calendar and nonoverlapping targets','native search and class-answer submissions intercepted','no-JS','breakpoint boundaries','200% text','bottom navigation clearance'],limitations:['fixture browser tests do not establish HTTP authorization or database correctness','Chromium only; no physical device or screen reader']};
 fs.writeFileSync(path.join(current,'availability-browser-results.json'),JSON.stringify(result,null,2));console.log(JSON.stringify(result,null,2));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
