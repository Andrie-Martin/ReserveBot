// Isolated timetable fixtures. All requests are fulfilled locally; native submissions are intercepted, never sent.
// node tests/Support/timetable-browser.cjs /existing/playwright-core /tmp/rendered-timetable [/tmp/baseline-timetable]
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

    // 2. Contrast of every visible control label against its background (guards against invisible button text).
    await page.setViewportSize({ width: 1438, height: 900 });
    for (const name of cases) {
      await visit(name);
      const low = await page.evaluate(() => {
        const lum = c => { const m = c.match(/[\d.]+/g).map(Number); const f = v => { v /= 255; return v <= .03928 ? v / 12.92 : Math.pow((v + .055) / 1.055, 2.4); }; return .2126 * f(m[0]) + .7152 * f(m[1]) + .0722 * f(m[2]); };
        const bg = e => { while (e) { const c = getComputedStyle(e).backgroundColor, m = c.match(/[\d.]+/g); if (m && (m.length < 4 || +m[3] > 0)) return c; e = e.parentElement; } return 'rgb(255,255,255)'; };
        return [...document.querySelectorAll('main a, main button, main summary')].filter(e => e.getBoundingClientRect().width > 0 && e.textContent.trim() && !e.closest('.tt-item')).map(e => { const a = lum(getComputedStyle(e).color), b = lum(bg(e)); return [(Math.max(a, b) + .05) / (Math.min(a, b) + .05), e.className + ' ' + e.textContent.trim().slice(0, 30)]; }).filter(x => x[0] < 3).map(x => x[1]);
      });
      assert.deepEqual(low, [], name + ' control text contrast');
    }
    summary.push('control text contrast >= 3:1 on every state');

    // 3. CSS-only row type switch (style.css) and native submissions (intercepted): methods, targets, field names.
    await visit('tt-row-admin-class');
    const shown = sel => page.locator(sel).first().isVisible();
    await page.locator('input[name=type][value=Blocked]').check();
    assert.equal(await shown('.tt-class-only'), false, 'Blocked hides the class fields');
    await page.locator('input[name=type][value=Class]').check();
    assert.equal(await shown('.tt-class-only'), true, 'A class shows the class fields');
    let confirmations = 0;
    page.on('dialog', d => { confirmations++; d.accept(); });
    const post = async click => {
      const n = submissions.length;
      await Promise.all([page.waitForRequest(r => r.method() === 'POST'), click()]);
      await page.waitForLoadState();
      const s = submissions[n];
      return { path: s.path, body: decodeURIComponent(s.body.replace(/\+/g, ' ')) };
    };
    const has = (r, keys, what) => { for (const k of keys) assert.ok(r.body.includes(k), what + ' carries ' + k + ' in ' + r.body.slice(0, 200)); };
    await visit('tt-row-admin-class');
    let r = await post(() => page.locator('form.tt-edit button[type=submit]').click());
    assert.equal(r.path, '/manage/timetable/draft/rows/1'); has(r, ['_method=PUT', 'day=Mon', 'start=07:30', 'end=09:00', 'room=CEA300', 'type=Class', 'code=CMPE 402'], 'row update');
    await visit('tt-row-admin-class');
    r = await post(() => page.locator('form.tt-delete-form button[type=submit]').click());
    assert.equal(r.path, '/manage/timetable/draft/rows/1'); has(r, ['_method=DELETE', 'back='], 'row delete');
    await visit('tt-draft-admin-day');
    await page.locator('.tt-add > summary').click();
    await page.locator('#add-room').fill('CEA300'); await page.locator('#add-start').fill('15:00'); await page.locator('#add-end').fill('16:00'); await page.locator('#add-code').fill('CMPE 777');
    r = await post(() => page.locator('.tt-add button[type=submit]').click());
    assert.equal(r.path, '/manage/timetable/draft/rows'); has(r, ['day=Mon', 'start=15:00', 'end=16:00', 'room=CEA300', 'code=CMPE 777'], 'row add');
    await visit('tt-draft-admin-day');
    r = await post(() => page.locator('.tt-row-actions button.tt-delete').first().click());
    assert.match(r.path, /^\/manage\/timetable\/draft\/rows\/\d+$/); has(r, ['_method=DELETE', 'back='], 'list delete');
    await visit('tt-draft-admin-undo');
    r = await post(() => page.locator('.tt-undo button').click());
    assert.equal(r.path, '/manage/timetable/draft/rows/undo'); has(r, ['row=encrypted-undo-token', 'back='], 'undo');
    await visit('tt-overview-admin-inuse');
    r = await post(() => page.locator('form[action$="/manage/timetable/draft"] button').click());
    assert.equal(r.path, '/manage/timetable/draft'); has(r, ['_token='], 'start');
    await visit('tt-overview-admin-inuse');
    await page.locator('#tt-file').setInputFiles({ name: 'timetable.csv', mimeType: 'text/csv', buffer: Buffer.from('Day,Start,End\nMon,7:30,9:00\n') });
    r = await post(() => page.locator('.tt-upload button[type=submit]').click());
    assert.equal(r.path, '/manage/timetable/upload'); has(r, ['name="file"', 'filename="timetable.csv"'], 'upload');
    await visit('tt-overview-admin-inuse');
    r = await post(() => page.locator('.tt-backups form button').first().click());
    assert.match(r.path, /^\/manage\/timetable\/backups\/\d+\/restore$/);
    await visit('tt-overview-admin-draft');
    await page.locator('.tt-discard > summary').click();
    r = await post(() => page.locator('.tt-discard button[type=submit]').click());
    assert.equal(r.path, '/manage/timetable/draft'); has(r, ['_method=DELETE'], 'discard');
    await visit('tt-overview-admin-draft');
    await page.locator('.tt-restore > summary').first().click();
    r = await post(() => page.locator('.tt-restore button[type=submit]').first().click());
    assert.match(r.path, /^\/manage\/timetable\/backups\/\d+\/restore$/);
    await visit('tt-check-admin-ok');
    r = await post(() => page.locator('button.btn-replace').click());
    assert.equal(r.path, '/manage/timetable/replace'); has(r, ['version=3', 'term_name=2nd Semester AY 2026-2027', 'term_start=2027-01-11', 'term_end=2027-05-22'], 'replace');
    await visit('tt-check-admin-ok');
    r = await post(() => page.locator('button[formaction$="/manage/timetable/semester"]').click());
    assert.equal(r.path, '/manage/timetable/semester'); has(r, ['version=3', 'term_name='], 'semester formaction');
    await visit('tt-check-admin-problems');
    assert.equal(await page.locator('button.btn-replace').isDisabled(), true, 'Replace is disabled while rows have problems');
    summary.push('row type switch; intercepted upload, start, discard, restore, add/update/delete row, undo, semester (formaction) and replace carry exact targets and field names');

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
