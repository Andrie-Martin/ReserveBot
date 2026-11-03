// Usage: node foundations-browser.cjs /path/to/playwright-core /path/to/rendered-fixtures
// Uses existing browser tooling only. All HTTP requests are fulfilled from local fixtures/assets.
const { chromium } = require(process.argv[2]);
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const fixtureDir = path.resolve(process.argv[3]);
const publicDir = path.resolve(__dirname, '../../public');
(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    const failures = [];
    page.on('pageerror', error => failures.push(error.message));
    await page.route('**/*', async route => {
      const request = route.request();
      assert.equal(request.method(), 'GET', 'Examples must not submit requests');
      const url = new URL(request.url());
      assert.equal(url.hostname, 'reservebot.test', 'No external requests allowed');
      const relative = decodeURIComponent(url.pathname).replace(/^\/+/, '');
      const base = /^(css|fonts)\//.test(relative) ? publicDir : fixtureDir;
      const file = path.resolve(base, relative || 'gallery.html');
      assert.ok(file.startsWith(base + path.sep), 'Path must stay inside fixture/assets directory');
      const type = file.endsWith('.css') ? 'text/css' : file.endsWith('.woff2') ? 'font/woff2' : 'text/html';
      await route.fulfill({ status: 200, contentType: type, body: fs.readFileSync(file) });
    });
    const visit = async name => page.goto('http://reservebot.test/' + name + '.html');
    const data = selector => page.locator(selector).evaluate(form => [...new FormData(form)].map(([k,v]) => [k, typeof v === 'string' ? v : v.name]));
    const snapshot = async () => {
      await page.evaluate(async () => {
        await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        document.activeElement?.blur();
      });
      return page.evaluate(() => [...document.body.querySelectorAll('*')].map(el => {
      const style = getComputedStyle(el);
      return [el.tagName, el.className, ...Array.from(style).filter(key => !key.startsWith('--rb-')).sort().map(key => [key, style.getPropertyValue(key)])];
    }));
    };
    for (const width of [320, 375, 768, 1440]) {
      await page.setViewportSize({ width, height: 1000 });
      await visit('legacy');
      const withFoundations = await snapshot();
      await page.screenshot({ path: path.join(fixtureDir, `legacy-${width}-after.png`), fullPage: true });
      await page.locator('link[href*="/foundations/"]').evaluateAll(links => links.forEach(link => link.remove()));
      assert.deepEqual(await snapshot(), withFoundations, 'Legacy login computed styles changed at ' + width);
      await page.screenshot({ path: path.join(fixtureDir, `legacy-${width}-before.png`), fullPage: true });
      await visit('gallery');
      await page.evaluate(() => document.fonts.ready);
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Gallery has page overflow at ' + width);
      await page.screenshot({ path: path.join(fixtureDir, `gallery-${width}.png`), fullPage: true });
    }
    const loaded = await page.evaluate(async () => {
      await Promise.all([400,500,600,700].map(weight => document.fonts.load(`${weight} 16px "ReserveBot Inter"`)));
      return [...document.fonts].filter(font => font.family.includes('ReserveBot Inter')).map(font => [font.weight, font.status]);
    });
    assert.equal(loaded.length, 4);
    assert.ok(loaded.every(([, status]) => status === 'loaded'), 'Local Inter faces must load');
    assert.equal(await page.locator('.rb-card').first().evaluate(el => getComputedStyle(el).borderRadius), '16px');
    assert.equal(await page.locator('#default-button').evaluate(el => getComputedStyle(el).borderRadius), '10px');
    await page.locator('#default-button').focus();
    await page.keyboard.press('Tab');
    assert.equal(await page.locator(':focus').textContent(), 'Secondary');
    assert.equal(await page.locator(':focus').evaluate(el => getComputedStyle(el).outlineStyle), 'solid');
    assert.equal(await page.locator(':focus').evaluate(el => getComputedStyle(el).outlineWidth), '2px');
    for (let i = 0; i < 4; i++) {
      await page.keyboard.press('Tab');
      assert.notEqual(await page.locator(':focus').getAttribute('id'), 'disabled-button', 'Disabled button must be skipped');
    }
    const submitted = await page.locator('#sample-form').evaluate(form => [...new FormData(form, document.querySelector('#save-example'))].map(([k,v]) => [k, typeof v === 'string' ? v : v.name]));
    assert.ok(submitted.some(([k,v]) => k === 'intent' && v === 'save'));
    assert.ok(submitted.some(([k,v]) => k === '_method' && v === 'PUT'));
    assert.ok(!submitted.some(([k]) => k === 'disabled'));
    assert.ok(submitted.some(([k,v]) => k === 'readonly' && v === 'Retained value'));
    const cases = [
      ['conditional', '#account-fixture', 'role', [['rep', '.for-staff', '.for-rep'], ['lab_staff', '.for-rep', '.for-staff']]],
      ['conditional', '#timetable-fixture', 'type', [['Blocked', '.tt-class-only'], ['Class', null, '.tt-class-only']]],
      ['booking-first', '.step1', 'for', [['other', '.when-class', '.when-other'], ['class', '.when-other', '.when-class']]],
      ['booking-second', '.step2', 'need', [['equipment', '.part-room', '.part-equipment'], ['room', '.part-equipment', '.part-room'], ['both', null, '.part-room']]],
    ];
    let conditionalStates = 0;
    for (const [file, form, name, states] of cases) {
      for (const [value, hidden, visible] of states) {
        await visit(file);
        await page.locator(`${form} input[name="${name}"][value="${value}"]`).check();
        if (hidden) assert.ok(!await page.locator(`${form} ${hidden}`).first().isVisible(), `${name}=${value} hidden panel`);
        if (visible) assert.ok(await page.locator(`${form} ${visible}`).first().isVisible(), `${name}=${value} visible panel`);
        const before = await data(form);
        const styles = await snapshot();
        await page.locator('link[href*="/foundations/"]').evaluateAll(links => links.forEach(link => link.remove()));
        assert.deepEqual(await data(form), before, 'Foundation CSS changed submitted values');
        assert.deepEqual(await snapshot(), styles, 'Foundation CSS changed existing conditional form styles');
        conditionalStates++;
      }
    }
    // Simulate a browser ignoring :has rules, retaining the existing show-all fallback.
    for (const [file, form, , states] of cases) {
      await visit(file);
      const before = await data(form);
      await page.evaluate(() => {
        for (const sheet of document.styleSheets) {
          for (let i = sheet.cssRules.length - 1; i >= 0; i--) {
            if (sheet.cssRules[i].selectorText?.includes(':has(')) sheet.deleteRule(i);
          }
        }
      });
      for (const selector of new Set(states.flatMap(([,hidden,visible]) => [hidden,visible]).filter(Boolean))) {
        assert.ok(await page.locator(`${form} ${selector}`).first().isVisible(), 'No-:has fallback must show all panels');
      }
      assert.deepEqual(await data(form), before, 'Fallback must retain submitted values');
    }
    await visit('conditional');
    await page.locator('[name=code]').fill('ex101');
    await page.locator('[name=code]').dispatchEvent('change');
    assert.equal(await page.locator('[name=title]').inputValue(), 'Example course');
    await page.locator('[name=title]').fill('Caller title');
    await page.locator('[name=code]').dispatchEvent('change');
    assert.equal(await page.locator('[name=title]').inputValue(), 'Caller title');
    // Text enlargement and a 1280px viewport equivalent at 400% zoom (320 CSS pixels).
    await visit('gallery');
    await page.setViewportSize({ width: 320, height: 800 });
    await page.evaluate(() => document.documentElement.style.fontSize = '200%');
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), '200% text must not cause page overflow');
    const contrast = await page.evaluate(() => {
      const s = getComputedStyle(document.documentElement);
      const luminance = hex => {
        const c = hex.trim().slice(1).match(/../g).map(x => parseInt(x,16)/255).map(x => x <= .04045 ? x/12.92 : ((x+.055)/1.055)**2.4);
        return c[0]*.2126+c[1]*.7152+c[2]*.0722;
      };
      return [['text-primary','color-white'],['text-muted','color-canvas'],['color-white','color-maroon'],['success-text','success-bg'],['warning-text','warning-bg'],['danger-text','danger-bg'],['neutral-text','neutral-bg'],['border-control','color-white']].map(([a,b]) => {
        const x=luminance(s.getPropertyValue('--rb-'+a)), y=luminance(s.getPropertyValue('--rb-'+b));
        return [a,b,(Math.max(x,y)+.05)/(Math.min(x,y)+.05)];
      });
    });
    for (const [a,b,ratio] of contrast) assert.ok(ratio >= (a==='border-control'?3:4.5), `${a}/${b} contrast ${ratio}`);
    assert.deepEqual(failures, []);
    console.log(JSON.stringify({result:'PASS', widths:[320,375,768,1440], conditionalStates, fonts:loaded, contrast:contrast.map(([a,b,r])=>[a,b,+r.toFixed(2)]), checks:['legacy computed-style parity','native FormData','keyboard focus','disabled skip','datalist autofill','text enlargement','no external requests'], screenshots:fixtureDir}, null, 2));
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
