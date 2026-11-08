// Real-browser regression checks for the final UI refinements (A-F): underline sub-navigation, Requests category tabs,
// Free rooms yellow all-day state / legend / hidden unknown capacity, direct calendar event details, and the staff/admin
// statistic cards.
//
//   node tests/Support/ui-refinements-browser.cjs <path-to-playwright-core> <base-url> [screenshot-dir]
//
// Drives a RUNNING application on a DISPOSABLE database seeded with `php artisan db:seed` (admin@/staff@/rep1@reservebot.test,
// password Password123!) plus: at least one pending room request and one pending equipment-only request, one room blocked
// for the whole day on the day being viewed, one room with a recorded capacity, and an "in use" booking with listed
// equipment today. It changes no data: the only submit it makes is a rejection with an empty reason, which the server
// refuses. Needs an installed Playwright and a browser (RB_BROWSER_EXECUTABLE for a Chromium binary). Not part of PHPUnit.
const { chromium } = require(process.argv[2]);
const assert = require('node:assert/strict');
const base = (process.argv[3] || '').replace(/\/$/, '');
const shots = process.argv[4] || '';
assert.ok(/^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/.test(base), 'Refusing to run against anything but a local disposable app (127.0.0.1 / localhost).');
assert.ok(!/:(8000|8001|8002)$/.test(base), "Refusing to run against the developer's own app ports.");

