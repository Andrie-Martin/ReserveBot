// Real-browser regression checks for the Booking Details action panels: Approve / Reject for lab staff and admins, and
// Cancel for representatives (always-visible panels, no dropdowns).
//
//   node tests/Support/booking-actions-browser.cjs <path-to-playwright-core> <base-url> '<ids-json>' [screenshot-dir]
//   ids-json: {"pending":4,"other":5,"late":6,"confirmed":7}
//     pending   a Pending booking of rep1 with a room and equipment, more than the cancel cut-off away
//     other     a Pending booking of rep2 (the same department)
//     late      a Pending booking of rep1 that starts inside the cancel cut-off
//     confirmed a Confirmed booking of rep1, more than the cut-off away
//   RB_MUTATE=1 also performs the real approve, reject and cancel submissions (each on its own booking, once only):
//     pending is rejected by lab staff, other is approved by an admin, confirmed is cancelled by rep1.
//
// Drives a RUNNING application on a DISPOSABLE database seeded with `php artisan db:seed` and the example ECE department
// (staff.ece@reservebot.test, with its first-login password change already cleared). Never run it against real data.
// Needs an installed Playwright and a browser (RB_BROWSER_EXECUTABLE for a Chromium binary). Not part of PHPUnit.
const { chromium } = require(process.argv[2]);
const assert = require('node:assert/strict');
const base = (process.argv[3] || '').replace(/\/$/, '');
const ids = JSON.parse(process.argv[4] || '{}');
const shots = process.argv[5] || '';
assert.ok(/^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/.test(base), 'Refusing to run against anything but a local disposable app (127.0.0.1 / localhost).');
assert.ok(!/:(8000|8001|8002)$/.test(base), "Refusing to run against the developer's own app ports.");
for (const key of ['pending', 'other', 'late', 'confirmed']) assert.ok(Number.isInteger(ids[key]), 'ids-json needs ' + key);

