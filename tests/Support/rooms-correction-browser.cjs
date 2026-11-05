// Isolated fixtures only. No production server, user data or database.
// node tests/Support/rooms-correction-browser.cjs /existing/playwright-core /tmp/rb-rooms-correction
const {chromium}=require(process.argv[2]),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const root=path.resolve(process.argv[3]);
const sizes=[[1438,638],[1440,900],[1366,768],[1280,720],[1024,600],[768,1024],[390,844],[375,812],[320,640],[1438,631],[1438,632]];
(async()=>{
 const browser=await chromium.launch({headless:true}),measurements=[],errors=[];let cases=0;
 const routeFor=version=>async route=>{
  const u=new URL(route.request().url());assert.equal(route.request().method(),'GET');assert.ok(['reservebot.test','before.test','after.test'].includes(u.hostname));
  const rel=decodeURIComponent(u.pathname).slice(1),asset=/^(css|js|fonts)\//.test(rel),base=asset?path.join(root,version,'public'):path.join(root,version+'-availability');
  const file=path.resolve(base,rel);assert.ok(file.startsWith(base+path.sep));
  await route.fulfill({body:fs.readFileSync(file),contentType:file.endsWith('.css')?'text/css':file.endsWith('.js')?'text/javascript':file.endsWith('.woff2')?'font/woff2':'text/html'});
 };
 try{
 const context=await browser.newContext({deviceScaleFactor:2});await context.route('**/*',routeFor('after'));const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
 const oldContext=await browser.newContext();await oldContext.route('**/*',routeFor('before'));const oldPage=await oldContext.newPage();
 const settle=()=>page.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));
 const visit=async(name,p=page)=>{await p.goto('http://reservebot.test/'+name+'.html');await p.evaluate(()=>document.fonts.ready);};
 const eventRecords=p=>p.locator('.rb-availability__calendar-events').evaluateAll(es=>es.map(e=>({date:e.querySelector('summary').textContent.replace(/\s+/g,' ').trim(),events:[...e.querySelectorAll('li')].map(li=>li.textContent.replace(/\s+/g,' ').trim())})).sort((a,b)=>a.date.localeCompare(b.date)));
 const calendar=()=>page.locator('.rb-availability__calendar').evaluate(e=>({height:e.getBoundingClientRect().height,rows:[...e.rows].map(r=>r.getBoundingClientRect().height),cardHeight:e.closest('.rb-card').getBoundingClientRect().height}));
 const geometry=()=>page.evaluate(()=>{
  const rect=s=>{const e=document.querySelector(s),r=e.getBoundingClientRect();return {x:r.x,y:r.y,width:r.width,height:r.height,bottom:r.bottom};};
  const p=document.querySelector('.rb-shell__content');return {search:rect('.rb-availability__finder'),from:rect('#from'),to:rect('#to'),submit:rect('.rb-availability__finder button'),firstRoom:rect('.rb-availability__room-row'),panel:rect('.rb-shell__content'),panelScroll:p.scrollTop,panelHeight:p.scrollHeight,documentHeight:document.documentElement.scrollHeight,documentWidth:document.documentElement.scrollWidth,scrollY};
 });
 for(const role of ['rep','lab_staff','admin']){
  await visit('rooms-'+role+'-long-events',oldPage);const oldEvents=await eventRecords(oldPage);
  await visit('rooms-'+role+'-long-events');assert.deepEqual(await eventRecords(page),oldEvents,role+' complete authorized event details, order and counts');
  assert.equal(await page.locator('table details').count(),0,'no details in table');
  const anchors=await page.locator('.rb-availability__event-count').evaluateAll(es=>es.map(e=>({href:e.getAttribute('href'),text:e.textContent.replace(/\s+/g,' ').trim()})));
  for(const a of anchors){assert.equal(await page.locator(a.href).count(),1,'unique external target');assert.ok(await page.locator(a.href+' > summary').count());}
  for(const [width,height]of sizes)for(const text of [100,125,200]){
   await page.setViewportSize({width,height});await visit('rooms-'+role+'-long-events');await page.evaluate(n=>document.documentElement.style.fontSize=n+'%',text);await settle();
   assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'initial page width');const initial=await calendar();
   const stages=[];
   for(const date of ['2026-11-03','2026-11-05']){
    const trigger=page.locator('a[href="#events-'+date+'"]');await trigger.focus();await page.keyboard.press('Enter');const summary=page.locator('#events-'+date+' > summary');await settle();
    assert.ok(await summary.evaluate(e=>e.parentElement.open),'keyboard opens '+date);
    assert.equal(await summary.evaluate(e=>getComputedStyle(e).outlineStyle),'solid');
    assert.equal(await page.locator('.rb-availability__calendar-events:not([hidden])').count(),1,'one selected date panel');
    assert.equal(await trigger.getAttribute('aria-expanded'),'true');
    const measured=await calendar();assert.deepEqual({height:measured.height,rows:measured.rows,cardHeight:measured.cardHeight},initial,'calendar and every row invariant after '+date+' '+role+' '+width+' text'+text);
    assert.ok(await page.locator('#events-'+date+' .rb-availability__event-list li').last().isVisible());
    assert.equal(await page.locator('#events-'+date+' .rb-disclosure__content').evaluate(e=>getComputedStyle(e).overflowY),'visible','natural detail flow');
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'expanded page width');stages.push({date,...measured,...await geometry()});
   }
   if(width===1438&&height===638)measurements.push({role,text,initial,expanded:stages});cases++;
  }
  await page.setViewportSize({width:1438,height:638});await visit('rooms-'+role+'-normal');await settle();
  const normal=await geometry();for(const key of ['from','to','submit'])assert.ok(normal[key].y>=normal.panel.y&&normal[key].bottom<=638,role+' initial '+key+' '+JSON.stringify(normal));
  assert.ok(normal.firstRoom.y>=normal.panel.y&&normal.firstRoom.y<638,role+' first room begins in viewport');
  assert.equal(normal.documentHeight,638);assert.equal(normal.scrollY,0);assert.equal(normal.panelScroll,0);measurements.push({role,normal});
  // Selection is local state: no routing or scroll-coordinate changes. Calendar
  // shortcut and original date URLs retain native fragment/history behavior.
  await page.locator('a[href="#calendar-and-agenda"]').click();await settle();
  const originalURL=page.url(),historyLength=await page.evaluate(()=>history.length);
  const rowsBefore=await calendar();const selections=[];
  for(const date of ['2026-11-03','2026-11-05']){
   const trigger=page.locator('a[href="#events-'+date+'"]');const prior=await geometry();await trigger.click();await settle();const next=await geometry();assert.ok(next.panelScroll<=prior.panelScroll+2,'selection does not jump down the page');selections.push({date,before:prior.panelScroll,after:next.panelScroll});
   assert.equal(page.url(),originalURL);assert.equal(await page.evaluate(()=>history.length),historyLength);
   assert.ok(await page.locator('#events-'+date).evaluate(e=>e.open&&!e.hidden));
   assert.deepEqual(await calendar(),rowsBefore);
   const summary=page.locator('#events-'+date+' > summary'),focused=await summary.boundingBox(),panel=await page.locator('.rb-shell__content').boundingBox();
   assert.ok(focused.y>=panel.y-1&&focused.y+focused.height<=638,'selected summary focus visible');assert.equal(await page.evaluate(()=>scrollY),0);
  }
  const layout=await page.evaluate(()=>{const box=s=>{const r=document.querySelector(s).getBoundingClientRect();return {x:r.x,y:r.y,width:r.width,height:r.height};};return {calendar:box('.rb-availability__cal-card'),details:box('.rb-availability__calendar-details'),agenda:box('.rb-availability__agenda-card')};});
  assert.ok(layout.details.x>layout.calendar.x,'desktop contextual panel alongside calendar');
  assert.ok(layout.agenda.y>=Math.max(layout.calendar.y+layout.calendar.height,layout.details.y+layout.details.height),'selected-day agenda retained separately');
  measurements.push({role,selections,layout});
  await page.locator('a[href="#events-2026-11-03"]').focus();await page.keyboard.press('Space');assert.ok(await page.locator('#events-2026-11-03').evaluate(e=>!e.hidden&&e.open));
  await page.goBack();await page.waitForTimeout(150);assert.equal(new URL(page.url()).hash,'');
  await page.goForward();await page.waitForTimeout(150);assert.equal(new URL(page.url()).hash,'#calendar-and-agenda');
  // Bookmarked fallback URL reveals that exact group on load.
  await page.goto('http://reservebot.test/rooms-'+role+'-normal.html#events-2026-11-03');await settle();
  assert.ok(await page.locator('#events-2026-11-03').evaluate(e=>e.open&&!e.hidden));

 }
 // Real touch activation on mobile: details follow the calendar and focus is reachable.
 const touch=await browser.newContext({viewport:{width:390,height:844},deviceScaleFactor:2,isMobile:true,hasTouch:true});await touch.route('**/*',routeFor('after'));const t=await touch.newPage();await visit('rooms-admin-long-events',t);
 await t.locator('a[href="#events-2026-11-05"]').tap();assert.ok(await t.locator('#events-2026-11-05').evaluate(e=>e.open&&!e.hidden));
 const mobile=await t.evaluate(()=>{const c=document.querySelector('.rb-availability__cal-card').getBoundingClientRect(),p=document.querySelector('.rb-availability__calendar-details').getBoundingClientRect(),f=document.activeElement.getBoundingClientRect();return {calendarBottom:c.bottom,panelTop:p.top,focusTop:f.top,focusBottom:f.bottom,height:innerHeight};});assert.ok(mobile.panelTop>=mobile.calendarBottom);assert.ok(mobile.focusTop>=0&&mobile.focusBottom<=mobile.height);measurements.push({mobile});await t.screenshot({path:path.join(root,'contextual-mobile-selected-390x844.png')});await touch.close();
 // No JS: external details, keyboard activation and all authorized information remain usable.
 const nojs=await browser.newContext({javaScriptEnabled:false,viewport:{width:390,height:844}});await nojs.route('**/*',routeFor('after'));const n=await nojs.newPage();
 for(const role of ['rep','lab_staff','admin']){
  await visit('rooms-'+role+'-long-events',n);
  const initial=await n.locator('.rb-availability__calendar tr').evaluateAll(es=>es.map(e=>e.getBoundingClientRect().height));
  for(const date of ['2026-11-03','2026-11-05']){await n.locator('a[href="#events-'+date+'"]').click();const summary=n.locator('#events-'+date+' > summary');assert.ok(await summary.evaluate(e=>e.parentElement.open),'single native fallback click reveals details');}
  assert.deepEqual(await n.locator('.rb-availability__calendar tr').evaluateAll(es=>es.map(e=>e.getBoundingClientRect().height)),initial);
  assert.equal(await n.locator('#events-2026-11-03 li:visible').count(),4);assert.equal(await n.locator('#events-2026-11-05 li:visible').count(),4);
  assert.ok(await n.locator('#from').isVisible());
 }await nojs.close();
 // Screenshot evidence uses the actual container scroll position, never a restyled capture.
 await page.setViewportSize({width:1438,height:638});await visit('rooms-admin-normal');await page.screenshot({path:path.join(root,'correction-initial-admin-1438x638.png')});
 await page.locator('.rb-availability__finder').scrollIntoViewIfNeeded();await page.screenshot({path:path.join(root,'correction-search-rooms-1438x638.png')});
 await visit('rooms-admin-long-events');await page.locator('a[href="#calendar-and-agenda"]').click();await settle();await page.screenshot({path:path.join(root,'correction-calendar-closed-1438x638.png')});
 for(const date of ['2026-11-03','2026-11-05'])await page.locator('a[href="#events-'+date+'"]').click();
 await page.locator('.rb-shell__content').evaluate(e=>e.scrollTop+=document.querySelector('.rb-availability__secondary').getBoundingClientRect().top-e.getBoundingClientRect().top-16);await settle();await page.screenshot({path:path.join(root,'correction-calendar-multiple-open-1438x638.png')});
 await page.locator('a[href="#events-2026-11-03"]').click();await page.screenshot({path:path.join(root,'correction-expanded-detail-1438x638.png')});
 await page.setViewportSize({width:390,height:844});await visit('rooms-admin-normal');await page.screenshot({path:path.join(root,'correction-mobile-390x844.png'),fullPage:true});
 assert.deepEqual(errors,[]);const result={result:'PASS',cases,roles:['rep','lab_staff','admin'],sizes,deviceScaleFactor:2,measurements,checks:['complete original event detail parity','multiple successive date selections with long descriptions/equipment','invariant calendar/card/row heights','initial search and first room visible for all roles','keyboard focus, local selection without history mutations, native fragment Back/Forward','no-JS all roles','100/125/200% root text and reflow'],limits:['synthetic authorized data; not HTTP or service-privacy certification','Chromium; root text enlargement not toolbar zoom']};fs.writeFileSync(path.join(root,'rooms-correction-results.json'),JSON.stringify(result,null,2));console.log(JSON.stringify({result:result.result,cases,checks:result.checks}));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
