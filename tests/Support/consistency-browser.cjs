// Real-browser regression checks for the final consistency pass: Requests Reject/Cancel action group, My bookings
// button-styled cancel links, hidden missing capacities, always-visible unavailable rooms on /book, the removed sidebar
// card, and one typography scale across the three roles.
//
//   node tests/Support/consistency-browser.cjs <path-to-playwright-core> <base-url> [screenshot-dir]
//
// Drives a RUNNING application on a DISPOSABLE database seeded with `php artisan db:seed` (admin@/staff@/rep1@reservebot.test,
// password Password123!) plus: pending room and equipment-only requests, several of rep1's upcoming bookings (one inside
// the cancel cut-off), rooms without a recorded capacity and one with a capacity, and busy rooms at 09:00-11:00 three days
// ahead. It changes no data: it never submits a form with a reason. Not part of PHPUnit.
const { chromium } = require(process.argv[2]);
const assert = require('node:assert/strict');
const base = (process.argv[3] || '').replace(/\/$/, '');
const shots = process.argv[4] || '';
assert.ok(/^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/.test(base), 'Refusing to run against anything but a local disposable app (127.0.0.1 / localhost).');
assert.ok(!/:(8000|8001|8002)$/.test(base), "Refusing to run against the developer's own app ports.");

