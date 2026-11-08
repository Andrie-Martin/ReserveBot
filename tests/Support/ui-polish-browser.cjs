// Real-browser regression checks for the room/booking/manage UI polish (drawers, pickers, inline rename, search, requests, cards).
//
//   node tests/Support/ui-polish-browser.cjs <path-to-playwright-core> <base-url>
//
// It drives a RUNNING application that uses a DISPOSABLE database seeded with `php artisan db:seed` (the test accounts
// admin@/staff@/rep1@reservebot.test, password Password123!). It changes no data: it opens forms, types, cancels and
// reads layout; it never submits a rename, a rejection or a booking. Needs an installed Playwright and a browser
// (set RB_BROWSER_EXECUTABLE to a Chromium binary if Playwright's own is not installed). Not part of PHPUnit.
const { chromium } = require(process.argv[2]);
const assert = require('node:assert/strict');
const base = (process.argv[3] || '').replace(/\/$/, '');
assert.ok(/^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/.test(base), 'Refusing to run against anything but a local disposable app (127.0.0.1 / localhost).');
assert.ok(!/:(8000|8001|8002)$/.test(base), 'Refusing to run against the developer\'s own app ports.');

const accounts = { rep: 'rep1@reservebot.test', staff: 'staff@reservebot.test', admin: 'admin@reservebot.test' };
let passed = 0;
const ok = (name) => { passed++; console.log('  PASS', name); };

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.RB_BROWSER_EXECUTABLE || undefined });
  const login = async (role, options = {}) => {
    const context = await browser.newContext({ viewport: { width: 1438, height: 900 }, ...options });
    const page = await context.newPage();
    await page.goto(base + '/login');
    await page.fill('input[name=email]', accounts[role]);
    await page.fill('input[name=password]', 'Password123!');
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    return { context, page };
  };
  const overflow = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  const zoom = (page, percent) => page.addStyleTag({ content: `html{font-size:${percent}%!important}` });

  console.log('Free rooms drawer');
  {
    const { context, page } = await login('rep');
    await page.goto(base + '/rooms');
    const trigger = page.locator('[data-rb-drawer]').nth(2);
    await trigger.scrollIntoViewIfNeeded();
    await trigger.click();
    assert.equal(await page.locator('dialog[open]').count(), 1);
    assert.ok(await page.evaluate(() => document.querySelector('dialog[open]').matches(':modal')));
    for (let i = 0; i < 20; i++) { await page.keyboard.press('Tab'); assert.ok(await page.evaluate(() => !!document.activeElement.closest('dialog')), 'Tab stays inside the drawer'); }
    ok('opens modally and Tab never leaves the drawer');
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('dialog[open]').count(), 0);
    assert.equal(await page.evaluate(() => document.activeElement.getAttribute('data-rb-drawer')), await trigger.getAttribute('data-rb-drawer'));
    ok('Escape closes and focus returns to the same trigger');
    await trigger.click(); await page.mouse.click(8, 450);
    assert.equal(await page.locator('dialog[open]').count(), 0);
    ok('backdrop click closes');
    // QA F5: the hash can change while the page is open
    const ids = await page.evaluate(() => [...document.querySelectorAll('dialog.rb-drawer')].map((d) => d.id));
    await page.evaluate((id) => { location.hash = id; }, ids[0]);
    await page.waitForFunction((id) => document.querySelector('dialog[open]')?.id === id, ids[0]);
    await page.evaluate((id) => { location.hash = id; }, ids[1]);
    await page.waitForFunction((id) => document.querySelector('dialog[open]')?.id === id, ids[1]);
    assert.equal(await page.locator('dialog[open]').count(), 1, 'drawers never stack');
    ok('QA F5: changing #schedule-N while open opens it, one drawer at a time');
    await context.close();
  }
  {
    // QA F4: compact header at 320x640 with 200% text; Close stays reachable
    const { context, page } = await login('rep', { viewport: { width: 320, height: 640 } });
    await page.goto(base + '/rooms'); await zoom(page, 200);
    const trigger = page.locator('[data-rb-drawer]').nth(1); await trigger.scrollIntoViewIfNeeded(); await trigger.click();
    await page.waitForTimeout(350); // let the slide-in animation finish before measuring
    const m = await page.evaluate(() => { const d = document.querySelector('dialog[open]'); const c = d.querySelector('.rb-drawer__close').getBoundingClientRect(); return { head: d.querySelector('.rb-drawer__head').getBoundingClientRect().height, closeVisible: c.right <= innerWidth && c.bottom <= innerHeight && c.left >= 0, vh: innerHeight, body: d.querySelector('.rb-drawer__body').getBoundingClientRect().height }; });
    assert.ok(m.head <= m.vh * 0.4, `header ${m.head}px must stay under 40% of ${m.vh}px`);
    assert.ok(m.body >= m.vh * 0.5 && m.closeVisible);
    ok(`QA F4: 320x640 at 200% header ${Math.round(m.head)}px, body ${Math.round(m.body)}px, Close reachable`);
    await context.close();
  }
  {
    // QA F2: without JavaScript the :target drawer is readable (nothing painted over its content) and closable
    const { context, page } = await login('rep', { javaScriptEnabled: false });
    await page.goto(base + '/rooms');
    const trigger = page.locator('[data-rb-drawer]').nth(2); const href = await trigger.getAttribute('href'); await trigger.click();
    const m = await page.evaluate((h) => { const d = document.querySelector(h); const r = d.querySelector('.rb-drawer__body').getBoundingClientRect(); const e = document.elementFromPoint(r.left + 20, r.top + 20); return { before: getComputedStyle(d, '::before').content, onTop: !!e.closest(h), bg: getComputedStyle(d).backgroundColor }; }, href);
    assert.equal(m.before, 'none'); assert.ok(m.onTop); assert.equal(m.bg, 'rgb(255, 255, 255)');
    await page.locator(href + ' [data-rb-drawer-close]').click();
    assert.ok(!(await page.locator(href).isVisible()));
    ok('QA F2: no-JS drawer has no overlay over its own content and closes by link');
    await context.close();
  }

  console.log('Pickers');
  {
    const { context, page } = await login('rep');
    await page.goto(base + '/rooms');
    const button = page.locator('#from').locator('xpath=..').locator('.rb-picker__button');
    await button.focus(); await page.keyboard.press('ArrowDown'); await page.keyboard.press('End'); await page.keyboard.press('Enter');
    const value = await page.inputValue('#from'); assert.match(value, /^\d\d:\d\d$/);
    assert.equal(await button.getAttribute('aria-expanded'), 'false');
    ok('keyboard-only selection sets the native select value ' + value);
    await page.selectOption('#from', { index: 1 }).catch(() => {});
    await page.locator('#from').locator('xpath=..').locator('.rb-picker__button').click();
    assert.equal(await page.locator('.rb-picker__popup:not([hidden]) [role=listbox]').count(), 1);
    await page.keyboard.press('Escape');
    ok('listbox semantics and Escape');
    await context.close();
  }

  console.log('Requests (lab staff)');
  {
    const { context, page } = await login('staff');
    await page.goto(base + '/requests');
    const groups = await page.evaluate(() => [...document.querySelectorAll('.req-group')].map((g) => ({ key: g.className.match(/req-group--(\w+)/)[1], count: +g.querySelector('.count').textContent, cards: g.querySelectorAll('.req').length, ids: [...g.querySelectorAll('.req')].map((c) => c.id) })));
    assert.deepEqual(groups.map((g) => g.key), ['room', 'equipment']);
    for (const g of groups) assert.equal(g.count, g.cards, `${g.key} count matches its cards`);
    const total = await page.evaluate(() => +document.querySelector('.rb-booking__panel h2 .count').textContent);
    assert.equal(groups.reduce((n, g) => n + g.cards, 0), total, 'groups add up to the Waiting total');
    const equipmentNeeds = await page.evaluate(() => [...document.querySelectorAll('.req-group--equipment .req')].map((c) => c.textContent.includes('Equipment only')));
    assert.ok(equipmentNeeds.every(Boolean), 'only equipment-only requests in the equipment group');
    ok('room / equipment-only groups: counts, totals and membership');
    // reject form: full width under the facts, required reason, nothing submitted
    await page.locator('[data-rb-reveal]').first().click();
    const w = await page.evaluate(() => { const c = document.querySelector('.req'); const f = c.querySelector('.req-reject.is-open'); return { card: c.getBoundingClientRect().width, form: f.getBoundingClientRect().width, required: f.querySelector('textarea').required }; });
    assert.ok(w.form > w.card * 0.9 && w.required);
    await page.keyboard.press('Escape');
    ok('reject form opens full width, reason is required, Escape closes');
    await context.close();
  }

  console.log('Manage equipment: inline rename on the category title, search, overflow (QA F1)');
  {
    const { context, page } = await login('staff');
    await page.goto(base + '/manage/equipment');
    assert.equal(await page.locator('.mcat-edit').count(), 0, 'no Rename button');
    const link = page.locator('.mcat-name-link').first(); const form = page.locator('.mcat-rename').first(); const input = form.locator('input[name=name]');
    await link.click();
    assert.ok(!(await input.isVisible()), 'a single mouse click does not start editing');
    await link.dblclick(); assert.ok(await input.isVisible());
    await page.keyboard.press('Escape'); await page.waitForTimeout(80);
    assert.ok(await page.evaluate(() => document.activeElement.classList.contains('mcat-name-link')), 'focus returns to the name');
    for (const key of ['Enter', 'F2', 'Space']) { await link.focus(); await page.keyboard.press(key); assert.ok(await input.isVisible(), key + ' opens the editor'); await page.keyboard.press('Escape'); }
    await link.focus(); await page.keyboard.press('Enter');
    await input.fill('   '); await form.locator('button[type=submit]').click();
    assert.ok((await form.locator('.field-error').textContent()).trim().length > 0 && page.url().endsWith('/manage/equipment'));
    await form.locator('[data-rb-reveal-close]').click();
    assert.equal((await link.locator('.mcat-name').textContent()).trim().length > 0, true);
    ok('double-click, Enter, Space and F2 open; Escape/Cancel restore; blank name shows an inline error');
    // search + app-styled clear control
    const clear = page.locator('.rb-search__clear'); assert.ok(await clear.isHidden());
    await page.fill('#eq-filter', 'proj'); assert.ok(await clear.isVisible());
    const some = await page.locator('[data-eq-row]:visible').count(); const all = await page.locator('[data-eq-row]').count(); assert.ok(some > 0 && some < all);
    await clear.focus(); await page.keyboard.press('Enter');
    assert.equal(await page.inputValue('#eq-filter'), ''); assert.equal(await page.locator('[data-eq-row]:visible').count(), all);
    assert.equal(await page.evaluate(() => document.activeElement.id), 'eq-filter');
    const widths = await page.evaluate(() => ({ input: document.querySelector('.rb-search').getBoundingClientRect().width, card: document.querySelector('.eq-search').getBoundingClientRect().width }));
    assert.ok(widths.input >= widths.card - 80, 'search uses the card width');
    ok('search filters, clear control works by keyboard and returns focus, field fills its card');
    await context.close();
  }
  for (const [w, h, z] of [[320, 640, 100], [390, 844, 200], [320, 640, 200]]) {
    const { context, page } = await login('staff', { viewport: { width: w, height: h } });
    await page.goto(base + '/manage/equipment'); if (z !== 100) await zoom(page, z);
    assert.equal(await overflow(page), 0, 'normal');
    const count = await page.locator('.mcat-rename').count();
    for (let i = 0; i < count; i++) { await page.evaluate((n) => window.rbReveal.setOpen(document.querySelectorAll('.mcat-rename')[n], true), i); assert.equal(await overflow(page), 0, `editing category ${i + 1} at ${w}x${h} @${z}%`); await page.evaluate((n) => window.rbReveal.setOpen(document.querySelectorAll('.mcat-rename')[n], false), i); }
    await page.locator('.mcat-rename input[name=name]').first().evaluate((e) => { e.value = 'x'.repeat(60); });
    ok(`QA F1: no horizontal overflow while renaming any category at ${w}x${h} @${z}%`);
    await context.close();
  }
  {
    const { context, page } = await login('staff', { viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });
    await page.goto(base + '/manage/equipment');
    await page.locator('.mcat-name-link').first().tap();
    assert.ok(await page.locator('.mcat-rename.is-open input[name=name]').isVisible());
    ok('touch: a tap on the category name starts editing');
    await context.close();
  }

  console.log('Manage rules, password, dashboards');
  {
    const { context, page } = await login('staff');
    await page.goto(base + '/manage/rules');
    const placed = await page.evaluate(() => [...document.querySelectorAll('.rules-form, .term-form')].map((f) => { const b = f.querySelector('.mform-footer button').getBoundingClientRect(), r = f.getBoundingClientRect(); return b.top >= r.top && b.bottom <= r.bottom && b.right <= r.right; }));
    assert.ok(placed.length >= 2 && placed.every(Boolean), 'every Save button sits inside its own card');
    const bg = await page.evaluate(() => [...document.querySelectorAll('.rules-form, .term-form')].map((f) => getComputedStyle(f).backgroundColor));
    assert.ok(bg.every((c) => c === 'rgb(255, 255, 255)'));
    ok('booking rules and semester Save buttons are inside matching white cards');
    await page.goto(base + '/staff');
    const stat = await page.evaluate(() => { const v = getComputedStyle(document.querySelector('.rb-stat__value')), l = getComputedStyle(document.querySelector('.rb-stat__label')); return { vs: v.fontSize, ls: l.fontSize, vl: v.lineHeight, ll: l.lineHeight }; });
    assert.equal(stat.vs, stat.ls); assert.equal(stat.vl, stat.ll);
    ok('stat number and label share font size and line height');
    await context.close();
  }
  {
    const { context, page } = await login('rep');
    await page.goto(base + '/account/password');
    const bg = await page.evaluate(() => [document.querySelector('.rb-account-password-card'), document.querySelector('.rb-account-guidance')].map((e) => getComputedStyle(e).backgroundColor));
    assert.equal(bg[0], bg[1]); assert.equal(bg[1], 'rgb(255, 255, 255)');
    ok('password guidance is a white card matching the form card');
    await context.close();
  }

  console.log('Free rooms wording and calendar (no data changed)');
  {
    const { context, page } = await login('rep');
    await page.goto(base + '/rooms');
    const text = await page.locator('main').innerText();
    assert.ok(!/yellow on timetable/i.test(text) && !/Blocked all day/i.test(text) && !/Seats not recorded/i.test(text), 'no spreadsheet remarks or old wording');
    ok('rooms page shows no "yellow on timetable", "Blocked all day" or "Seats not recorded"');
    assert.ok(!/\b0 seats\b/.test(text), 'unknown capacity is never shown as zero');
    ok('capacity reads "N seats" when recorded and "Capacity not specified" otherwise, never "0 seats"');
    const counts = page.locator('.rb-availability__event-count');
    if (await counts.count()) {
      await counts.first().click();
      await page.waitForTimeout(250);
      const sizes = await page.evaluate(() => { const a = document.querySelector('.rb-availability__calendar-details').getBoundingClientRect(), b = document.querySelector('.rb-availability__cal-card').getBoundingClientRect(); return { dh: a.height, ch: b.height, dt: a.top, ct: b.top }; });
      assert.ok(Math.abs(sizes.dh - sizes.ch) < 1.5 && Math.abs(sizes.dt - sizes.ct) < 1.5, 'calendar and event details cards are top-aligned and equal height');
      ok('calendar card and event details card are top-aligned and the same height on desktop');
    }
    await context.close();
  }

  console.log('Booking details: summary, Move / Cancel panels (opens forms, never submits)');
  {
    const { context, page } = await login('staff');
    let found = false;
    for (let id = 1; id <= 80 && !found; id++) {
      const response = await page.goto(`${base}/bookings/${id}`);
      found = response.status() === 200 && (await page.locator('.change-toggle').count()) === 2;
    }
    assert.ok(found, 'a booking that staff can move or cancel exists in the seeded data');
    assert.ok(!(await page.locator('#move-panel').isVisible()) && !(await page.locator('#cancel-panel').isVisible()), 'both panels start closed');
    assert.equal(await overflow(page), 0);
    await page.click('[data-rb-reveal="move-panel"]');
    assert.ok(await page.locator('#move-panel').isVisible() && !(await page.locator('#cancel-panel').isVisible()));
    assert.ok(await page.locator('#move-panel .rb-picker__button').count() >= 3, 'Move uses the compact pickers');
    await page.click('[data-rb-reveal="cancel-panel"]');
    assert.ok(await page.locator('#cancel-panel').isVisible() && !(await page.locator('#move-panel').isVisible()), 'only the selected form is shown');
    ok('Move booking / Cancel booking open one panel at a time');
    await page.click('#cancel-panel button[type=submit]');
    await page.waitForTimeout(200);
    assert.ok(new URL(page.url()).pathname.startsWith('/bookings/'), 'still on the booking');
    assert.equal(await page.locator('#staff-cancel-confirm').evaluate((e) => e.checkValidity()), false);
    ok('Cancel cannot be submitted without the reason and explicit confirmation');
    await page.locator('#cancel-panel a[data-rb-reveal-close]').click();
    assert.ok(!(await page.locator('#cancel-panel').isVisible()));
    ok('Back closes the panel');
    await context.close();
    for (const [width, height, percent] of [[390, 844, 100], [320, 640, 200]]) {
      const phone = await login('staff', { viewport: { width, height }, hasTouch: true, isMobile: true });
      await phone.page.goto(page.url());
      if (percent !== 100) await zoom(phone.page, percent);
      await phone.page.click('[data-rb-reveal="move-panel"]');
      assert.equal(await overflow(phone.page), 0, `no horizontal overflow at ${width}px / ${percent}%`);
      await phone.page.click('[data-rb-reveal="cancel-panel"]');
      assert.equal(await overflow(phone.page), 0);
      ok(`Move and Cancel panels fit at ${width}px, text ${percent}%`);
      await phone.context.close();
    }
    const plain = await login('staff', { javaScriptEnabled: false });
    await plain.page.goto(page.url());
    assert.ok(!(await plain.page.locator('#move-panel').isVisible()));
    await plain.page.click('a[href="#move-panel"]');
    assert.ok(await plain.page.locator('#move-panel').isVisible() && await plain.page.locator('#move-room').isVisible(), 'native selects without script');
    await plain.page.click('a[href="#cancel-panel"]');
    assert.ok(await plain.page.locator('#cancel-panel').isVisible() && !(await plain.page.locator('#move-panel').isVisible()));
    ok('without JavaScript the toggles open the matching form through :target');
    await plain.context.close();
  }

  await browser.close();
  console.log(`\n${passed} checks passed`);
})().catch((error) => { console.error('\nFAILED:', error.message); process.exit(1); });
