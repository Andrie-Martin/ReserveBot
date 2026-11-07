// Isolated class-check fixtures. All requests are fulfilled locally; native submissions are intercepted, never sent.
// node tests/Support/classcheck-browser.cjs /existing/playwright-core /tmp/rendered-classcheck [/tmp/baseline-classcheck]
const { chromium } = require(process.argv[2]);
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const current = path.resolve(process.argv[3]);
const baseline = process.argv[4] ? path.resolve(process.argv[4]) : null;
const assets = path.resolve(__dirname, '../../public');
const sizes = [[1438, 638], [1438, 900], [390, 844], [320, 640]];

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: '/home/james/.cache/ms-playwright/chromium_headless_shell-1248/chrome-headless-shell-linux64/chrome-headless-shell' });
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

    // 2. The answer is saved only by a button press (POST to the signed URL); intercepted, never sent.
    await page.setViewportSize({ width: 1438, height: 900 });
    for (const name of cases) {
      await visit(name);
      assert.equal(await page.locator('a[href*="answer="]').count(), 0, name + ' no answering link');
    }
    const post = async click => {
      const n = submissions.length;
      await Promise.all([page.waitForRequest(r => r.method() === 'POST'), click()]);
      await page.waitForLoadState();
      const s = submissions[n];
      return { path: s.path, body: decodeURIComponent(s.body.replace(/\+/g, ' ')) };
    };
    await visit('check-guest-waiting');
    let r = await post(() => page.locator('button[value=f2f]').click());
    assert.ok(r.body.includes('answer=f2f') && r.body.includes('_token='), 'face-to-face answer carries answer=f2f and the token');
    await visit('check-guest-waiting');
    r = await post(() => page.locator('button[value=online]').click());
    assert.ok(r.body.includes('answer=online'), 'online answer carries answer=online');
    await visit('check-guest-ended');
    assert.equal(await page.locator('button[name=answer]').count(), 0, 'an ended class offers no answer');
    await visit('check-guest-booked');
    assert.equal(await page.locator('button[name=answer]').count(), 0, 'a booked class meeting offers no answer');
    // The saved answer decides what is marked; the email button's ?answer= only shows while nothing is saved.
    await visit('check-guest-picked');
    assert.equal(await page.locator('button.picked').count(), 1, 'an unanswered class marks the email choice');
    assert.equal(await page.locator('button.current').count(), 0, 'an unanswered class has no saved answer to mark');
    assert.match(await page.locator('button.picked').innerText(), /Chosen in your email/, 'the email choice says so in text');
    for (const stale of ['check-guest-mismatch', 'check-guest-stale']) {
      await visit(stale);
      assert.equal(await page.locator('button.picked').count(), 0, stale + ' a leftover ?answer= is not marked once an answer is saved');
      assert.equal(await page.locator('button.current').count(), 1, stale + ' the saved answer is marked');
      assert.match(await page.locator('button.current').innerText(), /Your answer/, stale + ' the saved answer says so in text');
    }
    summary.push('intercepted POST answers carry answer=f2f / answer=online; closed states offer no buttons; GET never answers');

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
