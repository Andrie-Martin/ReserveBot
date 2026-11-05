// Isolated, no-database fixtures. All requests are fulfilled locally.
// node tests/Support/timeline-browser.cjs /existing/playwright-core /tmp/rb-timeline
const {chromium}=require(process.argv[2]);
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const root=path.resolve(process.argv[3]);
const sizes=[[1438,638],[1440,900],[1366,768],[1280,720],[1024,600],[768,1024],[390,844],[320,640]];
const expected=JSON.parse(fs.readFileSync(path.join(root,'after-timeline/expected.json')));
(async()=>{
 const browser=await chromium.launch({headless:true}),measurements=[],errors=[];let combinations=0,bandChecks=0;
 const serve=version=>async route=>{
  const u=new URL(route.request().url());assert.equal(u.hostname,'reservebot.test');assert.equal(route.request().method(),'GET');
  const rel=decodeURIComponent(u.pathname).slice(1),base=path.join(root,/^(css|js|fonts)\//.test(rel)?version+'/public':version+'-timeline');
  const file=path.resolve(base,rel);assert.ok(file.startsWith(base+path.sep));
  await route.fulfill({body:fs.readFileSync(file),contentType:file.endsWith('.css')?'text/css':file.endsWith('.js')?'text/javascript':file.endsWith('.woff2')?'font/woff2':'text/html'});
 };
 try{
  const context=await browser.newContext({deviceScaleFactor:2});await context.route('**/*',serve('after'));const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
  const oldContext=await browser.newContext({deviceScaleFactor:2});await oldContext.route('**/*',serve('before'));const oldPage=await oldContext.newPage();
  const visit=async(name,p=page)=>{await p.goto('http://reservebot.test/'+name+'.html');await p.evaluate(()=>document.fonts.ready);};
  const rows=p=>p.locator('.rb-availability__room-row');
  const records=p=>rows(p).evaluateAll(es=>es.map(e=>({name:e.querySelector('.rb-availability__room-name strong').textContent.trim(),capacity:e.querySelector('.rb-availability__room-name > span').textContent.trim(),bookLabel:e.querySelector('.rb-availability__btn-book')?.textContent.replace(/\s+/g,' ').trim()??null,free:e.querySelector('.rb-availability__room-free').textContent.replace(/\s+/g,' ').trim(),book:e.querySelector('.rb-availability__btn-book')?.getAttribute('href')??null,busy:e.querySelector('details')?.textContent.replace(/\s+/g,' ').trim()??null,match:e.querySelector('.rb-availability__matchline,.rb-availability__clashline')?.textContent.replace(/\s+/g,' ').trim()??null})));
  const geometry=()=>page.evaluate(()=>{
   const box=e=>{const r=e.getBoundingClientRect();return{x:r.x,y:r.y,width:r.width,height:r.height};};
   const b=document.querySelector('.rb-availability__board'),panel=document.querySelector('.rb-shell__content');
   return {documentWidth:document.documentElement.scrollWidth,documentHeight:document.documentElement.scrollHeight,panel:{...box(panel),clientWidth:panel.clientWidth,scrollWidth:panel.scrollWidth},board:b?{...box(b),clientWidth:b.clientWidth,scrollWidth:b.scrollWidth,clientHeight:b.clientHeight,scrollHeight:b.scrollHeight}:null,axis:document.querySelector('.rb-availability__ticks')?box(document.querySelector('.rb-availability__ticks')):null,rows:[...document.querySelectorAll('.rb-availability__room-row')].map(e=>({...box(e),name:box(e.querySelector('.rb-availability__room-name')),track:e.querySelector('.rb-availability-track')?box(e.querySelector('.rb-availability-track')):null,bands:[...e.querySelectorAll('.rb-availability-track__free')].map(s=>({...box(s),from:Number(s.dataset.from),to:Number(s.dataset.to)}))}))};
  });
  const checkGeometry=async(name)=>{
   const g=await geometry(),exp=expected[name];
   assert.ok(g.documentWidth<=await page.evaluate(()=>innerWidth),'no page overflow '+name);
   assert.ok(g.panel.scrollWidth<=g.panel.clientWidth,'no main panel horizontal overflow');
   assert.ok(g.board.scrollHeight<=g.board.clientHeight+1,'no extra vertical scrolling panel');
   for(let i=0;i<exp.rows.length;i++){
    const row=g.rows[i],ranges=exp.rows[i].free;
    assert.ok(Math.abs(row.track.x-g.axis.x)<1&&Math.abs(row.track.width-g.axis.width)<1,'same axis for every room');
    assert.equal(row.bands.length,ranges.length);
    for(let j=0;j<ranges.length;j++){
     const [from,to]=ranges[j],b=row.bands[j];assert.deepEqual([b.from,b.to],[from,to]);
     assert.ok(Math.abs(b.x-row.track.x-row.track.width*(from-exp.axisStart)/(exp.axisEnd-exp.axisStart))<1,'exact band left');
     assert.ok(Math.abs(b.width-row.track.width*(to-from)/(exp.axisEnd-exp.axisStart))<1,'exact band width');bandChecks++;
    }
   }
   return g;
  };
  // All malformed fallbacks and every supplied interval/room/URL, across all roles.
  for(const [name,exp]of Object.entries(expected)){
   await visit(name);assert.equal(await rows(page).count(),12);
   assert.deepEqual(await page.locator('.rb-availability__room-name strong').allTextContents(),exp.rows.map(r=>r.name));
   for(let i=0;i<12;i++){
    const row=rows(page).nth(i);assert.ok((await row.locator('.rb-availability__room-name').textContent()).includes('40 seats'));
    const free=row.locator('.rb-availability__room-free');assert.ok(await free.isVisible());
    for(const text of exp.rows[i].text)assert.ok((await free.textContent()).includes(text),name+' retains exact text '+text);
    const book=row.locator('.rb-availability__btn-book');assert.equal(await book.count(),exp.rows[i].bookUrl?1:0);
    if(exp.rows[i].bookUrl)assert.equal(await book.getAttribute('href'),exp.rows[i].bookUrl);
   }
   if(/empty-slots|invalid-axis|invalid-bounds/.test(name)){
    assert.equal(await page.locator('.rb-availability__board,.rb-availability-track').count(),0,'text-only invalid axis');
   }else if(name.endsWith('malformed')){
    for(const i of [0,1,4,5,8])assert.equal(await rows(page).nth(i).locator('.rb-availability-track').count(),0,'no misleading malformed band');
    assert.equal(await rows(page).nth(9).locator('.rb-availability-track').count(),1,'unaffected room remains valid');
   }else{
    await visit(name,oldPage);assert.deepEqual(await records(page),await records(oldPage),'complete ordered room/interval/detail/URL parity');
    await checkGeometry(name);
    assert.equal(await page.locator('.rb-availability-track:not([aria-hidden="true"]),.rb-availability-track [tabindex],.rb-availability-track a,.rb-availability-track button').count(),0);
   }
  }
  for(const role of ['rep','lab_staff','admin'])for(const [width,height]of sizes)for(const text of [100,125,200]){
   const name='timeline-'+role+'-coherent';await page.setViewportSize({width,height});await visit(name);await page.evaluate(n=>document.documentElement.style.fontSize=n+'%',text);
   const g=await checkGeometry(name);combinations++;
   if(width===1438&&height===638){measurements.push({role,text,...g});if(text===100){assert.ok(g.rows[0].y+g.rows[0].height<=638,'complete first room visible initially');for(const row of g.rows)assert.ok(row.height>=50&&row.height<=60,'ordinary row target');}}
   const summary=rows(page).nth(1).locator('summary');await summary.focus();await page.keyboard.press('Enter');assert.ok(await summary.evaluate(e=>e.parentElement.open));
   assert.equal(await summary.evaluate(e=>getComputedStyle(e).outlineStyle),'solid');await checkGeometry(name);
   await page.keyboard.press('Space');assert.equal(await summary.evaluate(e=>e.parentElement.open),false);
   if(role==='rep'){const book=rows(page).first().locator('a');await book.focus();const r=await book.boundingBox(),boardRect=await page.locator('.rb-availability__board').boundingBox(),identity=await rows(page).first().locator('.rb-availability__room-name').boundingBox();assert.ok(r.x>=boardRect.x-1&&r.x+r.width<=boardRect.x+boardRect.width+1,'focused Book fully inside board');assert.ok(r.y>=identity.y+identity.height||r.x>=identity.x+identity.width,'identity does not cover focused Book');}
   if(width<=390){
    const board=page.locator('.rb-availability__board');assert.ok(g.board.scrollWidth>g.board.clientWidth);
    await board.focus();assert.equal(await board.evaluate(e=>getComputedStyle(e).outlineStyle),'solid');await page.keyboard.press('ArrowRight');await page.waitForTimeout(100);
    assert.ok(await board.evaluate(e=>e.scrollLeft>0),'native keyboard horizontal scroll');
    const nameRect=await rows(page).first().locator('.rb-availability__room-name').boundingBox(),boardRect=await board.boundingBox();assert.ok(Math.abs(nameRect.x-boardRect.x)<1,'sticky room identity');
    if(role==='rep'){const book=rows(page).first().locator('a');await page.keyboard.press('Tab');assert.ok(await book.evaluate(e=>e===document.activeElement),'Tab reaches first Book link');assert.equal(await book.evaluate(e=>getComputedStyle(e).outlineStyle),'solid');const r=await book.boundingBox();assert.ok(r.x>=boardRect.x-1&&r.x+r.width<=boardRect.x+boardRect.width+1,'keyboard reveals Book action '+JSON.stringify({width,text,book:r,board:boardRect,scroll:await board.evaluate(e=>e.scrollLeft)}));}
   }
  }
  // Already-started matches remain textual; bands use only computed future free ranges.
  await visit('timeline-rep-started');assert.equal(await rows(page).first().locator('.rb-availability__matchline').count(),1);
  assert.equal(await rows(page).first().locator('.rb-availability-track__free').first().getAttribute('data-from'),'570');
  assert.ok((await page.locator('#availability-search-feedback').textContent()).includes('Part of that time has already passed today.'));await checkGeometry('timeline-rep-started');
  // Native no-JS rendering/disclosures/keyboard scroll, all roles.
  const nojs=await browser.newContext({javaScriptEnabled:false,viewport:{width:390,height:844}});await nojs.route('**/*',serve('after'));const n=await nojs.newPage();
  for(const role of ['rep','lab_staff','admin']){
   await visit('timeline-'+role+'-coherent',n);assert.equal(await rows(n).count(),12);const summary=rows(n).nth(1).locator('summary');await summary.focus();await n.keyboard.press('Enter');assert.ok(await summary.evaluate(e=>e.parentElement.open));assert.ok(await rows(n).nth(1).locator('li').last().isVisible());
   const board=n.locator('.rb-availability__board');await board.focus();await n.keyboard.press('ArrowRight');await n.waitForTimeout(100);assert.ok(await board.evaluate(e=>e.scrollLeft>0));assert.ok(await n.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  }await nojs.close();
  // Matched before/after screenshots use each snapshot's actual assets and native scrolling.
  const align=async(p,selector)=>p.locator(selector).first().evaluate(e=>e.scrollIntoView({block:'start'}));
  for(const [version,p]of [['before',oldPage],['after',page]]){
   await p.setViewportSize({width:1438,height:638});await visit('timeline-rep-coherent',p);
   await p.screenshot({path:path.join(root,'timeline-'+version+'-initial-1438x638.png')});
   await align(p,version==='after'?'.rb-availability__timeline-legend':'.rb-availability__list-title');await p.screenshot({path:path.join(root,'timeline-'+version+'-first-1438x638.png')});
   await rows(p).nth(5).evaluate(e=>e.scrollIntoView({block:'start'}));await p.screenshot({path:path.join(root,'timeline-'+version+'-middle-1438x638.png')});
   await rows(p).nth(1).locator('summary').click();await rows(p).nth(1).evaluate(e=>e.scrollIntoView({block:'start'}));await p.screenshot({path:path.join(root,'timeline-'+version+'-expanded-1438x638.png')});
   await p.setViewportSize({width:390,height:844});await visit('timeline-rep-coherent',p);await align(p,version==='after'?'.rb-availability__timeline-legend':'.rb-availability__list-title');await p.screenshot({path:path.join(root,'timeline-'+version+'-mobile-390x844.png')});
  }
  assert.deepEqual(errors,[]);
  const result={result:'PASS',fixtures:Object.keys(expected).length,combinations,bandChecks,sizes,measurements,checks:['exact supplied geometry and shared axis','ordered room/interval/detail/URL parity','computed off-grid, elapsed, status, blocked and non-divisible boundary cases','separate adjacent-range preservation case','malformed axis/range text fallbacks','native disclosures and keyboard scroll/focus','no page/panel overflow, no nested vertical panel','three roles without JavaScript','125/200% root text'],limitations:['synthetic authorized data, not authenticated HTTP/privacy/security verification','Chromium only; root text enlargement is not browser-toolbar zoom']};
  fs.writeFileSync(path.join(root,'timeline-results.json'),JSON.stringify(result,null,2));console.log(JSON.stringify({...result,measurements:undefined}));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
