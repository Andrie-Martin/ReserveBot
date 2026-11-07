// No server/database: serve fixed before/after HTML and each snapshot's own assets.
// Usage: node shell-refinement-browser.cjs /existing/playwright-core /tmp/evidence-root
const {chromium}=require(process.argv[2]);
const fs=require('node:fs'), path=require('node:path'), assert=require('node:assert/strict');
const root=path.resolve(process.argv[3]);
const sizes=[[1438,638],[1440,900],[1366,768],[1280,720],[1024,600],[768,1024],[390,844],[320,640],[1440,480],[1280,400],[1438,631],[1438,632],[1438,633]];
(async()=>{
 const browser=await chromium.launch({headless:true});
 const measurements=[],errors=[];
 try {
  const context=await browser.newContext();
  let assetVersion="after";
  await context.route('**/*',async route=>{
   const u=new URL(route.request().url()); assert.equal(route.request().method(),'GET');
   assert.ok(['before.test','after.test','reservebot.test'].includes(u.hostname));
   const version=(u.hostname==='reservebot.test'?assetVersion:u.hostname.split('.')[0]), rel=decodeURIComponent(u.pathname).slice(1);
   const assets=/^(css|js|fonts)\//.test(rel);
   const base=assets?path.join(root,version,'public'):root;
   const file=path.resolve(base,assets?rel:version+'-'+rel);
   assert.ok(file.startsWith(base+path.sep));
   await route.fulfill({body:fs.readFileSync(file),contentType:file.endsWith('.css')?'text/css':file.endsWith('.js')?'text/javascript':file.endsWith('.woff2')?'font/woff2':'text/html'});
  });
  const page=await context.newPage(); page.on('pageerror',e=>errors.push(e.message));
  const settle=()=>page.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));
  const visit=async(version,fixture)=>{assetVersion=version;await page.goto(`http://${version}.test/${fixture}.html`);await page.evaluate(()=>document.fonts.ready);await settle();};
  const reveal=async locator=>{
   const ancestors=locator.locator('xpath=ancestor::details');
   for(let i=0;i<await ancestors.count();i++){const d=ancestors.nth(i);if(!await d.evaluate(e=>e.open))await d.locator(':scope > summary').click();}
  };
  const fixtures=['availability/home-rep-long','availability/rooms-rep-long','availability/rooms-lab_staff-normal','availability/rooms-admin-search','layout/long','layout/guest','layout/onboarding','layout/hidden','layout/unconnected'];
  for(const [width,height]of sizes)for(const fixture of fixtures)for(const text of [100,125,200]){
   await page.setViewportSize({width,height});await visit('after',fixture);
   if(text!==100){await page.evaluate(n=>document.documentElement.style.fontSize=n+'%',text);await settle();}
   assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`${fixture} ${width} text${text} overflow`);
   const sidebar=page.locator('.rb-sidebar'), desktop=await page.evaluate(()=>matchMedia('(min-width:80rem) and (min-height:39.5rem)').matches);
   if(await sidebar.count()&&desktop){
    const panel=page.locator('.rb-shell__content');
    const fixed=await page.locator('.rb-masthead,.rb-header,.rb-sidebar').evaluateAll(es=>es.map(e=>{const r=e.getBoundingClientRect();return [r.x,r.y,r.width,r.height];}));
    let middle=0;
    for(const fraction of [0,.5,1]){
     await panel.evaluate((e,f)=>e.scrollTop=(e.scrollHeight-e.clientHeight)*f,fraction);await settle();
     const m=await panel.evaluate(el=>{const r=el.getBoundingClientRect();return {top:r.top,bottom:r.bottom,height:r.height,client:el.clientHeight,scroll:el.scrollHeight,clientWidth:el.clientWidth,scrollWidth:el.scrollWidth,scrollTop:el.scrollTop,viewport:innerHeight,document:document.documentElement.scrollHeight,y:scrollY,documentTop:document.scrollingElement.scrollTop};});
     const now=await page.locator('.rb-masthead,.rb-header,.rb-sidebar').evaluateAll(es=>es.map(e=>{const r=e.getBoundingClientRect();return [r.x,r.y,r.width,r.height];}));
     assert.deepEqual(now,fixed,'header/sidebar must remain stationary');
     assert.equal(m.y,0);assert.equal(m.documentTop,0);assert.ok(m.document<=height+1,'no document scrollbar '+JSON.stringify({fixture,width,height,text,m}));
     assert.ok(m.client>=92,'usable content height');assert.ok(m.scrollWidth<=m.clientWidth,'no horizontal main-panel overflow');assert.ok(m.bottom<=height+1);
     if(fraction===.5)middle=m.scrollTop;
     if(fraction===1&&m.scroll>m.client+2)assert.ok(m.scrollTop>middle&&middle>0,'content alone scrolls');
     measurements.push({fixture,viewportWidth:width,viewportHeight:height,text,fraction,...m,stationary:fixed});
    }
    // Native focus must reveal controls in the panel, not move the document.
    const lastControl=page.locator('main a, main button:not([disabled]), main input:not([type=hidden]), main select').last();
    if(await lastControl.count()){
     await reveal(lastControl);await panel.evaluate(e=>e.scrollTop=0);await lastControl.focus();await settle();
     const control=await lastControl.boundingBox(),bounds=await panel.boundingBox();
     assert.ok(control.y>=bounds.y-1&&control.y+control.height<=bounds.y+bounds.height+1,'final focused control reachable '+JSON.stringify({fixture,width,height,text,control,bounds}));assert.equal(await page.evaluate(()=>scrollY),0);
    }
    const record=page.locator('.rb-availability__room-row, .rb-dashboard__classes tbody tr').last();
    if(await record.count()){
     // The last room may precede a taller adjacent calendar; target its actual document position.
     await reveal(record);
     const end=await record.evaluate(e=>e.getBoundingClientRect().bottom);
     await panel.evaluate((e,bottom)=>e.scrollTop+=bottom-e.getBoundingClientRect().bottom+8,end);await settle();
     const r=await record.boundingBox(),p=await panel.boundingBox();assert.ok(r.y+r.height<=p.y+p.height+1&&r.y+r.height>p.y,'last record reachable '+JSON.stringify({fixture,width,height,text,r,p}));
    }
    await sidebar.locator('a').first().focus();
    const links=await sidebar.locator('a').count();for(let i=1;i<links;i++)await page.keyboard.press('Tab');
    assert.equal(await page.locator(':focus').getAttribute('href'),await sidebar.locator('a').last().getAttribute('href'));
    const focus=await page.locator(':focus').boundingBox(),bounds=await sidebar.boundingBox();
    assert.ok(focus.y>=bounds.y-1&&focus.y+focus.height<=bounds.y+bounds.height+1,'last navigation link visible');
    assert.equal(await page.locator(':focus').evaluate(e=>getComputedStyle(e).outlineStyle),'solid');
    await sidebar.evaluate(e=>e.scrollTop=e.scrollHeight);
    const note=await sidebar.locator('.rb-workspace-note').boundingBox(),end=await sidebar.boundingBox();
    assert.ok(note.y+note.height<=end.y+end.height+1,'advisory reachable');
   } else if(await sidebar.count()) {
    if(await page.locator('.rb-shell--rep').count()){
     const last=page.locator('main a, main button:not([disabled]), main input:not([type=hidden]), main select').last();
     if(await last.count()){await reveal(last);await last.focus();await settle();const b=await last.boundingBox(),nav=await sidebar.boundingBox();assert.ok(b.y+b.height<=nav.y+1,'mobile focus clear of bottom navigation');}
    }else{await page.locator('#rb-menu-toggle').click();await sidebar.locator('a').last().focus();assert.ok(await sidebar.locator('a').last().isVisible());await page.keyboard.press('Escape');assert.equal(await page.locator(':focus').getAttribute('id'),'rb-menu-toggle');}
   }
  }
  // The active route and an unrelated hovered route must look different.
  await page.setViewportSize({width:1440,height:900});await visit('after','layout/rep');
  const active=page.locator('.rb-nav-item[aria-current=page]'),other=page.locator('.rb-nav-item').nth(1);
  await other.hover();assert.notEqual(await active.evaluate(e=>getComputedStyle(e).backgroundColor),await other.evaluate(e=>getComputedStyle(e).backgroundColor));
  assert.equal(await page.locator('.rb-nav-item__icon[aria-hidden=true][focusable=false]').count(),4);
  assert.equal(await page.locator('.rb-nav-item__tile').count(),0);
  assert.ok(!(await page.locator('header,.rb-masthead').allTextContents()).join(' ').toLowerCase().includes('concept'));
  // Before/after viewport evidence uses the correct version of CSS and JS.
  for(const [width,height]of sizes.slice(0,8))for(const fixture of ['availability/home-rep-long','availability/rooms-rep-long','layout/admin','layout/guest'])for(const version of ['before','after']){
   await page.setViewportSize({width,height});await visit(version,fixture);await page.mouse.move(width-1,height-1);
   await page.screenshot({path:path.join(root,`${version}-${fixture.replace('/','-')}-${width}x${height}.png`)});
  }
  for(const version of ['before','after'])for(const fixture of ['availability/home-rep-long','availability/rooms-rep-long'])for(const fraction of [.5,1]){
   await page.setViewportSize({width:1438,height:638});await visit(version,fixture);
   if(version==='after')await page.locator('.rb-shell__content').evaluate((e,f)=>e.scrollTop=(e.scrollHeight-e.clientHeight)*f,fraction);
   else await page.evaluate(f=>scrollTo(0,(document.documentElement.scrollHeight-innerHeight)*f),fraction);await settle();
   await page.screenshot({path:path.join(root,`${version}-${fixture.replace('/','-')}-scroll-${fraction}.png`)});
  }
  assert.deepEqual(errors,[]);
  const result={result:'PASS',sizes,cases: sizes.length*fixtures.length*3,textPercent:[100,125,200],measurements,errors,limitations:['Text enlargement is tested directly; actual browser UI zoom is not represented by device scale or text enlargement.','Chromium fixtures, not HTTP authorization or a screen reader audit.']};
  fs.writeFileSync(path.join(root,'shell-results.json'),JSON.stringify(result,null,2));console.log(JSON.stringify({result:result.result,cases:result.cases,sidebarMeasurements:measurements.length}));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