const accounts = { rep: 'rep1@reservebot.test', rep2: 'rep2@reservebot.test', staff: 'staff@reservebot.test', admin: 'admin@reservebot.test', staffEce: 'staff.ece@reservebot.test' };
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
  const box = async (page, selector) => (await page.locator(selector).first().boundingBox());
  const snap = async (page, name) => { if (shots) await page.screenshot({ path: `${shots}/${name}.png`, fullPage: true }); };
  const posts = (page) => { const sent = []; page.on('request', r => { if (r.method() === 'POST') sent.push(r.url()); }); return sent; };
  const open = async (page, id) => { await page.goto(`${base}/bookings/${id}`); await page.waitForLoadState('networkidle'); };
  const shift = (page) => page.evaluate(() => new Promise(resolve => {
    let total = 0; const seen = new PerformanceObserver(list => { for (const e of list.getEntries()) if (!e.hadRecentInput) total += e.value; });
    seen.observe({ type: 'layout-shift', buffered: true }); setTimeout(() => { seen.disconnect(); resolve(total); }, 600);
  }));

  for (const role of ['staff', 'admin']) {
    console.log(`A. Approve / Reject panels (${role})`);
    const { context, page } = await login(role);
    const sent = posts(page);
    await open(page, ids.pending);
    assert.equal(await page.locator('details, summary').count(), 0, 'no dropdown or disclosure on the page');
    assert.ok(await page.locator('#reject-reason').isVisible(), 'the reason field is visible without clicking anything');
    assert.ok(await page.locator('.action-panel--approve button').isVisible() && await page.locator('.action-panel--reject button').isVisible());
    ok('both panels and the reason field are visible immediately, with no dropdown');

    const a = await box(page, '.action-panel--approve'), r = await box(page, '.action-panel--reject');
    assert.ok(a.x < r.x && Math.abs(a.y - r.y) < 2, 'approve on the left, reject on the right, tops aligned');
    const ratio = r.width / a.width;
    assert.ok(ratio > 1.8 && ratio < 2.2, 'reject is about twice as wide as approve, got ' + ratio.toFixed(2));
    assert.ok(Math.abs(a.height - r.height) < 2, 'equal heights');
    const headA = await box(page, '.action-panel--approve h3'), headR = await box(page, '.action-panel--reject h3');
    assert.ok(Math.abs(headA.y - headR.y) < 2, 'headings align');
    const btnA = await box(page, '.action-panel--approve button'), btnR = await box(page, '.action-panel--reject button');
    assert.ok(Math.abs(btnA.y - btnR.y) < 2, 'the two actions sit on one line');
    ok('desktop: approve about one third, reject two thirds; headings and buttons align');

    const colours = await page.evaluate(() => {
      const c = (s) => getComputedStyle(document.querySelector(s));
      return { approveBg: c('.action-panel--approve .btn-approve').backgroundColor, rejectBg: c('.action-panel--reject .btn-reject').backgroundColor, rejectText: c('.action-panel--reject .btn-reject').color };
    });
    assert.notEqual(colours.approveBg, 'rgb(255, 255, 255)', 'approve is the filled, primary action');
    assert.equal(colours.rejectBg, 'rgb(255, 255, 255)', 'reject is outlined, not a solid red block');
    assert.notEqual(colours.rejectText, 'rgb(255, 255, 255)');
    ok('Approve is primary (filled); Reject is destructive but outlined');

    // Focusing, clicking the label or typing in the field decides nothing.
    await page.locator('#reject-reason').focus();
    await page.locator('label[for=reject-reason]').click();
    await page.keyboard.type('Draft wording');
    await page.waitForTimeout(300);
    assert.equal(sent.length, 0, 'no request was sent');
    await open(page, ids.pending);
    assert.equal(await page.locator('.action-panel--approve').count(), 1, 'still pending after revealing and typing');
    ok('focusing or typing in the reason never approves or rejects');

    // The browser refuses an empty reason (required) before anything is sent.
    await page.locator('.action-panel--reject button').click();
    assert.equal(await page.locator('#reject-reason').evaluate(e => e.validity.valueMissing), true);
    await page.waitForTimeout(300);
    assert.equal(sent.length, 0, 'an empty reason is stopped in the browser');
    // Past the browser check (as a script or old browser would), the server refuses it and the error sits under the field.
    await page.evaluate(() => document.querySelector('#reject-reason').removeAttribute('required'));
    await Promise.all([page.waitForURL(`${base}/bookings/${ids.pending}`), page.locator('.action-panel--reject button').click()]);
    await page.waitForLoadState('networkidle');
    assert.ok(await page.locator('#reject-reason-error').isVisible());
    assert.equal(await page.locator('#reject-reason').getAttribute('aria-describedby'), 'reject-reason-error');
    assert.equal(await page.locator('#reject-reason').getAttribute('aria-invalid'), 'true');
    const err = await box(page, '#reject-reason-error'), field = await box(page, '#reject-reason');
    assert.ok(err.y >= field.y + field.height - 1 && err.y - (field.y + field.height) < 24, 'the error sits directly under the reason field');
    assert.ok(err.x >= r.x && err.x < r.x + r.width, 'the error is inside the reject panel, not the approve panel');
    assert.equal(await page.evaluate(() => document.activeElement.id), 'reject-reason', 'focus lands on the field that needs fixing');
    assert.equal(await page.locator('.action-panel--approve .field-error').count(), 0);
    await snap(page, `${role}-reject-error-desktop`);
    ok('missing reason: stopped by the browser, and by the server with the error beside the field and focus on it');

    // Keyboard: the field, then Reject, are reachable in order and show a visible focus ring.
    await open(page, ids.pending);
    await page.locator('.action-panel--approve button').focus();
    await page.keyboard.press('Tab');
    assert.equal(await page.evaluate(() => document.activeElement.id), 'reject-reason');
    assert.notEqual(await page.evaluate(() => getComputedStyle(document.activeElement).outlineStyle), 'none', 'visible focus ring on the field');
    await page.keyboard.press('Tab');
    assert.equal(await page.evaluate(() => document.activeElement.textContent.trim()), 'Reject booking');
    assert.notEqual(await page.evaluate(() => getComputedStyle(document.activeElement).outlineStyle), 'none', 'visible focus ring on the button');
    ok('keyboard order: Approve, reason, Reject, each with a focus ring');

    for (const [name, [w, h]] of Object.entries(sizes)) {
      await page.setViewportSize({ width: w, height: h });
      await open(page, ids.pending);
      assert.equal(await overflow(page), 0, `${name}: no horizontal overflow`);
      if (name !== 'desktop') {
        const pa = await box(page, '.action-panel--approve'), pr = await box(page, '.action-panel--reject');
        assert.ok(pr.y > pa.y + pa.height - 1 && Math.abs(pa.x - pr.x) < 2 && Math.abs(pa.width - pr.width) < 2, `${name}: panels stack at one width`);
        const b = await box(page, '.action-panel--reject button'), f = await box(page, '#reject-reason');
        assert.ok(Math.abs(b.width - f.width) < 2, `${name}: the button is as wide as the field`);
        assert.ok(b.height >= 40, `${name}: touch-sized button`);
      }
      await snap(page, `${role}-pending-${name}`);
    }
    await page.setViewportSize({ width: 320, height: 640 });
    await zoom(page, 200);
    await page.waitForTimeout(200);
    assert.equal(await overflow(page), 0, '320px at 200% text: no horizontal overflow');
    await snap(page, `${role}-pending-narrow-200`);
    await page.setViewportSize({ width: 1438, height: 900 });
    await open(page, ids.pending);
    await zoom(page, 200);
    await page.waitForTimeout(200);
    assert.equal(await overflow(page), 0, '200% text on desktop: no horizontal overflow');
    // Browser page zoom of 200% on a 1438px window is a 719px-wide layout viewport; the panels stack there.
    await page.setViewportSize({ width: 719, height: 450 });
    await open(page, ids.pending);
    assert.equal(await overflow(page), 0, '200% page zoom: no horizontal overflow');
    const stacked = await page.evaluate(() => { const a = document.querySelector('.action-panel--approve').getBoundingClientRect(), r = document.querySelector('.action-panel--reject').getBoundingClientRect(); return r.top >= a.bottom - 1; });
    assert.ok(stacked, 'at 200% page zoom the panels stack instead of squeezing');
    ok('390, 320, 200% text and 200% page zoom: stacked, full-width actions, no overflow');
    await page.setViewportSize({ width: 1438, height: 900 });
    await open(page, ids.pending);
    const cls = await shift(page);
    assert.ok(cls < 0.01, 'layout shift ' + cls);
    ok('no layout shift while the page settles (CLS ' + cls.toFixed(3) + ')');
    await context.close();
  }

  console.log('A. Without JavaScript (lab staff)');
  {
    const { context, page } = await login('staff', { javaScriptEnabled: false });
    await open(page, ids.pending);
    assert.ok(await page.locator('#reject-reason').isVisible() && await page.locator('.action-panel--approve button').isVisible());
    await page.locator('#reject-reason').fill('');
    await page.locator('.action-panel--reject button').click(); // required: refused by the browser, nothing sent
    assert.equal(new URL(page.url()).pathname, `/bookings/${ids.pending}`);
    ok('both forms are usable with JavaScript off');
    await context.close();
  }

  console.log('B. Representative cancel card');
  {
    const { context, page } = await login('rep');
    const sent = posts(page);
    await open(page, ids.pending);
    assert.equal(await page.locator('details, summary').count(), 0);
    assert.ok(await page.locator('#cancel #cancel-reason').isVisible(), 'the cancellation form is visible immediately');
    assert.equal(await page.locator('.cancel-card').count(), 1, 'one card');
    const deadline = await page.locator('.cancel-card__deadline').innerText();
    assert.ok(/You can cancel until/.test(deadline), deadline);
    const info = await box(page, '.cancel-card__info'), form = await box(page, '.cancel-card__form'), card = await box(page, '.cancel-card');
    assert.ok(info.x < form.x && Math.abs(info.y - form.y) < 12, 'information on the left, form on the right');
    assert.ok(form.width <= 600 && (await box(page, '#cancel-reason')).width <= 576, 'the textarea is not excessively wide');
    assert.ok(card.height < 260, 'the card is compact: ' + card.height);
    const btn = await box(page, '.cancel-card button'), area = await box(page, '#cancel-reason');
    assert.ok(Math.abs(btn.x - area.x) < 2, 'the submit button lines up with the field');
    ok('desktop: one card, deadline and explanation left, reason and submit right, compact');

    await page.locator('#cancel-reason').focus();
    await page.keyboard.type('Typing only');
    await page.waitForTimeout(300);
    assert.equal(sent.length, 0);
    await page.evaluate(() => { document.querySelector('#cancel-reason').value = ''; });
    await page.locator('.cancel-card button').click();
    assert.equal(await page.locator('#cancel-reason').evaluate(e => e.validity.valueMissing), true);
    await page.waitForTimeout(300);
    assert.equal(sent.length, 0, 'an empty reason is stopped in the browser');
    await page.evaluate(() => document.querySelector('#cancel-reason').removeAttribute('required'));
    await Promise.all([page.waitForURL(`${base}/bookings/${ids.pending}`), page.locator('.cancel-card button').click()]);
    await page.waitForLoadState('networkidle');
    assert.ok(await page.locator('#cancel-reason-error').isVisible());
    assert.equal(await page.locator('#cancel-reason').getAttribute('aria-describedby'), 'cancel-reason-error');
    assert.equal(await page.evaluate(() => document.activeElement.id), 'cancel-reason');
    const e2 = await box(page, '#cancel-reason-error'), f2 = await box(page, '#cancel-reason');
    assert.ok(e2.y >= f2.y + f2.height - 1 && e2.y - (f2.y + f2.height) < 24, 'error directly under the field');
    await snap(page, 'rep-cancel-error-desktop');
    ok('missing reason: refused, error beside the field, nothing cancelled');

    for (const [name, [w, h]] of Object.entries(sizes)) {
      await page.setViewportSize({ width: w, height: h });
      await open(page, ids.pending);
      assert.equal(await overflow(page), 0, `${name}: no horizontal overflow`);
      if (name !== 'desktop') {
        const i = await box(page, '.cancel-card__info'), f = await box(page, '.cancel-card__form');
        assert.ok(f.y > i.y + i.height - 1 && Math.abs(i.x - f.x) < 2, `${name}: information above the form`);
        const b = await box(page, '.cancel-card button'), t = await box(page, '#cancel-reason');
        assert.ok(Math.abs(b.width - t.width) < 2 && b.height >= 40, `${name}: easy to use`);
      }
      await snap(page, `rep-cancel-${name}`);
    }
    await page.setViewportSize({ width: 320, height: 640 });
    await zoom(page, 200);
    await page.waitForTimeout(200);
    assert.equal(await overflow(page), 0, '320px at 200% text: no overflow');
    await snap(page, 'rep-cancel-narrow-200');
    await page.setViewportSize({ width: 1438, height: 900 });
    await open(page, ids.pending);
    await zoom(page, 200);
    await page.waitForTimeout(200);
    assert.equal(await overflow(page), 0, '200% text on desktop: no overflow');
    await page.setViewportSize({ width: 1438, height: 900 });
    await open(page, ids.pending);
    const cls = await shift(page);
    assert.ok(cls < 0.01, 'layout shift ' + cls);
    ok('390, 320 and 200% text: stacked, full-width action, no overflow, no layout shift');

    // After the cut-off: only the explanation.
    await open(page, ids.late);
    assert.equal(await page.locator('#cancel, form[action$="/cancel"], textarea').count(), 0, 'no cancel form after the deadline');
    assert.ok(/too late to cancel/.test(await page.locator('.notice').innerText()));
    await snap(page, 'rep-toolate-desktop');
    ok('past the deadline: the explanation only, no form');

    // The deep link used by "Cancel…" on My bookings.
    await page.goto(`${base}/bookings/${ids.pending}#cancel`);
    assert.ok(await page.locator('#cancel').isVisible());
    ok('the #cancel link still lands on the card');
    await context.close();
  }

  console.log('B. Without JavaScript (representative)');
  {
    const { context, page } = await login('rep', { javaScriptEnabled: false });
    await open(page, ids.pending);
    assert.ok(await page.locator('#cancel-reason').isVisible() && await page.locator('.cancel-card button').isVisible());
    ok('the cancel form is usable with JavaScript off');
    await context.close();
  }

  console.log('Authorization and CSRF');
  {
    const staff = await login('staff');
    const token = await staff.page.locator('input[name=_token]').first().getAttribute('value');
    const naked = await staff.context.request.post(`${base}/bookings/${ids.pending}/approve`, { maxRedirects: 0 });
    assert.equal(naked.status(), 419, 'a POST without the CSRF token is refused');
    const nakedReject = await staff.context.request.post(`${base}/bookings/${ids.pending}/reject`, { form: { reason: 'No token' }, maxRedirects: 0 });
    assert.equal(nakedReject.status(), 419);
    await open(staff.page, ids.pending);
    assert.equal(await staff.page.locator('.action-panel--approve').count(), 1, 'still pending after the refused posts');
    ok('approve and reject without a CSRF token get 419 and change nothing');
    await staff.context.close();

    const ece = await login('staffEce');
    const t = await ece.page.locator('input[name=_token]').first().getAttribute('value');
    const res = await ece.page.goto(`${base}/bookings/${ids.pending}`);
    assert.equal(res.status(), 404, 'another department cannot open the booking');
    for (const action of ['approve', 'reject']) {
      const post = await ece.context.request.post(`${base}/bookings/${ids.pending}/${action}`, { form: { _token: t, reason: 'Not mine' }, maxRedirects: 0 });
      assert.equal(post.status(), 404, `another department cannot ${action}`);
    }
    ok('lab staff from another department: 404 on the page, approve and reject');
    await ece.context.close();

    const rep = await login('rep');
    const rt = await rep.page.locator('input[name=_token]').first().getAttribute('value');
    for (const action of ['approve', 'reject']) {
      const post = await rep.context.request.post(`${base}/bookings/${ids.pending}/${action}`, { form: { _token: rt, reason: 'Not allowed' }, maxRedirects: 0 });
      assert.equal(post.status(), 403, `a rep cannot ${action}`);
    }
    await open(rep.page, ids.pending);
    assert.equal(await rep.page.locator('.action-panel--approve, .action-panel--reject').count(), 0, 'a rep never sees the review panels');
    const own = await rep.context.request.post(`${base}/bookings/${ids.pending}/cancel`, { form: { reason: 'No token' }, maxRedirects: 0 });
    assert.equal(own.status(), 419, 'cancel without a token is refused');
    const foreign = await rep.context.request.post(`${base}/bookings/${ids.other}/cancel`, { form: { _token: rt, reason: 'Not mine' }, maxRedirects: 0 });
    assert.equal(foreign.status(), 404, "a rep cannot cancel another rep's booking");
    assert.equal((await rep.page.goto(`${base}/bookings/${ids.other}`)).status(), 404);
    ok('reps: 403 on approve/reject, 419 without a token, 404 on someone else\'s booking');
    await rep.context.close();

    const rep2 = await login('rep2');
    assert.equal((await rep2.page.goto(`${base}/bookings/${ids.pending}`)).status(), 404);
    await rep2.context.close();
    ok('another rep cannot open the booking');
  }

  console.log('Unchanged: staff Move / Cancel on a confirmed booking');
  {
    const { context, page } = await login('staff');
    await open(page, ids.confirmed);
    assert.equal(await page.locator('.action-panel--approve').count(), 0);
    assert.ok(await page.locator('a[href="#move-panel"]').isVisible() && await page.locator('a[href="#cancel-panel"]').isVisible());
    assert.ok(!(await page.locator('#move-panel').isVisible()));
    await page.locator('a[href="#move-panel"]').click();
    assert.ok(await page.locator('#move-panel').isVisible() && !(await page.locator('#cancel-panel').isVisible()));
    ok('Move / Cancel reveal workflow is unchanged');
    await context.close();
  }

  if (process.env.RB_MUTATE === '1') {
    console.log('Real submissions (RB_MUTATE=1)');
    const staff = await login('staff');
    await open(staff.page, ids.pending);
    await staff.page.locator('#reject-reason').fill('Lab closed for maintenance');
    await Promise.all([staff.page.waitForURL(u => !u.pathname.startsWith('/bookings/') || true), staff.page.locator('.action-panel--reject button').click()]);
    await staff.page.waitForLoadState('networkidle');
    await open(staff.page, ids.pending);
    assert.equal(await staff.page.locator('.action-panel--reject').count(), 0, 'rejected: the panels are gone');
    assert.ok(/Rejected/i.test(await staff.page.locator('.status-badge').innerText()));
    ok('lab staff rejected a booking with a reason');
    await staff.context.close();
    const rep = await login('rep');
    await open(rep.page, ids.pending);
    assert.ok((await rep.page.content()).includes('Lab closed for maintenance'), 'the rep sees the reason');
    await rep.context.close();
    const admin = await login('admin');
    await open(admin.page, ids.other);
    await Promise.all([admin.page.waitForNavigation(), admin.page.locator('.action-panel--approve button').click()]);
    await open(admin.page, ids.other);
    assert.ok(/Approved|Confirmed/i.test(await admin.page.locator('.status-badge').innerText()));
    ok('an admin approved a booking');
    await admin.context.close();
    const rep1 = await login('rep');
    await open(rep1.page, ids.confirmed);
    await rep1.page.locator('#cancel-reason').fill('Class moved online');
    await Promise.all([rep1.page.waitForNavigation(), rep1.page.locator('.cancel-card button').click()]);
    assert.ok(/cancelled/i.test(await rep1.page.locator('.status-badge').innerText()));
    assert.equal(await rep1.page.locator('.cancel-card').count(), 0);
    ok('the rep cancelled a confirmed booking with a reason');
    await rep1.context.close();
  }

  await browser.close();
  console.log(`\n${passed} checks passed`);
})().catch(e => { console.error(e); process.exit(1); });
