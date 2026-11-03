// node tests/Support/layout-browser.cjs /existing/playwright-core /tmp/rendered-fixtures
// Isolated browser coverage: no production server, database, credentials or submissions.
const { chromium } = require(process.argv[2]);
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const evidence = path.resolve(process.argv[3]);
const baseline = process.argv[4] ? path.resolve(process.argv[4]) : null;
const publicDir = path.resolve(__dirname, '../../public');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const errors = [], checks = [], measurements = {};
  const route = async requestRoute => {
    const req = requestRoute.request(), url = new URL(req.url());
    assert.equal(url.hostname, 'reservebot.test');
    assert.equal(req.method(), 'GET', 'No real submission permitted');
    let relative = decodeURIComponent(url.pathname).replace(/^\/+/, '');
    const before = relative.startsWith('before/');
    const base = before ? baseline : /^(css|js|fonts)\//.test(relative) ? publicDir : evidence;
    if (before) relative = relative.slice(7);
    assert.ok(base, 'Provide baseline directory for before fixtures');
    const file = path.resolve(base, relative);
    assert.ok(file.startsWith(base + path.sep));
    await requestRoute.fulfill({ contentType: file.endsWith('.css') ? 'text/css' : file.endsWith('.js') ? 'text/javascript' : file.endsWith('.woff2') ? 'font/woff2' : 'text/html', body: fs.readFileSync(file) });
  };
  const page = await browser.newPage();
  page.on('pageerror', e => errors.push(e.message));
  await page.route('**/*', route);
  const resize = async size => { await page.setViewportSize(size); await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))); };
  const visit = async name => { await page.goto(`http://reservebot.test/${name}.html`); await page.evaluate(() => document.fonts.ready); };
  const overflow = async label => assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), label + ' page overflow');
  const box = selector => page.locator(selector).boundingBox();
  try {
    const widths = [320, 375, 390, 768, 1024, 1279, 1280, 1440];
    for (const width of widths) {
      await resize({ width, height: 844 });
      for (const state of ['rep','staff','admin','guest','onboarding','unconnected','note','hidden','long']) {
        await visit(state);
        await overflow(`${state}/${width}`);
        const mobile = width < 1280;
        if (['rep','staff','admin','unconnected','note','long'].includes(state)) {
          assert.equal(await page.locator('#rb-menu-toggle').isVisible(), mobile);
          assert.equal(await page.locator('#rb-account-controls').isVisible(), !mobile);
          if (mobile) {
            await page.locator('#rb-menu-toggle').focus();
            await page.keyboard.press('Enter');
            assert.equal(await page.locator('#rb-menu-toggle').getAttribute('aria-expanded'), 'true');
            assert.ok(await page.locator('#rb-account-controls').isVisible());
            await page.locator('#rb-account-controls a').focus();
            await page.keyboard.press('Escape');
            assert.equal(await page.locator(':focus').getAttribute('id'), 'rb-menu-toggle');
            assert.equal(await page.locator('#rb-menu-toggle').getAttribute('aria-expanded'), 'false');
            await overflow(`${state}/${width}/expanded`);
          }
        }
        if (state === 'rep') {
          assert.equal(await page.locator('nav a').count(), 4);
          const position = await page.locator('.rb-sidebar').evaluate(el => getComputedStyle(el).position);
          assert.equal(position, mobile ? 'fixed' : 'static');
          await page.locator('#layout-form button').focus();
          await page.keyboard.press('Tab');
          assert.equal(await page.locator(':focus').getAttribute('id'), 'layout-last');
          const last = await box('#layout-last'), nav = await box('.rb-sidebar');
          if (mobile) assert.ok(last.y + last.height <= nav.y, 'Bottom navigation obscures final focus target');
        }
        if (['staff','admin','long'].includes(state)) {
          assert.notEqual(await page.locator('.rb-sidebar').evaluate(el => getComputedStyle(el).position), 'fixed', 'No staff bottom bar');
        }
      }
    }
    checks.push('72 role/state/viewport combinations; menu keyboard and Escape; final content focus above bottom navigation');
    // Breakpoint transitions with keyboard focus, including focus already inside controls.
    await resize({width:1280,height:844}); await visit('admin');
    await page.locator('#rb-account-controls a').focus();
    await resize({width:1279,height:844});
    assert.ok(await page.locator('#rb-account-controls a').isVisible());
    assert.equal(await page.locator(':focus').getAttribute('href'), 'http://reservebot.test/account');
    await page.keyboard.press('Escape');
    await resize({width:1280,height:844});
    assert.equal(await page.locator(':focus').getAttribute('href'), 'http://reservebot.test/account');
    await page.locator('nav a').last().focus();
    await resize({width:1279,height:844});
    assert.ok(await page.locator('nav a').last().isVisible());
    assert.equal(await page.locator(':focus').textContent().then(s=>s.trim().replace(/\s+/g,' ')), 'A Accounts');
    checks.push('1279/1280 focus preserved in account/navigation; disappearing toggle focus transferred');
    // No JS fallback: all native account/logout and staff navigation remain reachable.
    const nojs = await browser.newContext({javaScriptEnabled:false,viewport:{width:390,height:844}});
    await nojs.route('**/*', route);
    const fallback = await nojs.newPage();
    for (const state of ['rep','staff','admin','onboarding','unconnected']) {
      await fallback.goto(`http://reservebot.test/${state}.html`);
      assert.ok(await fallback.locator('form[action$="/logout"] button').isVisible());
      if (state !== 'onboarding') assert.ok(await fallback.locator('#rb-account-controls a').isVisible());
      if (['staff','admin'].includes(state)) assert.ok(await fallback.locator('nav a').last().isVisible());
      if (state === 'unconnected') assert.ok(await fallback.locator('form[action$="/account/email-link"] button').isVisible());
    }
    await fallback.screenshot({path:path.join(evidence,'nojs-mobile.png'),fullPage:true});
    await nojs.close(); checks.push('no-JavaScript native account/logout/resend and staff navigation');
    // Native semantics remain in the browser, without actually sending a request.
    await visit('unconnected');
    const payloads = await page.locator('form').evaluateAll(forms => forms.map(form => ({method:form.method, action:form.action, data:[...new FormData(form, form.querySelector('button'))]})));
    assert.ok(payloads.every(p=>p.method==='post' && p.data.some(([k,v])=>k==='_token' && v==='isolated-layout-csrf')));
    assert.ok(payloads.find(p=>p.action.endsWith('#sample-only')).data.some(([k,v])=>k==='_method' && v==='PUT'));
    checks.push('native POST actions, CSRF and content method spoofing via FormData');
    // Enlarged text and narrow CSS viewport (400% zoom equivalent).
    for (const state of ['rep','admin','long']) {
      await resize({width:320,height:844}); await visit(state);
      await page.evaluate(()=>document.documentElement.style.fontSize='200%');
      await overflow(`${state} 200% text at 320 CSS pixels`);
      await page.locator('#rb-menu-toggle').click();
      await overflow(`${state} enlarged expanded menu`);
      await page.screenshot({path:path.join(evidence,`${state}-text200.png`),fullPage:true});
    }
    checks.push('200% root text at 320 CSS pixels; 400%-zoom-equivalent viewport, not browser zoom automation');
    // Short viewport / simulated safe-area padding exercise actual bottom-space measurement.
    await resize({width:390,height:300}); await visit('rep');
    await page.locator('.rb-sidebar').evaluate(el => el.style.paddingBottom='34px');
    await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
    await page.locator('#layout-form button').focus(); await page.keyboard.press('Tab');
    const lastBox = await box('#layout-last'), bottomBox = await box('.rb-sidebar');
    assert.ok(lastBox.y+lastBox.height <= bottomBox.y, 'Short viewport / simulated safe area overlap');
    checks.push('390×300 short viewport with simulated 34px safe-area inset; final focus remains above measured bar');
    if (baseline) {
      const content = () => page.locator('main').evaluate(main => [...main.querySelectorAll('*')].map(el => {
        const style = getComputedStyle(el);
        return [el.tagName, el.className, ...['fontFamily','fontSize','fontWeight','lineHeight','color','backgroundColor','borderColor','borderRadius','display'].map(k=>[k,style[k]])];
      }));
      const forms = () => page.locator('main form').evaluateAll(forms=>forms.map(form=>({method:form.method,action:form.getAttribute('action'),data:[...new FormData(form)].map(([k,v])=>[k,typeof v==='string'?v:v.name])})));
      for (const width of [390,1440]) {
        await resize({width,height:900});
        for (const state of ['legacy','conditional','booking-first','booking-second']) {
          await visit('before/'+state); const beforeStyles=await content(), beforeForms=await forms();
          await page.screenshot({path:path.join(evidence,`baseline-${state}-${width}.png`),fullPage:true});
          await visit(state);
          assert.deepEqual(await content(),beforeStyles,`${state}/${width} content styling changed`);
          assert.deepEqual(await forms(),beforeForms,`${state}/${width} native form data changed`);
          await page.screenshot({path:path.join(evidence,`current-${state}-${width}.png`),fullPage:true});
        }
      }
      checks.push('HEAD-layout before/after content fonts, colors, borders, display and native form payload parity at 390/1440; geometry intentionally differs');
    }
    // Existing content constraints and available-width expansion.
    await resize({width:1920,height:1080});
    for (const [state,max] of [['narrow',400],['booking-width',880],['account-width',820],['timetable-width',1180]]) {
      await visit(state); assert.ok((await box('#layout-content')).width <= max, state+' maximum width lost');
    }
    await visit('wide'); assert.ok((await box('main')).width > 1240, 'Wide main must use available shell column');
    checks.push('400/820/880/1180px content constraints; wide main uses shell column');
    // Shell-only reference comparison captures, excluding intentionally unmigrated content.
    for (const [state,width,height] of [['rep',1440,1015],['staff',1440,1020],['admin',1440,1030],['rep',390,844],['guest',1440,900]]) {
      await resize({width,height}); await visit(state);
      await page.locator('main > div[aria-hidden="true"]').evaluateAll(els=>els.forEach(el=>el.remove()));
      await page.screenshot({path:path.join(evidence,`${state}-${width}-shell.png`),mask:[page.locator('main')],maskColor:'#F8F5EF'});
      const header = await box('.rb-header'), masthead = await box('.rb-masthead');
      measurements[`${state}-${width}`] = {masthead,header,sidebar:state==='guest'?null:await box('.rb-sidebar')};
    }
    await resize({width:390,height:844}); await visit('rep');
    await page.locator('#rb-menu-toggle').click();
    await page.screenshot({path:path.join(evidence,'rep-mobile-menu.png')});
    await page.locator('#rb-account-controls a').focus();
    await page.keyboard.press('Tab');
    assert.equal(await page.locator(':focus').evaluate(el=>getComputedStyle(el).outlineStyle), 'solid');
    // Font/canvas of legacy content stays legacy; shell opts into Inter.
    assert.ok(!(await page.locator('main').evaluate(el=>getComputedStyle(el).fontFamily)).includes('ReserveBot Inter'));
    assert.ok((await page.locator('.rb-header').evaluate(el=>getComputedStyle(el).fontFamily)).includes('ReserveBot Inter'));
    const contrast = await page.evaluate(() => {
      const styles=getComputedStyle(document.documentElement);
      const luminance=token=>styles.getPropertyValue('--rb-'+token).trim().slice(1).match(/../g).map(x=>parseInt(x,16)/255).map(x=>x<=.04045?x/12.92:((x+.055)/1.055)**2.4).reduce((sum,x,i)=>sum+x*[.2126,.7152,.0722][i],0);
      return [['text-secondary','color-white'],['text-secondary','color-canvas'],['color-maroon','surface-active'],['color-gold','color-deep']].map(([a,b])=>{const x=luminance(a),y=luminance(b);return [a,b,(Math.max(x,y)+.05)/(Math.min(x,y)+.05)];});
    });
    for(const [a,b,ratio] of contrast) assert.ok(ratio>=4.5,`${a}/${b} contrast ${ratio}`);
    checks.push({shellTextContrast:contrast});
    assert.deepEqual(errors, []);
    const result = {result:'PASS',checks,measurements,errors,screenshots:evidence};
    fs.writeFileSync(path.join(evidence,'layout-browser-results.json'),JSON.stringify(result,null,2));
    console.log(JSON.stringify(result,null,2));
  } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
