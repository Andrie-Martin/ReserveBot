// Isolated account fixtures. All requests are fulfilled locally; native submissions are intercepted, never sent.
// node tests/Support/accounts-browser.cjs /existing/playwright-core /tmp/rendered-accounts [/tmp/baseline-accounts]
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

    // 2. Role switching (CSS-only :has() in style.css) still works with the redesigned markup.
    await page.setViewportSize({ width: 1438, height: 900 });
    const shown = sel => page.locator(sel).first().isVisible();
    await visit('accounts-index-normal');
    await page.locator('.add-box > summary').click();
    await page.locator('.add-box input[name=role][value=rep]').check();
    assert.equal(await shown('.add-box .for-rep'), true, 'Block rep shows the block field');
    assert.equal(await shown('.add-box .for-staff'), false, 'Block rep hides the staff field');
    await page.locator('.add-box input[name=role][value=lab_staff]').check();
    assert.equal(await shown('.add-box .for-rep'), false, 'Lab staff hides the block field');
    assert.equal(await shown('.add-box .for-staff'), true, 'Lab staff shows the staff field');
    summary.push('role switching show/hide behaviour');

    // 3. Native submissions (intercepted): exact methods, targets and field names; delete keeps its confirmation.
    let confirms = 0;
    page.on('dialog', d => { confirms++; d.accept(); });
    const post = async (action, click) => {
      const n = submissions.length;
      await Promise.all([page.waitForRequest(r => r.method() === 'POST'), click()]);
      await page.waitForFunction(() => document.title === 'intercepted'); await page.waitForLoadState('load'); // the stub page has committed: the next goto cannot collide with this navigation
      const s = submissions[n];
      return { path: s.path, body: decodeURIComponent(s.body.replace(/\+/g, ' ')) };
    };
    await visit('accounts-index-normal');
    await page.locator('.add-box > summary').click();
    await page.locator('.add-box input[name=name]').fill('Example Person');
    await page.locator('.add-box input[name=email]').fill('example.person@example.invalid');
    await page.locator('#add-password').fill('Example-pass1');
    let r = await post('store', () => page.locator('.add-box button[type=submit]').click());
    assert.equal(r.path, '/accounts');
    for (const k of ['_token=', 'role=', 'name=Example Person', 'email=example.person@example.invalid', 'password=Example-pass1']) assert.ok(r.body.includes(k), 'add POST carries ' + k);
    await visit('accounts-edit-rep');
    r = await post('update', () => page.locator('form.acct-form button[type=submit]').click());
    assert.equal(r.path, '/accounts/101');
    for (const k of ['_method=PUT', 'role=rep', 'name=Ana Cruz', 'is_active=1']) assert.ok(r.body.includes(k), 'update carries ' + k);
    await visit('accounts-edit-rep');
    await page.locator('#new-password').fill('Example-pass2');
    r = await post('password', () => page.locator('#new-password ~ button, .inline-form button').click());
    assert.ok(r.path.startsWith('/accounts/101'), 'password path');
    for (const k of ['_method=PUT', 'password=Example-pass2']) assert.ok(r.body.includes(k), 'password carries ' + k);
    await visit('accounts-edit-rep');
    r = await post('destroy', () => page.locator('.danger-box button[type=submit]').click());
    assert.equal(r.path, '/accounts/101');
    assert.ok(r.body.includes('_method=DELETE'), 'delete spoof');
    assert.equal(confirms, 1, 'delete asks for confirmation before sending');
    summary.push('intercepted add, update, password and delete POSTs carry the exact field names; delete confirmation kept');

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
