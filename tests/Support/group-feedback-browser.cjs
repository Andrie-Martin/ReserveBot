// Real-browser checks for the groupmate feedback pass (login header/disclaimer, photo actions, compact email status, Free rooms
// calendar/timetable/drawer, My bookings on phones, Today review button, safe Delete for rooms and equipment).
//
//   node tests/Support/group-feedback-browser.cjs <path-to-playwright-core> <base-url> [screenshot-dir]
//
// Drives a RUNNING application on a DISPOSABLE database seeded with `php artisan db:seed` (admin@/staff@/rep1@/rep2@reservebot.test,
// password Password123!) plus: an UNCONNECTED-email rep (rep3@reservebot.test), several of rep1's bookings (upcoming with long
// text and equipment, and past/rejected/cancelled ones), and one pending booking for a day in the next two weeks.
// It DOES write to that disposable database (uploads and removes a photo, adds then deletes a room and an equipment item through the
// real forms) and refuses to run against anything but 127.0.0.1/localhost outside the developer's own ports. Not part of PHPUnit.
const { chromium } = require(process.argv[2]);
const assert = require('node:assert/strict');
const zlib = require('node:zlib');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const base = (process.argv[3] || '').replace(/\/$/, '');
const shots = process.argv[4] || '';
assert.ok(/^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/.test(base), 'Refusing to run against anything but a local disposable app (127.0.0.1 / localhost).');
assert.ok(!/:(8000|8001|8002)$/.test(base), "Refusing to run against the developer's own app ports.");

const accounts = { rep: 'rep1@reservebot.test', rep2: 'rep2@reservebot.test', unconnected: 'rep3@reservebot.test', staff: 'staff@reservebot.test', admin: 'admin@reservebot.test' };
const sizes = { desktop: [1438, 900], phone: [390, 844], narrow: [320, 640] };
const tomorrow = new Date(Date.now() + 86400e3).toISOString().slice(0, 10);
let passed = 0;
const ok = (name) => { passed++; console.log('  PASS', name); };

