// Chromium regression checks for frontend QA round 4, run by qa-round4-browser.sh against a disposable local app.
// node tests/Support/qa-round4-browser.cjs /path/to/playwright-core http://127.0.0.1:8011 /output/dir
const { chromium } = require(process.argv[2]);
const assert = require('node:assert/strict');
const path = require('node:path');
const base = process.argv[3], out = path.resolve(process.argv[4]);
const results = [];
const check = async (name, fn) => { try { await fn(); results.push(['PASS', name]); } catch (e) { results.push(['FAIL', name, e.message.split('\n').slice(0, 4).join(' | ')]); } };

(async () => {
  const browser = await chromium.launch({ headless: true });
  const login = async (email, opts = {}) => {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ...opts });
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.goto(base + '/login');
    await page.fill('input[name=email]', email);
    await page.fill('input[name=password]', 'Starting123');
    try { await Promise.all([page.waitForURL(u => !u.pathname.startsWith('/login'), { timeout: 15000 }), page.click('button[type=submit]')]); }
    catch (e) { throw new Error(`login as ${email} failed: ` + (await page.locator('main').innerText()).replace(/\s+/g, ' ').slice(0, 300)); }
    page.errors = errors;
    return page;
  };
  const noOverflow = page => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
  const cell = (page, date) => page.locator('td.rb-availability__day', { has: page.locator(`.sr-only:text-matches("^${date}")`) });
  const selectedCount = page => page.locator('td.rb-availability__selected').count();
  const heading = page => page.locator('#events-selected-title').innerText();

  // ===== A: calendar, rep, with and without JavaScript =====
  for (const js of [true, false]) {
    const rep = await login('rep@qa.test', { javaScriptEnabled: js });
    const tag = js ? '' : ' (no JavaScript)';
    await check('1 month arrows are not drawn, Today stays' + tag, async () => {
      await rep.goto(base + '/rooms?date=2026-11-16');
      assert.equal(await rep.locator('.rb-availability__cal-arrow').count(), 0);
      assert.equal(await rep.locator('.rb-availability__cal-nav [aria-label="Go to today"]').count(), 1);
    });
    await check('9-11 Nov 16 then 17: one selection, no residue' + tag, async () => {
      await rep.goto(base + '/rooms?date=2026-11-16');
      assert.equal(await selectedCount(rep), 1);
      assert.match(await heading(rep), /Monday, November 16/);
      const c17 = cell(rep, 'Tuesday, November 17');
      // click the LOWER region (the event count) of Nov 17
      await c17.scrollIntoViewIfNeeded(); const box = await c17.boundingBox();
      await Promise.all([rep.waitForURL(/date=2026-11-17/), rep.mouse.click(box.x + box.width / 2, box.y + box.height - 6)]);
      assert.equal(await selectedCount(rep), 1);
      assert.equal(await rep.locator('td.rb-availability__selected .sr-only').first().innerText().then(t => t.includes('November 17')), true);
      assert.equal(await cell(rep, 'Monday, November 16').evaluate(e => e.classList.contains('rb-availability__selected') || e.querySelector('[aria-current]') !== null), false, 'Nov 16 fully cleared');
      assert.match(await heading(rep), /Tuesday, November 17/);
      assert.equal(await rep.locator('.rb-availability__event-card').count(), 3);
      // and back to 16 via the UPPER region (the day number)
      await cell(rep, 'Monday, November 16').scrollIntoViewIfNeeded(); const b16 = await cell(rep, 'Monday, November 16').boundingBox();
      await Promise.all([rep.waitForURL(/date=2026-11-16/), rep.mouse.click(b16.x + b16.width / 2, b16.y + 8)]);
      assert.equal(await selectedCount(rep), 1);
      assert.match(await heading(rep), /Monday, November 16/);
      assert.equal(await rep.locator('.rb-availability__event-card').count(), 2);
    });
    await check('9-11 refresh, Back/Forward and direct navigation keep one selection' + tag, async () => {
      await rep.goto(base + '/rooms?date=2026-11-16');
      await cell(rep, 'Tuesday, November 17').scrollIntoViewIfNeeded(); const b = await cell(rep, 'Tuesday, November 17').boundingBox();
      await Promise.all([rep.waitForURL(/date=2026-11-17/), rep.mouse.click(b.x + 10, b.y + 10)]);
      await rep.reload(); assert.match(await heading(rep), /November 17/); assert.equal(await selectedCount(rep), 1);
      await rep.goBack(); assert.match(await heading(rep), /November 16/); assert.equal(await selectedCount(rep), 1);
      assert.equal(await cell(rep, 'Tuesday, November 17').evaluate(e => e.classList.contains('rb-availability__selected')), false);
      await rep.goForward(); assert.match(await heading(rep), /November 17/); assert.equal(await selectedCount(rep), 1);
      await rep.goto(base + '/rooms?date=2026-11-17#day'); assert.equal(await selectedCount(rep), 1); assert.match(await heading(rep), /November 17/);
    });
    await check('9-10 whole-cell hover (top and bottom regions identical)' + tag, async () => {
      await rep.goto(base + '/rooms?date=2026-11-16');
      const c = cell(rep, 'Tuesday, November 17'); await c.scrollIntoViewIfNeeded(); const b = await c.boundingBox();
      const paint = () => c.evaluate(e => ({ td: getComputedStyle(e).backgroundColor, link: getComputedStyle(e.querySelector('a')).backgroundColor, linkH: e.querySelector('a').getBoundingClientRect().height, tdH: e.getBoundingClientRect().height }));
      await rep.mouse.move(b.x + b.width / 2, b.y + 6); const top = await paint();
      await rep.mouse.move(b.x + b.width / 2, b.y + b.height - 6); const bottom = await paint();
      assert.deepEqual(top, bottom);
      assert.ok(Math.abs(top.linkH - top.tdH) < 3.5, 'the link fills the whole cell ' + JSON.stringify(top));
      await rep.mouse.move(2, 2); const idle = await paint();
      assert.notEqual(idle.td, top.td, 'hover changes the whole cell background');
    });
    await check('9-10 selected cell: full ring and tint, no bottom bar' + tag, async () => {
      await rep.goto(base + '/rooms?date=2026-11-17');
      const s = await rep.locator('td.rb-availability__selected').evaluate(e => { const cs = getComputedStyle(e); return { shadow: cs.boxShadow, bg: cs.backgroundColor }; });
      assert.match(s.shadow, /inset/); assert.equal((s.shadow.match(/inset/g) || []).length, 1, 'one ring only: ' + s.shadow);
      assert.notEqual(s.bg, 'rgba(0, 0, 0, 0)');
    });
    await check('11 structure: one link per pickable cell, no nested interactive elements, no event-count links' + tag, async () => {
      await rep.goto(base + '/rooms?date=2026-11-16');
      assert.equal(await rep.locator('a.rb-availability__event-count').count(), 0);
      assert.equal(await rep.locator('td.rb-availability__day a a, td.rb-availability__day a button, td.rb-availability__day button').count(), 0);
      const links = await rep.locator('td.rb-availability__day').evaluateAll(tds => tds.map(t => t.querySelectorAll('a').length));
      assert.ok(links.every(n => n <= 1), 'at most one link per cell');
    });
    if (js) {
      await check('11 keyboard: focus ring visible and Enter selects' + tag, async () => {
        await rep.goto(base + '/rooms?date=2026-11-16');
        await cell(rep, 'Thursday, November 19').locator('a').focus();
        const ring = await rep.evaluate(() => { const s = getComputedStyle(document.activeElement); return s.outlineStyle !== 'none' && parseFloat(s.outlineWidth) > 0; });
        assert.ok(ring, 'visible focus');
        await Promise.all([rep.waitForURL(/date=2026-11-19/), rep.keyboard.press('Enter')]);
        assert.equal(await selectedCount(rep), 1); assert.match(await heading(rep), /November 19/);
        assert.match(await rep.locator('.rb-availability__calendar-details').innerText(), /No events on this day/);
      });
    }
    await rep.context().close();
  }

  // ===== A: widths / zoom =====
  const rep = await login('rep@qa.test');
  for (const w of [320, 390, 640, 768, 1024, 1422, 1920]) {
    await check(`calendar fits and selects at ${w}px`, async () => {
      await rep.setViewportSize({ width: w, height: 900 });
      await rep.goto(base + '/rooms?date=2026-11-17');
      assert.ok(await noOverflow(rep), 'no page-level horizontal overflow');
      assert.equal(await selectedCount(rep), 1);
      if (w === 1280 || w === 390) await rep.screenshot({ path: `${out}/calendar-${w}.png`, fullPage: true });
    });
  }
  await rep.setViewportSize({ width: 390, height: 844 });
  await check('calendar mobile screenshot', async () => { await rep.goto(base + '/rooms?date=2026-11-16'); await rep.screenshot({ path: `${out}/calendar-mobile.png`, fullPage: true }); });
  await rep.context().close();
  const rm = await login('rep@qa.test', { reducedMotion: 'reduce' });
  await check('calendar with reduced motion', async () => { await rm.goto(base + '/rooms?date=2026-11-16'); assert.equal(await selectedCount(rm), 1); });
  await rm.context().close();

  // ===== staff / admin pages =====
  const staff = await login('lab_staff@qa.test');
  await check('2/4 Today: Classes is a plain section, 15 rows, numbered pages', async () => {
    await staff.goto(base + '/today');
    assert.equal(await staff.locator('details.class-list, details:has(> summary:has-text("Classes"))').count(), 0);
    assert.ok(await staff.locator('h2:has-text("Classes")').isVisible());
    assert.equal(await staff.locator('table.class-table tbody tr').count(), 15);
    assert.ok(await staff.locator('.rb-pager__info:has-text("of 20 classes")').isVisible());
    assert.ok(await staff.locator('.rb-pager a.rb-pager__num:has-text("2")').isVisible());
    assert.ok(await staff.locator('th:has-text("Face-to-face?")').isVisible());
    await staff.screenshot({ path: `${out}/today-1280.png`, fullPage: true });
    await Promise.all([staff.waitForURL(/classes_page=2/), staff.locator('.rb-pager a.rb-pager__num:has-text("2")').click()]);
    assert.equal(await staff.locator('table.class-table tbody tr').count(), 5);
    assert.ok(await staff.locator('.rb-pager__num--current:has-text("2")').isVisible());
    await staff.setViewportSize({ width: 390, height: 844 }); await staff.reload(); assert.ok(await noOverflow(staff));
    await staff.screenshot({ path: `${out}/today-390.png`, fullPage: true });
    await staff.setViewportSize({ width: 1280, height: 900 });
  });
  await check('3 Requests: visible label is "Reject" and still opens the reason form', async () => {
    await staff.goto(base + '/requests');
    const link = staff.locator('a.btn-reject[data-rb-reveal]').first();
    assert.equal((await link.evaluate(e => [...e.childNodes].filter(n => n.nodeType === 3).map(n => n.textContent).join(''))).trim(), 'Reject');
    await link.click();
    assert.ok(await staff.locator('form.req-reject textarea[name=reason]').first().isVisible());
    assert.equal(await staff.locator('form.req-reject textarea[name=reason]').first().getAttribute('required'), '');
  });
  await check('12 booking details opened from Requests/Today keep the sidebar entry and mascot pose', async () => {
    await staff.goto(base + '/requests');
    await Promise.all([staff.waitForURL(/\/bookings\/\d+\?from=requests/), staff.locator('a.req-link').first().click()]);
    assert.equal(await staff.locator('.rb-nav-item[aria-current=page] .rb-nav-item__label').innerText(), 'Requests');
    assert.equal(await staff.locator('.rb-sidebar [data-pose]').first().getAttribute('data-pose'), 'clipboard');
    await staff.goto(base + '/bookings/1?from=today');
    assert.equal(await staff.locator('.rb-nav-item[aria-current=page] .rb-nav-item__label').innerText(), 'Today');
    assert.equal(await staff.locator('.rb-sidebar [data-pose]').first().getAttribute('data-pose'), 'calendar');
    await staff.setViewportSize({ width: 390, height: 844 }); await staff.goto(base + '/bookings/1?from=today');
    assert.equal(await staff.locator('.rb-nav-item[aria-current=page]').count() >= 1, true);
    await staff.setViewportSize({ width: 1280, height: 900 });
  });
  await check('13 /bookings is not a page, and no link leads to it', async () => {
    const r = await staff.goto(base + '/bookings'); assert.equal(r.status(), 404);
    for (const p of ['/', '/today', '/requests', '/manage/rooms', '/manage/equipment']) {
      await staff.goto(base + p);
      assert.equal(await staff.locator('a[href$="/bookings"], a[href*="/bookings?"], a[href$="/bookings#"]').count(), 0, p);
    }
  });

  const admin = await login('admin@qa.test');
  const colX = (page, sel) => page.locator(sel).evaluateAll(els => els.map(e => Math.round(e.getBoundingClientRect().x)));
  for (const w of [1280, 1024, 640, 390]) {
    await check(`4/6 Manage rooms at ${w}px: 12 rows, pager, aligned Edit/Delete, no overflow`, async () => {
      await admin.setViewportSize({ width: w, height: 900 });
      await admin.goto(base + '/manage/rooms');
      assert.equal(await admin.locator('table.rooms-table tbody tr').count(), 12);
      assert.ok(await admin.locator('.rb-pager__info:has-text("of 28 rooms")').isVisible());
      assert.ok(await admin.locator('.rb-pager a:has-text("Next")').isVisible());
      assert.ok(await admin.locator('.rb-pager a.rb-pager__num:has-text("3")').isVisible());
      assert.ok(await noOverflow(admin));
      const edits = new Set(await colX(admin, 'td.room-edit a.row-edit'));
      const dels = new Set(await colX(admin, 'td.room-delete a.row-delete'));
      if (w >= 1024) { assert.equal(edits.size, 1, 'Edit buttons share one x: ' + [...edits]); assert.ok(dels.size <= 1, 'Delete buttons share one x'); assert.ok(!edits.has([...dels][0])); }
      const sizes = await admin.locator('a.row-edit, a.row-delete').evaluateAll(els => [...new Set(els.map(e => Math.round(e.getBoundingClientRect().width) + 'x' + Math.round(e.getBoundingClientRect().height)))]);
      assert.equal(sizes.length, 1, 'Edit and Delete are one size: ' + sizes);
      if (w === 1280 || w === 390) await admin.screenshot({ path: `${out}/rooms-${w}.png`, fullPage: true });
    });
  }
  await admin.setViewportSize({ width: 1280, height: 900 });
  await check('4 Manage rooms page 3 and out-of-range page', async () => {
    await admin.goto(base + '/manage/rooms?page=3'); assert.equal(await admin.locator('table.rooms-table tbody tr').count(), 4);
    await admin.goto(base + '/manage/rooms?page=99'); assert.equal(await admin.locator('table.rooms-table tbody tr').count(), 4);
    await admin.goto(base + '/manage/rooms'); await Promise.all([admin.waitForURL(/page=2/), admin.locator('.rb-pager a:has-text("Next")').click()]);
    assert.match(await admin.locator('table.rooms-table tbody tr td strong').first().innerText(), /QA113/);
  });
  for (const w of [1280, 640, 390]) {
    await check(`5/6 Manage equipment at ${w}px: rename controls equal, columns aligned, no overflow`, async () => {
      await admin.setViewportSize({ width: w, height: 900 });
      await admin.goto(base + '/manage/equipment');
      assert.ok(await noOverflow(admin));
      assert.equal(await admin.locator('tr[data-eq-row]').count(), 12);
      const first = admin.locator('.mcat-name-link').first();
      await first.dblclick().catch(() => {}); await first.click({ clickCount: 2 }).catch(() => {});
      if (!(await admin.locator('.mcat-rename.is-open').first().isVisible())) await first.focus(), await admin.keyboard.press('F2');
      const m = await admin.locator('.mcat-rename.is-open').first().evaluate(f => ['input[name=name]', 'button.btn-small', 'a.btn-ghost'].map(s => { const r = f.querySelector(s).getBoundingClientRect(); return { top: Math.round(r.top * 10) / 10, h: Math.round(r.height * 10) / 10 }; }));
      assert.equal(new Set(m.map(x => x.h)).size, 1, 'equal heights ' + JSON.stringify(m));
      if (w > 480) assert.equal(new Set(m.map(x => x.top)).size, 1, 'one baseline ' + JSON.stringify(m));
      await admin.screenshot({ path: `${out}/equipment-rename-${w}.png` });
      const edits = new Set(await colX(admin, 'td.eq-edit a.row-edit'));
      if (w >= 1024) assert.equal(edits.size, 1, 'one Edit column');
    });
  }
  await check('4 equipment: pages, search kept in links, category grouping, no live-only filter', async () => {
    await admin.setViewportSize({ width: 1280, height: 900 });
    await admin.goto(base + '/manage/equipment?q=ball');
    assert.equal(await admin.locator('tr[data-eq-row]').count(), 9);
    assert.ok(await admin.locator('.rb-pager__info:has-text("of 9 items")').isVisible());
    await admin.goto(base + '/manage/equipment');
    await Promise.all([admin.waitForURL(/page=2/), admin.locator('.rb-pager a:has-text("Next")').click()]);
    assert.equal(await admin.locator('tr[data-eq-row]').count(), 6);
    assert.ok(await admin.locator('.mcat-title:has-text("Sports Equipment")').isVisible());
    await admin.goto(base + '/manage/equipment?q=ball');
    await admin.fill('#eq-filter', 'ball'); assert.ok(await admin.locator('.rb-search__clear').isVisible());
    await Promise.all([admin.waitForURL(u => u.searchParams.get('q') === ''), admin.locator('.rb-search__clear').click()]);
  });
  await check('8 Accounts: identical column grid across the three groups', async () => {
    for (const w of [1280, 1024]) {
      await admin.setViewportSize({ width: w, height: 900 }); await admin.goto(base + '/accounts');
      assert.ok(await noOverflow(admin));
      const cols = await admin.locator('table.acct-table').evaluateAll(ts => ts.map(t => [...t.querySelectorAll('thead th')].map(th => Math.round(th.getBoundingClientRect().x) + ':' + Math.round(th.getBoundingClientRect().width))));
      assert.equal(cols.length, 3);
      assert.deepEqual(cols[1], cols[0]); assert.deepEqual(cols[2], cols[0]);
    }
    await admin.setViewportSize({ width: 1280, height: 900 }); await admin.screenshot({ path: `${out}/accounts-1280.png`, fullPage: true });
    await admin.setViewportSize({ width: 390, height: 844 }); await admin.goto(base + '/accounts'); assert.ok(await noOverflow(admin));
    await admin.screenshot({ path: `${out}/accounts-390.png`, fullPage: true });
  });
  await check('7 mail notice names the log-only transport', async () => {
    await admin.goto(base + '/accounts');
    const t = await admin.locator('p.notice').first().innerText();
    assert.match(t, /isn’t delivering email/); assert.match(t, /log file/); assert.match(t, /Email connected/);
  });
  await browser.close();
  let fail = 0;
  for (const r of results) { if (r[0] === 'FAIL') fail++; console.log(r.join('  ')); }
  console.log(`\n${results.length - fail} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