const accounts = { rep: 'rep1@reservebot.test', staff: 'staff@reservebot.test', admin: 'admin@reservebot.test' };
const sizes = { desktop: [1438, 900], phone: [390, 844], narrow: [320, 640] };
const day = new Date(Date.now() + 86400e3 * 3).toISOString().slice(0, 10);
const bookUrl = `/book?need=room&for=other&date=${day}&from=09:00&to=11:00`;
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
  const go = async (page, url) => { const r = await page.goto(base + url); await page.waitForLoadState('networkidle'); return r; };
  const overflow = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  const zoom = (page, percent) => page.addStyleTag({ content: `html{font-size:${percent}%!important}` });
  const box = (page, sel) => page.locator(sel).first().boundingBox();
  const snap = async (page, name) => { if (shots) await page.screenshot({ path: `${shots}/${name}.png`, fullPage: true }); };
  const style = (page, sel, props) => page.locator(sel).first().evaluate((e, p) => { const c = getComputedStyle(e); return Object.fromEntries(p.map(k => [k, c[k]])); }, props);
  const posts = (page) => { const sent = []; page.on('request', r => { if (r.method() === 'POST') sent.push(r.url()); }); return sent; };

  console.log('1. Requests: Reject booking and Cancel are one action group (lab staff)');
  {
    const { context, page } = await login('staff');
    const sent = posts(page);
    for (const [tab, url] of [['room', '/requests'], ['equipment', '/requests?tab=equipment']]) {
      for (const [name, [w, h]] of Object.entries(sizes)) {
        await page.setViewportSize({ width: w, height: h });
        await go(page, url);
        await page.locator('a[data-rb-reveal]').first().click();
        const form = page.locator('.req-reject.is-open').first();
        assert.ok(await form.isVisible(), `${tab}/${name}: the reason form opens`);
        const reject = await form.locator('.btn-reject').boundingBox(), cancel = await form.locator('.btn-ghost').boundingBox();
        assert.ok(Math.abs(reject.height - cancel.height) < 1, `${tab}/${name}: equal heights (${reject.height} vs ${cancel.height})`);
        const stacked = cancel.y >= reject.y + reject.height - 1;   // on very narrow screens the two wrap onto their own lines
        if (stacked) assert.ok(Math.abs(reject.width - cancel.width) < 1 && Math.abs(reject.x - cancel.x) < 1, `${tab}/${name}: stacked buttons share one width`);
        else assert.ok(Math.abs((reject.y + reject.height / 2) - (cancel.y + cancel.height / 2)) < 1, `${tab}/${name}: vertically centred together`);
        const a = await style(page, '.req-reject.is-open .btn-reject', ['fontSize', 'fontWeight', 'borderRadius', 'paddingTop', 'paddingLeft', 'lineHeight']);
        const b = await style(page, '.req-reject.is-open .btn-ghost', ['fontSize', 'fontWeight', 'borderRadius', 'paddingTop', 'paddingLeft', 'lineHeight']);
        assert.deepEqual(a, b, `${tab}/${name}: same type, padding and radius`);
        if (name === 'desktop') {
          const gap = cancel.x - (reject.x + reject.width);
          assert.ok(gap >= 6 && gap <= 12, `${tab}: consistent spacing (${gap}px)`);
          const area = await box(page, '.req-reject.is-open textarea');
          assert.ok(Math.abs(area.x - reject.x) < 1, `${tab}: the reason field and the action row share a left edge`);
          const colours = await page.evaluate(() => { const r = getComputedStyle(document.querySelector('.req-reject.is-open .btn-reject')), c = getComputedStyle(document.querySelector('.req-reject.is-open .btn-ghost')); return { rb: r.backgroundColor, rc: r.color, cb: c.backgroundColor }; });
          assert.notEqual(colours.rb, 'rgb(255, 255, 255)', 'Reject booking is filled');
          assert.equal(colours.rc, 'rgb(255, 255, 255)');
          assert.equal(colours.cb, 'rgb(255, 255, 255)', 'Cancel is an outline');
        } else {
          assert.equal(await overflow(page), 0, `${tab}/${name}: no overflow`);
          assert.ok(reject.height >= 40, `${tab}/${name}: touch-sized`);
        }
        await snap(page, `req-${tab}-actions-${name}`);
      }
      await page.setViewportSize({ width: 1438, height: 900 });
      await go(page, url);
      await page.locator('a[data-rb-reveal]').first().click();
      await zoom(page, 200);
      await page.waitForTimeout(150);
      const r2 = await page.locator('.req-reject.is-open .btn-reject').boundingBox(), c2 = await page.locator('.req-reject.is-open .btn-ghost').boundingBox();
      assert.ok(Math.abs(r2.height - c2.height) < 1, `${tab}: equal heights at 200% text`);
      assert.equal(await overflow(page), 0, `${tab}: no overflow at 200% text`);
      await page.setViewportSize({ width: 719, height: 450 });
      await go(page, url);
      await page.locator('a[data-rb-reveal]').first().click();
      assert.equal(await overflow(page), 0, `${tab}: no overflow at 200% page zoom`);
    }
    ok('Room and Equipment-only requests: equal height, type, padding and radius; filled Reject vs outlined Cancel; 390, 320, 200%');

    // Cancel dismisses without rejecting; an empty reason is refused by the browser.
    await go(page, '/requests');
    const count = await page.locator('article.req').count();
    await page.locator('a[data-rb-reveal]').first().click();
    await page.locator('.req-reject.is-open textarea').fill('Draft only');
    await page.locator('.req-reject.is-open .btn-ghost').click();
    assert.equal(await page.locator('.req-reject.is-open').count(), 0, 'Cancel closes the form');
    assert.equal(await page.locator('article.req').count(), count, 'nothing was rejected');
    await page.locator('a[data-rb-reveal]').first().click();
    await page.locator('.req-reject.is-open textarea').fill('');
    await page.locator('.req-reject.is-open .btn-reject').click();
    assert.equal(await page.locator('.req-reject.is-open textarea').evaluate(e => e.validity.valueMissing), true);
    await page.waitForTimeout(250);
    assert.equal(sent.length, 0, 'no request was sent');
    // Keyboard: textarea, then Reject booking, then Cancel, each with a focus ring.
    await page.locator('.req-reject.is-open textarea').focus();
    await page.keyboard.press('Tab');
    assert.equal(await page.evaluate(() => document.activeElement.textContent.trim()), 'Reject booking');
    assert.notEqual(await page.evaluate(() => getComputedStyle(document.activeElement).outlineStyle), 'none');
    await page.keyboard.press('Tab');
    assert.equal(await page.evaluate(() => document.activeElement.textContent.trim()), 'Cancel');
    assert.notEqual(await page.evaluate(() => getComputedStyle(document.activeElement).outlineStyle), 'none');
    ok('Cancel only dismisses; empty reason stopped; keyboard order and focus rings');
    await context.close();
    const plain = await login('staff', { javaScriptEnabled: false });
    await go(plain.page, '/requests');
    const href = await plain.page.locator('a[data-rb-reveal]').first().getAttribute('href');
    await plain.page.goto(base + '/requests' + href);
    assert.ok(await plain.page.locator('.req-reject:target textarea').isVisible(), 'without JavaScript the #fragment opens the form');
    ok('without JavaScript the reject form still opens by link');
    await plain.context.close();
  }

  console.log('2. My bookings: cancel links are button-styled anchors (representative)');
  {
    const { context, page } = await login('rep');
    const sent = posts(page);
    await go(page, '/my-bookings');
    const links = page.locator('a.brow-cancel');
    const n = await links.count();
    assert.ok(n >= 3, 'cancellable bookings have the control');
    for (let i = 0; i < n; i++) {
      const l = links.nth(i);
      assert.equal(await l.evaluate(e => e.tagName), 'A');
      assert.match(await l.getAttribute('href'), /\/bookings\/\d+#cancel$/);
      assert.equal((await l.evaluate(e => e.firstChild.textContent)).trim(), 'Cancel booking');
      assert.match(await l.evaluate(e => e.querySelector('.sr-only').textContent), /^: \S/, 'a unique accessible name');
    }
    assert.equal(await page.locator('a.brow-cancel', { hasText: '…' }).count(), 0);
    const s = await style(page, 'a.brow-cancel', ['textDecorationLine', 'borderTopStyle', 'fontSize', 'fontWeight', 'borderRadius', 'color']);
    assert.equal(s.textDecorationLine, 'none', 'no bare underlined link');
    assert.equal(s.borderTopStyle, 'solid');
    const ref = await style(page, '.btn-book', ['fontSize', 'fontWeight', 'borderRadius']);
    assert.deepEqual({ f: s.fontSize, w: s.fontWeight, r: s.borderRadius }, { f: ref.fontSize, w: ref.fontWeight, r: ref.borderRadius }, 'same type and radius as the other buttons');
    const link = await box(page, 'a.brow-cancel'), badge = await box(page, '.brow .status-badge');
    assert.ok(Math.abs((link.x + link.width) - (badge.x + badge.width)) < 2, 'right-aligned beneath the status badge');
    assert.ok(link.y >= badge.y + badge.height, 'beneath the badge');
    assert.ok(link.height >= 30 && link.height <= 40, 'compact: ' + link.height);
    assert.ok(n < await page.locator('.brow').count(), 'not every row has the control');
    await snap(page, 'mybookings-desktop');
    // The late booking (inside the cut-off) has no control.
    const lateRow = page.locator('.brow', { hasText: 'too late to cancel' });
    if (await lateRow.count()) assert.equal(await lateRow.locator('a.brow-cancel').count(), 0, 'no control inside the cut-off');
    // Navigation only: it opens the booking's cancel card and sends nothing.
    await Promise.all([page.waitForURL(/\/bookings\/\d+#cancel$/), links.first().click()]);
    assert.ok(await page.locator('#cancel #cancel-reason').isVisible(), 'lands on the cancellation card');
    assert.equal(sent.length, 0, 'no POST: nothing was cancelled from the list');
    ok('anchors to /bookings/{id}#cancel, styled as compact outlined buttons, no POST, ineligible rows have none');
    for (const [name, [w, h]] of Object.entries(sizes)) {
      await page.setViewportSize({ width: w, height: h });
      await go(page, '/my-bookings');
      assert.equal(await overflow(page), 0, `${name}: no overflow`);
      if (name !== 'desktop') assert.ok((await box(page, 'a.brow-cancel')).height >= 40, `${name}: touch-sized`);
      await snap(page, `mybookings-${name}`);
    }
    await page.setViewportSize({ width: 1438, height: 900 });
    await go(page, '/my-bookings'); await zoom(page, 200); await page.waitForTimeout(150);
    assert.equal(await overflow(page), 0, '200% text: no overflow');
    await page.setViewportSize({ width: 719, height: 450 });
    await go(page, '/my-bookings');
    assert.equal(await overflow(page), 0, '200% page zoom: no overflow');
    await page.setViewportSize({ width: 1438, height: 900 });
    await go(page, '/my-bookings');
    await page.locator('a.brow-cancel').first().focus();
    assert.notEqual(await page.evaluate(() => getComputedStyle(document.activeElement).outlineStyle), 'none', 'visible focus ring');
    ok('390, 320, 200% text and page zoom: no overflow; touch-sized on phones; focus ring');
    await context.close();
  }

  console.log('3. Missing capacities are omitted (representative, lab staff, admin)');
  {
    const rep = await login('rep');
    await go(rep.page, bookUrl);
    const text = await rep.page.locator('main').innerText();
    assert.ok(!/Capacity not specified|Not specified|unknown|N\/A/i.test(text.replace(/Reason not specified/g, '')), 'no capacity placeholder on /book');
    const names = await rep.page.locator('.room-opt-name').allInnerTexts();
    assert.ok(names.length >= 2, 'free rooms are listed');
    for (const t of names) assert.match(t.trim(), /^\S+( \d+ seats?)?$/, 'name alone, or name plus a real capacity: ' + t);
    assert.ok(names.some(t => /\d+ seats?/.test(t)) && names.some(t => !/seat/.test(t)), 'one known and one unknown capacity are both on the page');
    for (const url of ['/rooms', `/rooms?date=${day}`]) { await go(rep.page, url); assert.ok(!/Capacity not specified/.test(await rep.page.locator('main').innerText())); }
    await rep.page.locator('[data-rb-drawer-open], button[aria-controls^="schedule-"]').first().click().catch(() => {});
    assert.ok(!/Capacity not specified/.test(await rep.page.content()), 'nor in the schedule drawer');
    for (const url of ['/', '/my-bookings', `/bookings/9`]) { await go(rep.page, url); assert.ok(!/Capacity not specified/.test(await rep.page.content()), 'none on ' + url); }
    await snap(rep.page, 'book-rooms-desktop');
    await rep.context.close();
    for (const role of ['staff', 'admin']) {
      const { context, page } = await login(role);
      for (const url of ['/manage/rooms', '/rooms', '/today', '/requests', '/bookings/9']) {
        await go(page, url);
        const html = await page.content();
        assert.ok(!/Capacity not specified|>Not specified</.test(html), `${role}: none on ${url}`);
      }
      await go(page, '/manage/rooms');
      assert.ok(await page.locator('td', { hasText: /^40$/ }).count() >= 1, 'a real capacity is still shown');
      assert.equal(await page.locator('.rooms-table td:nth-child(3)').evaluateAll(cells => cells.filter(c => /not specified|unknown|n\/a|^[–—-]$/i.test(c.textContent.trim())).length), 0, 'unknown capacities are empty cells');
      await go(page, '/manage/rooms');
      await context.close();
    }
    ok('/book, Free rooms, Manage rooms, Today, Requests, Booking details, Home: no placeholder; real values kept');
  }

  console.log('4. /book: unavailable rooms are listed directly');
  {
    for (const options of [{}, { javaScriptEnabled: false }]) {
      const { context, page } = await login('rep', options);
      await go(page, bookUrl);
      assert.equal(await page.locator('details.busy-rooms, .busy-rooms').count(), 0, 'the old disclosure is gone');
      const panel = page.locator('section.unavail');
      assert.ok(await panel.isVisible(), 'visible without any click' + (options.javaScriptEnabled === false ? ' or script' : ''));
      const rows = page.locator('.unavail__row');
      const total = await rows.count();
      assert.ok(total >= 3);
      assert.equal(Number(await panel.locator('.count').innerText()), total, 'the count matches the rows');
      for (let i = 0; i < total; i++) {
        const row = rows.nth(i);
        assert.ok((await row.locator('.unavail__room').innerText()).trim().length > 0);
        assert.ok((await row.locator('.unavail__reason').innerText()).trim().length > 0);
        assert.match(await row.locator('.unavail__time').innerText(), /\d:\d\d/);
      }
      assert.equal(await panel.locator('input, select, button, a, summary').count(), 0, 'no controls');
      const pickedIds = await page.locator('input[name=room]').evaluateAll(es => es.map(e => e.value));
      const unavailableNames = await panel.locator('.unavail__room').allInnerTexts();
      const optionNames = await page.locator('.room-opt-name strong').allInnerTexts();
      assert.ok(unavailableNames.every(n => !optionNames.includes(n)), 'an unavailable room is never also a choice');
      assert.ok(pickedIds.length >= 1);
      const first = await box(page, '.room-pick'), pan = await box(page, 'section.unavail');
      assert.ok(pan.y >= first.y + first.height, 'immediately below the room choices');
      assert.ok(/Rooms are shared/.test(await panel.innerText()), 'the sharing note is kept, briefly');
      if (options.javaScriptEnabled !== false) await snap(page, 'book-unavailable-desktop');
      await context.close();
    }
    const { context, page } = await login('rep');
    for (const [name, [w, h]] of Object.entries(sizes)) {
      await page.setViewportSize({ width: w, height: h });
      await go(page, bookUrl);
      assert.equal(await overflow(page), 0, `${name}: no horizontal overflow`);
      const rowBox = await box(page, '.unavail__row'), panelBox = await box(page, 'section.unavail');
      assert.ok(rowBox.x >= panelBox.x && rowBox.x + rowBox.width <= panelBox.x + panelBox.width + 1, `${name}: rows stay inside the panel`);
      assert.ok(rowBox.height < 80, `${name}: rows are compact (${rowBox.height})`);
      await snap(page, `book-unavailable-${name}`);
    }
    await page.setViewportSize({ width: 1438, height: 900 });
    await go(page, bookUrl); await zoom(page, 200); await page.waitForTimeout(150);
    assert.equal(await overflow(page), 0, '200% text: no overflow');
    await page.setViewportSize({ width: 719, height: 450 });
    await go(page, bookUrl);
    assert.equal(await overflow(page), 0, '200% page zoom: no overflow');
    // Choosing "Equipment only" hides the room section, including the list.
    await page.setViewportSize({ width: 1438, height: 900 });
    await go(page, bookUrl);
    await page.locator('input[name=need][value=equipment]').check();
    assert.equal(await page.locator('section.unavail').isVisible(), false, 'hidden with the rest of the room section');
    ok('always visible (also without JavaScript), compact rows, no controls, matches the count; stacks on phones; no overflow');
    await context.close();
  }

  console.log('5. The sidebar information card is gone (all roles)');
  for (const role of ['rep', 'staff', 'admin']) {
    const { context, page } = await login(role);
    for (const [name, [w, h]] of Object.entries(sizes)) {
      await page.setViewportSize({ width: w, height: h });
      await go(page, role === 'rep' ? '/my-bookings' : '/requests');
      assert.equal(await page.locator('.rb-workspace-note').count(), 0);
      assert.ok(!/PUP CEA \/ NDC Campus|Reservations follow your department/.test(await page.content()));
      assert.ok(await page.locator('.rb-brand').first().isVisible(), `${role}/${name}: branding stays`);
      assert.equal(await overflow(page), 0);
      if (name === 'desktop') {
        const kids = await page.locator('.rb-sidebar > *').evaluateAll(es => es.map(e => e.className.split(' ')[0]));
        assert.deepEqual(kids, ['rb-role-card', 'rb-navigation'], `${role}: the sidebar holds only the role card and navigation`);
        const sidebar = await box(page, '.rb-sidebar'), nav = await box(page, '.rb-navigation');
        assert.ok(sidebar.width > 200 && sidebar.width < 300, 'sidebar width unchanged: ' + sidebar.width);
        assert.ok(await page.locator('.rb-navigation a[aria-current="page"]').count() === 1, 'active indicator stays');
        assert.ok(nav.y + nav.height < sidebar.y + sidebar.height, 'empty space below the navigation is simply empty');
        await snap(page, `sidebar-${role}-desktop`);
      } else if (role === 'rep') {
        assert.ok(await page.locator('.rb-navigation a').first().isVisible(), 'rep: bottom navigation visible');
      } else {
        await page.locator('#rb-menu-toggle').click();
        assert.ok(await page.locator('.rb-navigation a').last().isVisible(), `${role}/${name}: the menu opens and lists every link`);
        await page.keyboard.press('Escape');
      }
    }
    await context.close();
  }
  ok('no card, no leftover markup, branding/active state/menu intact on desktop and phones for rep, staff and admin');

  console.log('6. Typography: one scale across roles');
  {
    const probe = () => {
      const g = (sel) => { const e = document.querySelector(sel); if (!e) return null; const c = getComputedStyle(e); return { size: c.fontSize, weight: c.fontWeight }; };
      return { h1: g('main h1'), nav: g('.rb-nav-item__label'), label: g('main .field-label, main .rb-field__label'), sub: g('main .muted, main .rb-description, main .today-head p:not(.eyebrow), main .rb-dashboard p') };
    };
    const pages = { rep: ['/', '/rooms', bookUrl, '/my-bookings', '/account'], staff: ['/', '/rooms', '/today', '/requests', '/manage/rooms', '/manage/rules', '/account'], admin: ['/', '/rooms', '/today', '/requests', '/manage/rooms', '/accounts', '/manage/rules', '/account'] };
    for (const [name, [w, h]] of [['desktop', sizes.desktop], ['phone', sizes.phone]]) {
      const titles = new Set(), navs = new Set(), labels = new Set();
      for (const role of Object.keys(pages)) {
        const { context, page } = await login(role);
        await page.setViewportSize({ width: w, height: h });
        for (const url of pages[role]) {
          await go(page, url);
          const p = await page.evaluate(probe);
          assert.ok(p.h1, `${role} ${url}: has a page title`);
          titles.add(p.h1.size + '/' + p.h1.weight);
          if (p.nav && name === 'desktop') navs.add(p.nav.size + '/' + p.nav.weight);
          if (p.label) labels.add(p.label.size + '/' + p.label.weight);
          assert.equal(await overflow(page), 0, `${role} ${url} ${name}: no overflow`);
        }
        await context.close();
      }
      const expected = name === 'desktop' ? '29px/700' : '22px/700';
      assert.deepEqual([...titles], [expected], `${name}: every page title is ${expected}, got ${[...titles]}`);
      if (name === 'desktop') {
        assert.equal(navs.size, 1, 'one navigation label style for every role: ' + [...navs]);
        assert.equal(labels.size, 1, 'one form-label style everywhere: ' + [...labels]);
      }
    }
    // Buttons: the same type on the primary actions of different pages.
    const rep = await login('rep');
    await go(rep.page, '/my-bookings');
    const a = await style(rep.page, '.btn-book', ['fontSize', 'fontWeight']);
    await go(rep.page, '/account');
    const b = await style(rep.page, 'main .rb-button', ['fontSize', 'fontWeight']);
    assert.deepEqual(a, b, 'action buttons share one size and weight on booking and account pages');
    const tabs = await rep.page.locator('.rb-account-tabs.rb-subnav a').count();
    assert.equal(tabs, 2, 'account tabs use the shared underline navigation');
    assert.equal(await rep.page.locator('.rb-account-tabs a[aria-current="page"]').count(), 1);
    const current = (await style(rep.page, '.rb-account-tabs a[aria-current]', ['color'])).color, idle = (await style(rep.page, '.rb-account-tabs a:not([aria-current])', ['color'])).color;
    assert.notEqual(current, idle, 'the current tab is maroon and the idle tab is not');
    await rep.context.close();
    ok('page titles 29px / 22px on phones on all pages and roles; one nav-label and form-label style; shared button type; no overflow');
  }

  console.log('7. Booking Details panels are preserved');
  {
    const staff = await login('staff');
    await go(staff.page, '/bookings/9');
    assert.ok(await staff.page.locator('.action-panel--approve').isVisible() && await staff.page.locator('#reject-reason').isVisible());
    assert.equal(await staff.page.locator('details, summary').count(), 0);
    const a = await box(staff.page, '.action-panel--approve'), r = await box(staff.page, '.action-panel--reject');
    assert.ok(a.x < r.x && Math.abs(a.y - r.y) < 2 && r.width / a.width > 1.8);
    await snap(staff.page, 'booking-staff-desktop');
    await staff.context.close();
    const rep = await login('rep');
    await go(rep.page, '/bookings/9');
    assert.ok(await rep.page.locator('.cancel-card #cancel-reason').isVisible());
    assert.equal(await rep.page.locator('details, summary').count(), 0);
    await snap(rep.page, 'booking-rep-desktop');
    for (const [, [w, h]] of Object.entries(sizes)) { await rep.page.setViewportSize({ width: w, height: h }); await go(rep.page, '/bookings/9'); assert.equal(await overflow(rep.page), 0); }
    await rep.context.close();
    ok('approve/reject and cancel panels intact and not overflowing');
  }

  console.log('8. Authorization boundaries');
  {
    const rep = await login('rep');
    for (const url of ['/requests', '/accounts', '/manage/rooms', '/staff', '/admin']) assert.equal((await rep.page.goto(base + url)).status(), 403, 'rep refused: ' + url);
    await rep.context.close();
    const staff = await login('staff');
    for (const url of ['/accounts', '/admin']) assert.equal((await staff.page.goto(base + url)).status(), 403, 'lab staff refused: ' + url);
    assert.equal((await staff.page.goto(base + '/book')).status(), 403, 'lab staff cannot book');
    await staff.context.close();
    const admin = await login('admin');
    assert.equal((await admin.page.goto(base + '/accounts')).status(), 200);
    assert.equal((await admin.page.goto(base + '/book')).status(), 403);
    await admin.context.close();
    ok('rep, lab staff and admin boundaries unchanged');
  }

  await browser.close();
  console.log(`\n${passed} checks passed`);
})().catch(e => { console.error(e); process.exit(1); });