// A real 240x240 PNG (the photo rules require at least 100x100).
const png = (() => {
  const w = 240, raw = Buffer.concat(Array.from({ length: w }, () => Buffer.concat([Buffer.from([0]), Buffer.alloc(w * 3, 0x6b)])));
  const crc = (buf) => { let c, t = []; for (let n = 0; n < 256; n++) { c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; t[n] = c >>> 0; } let x = 0xffffffff; for (const b of buf) x = t[(x ^ b) & 255] ^ (x >>> 8); return (x ^ 0xffffffff) >>> 0; };
  const chunk = (type, data) => { const len = Buffer.alloc(4); len.writeUInt32BE(data.length); const body = Buffer.concat([Buffer.from(type), data]); const c = Buffer.alloc(4); c.writeUInt32BE(crc(body)); return Buffer.concat([len, body, c]); };
  const ihdr = Buffer.alloc(13); ihdr.writeUInt32BE(w, 0); ihdr.writeUInt32BE(w, 4); ihdr[8] = 8; ihdr[9] = 2;
  return Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), chunk('IHDR', ihdr), chunk('IDAT', zlib.deflateSync(raw)), chunk('IEND', Buffer.alloc(0))]);
})();
const photoFile = path.join(os.tmpdir(), `rb-feedback-photo-${process.pid}.png`);
fs.writeFileSync(photoFile, png);

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.RB_BROWSER_EXECUTABLE || undefined });
  const open = async (role, viewport = { width: 1438, height: 900 }, options = {}) => {
    const context = await browser.newContext({ viewport, ...options });
    const page = await context.newPage();
    if (role) {
      await page.goto(base + '/login');
      await page.fill('input[name=email]', accounts[role]);
      await page.fill('input[name=password]', 'Password123!');
      await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    }
    return { context, page };
  };
  const go = async (page, url) => {
    await page.waitForLoadState('load').catch(() => {});   // a redirect after a form POST may still be settling
    let r; try { r = await page.goto(base + url); } catch (e) { if (!/interrupted by another navigation/.test(e.message)) throw e; await page.waitForLoadState('load'); r = await page.goto(base + url); }
    await page.waitForLoadState('networkidle'); return r;
  };
  const overflow = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  const textZoom = (page, percent) => page.addStyleTag({ content: `html{font-size:${percent}%!important}` });
  const snap = async (page, name) => { if (shots) await page.screenshot({ path: `${shots}/${name}.png`, fullPage: false }); };
  const box = (page, sel) => page.locator(sel).first().boundingBox();
  const overlap = (a, b) => !(a.x + a.width <= b.x + 0.5 || b.x + b.width <= a.x + 0.5 || a.y + a.height <= b.y + 0.5 || b.y + b.height <= a.y + 0.5);
  const noOverflowAnywhere = async (page, url, label) => {
    for (const [name, [w, h]] of Object.entries(sizes)) {
      await page.setViewportSize({ width: w, height: h }); await go(page, url);
      assert.ok((await overflow(page)) <= 0, `${label}: no horizontal page overflow at ${name}`);
    }
    await page.setViewportSize({ width: 390, height: 844 }); await go(page, url); await textZoom(page, 200);
    assert.ok((await overflow(page)) <= 0, `${label}: no horizontal page overflow at 200% text`);
    await page.setViewportSize({ width: 1438, height: 900 });
  };

  console.log('1 + 6. Login: no student-design disclaimer, no floating header descriptor (login, forgot, reset)');
  {
    const { context, page } = await open(null);
    for (const url of ['/login', '/forgot-password', '/reset-password/abc123?email=x@example.com']) {
      for (const [name, [w, h]] of Object.entries(sizes)) {
        await page.setViewportSize({ width: w, height: h }); await go(page, url);
        const body = await page.locator('body').innerText();
        assert.ok(!/Unofficial|student design|Not an official/i.test(body), `${url} ${name}: disclaimer gone`);
        assert.ok(!/Room & equipment reservation system/i.test(body), `${url} ${name}: descriptor gone`);
        assert.ok(/ReserveBot/.test(body) && /PUP College of Engineering/.test(body), `${url} ${name}: truthful identity kept`);
        assert.ok((await overflow(page)) <= 0, `${url} ${name}: no overflow`);
      }
    }
    await page.setViewportSize({ width: 1438, height: 900 }); await go(page, '/login');
    const brand = await box(page, '.rb-brand'), header = await box(page, '.rb-header');
    assert.ok(header.height < 130, 'header height is not inflated');
    assert.ok(await page.locator('input[name=email]').isVisible() && await page.locator('input[name=password]').isVisible(), 'login form intact');
    await snap(page, 'login-desktop');
    await page.setViewportSize({ width: 390, height: 844 }); await go(page, '/login'); await snap(page, 'login-phone');
    ok('guest pages');
    await context.close();
  }

  console.log('2. Account photo: Replace/Remove share a row; Remove cannot submit the upload form');
  for (const role of ['rep2', 'staff', 'admin']) {
    const { context, page } = await open(role);
    await go(page, '/account');
    // upload
    if (await page.locator('button:has-text("Remove photo")').count()) {
      await Promise.all([page.waitForNavigation(), page.click('button:has-text("Remove photo")')]);
      await go(page, '/account');
    }
    assert.equal(await page.locator('.rb-account-photo__forms .rb-button').count(), 1, `${role}: only Upload without a photo`);
    await page.setInputFiles('#photo', photoFile);
    await Promise.all([page.waitForNavigation(), page.click('button:has-text("Upload photo")')]);
    await go(page, '/account');
    for (const [name, [w, h]] of Object.entries(sizes)) {
      await page.setViewportSize({ width: w, height: Math.max(h, 1200) }); await go(page, '/account');
      const btns = await page.locator('.rb-account-photo__forms .rb-button').evaluateAll(els => els.map(e => { const r = e.getBoundingClientRect(), c = getComputedStyle(e); return { t: e.textContent.trim(), top: Math.round(r.top), h: Math.round(r.height), w: Math.round(r.width), radius: c.borderRadius, tag: e.tagName, form: e.form && e.form.getAttribute('action'), method: e.form && (e.form.querySelector('input[name=_method]')?.value || e.form.method) }; }));
      assert.deepEqual(btns.map(b => b.t), ['Replace photo', 'Remove photo'], `${role} ${name}: both actions`);
      assert.ok(btns.every(b => b.tag === 'BUTTON'), 'real buttons');
      if (name === 'narrow') { assert.ok(btns[1].top > btns[0].top, `${role} ${name}: wraps to a clean stack`); assert.equal(btns[0].w, btns[1].w, `${role} ${name}: equal width when stacked`); }
      else assert.equal(btns[0].top, btns[1].top, `${role} ${name}: same row`);
      assert.equal(btns[0].h, btns[1].h, `${role} ${name}: same height`);
      assert.equal(btns[0].radius, btns[1].radius, `${role} ${name}: same radius`);
      assert.ok(btns[0].h >= 44, `${role} ${name}: touch target`);
      assert.ok(/\/account\/photo$/.test(btns[0].form) && /\/account\/photo$/.test(btns[1].form) && btns[1].method === 'DELETE' && btns[0].method !== 'DELETE', 'Replace is in the upload form, Remove in its own DELETE form');
      assert.ok((await overflow(page)) <= 0, `${role} ${name}: no overflow`);
      if (role === 'rep2') await snap(page, `account-photo-${name}`);
    }
    await page.setViewportSize({ width: 1438, height: 900 }); await go(page, '/account');
    // Remove sends only the DELETE request, never the upload
    const sent = []; page.on('request', r => { if (r.method() === 'POST') sent.push(r.url() + ' ' + (r.postData() || '').slice(0, 400)); });
    await Promise.all([page.waitForNavigation(), page.click('button:has-text("Remove photo")')]);
    assert.equal(sent.length, 1, `${role}: exactly one submission`);
    assert.ok(sent[0].includes('/account/photo') && /_method=DELETE/.test(sent[0]) && !/name="photo"|filename/.test(sent[0]), `${role}: it was the DELETE form`);
    await go(page, '/account');
    assert.equal(await page.locator('button:has-text("Remove photo")').count(), 0, `${role}: photo removed`);
    // validation error state still renders with the new row
    await page.setInputFiles('#photo', { name: 'tiny.png', mimeType: 'image/png', buffer: Buffer.from('not an image') });
    await Promise.all([page.waitForNavigation(), page.click('button:has-text("Upload photo")')]);
    await page.locator('.rb-error-summary, .rb-field__error').first().waitFor({ state: 'visible', timeout: 8000 });   // the redirected page may still be painting
    assert.ok(/JPG, PNG or WebP|photo/i.test(await page.locator('.rb-account-photo').innerText()), `${role}: validation error shown`);
    await context.close();
  }
  ok('photo actions for rep / lab staff / admin');

  console.log('3 + 7. Email status: compact connected and not-connected states');
  {
    const connected = await open('rep'); await go(connected.page, '/account');
    const row = await connected.page.locator('.rb-account-email').first().boundingBox();
    assert.ok(row.height <= 80, `connected status is a slim row (${Math.round(row.height)}px)`);
    const text = await connected.page.locator('.rb-account-email').innerText();
    assert.ok(/Email connected/.test(text) && /rep1@reservebot\.test/.test(text) && /since/.test(text), 'connected facts');
    assert.equal(await connected.page.locator('.rb-account-email form').count(), 0, 'no resend when connected');
    await snap(connected.page, 'email-connected-desktop');
    await connected.context.close();

    for (const [name, [w, h]] of Object.entries(sizes)) {
      const { context, page } = await open('unconnected', { width: w, height: h });
      for (const url of ['/rooms', '/account']) {
        await go(page, url);
        const status = url === '/account' ? '.rb-account-email' : '.rb-email-notice .rb-status';
        const el = page.locator(status).first();
        const b = await el.boundingBox();
        const t = await el.innerText();
        assert.ok(/Email not connected/.test(t) && /rep3@reservebot\.test/.test(t) && /Connect my email/.test(t), `${url} ${name}: instruction and address`);
        assert.equal(await page.locator(`${status} form[action$="/account/email-link"] button`).count(), 1, `${url} ${name}: resend action kept`);
        assert.equal(await el.getAttribute('role'), url === '/rooms' ? 'status' : null, `${url}: live-region role only on the global notice`);
        const msg = await box(page, `${status} .rb-status__body`), btn = await box(page, `${status} button`);
        if (w >= 1000) { assert.ok(b.height <= 90, `${url} ${name}: compact (${Math.round(b.height)}px)`); assert.ok(Math.abs((msg.y + msg.height / 2) - (btn.y + btn.height / 2)) < 30, `${url} ${name}: message and button share the row`); }
        else { assert.ok(btn.y >= msg.y + msg.height - 1, `${url} ${name}: button sits under the message`); assert.ok(Math.abs(btn.x - b.x) < 24 && btn.width > b.width * 0.8, `${url} ${name}: button aligned and wide`); }
        assert.ok((await overflow(page)) <= 0, `${url} ${name}: no overflow`);
        await snap(page, `email-unconnected-${url.slice(1)}-${name}`);
      }
      await context.close();
    }
    // the resend button still posts to the same route (rate limited / flash as before)
    const { context, page } = await open('unconnected'); await go(page, '/rooms');
    const [resp] = await Promise.all([page.waitForResponse(r => r.url().endsWith('/account/email-link') && r.request().method() === 'POST'), page.click('.rb-email-notice button')]);
    assert.ok([200, 302].includes(resp.status()), 'resend still works');
    await go(page, '/rooms');
    assert.ok(/./.test(await page.locator('.rb-email-notice').innerText()), 'notice (with the connect_note, if any) still shown');
    await context.close();
    ok('email status');
  }

  console.log('4. Free rooms calendar: today / selected, month arrows');
  for (const role of ['rep', 'staff', 'admin']) {
    const { context, page } = await open(role);
    await go(page, '/rooms');
    const todayCell = await page.locator('td.rb-availability__today').first();
    assert.equal(await todayCell.count(), 1);
    const disc = await todayCell.locator('.rb-availability__daynum').evaluate(e => { const c = getComputedStyle(e); return { bg: c.backgroundColor, color: c.color, radius: c.borderRadius, w: e.getBoundingClientRect().width, h: e.getBoundingClientRect().height }; });
    assert.ok(disc.bg === 'rgb(107, 25, 50)' && disc.color === 'rgb(255, 255, 255)' && parseFloat(disc.radius) >= 12, `${role}: today is a filled maroon disc`);
    const date = (await page.locator('td.rb-availability__today .sr-only').first().innerText());
    assert.ok(/today/.test(date), 'screen-reader text says today');
    const sel = await page.locator('td.rb-availability__selected').first().evaluate(e => getComputedStyle(e).boxShadow);
    assert.ok(/inset/.test(sel), `${role}: selected has a ring + base bar (shape, not only colour)`);
    // pick a different day: today and selected separate
    await go(page, `/rooms?date=${tomorrow}`);
    if (await page.locator('td.rb-availability__selected').count()) {
      const same = await page.locator('td.rb-availability__today.rb-availability__selected').count();
      assert.equal(same, 0, `${role}: different days, different cells`);
    }
    // arrows: focusable, named, explained, not silent
    const arrows = await page.locator('.rb-availability__cal-nav .rb-availability__cal-arrow').evaluateAll(els => els.map(e => ({ tag: e.tagName, name: (e.getAttribute('aria-label') || e.textContent || '').trim(), disabled: e.getAttribute('aria-disabled'), why: e.getAttribute('aria-describedby') && document.getElementById(e.getAttribute('aria-describedby'))?.textContent.trim(), href: e.getAttribute('href') })));
    assert.equal(arrows.length, 2);
    for (const a of arrows) assert.ok(a.name.length > 3 && (a.href || (a.disabled === 'true' && a.why && a.why.length > 10)), `${role}: each arrow is a named link or an explained disabled control (${JSON.stringify(a)})`);
    await page.locator('.rb-availability__cal-nav .rb-availability__btn-ghost').focus();
    await page.keyboard.press('Tab');
    assert.ok(await page.evaluate(() => document.activeElement.classList.contains('rb-availability__cal-arrow')), `${role}: arrows are in the keyboard order`);
    const hint = await page.locator('.rb-availability__cal-hint').innerText();
    assert.ok(/from today up to/.test(hint), 'booking window stated');
    // Today link returns to the current day and keeps the search
    await go(page, `/rooms?date=${tomorrow}&from=09:00&to=10:00`);
    const todayHref = await page.locator('.rb-availability__cal-nav .rb-availability__btn-ghost').getAttribute('href');
    assert.ok(/from=09%3A00&to=10%3A00/.test(todayHref) || /from=09:00&to=10:00/.test(todayHref), 'Today keeps the search');
    for (const [name, [w, h]] of Object.entries(sizes)) { await page.setViewportSize({ width: w, height: h }); await go(page, '/rooms'); assert.ok((await overflow(page)) <= 0, `${role} ${name}: no overflow`); }
    if (role === 'rep') { await page.setViewportSize({ width: 1438, height: 1500 }); await go(page, `/rooms?date=${tomorrow}`); await page.locator('.rb-availability__cal-card').screenshot({ path: shots ? `${shots}/calendar-desktop.png` : undefined }).catch(() => {}); }
    await context.close();
  }
  ok('calendar');

  console.log('9 + 10 + 11. Free rooms timetable: desktop alignment, phone cards, Schedule label, drawer');
  for (const role of ['rep', 'staff', 'admin']) {
    const { context, page } = await open(role, { width: 1438, height: 900 });
    await go(page, `/rooms?date=${tomorrow}`);
    const rows = await page.locator('.rb-availability__board .rb-availability__room-row').evaluateAll(els => els.map(li => {
      const r = s => { const e = li.querySelector(s); if (!e) return null; const b = e.getBoundingClientRect(); return { x: Math.round(b.x * 10) / 10, w: Math.round(b.width * 10) / 10, y: b.y, h: b.height }; };
      return { id: li.id, name: r('.rb-availability__room-name strong'), track: r('.rb-availability-track'), book: r('.rb-availability__room-book .rb-button'), sched: r('.rb-availability__btn-schedule'), label: li.querySelector('.rb-availability__btn-schedule')?.textContent.replace(/\s+/g, ' ').trim() };
    }));
    const axis = await page.evaluate(() => { const a = document.querySelector('.rb-availability__axis > div'), t = document.querySelector('.rb-availability__ticks'); const b = t.getBoundingClientRect(); return { x: b.x, w: b.width, labels: [...t.children].map(s => ({ text: s.textContent, left: parseFloat(s.style.left) })) }; });
    assert.ok(rows.length >= 5, 'rows rendered');
    const tracks = rows.filter(r => r.track), books = rows.filter(r => r.book), scheds = rows.filter(r => r.sched);
    for (const r of tracks) { assert.ok(Math.abs(r.track.x - axis.x) < 1 && Math.abs(r.track.w - axis.w) < 1, `${role} ${r.id}: track = axis column`); assert.ok(Math.abs(r.name.x - rows[0].name.x) < 1, `${role} ${r.id}: room names align`); }
    for (const list of [books, scheds]) { for (const r of list) { const k = list === books ? 'book' : 'sched'; assert.ok(Math.abs(r[k].x - list[0][k].x) < 1 && Math.abs(r[k].w - list[0][k].w) < 1, `${role} ${r.id}: ${k} cell identical`); } }
    if (role !== 'rep') assert.equal(books.length, 0, 'Book stays rep-only');
    assert.ok(scheds.length > 0 && scheds.every(r => /^Schedule\s*\d+$/.test(r.label.replace(/View /, '').replace(/ for .*/, '').trim()) || /Schedule/.test(r.label)), 'Schedule label');
    const labels = await page.locator('.rb-availability__btn-schedule').first().evaluate(e => ({ visible: [...e.childNodes].filter(n => n.nodeType === 3 || (n.nodeType === 1 && !n.classList.contains('sr-only') && n.getAttribute('aria-hidden') !== 'true')).map(n => n.textContent.trim()).join(''), name: e.textContent.replace(/\s+/g, ' ').trim() }));
    assert.equal(labels.visible, 'Schedule', `${role}: visible label is "Schedule"`);
    assert.ok(/^View Schedule/i.test(labels.name) && /for CEA|for \w+\d*/.test(labels.name), `${role}: descriptive accessible name (${labels.name})`);
    // ticks (marks) sit at the same percentages as the axis labels
    const tickX = await page.locator('.rb-availability-track:not(.rb-availability-track--blocked)').first().evaluate(t => [...t.querySelectorAll('.rb-availability-track__tick')].map(e => Math.round((e.getBoundingClientRect().x - t.getBoundingClientRect().x) / t.getBoundingClientRect().width * 100)));
    assert.deepEqual(tickX, axis.labels.map(l => Math.round(l.left)).filter(p => p > 0 && p < 100), `${role}: tick marks match the axis labels`);
    assert.ok((await overflow(page)) <= 0, `${role}: no page overflow`);
    if (role === 'rep') await snap(page, 'timetable-desktop');

    // drawer: open, structure, close, focus return
    const trigger = page.locator('.rb-availability__btn-schedule').first();
    await trigger.focus(); await page.keyboard.press('Enter'); await page.waitForTimeout(400);
    const dlg = page.locator('dialog[open]').first();
    assert.ok(await dlg.isVisible(), `${role}: drawer opens`);
    const parts = await dlg.evaluate(d => { const h = [...d.querySelectorAll('h3')].map(e => e.textContent.replace(/\s+/g, ' ').trim()); const free = d.querySelector('.rb-availability__schedule-section--free'); const c = free && getComputedStyle(free); return { h, borderTop: c && c.borderTopWidth + ' ' + c.borderTopColor, order: !!(d.querySelector('.rb-availability__schedule-section') && free && (d.querySelector('.rb-availability__schedule-section').compareDocumentPosition(free) & 4)) }; });
    assert.ok(/^Scheduled/.test(parts.h[0]) && parts.h[1] === 'Free times' && parts.order, `${role}: two headed sections in order (${parts.h})`);
    assert.ok(/^1px /.test(parts.borderTop) && /rgb\(2[0-9][0-9], ?2[0-9][0-9], ?2[0-9][0-9]\)|rgb\(233, 226, 218\)/.test(parts.borderTop), `${role}: divider uses the neutral border token (${parts.borderTop})`);
    if (role === 'rep') await snap(page, 'drawer-desktop');
    await page.keyboard.press('Escape'); await page.waitForTimeout(250);
    assert.equal(await page.locator('dialog[open]').count(), 0, `${role}: Escape closes`);
    assert.ok(await trigger.evaluate(e => e === document.activeElement), `${role}: focus returns to the Schedule button`);
    // Close link works as well
    await trigger.click(); await page.waitForTimeout(300);
    await page.locator('dialog[open] [data-rb-drawer-close]').click(); await page.waitForTimeout(250);
    assert.equal(await page.locator('dialog[open]').count(), 0, `${role}: Close closes`);

    // phone cards
    for (const [name, [w, h]] of [['phone', sizes.phone], ['narrow', sizes.narrow]]) {
      await page.setViewportSize({ width: w, height: h }); await go(page, `/rooms?date=${tomorrow}`);
      assert.ok((await overflow(page)) <= 0, `${role} ${name}: no page overflow`);
      const cards = await page.locator('.rb-availability__room-row').evaluateAll(els => els.map(li => { const b = li.getBoundingClientRect(); const bar = li.querySelector('.rb-availability-track')?.getBoundingClientRect(); const btns = [...li.querySelectorAll('.rb-availability__room-actions .rb-button')].map(e => { const r = e.getBoundingClientRect(); return { h: r.height, right: r.right }; }); return { l: b.left, r: b.right, bar: bar && { l: bar.left, r: bar.right }, btns, state: li.querySelector('.rb-availability__state')?.textContent.trim(), scale: li.querySelector('.rb-availability__scale')?.textContent.replace(/\s+/g, ' ').trim(), free: [...li.querySelectorAll('.rb-availability__free-chip')].map(c => c.textContent.trim()) }; }));
      assert.ok(cards.length >= 5);
      for (const c of cards) { assert.ok(c.l >= 0 && c.r <= w, `${role} ${name}: card inside the viewport`); if (c.bar) assert.ok(c.bar.l >= c.l && c.bar.r <= c.r, 'bar inside card'); for (const b of c.btns) { assert.ok(b.h >= 43.5, `${role} ${name}: 44px touch targets`); assert.ok(b.right <= c.r + 0.5, 'buttons inside card'); } assert.ok(c.state, 'availability state text'); }
      assert.ok(cards.some(c => /\d free window/.test(c.state) && c.free.length > 0 && /AM/.test(c.scale || '')), 'free windows + scale + written ranges');
      assert.equal(await page.locator('.rb-availability__board').evaluate(e => e.scrollWidth <= e.clientWidth + 1), true, `${role} ${name}: nothing scrolls sideways`);
      if (name === 'phone') { await snap(page, `timetable-phone-${role}`); }
      if (name === 'phone' && role === 'rep') {
        await page.locator('.rb-availability__btn-schedule').first().tap().catch(() => page.locator('.rb-availability__btn-schedule').first().click());
        await page.waitForTimeout(400);
        const full = await page.locator('dialog[open]').evaluate(d => { const r = d.getBoundingClientRect(); return { w: r.width, h: r.height }; });
        assert.ok(full.w >= w - 1 && full.h >= h - 1, 'phone drawer is full screen');
        assert.ok(await page.locator('dialog[open] .rb-availability__schedule-section--free').isVisible(), 'drawer sections visible on phone');
        await snap(page, 'drawer-phone');
        await page.keyboard.press('Escape'); await page.waitForTimeout(200);
      }
    }
    // text enlargement and browser zoom
    await page.setViewportSize({ width: 390, height: 844 }); await go(page, `/rooms?date=${tomorrow}`); await textZoom(page, 200);
    assert.ok((await overflow(page)) <= 0, `${role}: no overflow at 200% text`);
    await context.close();
    const zoomed = await open(role, { width: 719, height: 450 }, { deviceScaleFactor: 2 });   // 1438 CSS px at 200% browser zoom
    await go(zoomed.page, `/rooms?date=${tomorrow}`);
    assert.ok((await overflow(zoomed.page)) <= 0, `${role}: no overflow at 200% zoom`);
    assert.ok(await zoomed.page.locator('.rb-availability__room-row').first().isVisible(), 'rows usable at 200% zoom');
    await zoomed.context.close();
  }
  ok('timetable, drawer, calendar across roles and sizes');

  console.log('8. My bookings (phone): no overlaps, same data and actions');
  {
    const { context, page } = await open('rep');
    await go(page, '/my-bookings');
    const desktop = await page.locator('.brow').evaluateAll(els => els.map(e => ({ when: e.querySelector('.brow-when').innerText.replace(/\s+/g, ' '), title: e.querySelector('.brow-title').innerText, status: e.querySelector('.status-badge').innerText, cancel: !!e.querySelector('.brow-cancel') })));
    const cols = await page.locator('.brow').first().evaluate(e => getComputedStyle(e).gridTemplateColumns.split(' ').length);
    assert.equal(cols, 3, 'desktop keeps the three-column row');
    for (const [name, [w, h]] of Object.entries(sizes)) {
      await page.setViewportSize({ width: w, height: 900 }); await go(page, '/my-bookings');
      assert.ok((await overflow(page)) <= 0, `${name}: no overflow`);
      const phone = await page.locator('.brow').evaluateAll(els => els.map(e => ({ when: e.querySelector('.brow-when').innerText.replace(/\s+/g, ' '), title: e.querySelector('.brow-title').innerText, status: e.querySelector('.status-badge').innerText, cancel: !!e.querySelector('.brow-cancel') })));
      assert.deepEqual(phone, desktop, `${name}: identical data and actions`);
      if (w <= 640) {
        const geo = await page.locator('.brow').evaluateAll(els => els.map(e => { const rect = s => { const n = e.querySelector(s); return n && n.getBoundingClientRect().toJSON(); }; return { card: e.getBoundingClientRect().toJSON(), when: rect('.brow-when'), what: rect('.brow-what'), status: rect('.status-badge'), cancel: rect('.brow-cancel'), whenW: e.querySelector('.brow-when').scrollWidth <= e.querySelector('.brow-when').clientWidth + 1 }; }));
        for (const g of geo) {
          for (const [a, b] of [['when', 'what'], ['when', 'status'], ['status', 'what'], ['what', 'cancel'], ['status', 'cancel']]) if (g[a] && g[b]) assert.ok(!overlap(g[a], g[b]), `${name}: ${a} and ${b} do not overlap`);
          assert.ok(g.when.width > 70, `${name}: date column is not squeezed (${Math.round(g.when.width)}px)`);
          for (const k of ['when', 'what', 'status', 'cancel']) if (g[k]) assert.ok(g[k].left >= g.card.left - 0.5 && g[k].right <= g.card.right + 0.5, `${name}: ${k} inside its card`);
          if (g.cancel) assert.ok(g.cancel.height >= 43.5, 'cancel is a 44px target');
        }
        const lists = await page.locator('.blist').evaluateAll(els => els.map(e => e.className));
        assert.ok(lists.length === 2 && /past/.test(lists[1]), 'upcoming and past stay separate');
        await snap(page, `my-bookings-${name}`);
      }
    }
    await page.setViewportSize({ width: 390, height: 844 }); await go(page, '/my-bookings'); await textZoom(page, 200);
    assert.ok((await overflow(page)) <= 0, '200% text: no overflow');
    const links = await page.locator('.brow-cancel').evaluateAll(els => els.map(e => e.getAttribute('href')));
    assert.ok(links.every(h => /\/bookings\/\d+#cancel$/.test(h)), 'cancel destinations unchanged');
    await context.close();
    ok('my bookings');
  }

  console.log('12. Today: Review request');
  for (const role of ['staff', 'admin']) {
    const { context, page } = await open(role);
    // the pending booking for the next two weeks
    let found = null;
    for (let d = 0; d < 14 && !found; d++) {
      const day = new Date(Date.now() + 86400e3 * d).toISOString().slice(0, 10);
      await go(page, `/today?date=${day}`);
      if (await page.locator('a.tcard-link:has-text("Review request")').count()) found = day;
    }
    assert.ok(found, `${role}: a pending booking exists in the data`);
    const link = page.locator('a.tcard-link:has-text("Review request")').first();
    const info = await link.evaluate(e => { const c = getComputedStyle(e), r = e.getBoundingClientRect(); return { tag: e.tagName, href: e.getAttribute('href'), border: c.borderTopWidth, radius: c.borderRadius, h: r.height, deco: c.textDecorationLine, bg: c.backgroundColor, color: c.color }; });
    assert.equal(info.tag, 'A'); assert.ok(/\/bookings\/\d+$/.test(info.href));
    assert.equal(info.border, '1px'); assert.equal(info.radius, '10px'); assert.ok(info.h >= 43.5); assert.equal(info.deco, 'none'); assert.equal(info.bg, 'rgb(255, 255, 255)');
    const sibling = await page.locator('.tcard-side .status-badge').first().boundingBox(), lb = await link.boundingBox();
    assert.ok(Math.abs((sibling.x + sibling.width) - (lb.x + lb.width)) < 4, `${role}: aligned with the status`);
    const sent = []; page.on('request', r => { if (r.method() !== 'GET') sent.push(r.url()); });
    for (const [name, [w, h]] of Object.entries(sizes)) { await page.setViewportSize({ width: w, height: h }); await go(page, `/today?date=${found}`); assert.ok((await overflow(page)) <= 0, `${role} ${name}: no overflow`); if (role === 'staff') await snap(page, `today-${name}`); }
    await page.setViewportSize({ width: 1438, height: 900 }); await go(page, `/today?date=${found}`);
    await page.locator('a.tcard-link:has-text("Review request")').first().focus();
    assert.ok(await page.evaluate(() => getComputedStyle(document.activeElement).outlineStyle !== 'none'), `${role}: visible focus`);
    await page.keyboard.press('Enter');
    await page.waitForURL(/\/bookings\/\d+$/, { timeout: 8000 }).catch(() => {});
    assert.ok(/\/bookings\/\d+$/.test(page.url()), `${role}: opens the booking page (got ${page.url()})`);
    assert.equal(sent.length, 0, `${role}: clicking it submitted nothing`);
    assert.ok(await page.locator('text=Approve').first().isVisible(), 'the review controls are on that page');
    await context.close();
  }
  ok('today review button');

  console.log('5. Manage rooms / equipment: Delete (disposable records created through the real forms)');
  for (const role of ['staff', 'admin']) {
    const { context, page } = await open(role);
    const roomName = `Zdel-${role}-${Date.now() % 100000}`;
    await go(page, '/manage/rooms');
    await page.locator('summary:has-text("Add a room")').click(); await page.fill('#add-name', roomName);
    await page.click('button:has-text("Add room")');
    const row = page.locator(`tr:has-text("${roomName}")`);
    await row.first().waitFor({ state: 'visible', timeout: 8000 });
    assert.equal(await row.locator('.row-delete').count(), 1, `${role}: Delete offered for an unused room`);
    const dims = await row.locator('.row-actions a').evaluateAll(els => els.map(e => { const r = e.getBoundingClientRect(); return { t: e.textContent.trim(), top: Math.round(r.top), h: Math.round(r.height), w: Math.round(r.width) }; }));
    assert.equal(dims.length, 2); assert.equal(dims[0].top, dims[1].top); assert.equal(dims[0].h, dims[1].h);
    assert.equal(await page.locator('tr:has-text("CEA300") .row-delete').count(), 0, `${role}: no Delete for a room with history`);
    if (role === 'staff') await snap(page, 'manage-rooms-delete-desktop');
    for (const [name, [w, h]] of Object.entries(sizes)) { await page.setViewportSize({ width: w, height: h }); await go(page, '/manage/rooms'); assert.ok((await overflow(page)) <= 0, `${role} ${name}: manage rooms no overflow`); }
    await page.setViewportSize({ width: 1438, height: 900 }); await go(page, '/manage/rooms');
    // Delete is navigation; the confirmation is separate
    const sent = []; page.on('request', r => { if (r.method() !== 'GET') sent.push(r.method() + ' ' + r.url()); });
    await page.locator(`tr:has-text("${roomName}") .row-delete`).click(); await page.waitForLoadState('networkidle');
    assert.ok(/\/manage\/rooms\/\d+#delete$/.test(page.url()) && sent.length === 0, `${role}: the list link only navigates`);
    assert.ok(await page.locator('#delete').isVisible());
    const submit = page.locator('#delete button:has-text("Delete")');
    assert.ok((await page.locator('#delete').innerText()).includes(roomName), 'confirmation names the resource');
    // without the tick box the browser blocks submission
    await submit.click(); assert.ok(await page.locator('#delete input[name=confirm]').evaluate(e => !e.checkValidity()), 'tick box required');
    assert.equal(sent.length, 0);
    await snap(page, `delete-confirmation-${role}`);
    await page.check('#delete input[name=confirm]');
    await submit.click();
    await page.waitForURL(/\/manage\/rooms$/, { timeout: 8000 }).catch(() => {});
    await page.waitForLoadState('networkidle');
    assert.ok(/\/manage\/rooms$/.test(page.url()), `${role}: back to the list (got ${page.url()}: ${(await page.locator('body').innerText()).slice(0, 300).replace(/\n/g, ' ')})`);
    assert.ok(/Deleted /.test(await page.locator('.success').first().innerText()), `${role}: success message`);
    assert.equal(await page.locator(`tr:has-text("${roomName}")`).count(), 0, `${role}: the room is gone`);
    assert.deepEqual(sent.map(s => s.split(' ')[0]), ['POST'], 'one POST (the DELETE form is method-spoofed)');
    // a room with history: edit page explains and offers no delete form
    await go(page, '/manage/rooms'); const href = await page.locator('tr:has-text("CEA300") a.row-edit').getAttribute('href'); await go(page, new URL(href, base).pathname);
    assert.equal(await page.locator('#delete form').count(), 0); assert.ok(/can’t be deleted/.test(await page.locator('#delete').innerText()));
    if (role === 'staff') await snap(page, 'delete-blocked-desktop');
    // equipment
    const itemName = `Zitem-${role}-${Date.now() % 100000}`;
    await go(page, '/manage/equipment');
    await page.locator('summary:has-text("Add an item")').click(); await page.fill('#item-name', itemName);
    await page.click('button:has-text("Add item")');
    const erow = page.locator(`tr:has-text("${itemName}")`);
    await erow.first().waitFor({ state: 'visible', timeout: 8000 });
    assert.equal(await erow.locator('.row-delete').count(), 1);
    for (const [name, [w, h]] of Object.entries(sizes)) { await page.setViewportSize({ width: w, height: h }); await go(page, '/manage/equipment'); assert.ok((await overflow(page)) <= 0, `${role} ${name}: manage equipment no overflow`); }
    await page.setViewportSize({ width: 1438, height: 900 }); await go(page, '/manage/equipment');
    await page.locator(`tr:has-text("${itemName}") .row-delete`).click(); await page.waitForLoadState('networkidle');
    await page.check('#delete input[name=confirm]');
    await page.locator('#delete button:has-text("Delete")').click();
    await page.waitForURL(/\/manage\/equipment$/, { timeout: 8000 }).catch(() => {});
    await page.waitForLoadState('networkidle');
    assert.ok(/\/manage\/equipment$/.test(page.url()), `${role}: back to the equipment list (got ${page.url()})`);
    assert.equal(await page.locator(`tr:has-text("${itemName}")`).count(), 0, `${role}: item gone`);
    assert.ok(await page.locator('.mcat-title').count() > 0, 'categories untouched');
    await context.close();
  }
  {
    // a rep cannot reach any of it; another role's pages are unchanged
    const { context, page } = await open('rep');
    const r = await page.goto(base + '/manage/rooms'); assert.equal(r.status(), 403, 'rep: manage rooms forbidden');
    await context.close();
  }
  ok('delete');

  console.log('13/14. Shared consistency across roles: same shell identity, no regressions on key pages');
  for (const role of ['rep', 'staff', 'admin']) {
    const { context, page } = await open(role);
    for (const url of ['/', '/rooms', '/account', ...(role === 'rep' ? ['/my-bookings', '/book'] : ['/today', '/requests', '/manage/rooms', '/manage/equipment'])]) {
      const res = await go(page, url);
      assert.ok(res.status() < 400, `${role} ${url}: ${res.status()}`);
      assert.ok(await page.locator('.rb-masthead').first().innerText().then(t => /College of Engineering/i.test(t)), `${role} ${url}: global identity`);
      assert.ok(/Computer Engineering Department|Block rep|Lab staff|Admin/.test(await page.locator('body').innerText()), `${role} ${url}: user-specific department/role text retained`);
      assert.ok((await overflow(page)) <= 0, `${role} ${url}: no overflow at 1438`);
    }
    await context.close();
  }
  ok('role consistency');

  fs.rmSync(photoFile, { force: true });
  await browser.close();
  console.log(`\nALL ${passed} GROUPS PASSED`);
})().catch((e) => { try { fs.rmSync(photoFile, { force: true }); } catch {} console.error('\nFAIL:', e.message); process.exit(1); });
