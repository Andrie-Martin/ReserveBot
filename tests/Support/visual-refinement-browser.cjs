// Stage 3: snapshot-specific assets, no application HTTP or database access.
// node tests/Support/visual-refinement-browser.cjs /existing/playwright-core /tmp/rb-stage3
const { chromium } = require(process.argv[2]);
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(process.argv[3]);
const sizes = [[1438, 638], [1440, 900], [1366, 768], [1280, 720], [1024, 600], [768, 1024], [390, 844], [320, 640]];
const white = 'rgb(255, 255, 255)', maroon = 'rgb(107, 25, 50)', warm = 'rgb(233, 226, 218)';

(async () => {
  // CSS-only changes must leave every rendered capability, state and record intact.
  // Ignore only the documented asset cache-busting timestamps.
  let documents = 0;
  const normalize = html => html.replace(/\?v=\d+/g, '?v=ASSET');
  for (const suite of ['layout', 'auth', 'availability', 'timeline']) {
    for (const name of fs.readdirSync(path.join(root, 'before-' + suite)).filter(n => n.endsWith('.html'))) {
      assert.equal(normalize(fs.readFileSync(path.join(root, 'after-' + suite, name), 'utf8')),
        normalize(fs.readFileSync(path.join(root, 'before-' + suite, name), 'utf8')), suite + '/' + name + ' complete HTML parity');
      documents++;
    }
  }
  const browser = await chromium.launch({ headless: true });
  const errors = [], measurements = [], screenshots = [];
  let timelineComparisons = 0;
  try {
    const pages = {};
    for (const version of ['before', 'after']) {
      const context = await browser.newContext({ deviceScaleFactor: 2 });
      await context.route('**/*', async route => {
        const request = route.request(), url = new URL(request.url());
        assert.equal(url.hostname, 'reservebot.test');
        assert.equal(request.method(), 'GET');
        const relative = decodeURIComponent(url.pathname).slice(1);
        const asset = /^(css|js|fonts)\//.test(relative);
        const base = path.join(root, asset ? version + '/public' : version + '-' + relative.split('/')[0]);
        const file = path.resolve(base, asset ? relative : relative.split('/').slice(1).join('/'));
        assert.ok(file.startsWith(base + path.sep));
        await route.fulfill({ body: fs.readFileSync(file), contentType: file.endsWith('.css') ? 'text/css' : file.endsWith('.js') ? 'text/javascript' : file.endsWith('.woff2') ? 'font/woff2' : 'text/html' });
      });
      pages[version] = await context.newPage();
      pages[version].on('pageerror', e => errors.push(e.message));
    }
    const visit = async (page, fixture, size = [1438, 638], text = 100) => {
      await page.setViewportSize({ width: size[0], height: size[1] });
      await page.goto('http://reservebot.test/' + fixture + '.html');
      await page.evaluate(async n => {
        document.documentElement.style.fontSize = n + '%';
        await document.fonts.ready;
        await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        scrollTo(0, 0);
        document.querySelector('.rb-shell__content').scrollTop = 0;
      }, text);
    };
    const style = (page, selector) => page.locator(selector).first().evaluate(e => {
      const s = getComputedStyle(e), r = e.getBoundingClientRect();
      return { color: s.color, background: s.backgroundColor, border: s.borderColor, radius: s.borderRadius,
        font: s.fontFamily, fontSize: s.fontSize, outline: s.outlineStyle, borderStyle: s.borderStyle,
        height: r.height, width: r.width, shadow: s.boxShadow };
    });
    const geometry = page => page.evaluate(() => {
      const selectors = ['.rb-availability__board', '.rb-availability__axis', '.rb-availability__ticks',
        '.rb-availability__room-row', '.rb-availability__room-name', '.rb-availability-track', '.rb-availability-track__free'];
      return selectors.map(selector => [selector, [...document.querySelectorAll(selector)].map(e => {
        const r = e.getBoundingClientRect();
        return { x: r.x, y: r.y, width: r.width, height: r.height, scrollWidth: e.scrollWidth, scrollHeight: e.scrollHeight };
      })]);
    });
    // Untouched workflow content uses each version's own CSS, not shared current
    // assets. Compare every computed property except additive rb token definitions.
    let legacyComparisons = 0;
    for (const size of [[1438, 638], [390, 844]]) for (const fixture of ['layout/booking-first', 'layout/booking-second', 'layout/conditional']) {
      const styles = {};
      for (const version of ['before', 'after']) {
        const p = pages[version]; await visit(p, fixture, size);
        await p.evaluate(() => document.activeElement?.blur());
        styles[version] = await p.locator('main').evaluate(main => [...main.querySelectorAll('*')].map(e => {
          const s = getComputedStyle(e);
          return [e.tagName, e.className, ...Array.from(s).filter(k => !k.startsWith('--rb-')).sort().map(k => [k, s.getPropertyValue(k)])];
        }));
      }
      assert.deepEqual(styles.after, styles.before, fixture + ' unchanged computed styles');
      legacyComparisons++;
    }
    // Exact before/after timeline geometry, including expanded details, every role,
    // required viewport and root-text size. Separate from the interval math suite.
    for (const role of ['rep', 'lab_staff', 'admin']) for (const size of sizes) for (const text of [100, 125, 200]) {
      const fixture = 'timeline/timeline-' + role + '-coherent';
      for (const page of Object.values(pages)) await visit(page, fixture, size, text);
      assert.deepEqual(await geometry(pages.after), await geometry(pages.before), `${role} ${size} ${text}% collapsed geometry`);
      assert.equal((await style(pages.after, '.rb-availability__board')).background, white);
      assert.equal((await style(pages.after, '.rb-availability__room-name')).background, white);
      for (const page of Object.values(pages)) {
        await page.locator('.rb-availability__room-busy > summary').first().focus();
        await page.keyboard.press('Enter');
      }
      assert.deepEqual(await geometry(pages.after), await geometry(pages.before), `${role} ${size} ${text}% expanded geometry`);
      timelineComparisons += 2;
    }
    const page = pages.after;
    await visit(page, 'availability/home-rep-priority');
    const buttons = page.locator('.answer-buttons button');
    for (let i = 0; i < await buttons.count(); i++) {
      const b = buttons.nth(i);
      if (!await b.isVisible()) continue;
      assert.ok((await b.boundingBox()).height >= 44, 'meeting answer target >=44px');
      assert.equal(await b.evaluate(e => getComputedStyle(e).borderRadius), '10px');
    }
    const answer = page.locator('.answer-buttons button:not(:disabled)').first();
    await answer.focus();
    assert.equal(await answer.evaluate(e => getComputedStyle(e).outlineStyle), 'solid');
    await answer.hover();
    assert.equal(await answer.evaluate(e => getComputedStyle(e).backgroundColor), 'rgb(248, 237, 240)');
    assert.equal((await style(page, '.answer-buttons button.on:disabled')).borderStyle, 'dashed');
    for (const selector of ['.st-wait', '.st-freed']) {
      assert.equal((await style(page, selector)).background, 'rgb(243, 244, 246)');
      assert.equal((await style(page, selector)).color, 'rgb(75, 85, 99)');
    }
    assert.equal((await style(page, '.rb-dashboard .rb-card')).border, warm);
    assert.equal((await style(page, '.rb-dashboard .rb-card')).shadow, 'none');
    await page.locator('.rb-dashboard__other > summary').focus();
    await page.keyboard.press('Enter');
    assert.equal(await page.locator('.rb-dashboard__other > summary').evaluate(e => getComputedStyle(e).textDecorationLine), 'underline');

    await visit(page, 'auth/login');
    assert.equal((await style(page, '#email')).height, 44);
    assert.equal((await style(page, '#email')).radius, '10px');
    assert.equal((await style(page, '.rb-auth-card')).radius, '16px');
    assert.equal((await style(page, 'main button[type=submit]')).background, maroon);
    await page.locator('main button[type=submit]').hover();
    assert.equal((await style(page, 'main button[type=submit]')).background, 'rgb(72, 16, 33)');
    assert.equal((await style(page, 'main button[type=submit]')).color, white);
    await visit(page, 'availability/rooms-rep-normal');
    assert.equal((await style(page, '.rb-availability__finder')).background, white);
    const secondary = await style(page, '.rb-button--secondary');
    assert.equal(secondary.border, 'rgb(107, 114, 128)');
    assert.equal(secondary.height, 44);

    // Same fixture, viewport, scroll position and version-owned assets. Density is
    // measured before the screenshot scroll; interactions use native focus/scroll.
    const fixtures = ['auth/login', 'auth/login-error', 'availability/home-rep-normal',
      'availability/rooms-rep-normal', 'timeline/timeline-rep-coherent', 'layout/admin'];
    for (const size of [[1438, 638], [390, 844]]) for (const fixture of fixtures) {
      const pair = {};
      for (const version of ['before', 'after']) {
        const p = pages[version]; await visit(p, fixture, size);
        pair[version] = await p.evaluate(() => ({ documentHeight: document.documentElement.scrollHeight,
          panelHeight: document.querySelector('.rb-shell__content').scrollHeight,
          mainHeight: document.querySelector('main').getBoundingClientRect().height }));
        if (fixture.startsWith('timeline/')) await p.locator('.rb-availability__timeline-legend').evaluate(e => e.scrollIntoView({ block: 'start' }));
        if (fixture === 'layout/admin' && size[0] < 1280) await p.locator('.rb-menu-toggle').click();
        const name = `stage3-${fixture.replace('/', '-')}-${size.join('x')}-${version}.png`;
        await p.mouse.move(0, 0);
        await p.screenshot({ path: path.join(root, name) }); screenshots.push(name);
      }
      if (size[0] === 1438) {
        // A small allowance covers the deliberate increase from undersized answer
        // buttons to 44px. Decorative spacing must not inflate the page.
        assert.ok(pair.after.mainHeight <= pair.before.mainHeight + 48, fixture + ' laptop density');
      }
      measurements.push({ fixture, size, ...pair });
    }
    assert.deepEqual(errors, []);
    const result = { result: 'PASS', documents, legacyComparisons, timelineComparisons, sizes, measurements, screenshots,
      checks: ['complete rendered HTML parity except asset cache keys', 'exact collapsed/expanded timeline geometry',
        'untouched workflow computed styles with snapshot-specific assets',
        'white timeline and opaque sticky identity', 'meeting targets, selected/disabled/hover/focus states',
        'existing semantic status palette', 'shared surface/control borders and radii', 'bounded laptop density'],
      limitations: ['isolated Chromium fixtures, not live authenticated HTTP workflows', 'root text enlargement, not browser toolbar zoom',
        'screen reader and physical devices not tested'] };
    fs.writeFileSync(path.join(root, 'visual-refinement-results.json'), JSON.stringify(result, null, 2));
    console.log(JSON.stringify({ ...result, measurements: undefined, screenshots: undefined }));
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