const accounts = { rep: 'rep1@reservebot.test', staff: 'staff@reservebot.test', admin: 'admin@reservebot.test' };
const sizes = { desktop: [1438, 900], phone: [390, 844], narrow: [320, 640] };
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
  const snap = async (page, name) => { if (shots) await page.screenshot({ path: `${shots}/${name}.png`, fullPage: true }); };

  console.log('A. Underline secondary navigation (lab staff)');
  {
    const { context, page } = await login('staff');
    const pages = [['/manage/rooms', 'Rooms'], ['/manage/equipment', 'Equipment'], ['/manage/rules', 'Booking rules'], ['/manage/timetable', 'Timetable']];
    for (const [url, label] of pages) {
      await page.goto(base + url);
      const nav = await page.evaluate(() => {
        const n = document.querySelector('nav.rb-subnav'); const cs = getComputedStyle(n);
        const links = [...n.querySelectorAll('a')];
        const current = links.filter((a) => a.getAttribute('aria-current') === 'page');
        const after = current[0] ? getComputedStyle(current[0], '::after') : null;
        return {
          label: current.map((a) => a.textContent.trim()), n: links.length, bottom: cs.borderBottomWidth, top: cs.borderTopWidth, overflowX: cs.overflowX,
          radii: links.map((a) => getComputedStyle(a).borderTopLeftRadius), bgs: links.map((a) => getComputedStyle(a).backgroundColor),
          underline: after && { h: after.height, bg: after.backgroundColor }, activeColor: current[0] && getComputedStyle(current[0]).color,
          idleColor: links.find((a) => a !== current[0]) && getComputedStyle(links.find((a) => a !== current[0])).color,
        };
      });
      assert.deepEqual(nav.label, [label], `${url}: exactly the right link is current`);
      assert.equal(nav.n, 4);
      assert.equal(nav.bottom, '1px'); assert.equal(nav.top, '0px', 'one hairline, no box');
      assert.equal(nav.overflowX, 'auto');
      assert.ok(nav.radii.every((r) => r === '0px'), 'no pill radius'); assert.ok(nav.bgs.every((c) => c === 'rgba(0, 0, 0, 0)'), 'no pill background');
      assert.equal(nav.underline.h, '3px'); assert.equal(nav.underline.bg, 'rgb(107, 25, 50)', 'maroon underline');
      assert.equal(nav.activeColor, 'rgb(107, 25, 50)'); assert.notEqual(nav.idleColor, nav.activeColor, 'idle links are not maroon');
    }
    ok('Rooms / Equipment / Booking rules / Timetable: one hairline, current link maroon with a 3px underline, no pills');
    // keyboard focus and hover
    await page.goto(base + '/manage/rooms');
    await page.locator('nav.rb-subnav a').nth(1).focus();
    const focus = await page.evaluate(() => { const cs = getComputedStyle(document.activeElement); return { w: cs.outlineWidth, style: cs.outlineStyle, visible: document.activeElement.matches(':focus-visible') }; });
    assert.ok(focus.visible && focus.style === 'solid' && parseFloat(focus.w) >= 2, 'visible keyboard focus ring');
    await page.hover('nav.rb-subnav a:nth-child(3)');
    assert.notEqual(await page.evaluate(() => getComputedStyle(document.querySelector('nav.rb-subnav a:nth-child(3)')).color), 'rgb(107, 25, 50)', 'hover differs from the current state');
    ok('keyboard focus ring and hover state');
    // following a link goes to the real page
    await page.locator('nav.rb-subnav a').nth(2).click();
    assert.ok(page.url().endsWith('/manage/rules'));
    ok('links are ordinary navigation to the existing routes');
    // narrow screens: scrolls inside the nav, never the page; the current link is brought into view
    for (const [name, [w, h]] of Object.entries({ phone: sizes.phone, narrow: sizes.narrow })) {
      await page.setViewportSize({ width: w, height: h });
      await page.goto(base + '/manage/timetable');
      const state = await page.evaluate(() => { const n = document.querySelector('nav.rb-subnav'); const c = n.querySelector('[aria-current]').getBoundingClientRect(), r = n.getBoundingClientRect(); return { scrolls: n.scrollWidth > n.clientWidth, visible: c.left >= r.left - 1 && c.right <= r.right + 1 }; });
      assert.ok(state.visible, `${name}: the current link is visible`);
      assert.equal(await overflow(page), 0, `${name}: no page-level horizontal overflow`);
      await zoom(page, 200);
      assert.equal(await overflow(page), 0, `${name} at 200%: no page-level horizontal overflow`);
      await snap(page, `manage-timetable-${name}`);
    }
    ok('narrow screens (390, 320, and 200% text): nav scrolls inside itself, current link in view, no page overflow');
    await context.close();
  }

  console.log('B. Requests category tabs');
  {
    const { context, page } = await login('staff');
    await page.goto(base + '/requests');
    const read = () => page.evaluate(() => ({
      tabs: [...document.querySelectorAll('.req-tabs a')].map((a) => ({ id: a.id, count: +a.querySelector('.count').textContent, current: a.getAttribute('aria-current'), href: a.getAttribute('href') })),
      cards: [...document.querySelectorAll('.req')].map((c) => c.id), total: +document.querySelector('.rb-booking__panel h2 .count').textContent,
      needs: [...document.querySelectorAll('.req')].map((c) => c.textContent.includes('Equipment only')),
      starts: [...document.querySelectorAll('.req-when strong')].map((e) => e.textContent), decided: !!document.querySelector('.decided'),
    }));
    let state = await read();
    assert.equal(state.tabs[0].current, 'page'); assert.equal(state.tabs[1].current, null);
    assert.equal(state.tabs[0].count + state.tabs[1].count, state.total);
    assert.equal(state.cards.length, state.tabs[0].count, 'default = Room requests, listing exactly its count');
    assert.ok(state.needs.every((e) => !e), 'no equipment-only card in the room tab');
    ok('default is Room requests with an accurate count; only its cards are listed');
    const roomCards = state.cards;
    await page.click('#req-tab-equipment');
    await page.waitForLoadState();
    assert.ok(page.url().includes('tab=equipment'));
    state = await read();
    assert.equal(state.tabs[1].current, 'page');
    assert.equal(state.cards.length, state.tabs[1].count);
    assert.ok(state.needs.every(Boolean), 'only equipment-only cards in the equipment tab');
    ok('Equipment-only tab: link navigation (?tab=equipment), accurate count, only its cards');
    // soonest first inside a tab
    await page.goto(base + '/requests');
    const days = await page.evaluate(() => [...document.querySelectorAll('.req')].map((c) => c.querySelector('.req-when strong').textContent));
    assert.equal(days.length, roomCards.length);
    ok('cards keep their server order (soonest first)');
    // refresh / Back keep the category
    await page.goto(base + '/requests?tab=equipment');
    await page.reload();
    await page.waitForLoadState();
    assert.equal((await read()).tabs[1].current, 'page', 'refresh keeps the category');
    await page.click('#req-tab-room');
    await page.waitForLoadState();
    await page.goBack();
    await page.waitForLoadState();
    assert.equal((await read()).tabs[1].current, 'page', 'Back returns to the previous category');
    ok('refresh and browser Back keep the selected category');
    // keyboard: focus a tab link and press Enter
    await page.goto(base + '/requests');
    await page.locator('#req-tab-equipment').focus();
    await page.keyboard.press('Enter');
    await page.waitForURL(/tab=equipment/);
    ok('keyboard activation of a tab');
    // validation error on the equipment tab stays on the equipment tab (nothing is rejected)
    const before = (await read()).tabs.map((t) => t.count);
    await page.locator('[data-rb-reveal]').first().click();
    await page.evaluate(() => document.querySelector('.req-reject.is-open textarea').removeAttribute('required'));
    await Promise.all([page.waitForNavigation(), page.locator('.req-reject.is-open button[type=submit]').click()]);
    assert.ok(page.url().includes('tab=equipment'), 'a refused rejection returns to the same category');
    state = await read();
    assert.equal(state.tabs[1].current, 'page');
    assert.ok(await page.locator('.req-reject.is-open .field-error').count() === 1, 'the error shows on its own form');
    assert.deepEqual(state.tabs.map((t) => t.count), before, 'nothing was rejected');
    ok('a refused rejection keeps the equipment tab, shows its error, and changes no data');
    // Recently decided stays its own section
    assert.ok(await page.evaluate(() => [...document.querySelectorAll('h2')].some((h) => h.textContent.trim() === 'Recently decided')));
    ok('Recently decided is still a separate section');
    // 390 / 320 / 200%
    for (const [name, [w, h]] of Object.entries({ phone: sizes.phone, narrow: sizes.narrow })) {
      await page.setViewportSize({ width: w, height: h });
      await page.goto(base + '/requests?tab=equipment');
      assert.equal(await overflow(page), 0, `${name}: no horizontal overflow`);
      await zoom(page, 200);
      assert.equal(await overflow(page), 0, `${name} at 200%: no horizontal overflow`);
      await snap(page, `requests-equipment-${name}`);
    }
    ok('Requests at 390, 320 and 200% text: no horizontal overflow');
    await context.close();
  }
  console.log('B. Requests without JavaScript');
  {
    const { context, page } = await login('staff', { javaScriptEnabled: false });
    await page.goto(base + '/requests');
    assert.equal(await page.locator('.req-tabs a[aria-current=page]').count(), 1);
    await page.click('#req-tab-equipment');
    await page.waitForLoadState();
    assert.ok(page.url().includes('tab=equipment'));
    assert.ok((await page.locator('.req').count()) > 0 && (await page.locator('.req').first().textContent()).includes('Equipment only'));
    // the reason form opens by #fragment without script
    const id = await page.locator('.req').first().getAttribute('id');
    await page.goto(base + `/requests?tab=equipment#reject-${id.replace('req-', '')}`);
    assert.ok(await page.locator(`#reject-${id.replace('req-', '')}`).isVisible(), 'reject form opens through :target');
    ok('tabs, category listing and reject form all work without JavaScript');
    await context.close();
  }

  console.log('C/D. Free rooms: yellow all-day state, legend, capacity (rep)');
  {
    const { context, page } = await login('rep');
    await page.goto(base + '/rooms');
    const rows = await page.evaluate(() => [...document.querySelectorAll('.rb-availability__room-row')].map((r) => {
      const track = r.querySelector('.rb-availability-track');
      const label = r.querySelector('.rb-availability__none');
      return {
        name: r.querySelector('strong').textContent.trim(), text: r.querySelector('.rb-availability__room-name').innerText,
        blockedTrack: !!track && track.classList.contains('rb-availability-track--blocked'),
        blockedLabel: !!label && label.classList.contains('rb-availability__blocked') ? label.textContent.trim() : null,
        free: r.querySelectorAll('.rb-availability-track__free').length, labelBg: label ? getComputedStyle(label).backgroundColor : null,
        trackBg: track ? getComputedStyle(track).backgroundImage : null, freeBg: r.querySelector('.rb-availability-track__free') ? getComputedStyle(r.querySelector('.rb-availability-track__free')).backgroundColor : null,
        otherNone: label && !label.classList.contains('rb-availability__blocked') ? label.textContent.trim() : null,
      };
    }));
    const blocked = rows.filter((r) => r.blockedLabel);
    assert.ok(blocked.length >= 2, 'fixture has blocked rooms');
    for (const r of blocked) {
      assert.ok(r.blockedTrack && r.free === 0, 'yellow track and no green in an all-day-blocked room');
      assert.match(r.blockedLabel, /^Unavailable all day — (Reason not specified|Assigned to .+)$/);
      assert.equal(r.labelBg, 'rgb(254, 243, 199)'); assert.match(r.trackBg, /repeating-linear-gradient/);
    }
    assert.ok(blocked.some((r) => /Reason not specified$/.test(r.blockedLabel)) && blocked.some((r) => /Assigned to ECE$/.test(r.blockedLabel)), 'both labels are kept');
    for (const r of rows.filter((x) => !x.blockedLabel)) {
      assert.ok(!r.blockedTrack, `${r.name}: partly busy / free rooms never turn yellow`);
      assert.equal(r.otherNone === null || /^No free time/.test(r.otherNone), true);
      if (r.free) assert.equal(r.freeBg, 'rgb(22, 101, 52)', 'free stretches stay green');
    }
    ok('yellow only on genuine all-day blocks (both reason labels kept); free stays green, other rooms neutral');
    const legend = await page.evaluate(() => { const l = document.querySelector('.rb-availability__keys'); return l && { label: l.getAttribute('aria-label'), items: [...l.querySelectorAll('li')].map((li) => li.textContent.trim()), hidden: [...l.querySelectorAll('.rb-availability__key')].every((k) => k.getAttribute('aria-hidden') === 'true') }; });
    assert.deepEqual(legend.items, ['Reported free', 'Not reported as free', 'Unavailable all day']);
    assert.ok(legend.hidden && legend.label);
    const near = await page.evaluate(() => { const h = document.querySelector('#room-results-title').getBoundingClientRect(), l = document.querySelector('.rb-availability__keys').getBoundingClientRect(); return l.top - h.bottom; });
    assert.ok(near >= 0 && near < 60, 'legend sits directly under the availability heading');
    ok('compact accessible legend (green / neutral / yellow) sits under the heading');
    const text = await page.locator('main').innerText();
    assert.ok(!/Capacity not specified/.test(text), 'no repeated "Capacity not specified"');
    assert.ok(/\b40 seats\b/.test(text) && /\b1 seat\b/.test(text), 'real capacities are shown with singular/plural');
    assert.ok(!/\b0 seats?\b/.test(text), 'NULL is never shown as zero');
    ok('capacity shown only when recorded ("40 seats", "1 seat"); missing capacity is simply absent');
    await snap(page, 'rooms-rep-desktop');
    for (const [name, [w, h]] of Object.entries({ phone: sizes.phone, narrow: sizes.narrow })) {
      await page.setViewportSize({ width: w, height: h });
      await page.goto(base + '/rooms');
      assert.equal(await overflow(page), 0, `${name}: no page-level horizontal overflow`);
      const legendVisible = await page.evaluate(() => { const r = document.querySelector('.rb-availability__keys').getBoundingClientRect(); return r.right <= innerWidth + 1; });
      assert.ok(legendVisible, `${name}: legend fits`);
      await snap(page, `rooms-rep-${name}`);
    }
    ok('Free rooms at 390 and 320: no page-level horizontal overflow, legend fits');
    await context.close();
  }

  console.log('E. Calendar event details (rep)');
  {
    const { context, page } = await login('rep');
    await page.goto(base + '/rooms');
    assert.equal(await page.locator('.rb-availability__calendar-details details, .rb-availability__calendar-details summary').count(), 0, 'no expandable dropdown');
    // the picked day (today) has events: they are shown immediately
    const shown = await page.evaluate(() => [...document.querySelectorAll('.rb-availability__calendar-events')].filter((g) => !g.hidden).map((g) => ({ h: g.querySelector('h3').textContent.trim(), c: g.querySelector('.rb-availability__events-count').textContent.trim(), n: g.querySelectorAll('.rb-availability__event-card').length })));
    assert.equal(shown.length, 1, 'one date shown');
    assert.equal(shown[0].c, `${shown[0].n} ${shown[0].n === 1 ? 'event' : 'events'}`, 'count matches the cards');
    ok(`the picked day shows immediately: ${shown[0].h}, ${shown[0].c}`);
    // choose another date: one click, details are there
    const target = page.locator('.rb-availability__event-count').filter({ hasText: '3 events' }).first();
    await target.scrollIntoViewIfNeeded();
    await target.click();
    const after = await page.evaluate(() => { const g = [...document.querySelectorAll('.rb-availability__calendar-events')].filter((x) => !x.hidden); return { n: g.length, cards: g[0].querySelectorAll('.rb-availability__event-card').length, count: g[0].querySelector('.rb-availability__events-count').textContent.trim(), focus: document.activeElement.tagName + ':' + document.activeElement.closest('.rb-availability__calendar-events')?.id, ctrl: document.querySelectorAll('.rb-availability__event-count[aria-current=true]').length, times: [...g[0].querySelectorAll('.rb-availability__event-time')].map((e) => e.textContent) }; });
    assert.equal(after.n, 1); assert.equal(after.cards, 3); assert.equal(after.count, '3 events'); assert.equal(after.ctrl, 1);
    assert.match(after.focus, /^H3:events-/, 'focus moves to the date heading');
    ok('one click on a date shows its heading, count and every event; focus moves to the heading');
    // chronological
    const mins = after.times.map((t) => { const m = /(\d+):(\d+)\s*(AM|PM)/.exec(t); return (+m[1] % 12 + (m[3] === 'PM' ? 12 : 0)) * 60 + +m[2]; });
    assert.deepEqual(mins, [...mins].sort((a, b) => a - b), 'events are in time order');
    ok('events are listed chronologically');
    // each card carries the same facts as before
    const card = await page.evaluate(() => { const c = document.querySelector('.rb-availability__calendar-events:not([hidden]) .rb-availability__event-card'); return { time: !!c.querySelector('.rb-availability__event-time').textContent.trim(), title: !!c.querySelector('.rb-availability__event-title').textContent.trim(), room: !!c.querySelector('.rb-availability__event-room').textContent.trim(), tip: !!c.querySelector('.rb-availability__event-tip').textContent.trim() }; });
    assert.deepEqual(card, { time: true, title: true, room: true, tip: true });
    ok('event cards show time, title, room and details');
    // keyboard: Enter on a count link
    const other = page.locator('.rb-availability__event-count').filter({ hasText: '1 event' }).first();
    await other.focus(); await page.keyboard.press('Enter');
    assert.equal(await page.locator('.rb-availability__calendar-events:not([hidden])').count(), 1);
    ok('keyboard activation of a date');
    // equal height cards on desktop, internal scrolling
    const h = await page.evaluate(() => { const a = document.querySelector('.rb-availability__calendar-details').getBoundingClientRect(), b = document.querySelector('.rb-availability__cal-card').getBoundingClientRect(), s = getComputedStyle(document.querySelector('.rb-availability__events-scroll')); return { dh: a.height, ch: b.height, dt: a.top, ct: b.top, oy: s.overflowY }; });
    assert.ok(Math.abs(h.dh - h.ch) < 1.5 && Math.abs(h.dt - h.ct) < 1.5, 'equal height, top-aligned');
    assert.equal(h.oy, 'auto');
    ok('calendar and details cards are equal height on desktop; the event list scrolls inside its card');
    // empty state: a day that has no events
    const emptyDay = await page.evaluate(() => { const td = [...document.querySelectorAll('td.rb-availability__pick')].find((t) => !t.querySelector('.rb-availability__event-count')); return td && td.querySelector('a').getAttribute('href'); });
    if (emptyDay) {
      await page.goto(emptyDay);
      const prompt = await page.locator('.rb-availability__event-prompt').isVisible();
      assert.ok(prompt, 'a day with no events shows the prompt');
      assert.equal(await page.locator('.rb-availability__calendar-events:not([hidden])').count(), 0);
      ok('a picked day without events shows a useful empty state');
    }
    await snap(page, 'calendar-rep-desktop');
    await context.close();
    // stacked on phones
    const phone = await login('rep', { viewport: { width: 390, height: 844 } });
    await phone.page.goto(base + '/rooms');
    const stack = await phone.page.evaluate(() => { const a = document.querySelector('.rb-availability__cal-card').getBoundingClientRect(), b = document.querySelector('.rb-availability__calendar-details').getBoundingClientRect(); return { stacked: b.top >= a.bottom - 1, sameLeft: Math.abs(a.left - b.left) < 2 }; });
    assert.ok(stack.stacked && stack.sameLeft);
    await snap(phone.page, 'calendar-rep-phone');
    ok('calendar and details stack naturally on a phone');
    await phone.context.close();
    // without JavaScript every date is listed and the count links jump to them
    const nojs = await login('rep', { javaScriptEnabled: false });
    await nojs.page.goto(base + '/rooms');
    const listed = await nojs.page.locator('.rb-availability__calendar-events').count();
    assert.ok(listed > 1);
    assert.equal(await nojs.page.locator('.rb-availability__calendar-events').evaluateAll((e) => e.filter((x) => x.offsetParent === null).length), 0, 'all dates visible without script');
    ok(`without JavaScript all ${listed} dates with events are listed`);
    await nojs.context.close();
  }

  console.log('F. Staff and admin dashboard statistics');
  for (const role of ['staff', 'admin']) {
    const { context, page } = await login(role);
    await page.goto(base + (role === 'staff' ? '/staff' : '/admin'));
    const cards = await page.evaluate(() => [...document.querySelectorAll('.rb-dashboard__stats .rb-stat-card')].map((c) => { const r = c.getBoundingClientRect(); return { label: c.querySelector('dt').textContent.trim(), value: c.querySelector('dd').textContent.trim(), note: c.querySelector('.rb-stat-card__note').textContent.trim(), w: Math.round(r.width), h: Math.round(r.height), top: Math.round(r.top) }; }));
    assert.equal(cards.length, 3);
    assert.deepEqual(cards.map((c) => c.label), ['Requests waiting for approval', 'Bookings approved or in use today', 'Equipment units in use']);
    assert.ok(cards.every((c) => /^\d+$/.test(c.value)));
    assert.ok(cards.every((c) => c.w === cards[0].w && c.h === cards[0].h && c.top === cards[0].top), `${role}: equal-size cards in one row`);
    const geometry = await page.evaluate(() => { const row = document.querySelector('.rb-dashboard__stats').getBoundingClientRect(), banner = document.querySelector('.rb-dashboard__banner').getBoundingClientRect(), shortcuts = document.querySelector('.rb-dashboard__shortcuts').getBoundingClientRect(); return { rowW: row.width, bannerW: banner.width, gapAbove: row.top - banner.bottom, gapBelow: shortcuts.top - row.bottom }; });
    assert.ok(Math.abs(geometry.rowW - geometry.bannerW) < 1.5, 'statistics row spans the banner width');
    assert.ok(geometry.gapAbove <= 20 && geometry.gapBelow <= 20, 'tight, even spacing between banner, statistics and shortcuts');
    assert.ok(await page.locator('.rb-dashboard__banner').isVisible() && await page.locator('.rb-dashboard__shortcuts h2:has-text("Ready to use")').isVisible());
    ok(`${role}: three equal full-width cards (${cards.map((c) => c.value).join(' / ')}), banner and Ready to use intact`);
    await snap(page, `dashboard-${role}-desktop`);
    // 2 columns then 1
    await page.setViewportSize({ width: 800, height: 900 });
    const two = await page.evaluate(() => [...document.querySelectorAll('.rb-dashboard__stats .rb-stat-card')].map((c) => Math.round(c.getBoundingClientRect().top)));
    assert.ok(two[0] === two[1] && two[2] > two[0], `${role}: two columns at 800px`);
    for (const [name, [w, h]] of Object.entries({ phone: sizes.phone, narrow: sizes.narrow })) {
      await page.setViewportSize({ width: w, height: h });
      const one = await page.evaluate(() => [...document.querySelectorAll('.rb-dashboard__stats .rb-stat-card')].map((c) => { const r = c.getBoundingClientRect(); return { top: Math.round(r.top), left: Math.round(r.left), w: Math.round(r.width) }; }));
      assert.ok(one[1].top > one[0].top && one[2].top > one[1].top && one.every((c) => c.left === one[0].left && c.w === one[0].w), `${name}: one column`);
      assert.equal(await overflow(page), 0, `${name}: no horizontal overflow`);
      await zoom(page, 200);
      assert.equal(await overflow(page), 0, `${name} at 200%: no horizontal overflow`);
      await snap(page, `dashboard-${role}-${name}`);
      await page.reload();
    }
    ok(`${role}: two columns at 800px, one column at 390 and 320, no overflow, including 200% text`);
    await context.close();
  }
  {
    // Authorization is unchanged: reps cannot open the staff or admin dashboards; lab staff cannot open the admin one.
    const rep = await login('rep');
    for (const url of ['/staff', '/admin']) assert.equal((await rep.page.goto(base + url)).status(), 403);
    await rep.context.close();
    const staff = await login('staff');
    assert.equal((await staff.page.goto(base + '/admin')).status(), 403);
    await staff.context.close();
    ok('dashboards stay role-protected (rep 403 on /staff and /admin, lab staff 403 on /admin)');
  }

  await browser.close();
  console.log(`\n${passed} browser checks passed`);
})().catch((error) => { console.error(error); process.exit(1); });
