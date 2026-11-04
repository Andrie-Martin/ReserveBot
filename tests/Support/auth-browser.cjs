// Existing Playwright only. Requests are local fixtures/assets; submissions are intercepted.
const { chromium } = require(process.argv[2]);
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const current = path.resolve(process.argv[3]);
const before = path.resolve(process.argv[4]);
const assets = path.resolve(__dirname, '../../public');
const widths = [320, 375, 390, 768, 1024, 1280, 1440];
(async () => {
 const browser = await chromium.launch({headless:true});
 try {
  const context = await browser.newContext();
  const errors = [], submissions = [];
  await context.route('**/*', async route => {
   const req = route.request(), url = new URL(req.url());
   assert.equal(url.hostname, 'reservebot.test');
   if (req.method() !== 'GET') { submissions.push({url:url.pathname, method:req.method(), body:req.postData()}); return route.fulfill({status:200,body:'Intercepted test submission'}); }
   if (url.pathname.startsWith('/photos/')) return route.fulfill({status:200,contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80"><rect width="80" height="80" fill="#F2EADF"/></svg>'});
   const relative = decodeURIComponent(url.pathname).replace(/^\//,'');
   let base = assets, file = relative;
   if (relative.startsWith('before/')) { base=before; file=relative.slice(7); }
   else if (!/^(css|fonts|js)\//.test(relative)) base=current;
   const resolved=path.resolve(base,file);
   assert.ok(resolved.startsWith(base+path.sep));
   await route.fulfill({contentType:resolved.endsWith('.css')?'text/css':resolved.endsWith('.js')?'text/javascript':resolved.endsWith('.woff2')?'font/woff2':'text/html',body:fs.readFileSync(resolved)});
  });
  const page = await context.newPage();
  page.on('pageerror', e=>errors.push(e.message));
  const visit = async (name, baseline=false) => { await page.goto('http://reservebot.test/'+(baseline?'before/':'')+name+'.html'); await page.evaluate(()=>document.fonts.ready); };
  const contracts = () => page.evaluate(()=>({
   forms:[...document.forms].map(f=>({action:f.action,method:f.method,enctype:f.enctype,
    fields:[...f.elements].map(e=>({tag:e.tagName,name:e.name,type:e.type,value:e.value,required:e.required,disabled:e.disabled,readOnly:e.readOnly,minLength:e.minLength,maxLength:e.maxLength,accept:e.accept,autocomplete:e.autocomplete,autofocus:e.autofocus,
     options:e.tagName==='SELECT'?[...e.options].map(o=>[o.value,o.textContent.trim(),o.selected,o.disabled]):null})),
    data:[...new FormData(f)].map(([k,v])=>[k,typeof v==='string'?v:{name:v.name,size:v.size,type:v.type}])})),
   links:[...document.querySelectorAll('a')].map(a=>[a.getAttribute('href'),(()=>{ const copy=a.cloneNode(true); copy.querySelectorAll('.rb-nav-item__tile, .rb-nav-item__icon').forEach(el=>el.remove()); return copy.textContent.replace(/\s+/g,' ').trim(); })()])
  }));
  const cases=fs.readdirSync(current).filter(f=>f.endsWith('.html')).map(f=>f.slice(0,-5));
  let states=0;
  for (const name of cases) {
   await visit(name,true); const baseline=await contracts();
   const information=await page.locator('main p').allTextContents();
   await visit(name); assert.deepEqual(await contracts(),baseline,name+' complete native form/link parity');
   const mainText=(await page.locator('main').textContent()).replace(/\s+/g,' ').trim();
   for (const paragraph of information) {
    const text=paragraph.replace(/\s+/g,' ').trim();
    assert.ok(mainText.includes(text),name+' retained information: '+text);
   }
   for (const width of widths) {
    await page.setViewportSize({width,height:900});
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth), name+' overflow '+width);
    const clipped=await page.locator('main button, main input, main select').evaluateAll(nodes=>nodes.filter(el=>{const r=el.getBoundingClientRect();return r.width>0&&(r.left < -1 || r.right>innerWidth+1);} ).map(el=>el.outerHTML));
    assert.deepEqual(clipped,[],name+' clipped controls '+width); states++;
   }
  }
  for (const width of [1023,1024]) {
   await page.setViewportSize({width,height:900}); await visit('login');
   assert.equal(await page.locator('.rb-auth-login__art').isVisible(),width>=1024);
  }
  await visit('reset');
  const submittedBefore=submissions.length;
  await page.locator('main button[type=submit]').click();
  assert.equal(submissions.length,submittedBefore,'Required password blocks empty submission');
  assert.equal(await page.locator('[name=password]').evaluate(e=>e.validity.valueMissing),true);
  // Real browser validation and keyboard submission, with POST intercepted before any server.
  await visit('login');
  await page.locator('[name=email]').fill('keyboard@example.invalid');
  await page.locator('[name=password]').fill('Example123');
  await page.locator('[name=password]').press('Enter');
  await page.waitForLoadState();
  assert.equal(submissions.at(-1).url,'/login');
  assert.equal(submissions.at(-1).method,'POST');
  assert.ok(submissions.at(-1).body.includes('_token=isolated-layout-csrf'));
  await visit('reset');
  await page.locator('[name=password]').fill('Example123');
  await page.locator('[name=password_confirmation]').fill('Example123');
  await page.locator('main button[type=submit]').click();
  await page.waitForLoadState();
  assert.equal(submissions.at(-1).url,'/reset-password');
  assert.ok(submissions.at(-1).body.includes('token=sample-reset-token'));
  await visit('account-errors-lab_staff');
  const photo=page.locator('form[enctype="multipart/form-data"]');
  await page.locator('#photo').setInputFiles({name:'example.png',mimeType:'image/png',buffer:Buffer.from('test-only-file')});
  assert.deepEqual(await photo.evaluate(f=>[...new FormData(f)].map(([k,v])=>[k,typeof v==='string'?v:v.name])),[['_token','isolated-layout-csrf'],['photo','example.png']]);
  const remove=page.locator('form').filter({has:page.locator('input[value=DELETE]')});
  assert.equal(await remove.locator('input[name=photo], input[name=name]').count(),0);
  await remove.locator('button').click(); await page.waitForLoadState();
  assert.ok(submissions.at(-1).body.includes('_method=DELETE'));
  assert.ok(!submissions.at(-1).body.includes('email='));
  await visit('account-info-only');
  assert.equal(await page.locator('#email').getAttribute('aria-invalid'),'true');
  assert.equal(await page.locator('#photo').getAttribute('aria-invalid'),null);
  await visit('account-photo-only');
  assert.equal(await page.locator('#photo').getAttribute('aria-invalid'),'true');
  assert.equal(await page.locator('#email').getAttribute('aria-invalid'),null);
  for (const name of ['login','forgot','reset','welcome-rep','account-rep','account-errors-lab_staff','password-admin','link-guest-0','error-500-admin']) {
   for (const width of [390,1440]) {
    await page.setViewportSize({width,height:900});
    await visit(name,true); await page.screenshot({path:path.join(current,`${name}-${width}-before.png`),fullPage:true});
    await visit(name); await page.screenshot({path:path.join(current,`${name}-${width}-after.png`),fullPage:true});
   }
  }
  for (const name of ['login-error','reset-error','welcome-error-rep','account-long','account-errors-lab_staff','password-errors-admin','error-413-admin']) {
   await page.setViewportSize({width:320,height:700}); await visit(name);
   await page.evaluate(()=>document.documentElement.style.fontSize='200%');
   assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),name+' text enlargement overflow');
   await page.screenshot({path:path.join(current,name+'-text200.png'),fullPage:true});
  }
  await page.setViewportSize({width:390,height:300}); await visit('account-rep');
  await page.locator('main button[type=submit]').last().focus();
  await page.keyboard.press('Shift+Tab'); await page.keyboard.press('Tab');
  const target=await page.locator(':focus').boundingBox(), bar=await page.locator('.rb-sidebar').boundingBox();
  assert.ok(target.y+target.height<=bar.y+1,'Final submit above fixed rep navigation');
  await visit('login'); await page.locator('main a').focus();
  assert.equal(await page.locator(':focus').evaluate(e=>getComputedStyle(e).outlineStyle),'solid');
  // No-JS still has all native forms; CSS-only responsive composition.
  const nojs=await browser.newContext({javaScriptEnabled:false,viewport:{width:390,height:900}});
  await nojs.route('**/*',route=>{
   const url=new URL(route.request().url()), rel=url.pathname.slice(1);
   const base=/^(css|fonts|js)\//.test(rel)?assets:current;
   return route.fulfill({contentType:rel.endsWith('.css')?'text/css':rel.endsWith('.woff2')?'font/woff2':'text/html',body:fs.readFileSync(path.join(base,rel))});
  });
  const n=await nojs.newPage(); await n.goto('http://reservebot.test/account-rep.html');
  assert.ok(await n.locator('form[action$="logout"] button').isVisible());
  assert.ok(await n.locator('form[action$="email-link"] button').isVisible());
  assert.ok(await n.locator('input[name=name]').isVisible()); await nojs.close();
  assert.deepEqual(errors,[]);
  const result={result:'PASS',cases:cases.length,responsiveStates:states,widths,nativeSubmissions:submissions.length,checks:['before/after complete form/link parity and all original paragraph information','named bags','sensitive old input excluded','keyboard/native validation','separate multipart/remove forms','200% text','320 CSS px reflow equivalent','short viewport bottom clearance','no-JS'],limits:['fixture-only: no controller/session/database security proof','Chromium only','not actual browser UI zoom or screen-reader testing']};
  fs.writeFileSync(path.join(current,'auth-browser-results.json'),JSON.stringify(result,null,2)); console.log(JSON.stringify(result,null,2));
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
