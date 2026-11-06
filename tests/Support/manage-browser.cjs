// Isolated management fixtures. All requests are fulfilled locally; native submissions are intercepted, never sent.
// node tests/Support/manage-browser.cjs /existing/playwright-core /tmp/rendered-manage [/tmp/baseline-manage]
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

    // 2. Native submissions (intercepted): exact methods, targets and field names.
    await page.setViewportSize({ width: 1438, height: 900 });
    page.on('dialog', d => d.accept());
    const post = async click => {
      const n = submissions.length;
      await Promise.all([page.waitForRequest(r => r.method() === 'POST'), click()]);
      await page.waitForLoadState();
      const s = submissions[n];
      return { path: s.path, body: decodeURIComponent(s.body.replace(/\+/g, ' ')) };
    };
    const has = (r, keys, what) => { for (const k of keys) assert.ok(r.body.includes(k), what + ' carries ' + k); };
    await visit('manage-rooms-admin-normal');
    await page.locator('.add-box > summary').click();
    await page.locator('#add-name').fill('CE-400'); await page.locator('#add-capacity').fill('25'); await page.locator('#add-note').fill('Example note');
    let r = await post(() => page.locator('.add-box button[type=submit]').click());
    assert.equal(r.path, '/manage/rooms'); has(r, ['_token=', 'name=CE-400', 'capacity=25', 'note=Example note'], 'add room');
    await visit('manage-room-edit-admin-normal');
    r = await post(() => page.locator('form.mform button[type=submit]').click());
    has(r, ['_method=PUT', 'name=', 'is_active=1'], 'update room');
    await visit('manage-equipment-admin-normal');
    await page.locator('.rename > summary').first().click();
    await page.locator('.rename input[name=name]').first().fill('Renamed');
    r = await post(() => page.locator('.rename button[type=submit]').first().click());
    has(r, ['_method=PUT', 'name=Renamed'], 'rename category');
    await visit('manage-equipment-admin-normal');
    await page.locator('.add-row .add-box > summary').first().click();
    await page.locator('#item-name').fill('Example item');
    r = await post(() => page.locator('.add-row .add-box').first().locator('button[type=submit]').click());
    has(r, ['category_id=', 'name=Example item', 'total_qty=1', 'max_per_request=4'], 'add item');
    await visit('manage-equipment-edit-admin-normal');
    r = await post(() => page.locator('form.mform button[type=submit]').click());
    has(r, ['_method=PUT', 'category_id=', 'total_qty=4', 'max_per_request=1', 'is_active=1'], 'update item');
    await visit('manage-rules-admin-normal');
    await page.locator('input[name="open_days[]"][value=Sun]').check();
    r = await post(() => page.locator('.rules-form button[type=submit]').click());
    has(r, ['_method=PUT', 'requires_approval=1', 'open_days[]=Sun', 'business_hours_start=07:30', 'slot_minutes=30', 'no_show_grace_minutes='], 'rules');
    await visit('manage-rules-admin-normal');
    r = await post(() => page.locator('.term-form button[type=submit]').first().click());
    has(r, ['_method=PUT', 'start_date=2026-08-10', 'end_date=2026-12-19'], 'term');
    summary.push('intercepted room, item, category, rules and term POSTs carry the exact field names');

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
