// Isolated booking fixtures. All requests are fulfilled locally; native submissions are intercepted, never sent.
// node tests/Support/booking-browser.cjs /existing/playwright-core /tmp/rendered-booking [/tmp/baseline-booking]
const { chromium } = require(process.argv[2]);
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const current = path.resolve(process.argv[3]);
const baseline = process.argv[4] ? path.resolve(process.argv[4]) : null;
const assets = path.resolve(__dirname, '../../public');
const sizes = [[1438, 638], [1438, 900], [390, 844], [320, 640]];

(async () => {
  const browser = await chromium.launch({ headless: true });
  const submissions = [], errors = [], summary = [];
  try {
    const context = await browser.newContext();
    await context.route('http://reservebot.test/**', route => {
      const req = route.request(), url = new URL(req.url());
      let rel = decodeURIComponent(url.pathname).replace(/^\/+/, '');
      if (req.method() !== 'GET' || (!/^(css|js|fonts)\//.test(rel) && !rel.endsWith('.html'))) {
        submissions.push({ method: req.method(), path: url.pathname, query: [...url.searchParams.keys()], body: req.postData() || '' });
        return route.fulfill({ contentType: 'text/html', body: '<!doctype html><title>intercepted</title>' });
      }
      const before = rel.startsWith('before/');
      if (before) rel = rel.slice(7);
      const base = /^(css|js|fonts)\//.test(rel) ? assets : before ? baseline : current;
      return route.fulfill({
        contentType: rel.endsWith('.css') ? 'text/css' : rel.endsWith('.js') ? 'text/javascript' : rel.endsWith('.woff2') ? 'font/woff2' : 'text/html',
        body: fs.readFileSync(path.join(base, rel)),
      });
    });
    const page = await context.newPage();
    page.on('pageerror', e => errors.push(e.message));
    const visit = async (name, prefix = '') => { await page.goto(`http://reservebot.test/${prefix}${name}.html`); await page.evaluate(() => document.fonts.ready); };
    const cases = fs.readdirSync(current).filter(f => f.endsWith('.html')).map(f => f.slice(0, -5));
    const scrollRegion = '[tabindex="0"][role=region]';

    // 1. Layout, focus and text-size sweep over every state.
    let stops = 0, states = 0;
    for (const [width, height] of sizes) {
      await page.setViewportSize({ width, height });
      for (const name of cases) {
        await visit(name);
        for (const text of [100, 200]) {
          await page.evaluate(n => { document.documentElement.style.fontSize = n + '%'; }, text);
          const m = await page.evaluate(scroll => ({
            overflow: document.documentElement.scrollWidth > innerWidth,
            clipped: [...document.querySelectorAll('main button, main a, main select, main textarea, main input:not([type=hidden]), main summary')]
              .filter(e => { const r = e.getBoundingClientRect(); return r.width > 0 && (r.left < -1 || r.right > innerWidth + 1) && !e.closest(scroll); }).length,
          }), scrollRegion);
          assert.equal(m.overflow, false, `${name} ${width}x${height} text${text} page overflow`);
          assert.equal(m.clipped, 0, `${name} ${width}x${height} text${text} clipped controls`);
          states++;
        }
        await page.evaluate(() => { document.documentElement.style.fontSize = ''; document.activeElement?.blur(); scrollTo(0, 0); });
        const total = await page.evaluate(() => document.querySelectorAll('a[href], button, input:not([type=hidden]), select, textarea, summary').length);
        for (let i = 0; i < Math.min(total + 2, 90); i++) {
          await page.keyboard.press('Tab');
          const s = await page.evaluate(() => {
            const e = document.activeElement; if (!e || e === document.body) return null;
            const cs = getComputedStyle(e), r = e.getBoundingClientRect();
            const hostedByLabel = e.matches('input[type=radio]') && e.closest('label');
            const ring = (cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0) || cs.boxShadow !== 'none' || (hostedByLabel && getComputedStyle(hostedByLabel).outlineStyle !== 'none');
            return { ring, inView: r.width === 0 || (r.bottom > 0 && r.top < innerHeight && r.right > 0 && r.left < innerWidth), name: e.tagName + (e.name ? '[' + e.name + ']' : '') };
          });
          if (!s) continue;
          stops++;
          assert.ok(s.ring, `${name} ${width}x${height} focus indicator on ${s.name}`);
          assert.ok(s.inView, `${name} ${width}x${height} focused control in view ${s.name}`);
        }
      }
    }
    summary.push(`${states} layout states at 100%/200% text, ${stops} keyboard stops with visible focus`);

    // 2. The CSS-only show/hide (kept in style.css) still works with the redesigned markup.
    await page.setViewportSize({ width: 1438, height: 900 });
    await visit('book-step2-other');
    const shown = sel => page.locator(sel).first().isVisible();
    await page.locator('input[name=need][value=equipment]').check();
    assert.equal(await shown('.part-room'), false, 'Equipment only hides the room section');
    assert.equal(await shown('.part-equipment'), true, 'Equipment only shows equipment');
    await page.locator('input[name=need][value=room]').check();
    assert.equal(await shown('.part-room'), true, 'Room only shows the room section');
    assert.equal(await shown('.part-equipment'), false, 'Room only hides equipment');
    await page.locator('input[name=need][value=both]').check();
    assert.equal(await shown('.part-room') && await shown('.part-equipment'), true, 'Room and equipment shows both');
    await visit('book-step1-class');
    await page.locator('input[name=for][value=other]').check();
    assert.equal(await shown('.when-class'), false, 'Something else hides the class picker');
    assert.equal(await shown('.when-other'), true, 'Something else shows day and time');
    await page.locator('input[name=for][value=class]').check();
    assert.equal(await shown('.when-other'), false, 'One of my classes hides day and time');
    summary.push('need/for show-hide behaviour');

    // 3. Native submissions (intercepted): exact methods, targets and field names.
    await visit('book-step1-other');
    await page.locator('#date').selectOption({ index: 1 });
    await page.locator('#from').selectOption('09:30');
    await page.locator('#to').selectOption('11:00');
    await Promise.all([page.waitForURL(/\/book\?/), page.locator('button.btn-next').click()]);
    const step1 = submissions.at(-1) || { query: [] };
    const url1 = new URL(page.url());
    assert.equal(url1.pathname, '/book', 'step 1 submits to /book');
    for (const key of ['for', 'date', 'from', 'to']) assert.ok(url1.searchParams.has(key), 'step 1 GET key ' + key);
    assert.equal(url1.searchParams.get('for'), 'other');
    assert.equal(url1.searchParams.get('from'), '09:30');
    await visit('book-step2-other');
    await page.locator('input[name=room]').first().check();
    await page.locator('.eq-cat > summary').first().click();   // categories start collapsed, as a user would open one
    await page.locator('select[name^="qty["]:not(:disabled)').first().selectOption({ index: 1 });
    await page.locator('#other_items').fill('Example extra item');
    await page.locator('#purpose').fill('Example reason');
    const before2 = submissions.length;
    await Promise.all([page.waitForRequest(r => r.method() === 'POST'), page.locator('button.btn-next').click()]);
    await page.waitForLoadState();
    const post = submissions[before2];
    assert.equal(post.method, 'POST');
    assert.equal(post.path, '/book');
    const body = decodeURIComponent(post.body.replace(/\+/g, ' '));
    for (const key of ['_token=', 'need=', 'room=', 'qty[', 'other_items=Example extra item', 'purpose=Example reason', 'for=other', 'date=', 'from=13:30', 'to=15:00']) assert.ok(body.includes(key), 'step 2 POST carries ' + key);
    await visit('show-rep-pending');
    assert.equal(await shown('#cancel-reason'), false, 'cancel form starts collapsed');
    await page.locator('#cancel > summary').click();
    await page.locator('#cancel-reason').fill('Example cancel reason');
    const before3 = submissions.length;
    await Promise.all([page.waitForRequest(r => r.method() === 'POST'), page.locator('#cancel button[type=submit]').click()]);
    await page.waitForLoadState();
    assert.equal(submissions[before3].path, '/bookings/55/cancel');
    assert.ok(decodeURIComponent(submissions[before3].body.replace(/\+/g, ' ')).includes('reason=Example cancel reason'));
    summary.push('intercepted GET step 1, POST step 2 and POST cancel carry the exact field names');

    // 4. Ordered form/field/link parity with the pre-change fixtures.
    if (baseline) {
      const contracts = () => page.evaluate(() => ({
        forms: [...document.forms].map(f => ({ action: f.action, method: f.method, enctype: f.enctype,
          fields: [...f.elements].map(e => ({ tag: e.tagName, name: e.name, id: e.id, type: e.type, value: e.value, required: e.required, disabled: e.disabled, maxLength: e.maxLength,
            options: e.tagName === 'SELECT' ? [...e.options].map(o => [o.value, o.textContent.trim(), o.selected, o.disabled]) : null })),
          data: [...new FormData(f)].map(([k, v]) => [k, typeof v === 'string' ? v : v.name]) })),
        links: [...document.querySelectorAll('a')].map(a => [a.getAttribute('href'), a.textContent.replace(/\s+/g, ' ').trim()]),
        paragraphs: [...document.querySelectorAll('main p')].filter(p => !p.className.includes('kicker')).map(p => p.textContent.replace(/\s+/g, ' ').trim()),
      }));
      for (const name of cases) {
        await visit(name, 'before/'); const old = await contracts();
        await visit(name); assert.deepEqual(await contracts(), old, `${name} complete form/link/paragraph parity`);
      }
      summary.push(`ordered form, field, link and paragraph parity for ${cases.length} states vs baseline`);
    }
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({ result: 'PASS', cases: cases.length, checks: summary }, null, 2));
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
