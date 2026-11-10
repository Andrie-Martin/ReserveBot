// Real-browser check of the sidebar mascot: page-aware starting pose, hover/keyboard previews, persistence, one silhouette,
// responsive footer, zoom, reduced motion and no dependence on any global motion layer.
//
//   node tests/Support/mascot-browser.cjs <path-to-playwright-core>
//
// DB-free and server-free (see mascot-harness.cjs). Asserts states and geometry, never a frame rate.
const { chromium } = require(process.argv[2]);
const assert = require('node:assert/strict');
const { origin, serve } = require('./mascot-harness.cjs');
let checks = 0;
const ok = (name) => { checks++; console.log('  PASS', name); };
const wait = (ms) => new Promise((r) => setTimeout(r, ms));
const links = { rep: ['Home', 'Free rooms', 'Book', 'My bookings'], staff: ['Home', 'Free rooms', 'Today', 'Requests', 'Manage rooms', 'Equipment'], admin: ['Home', 'Free rooms', 'Today', 'Requests', 'Manage rooms', 'Equipment', 'Accounts'], college: ['Departments'] };
const poseOf = { 'Home': 'wave', 'Free rooms': 'door', 'Book': 'calendar', 'My bookings': 'clipboard', 'Today': 'calendar', 'Requests': 'clipboard', 'Manage rooms': 'key', 'Equipment': 'tool', 'Accounts': 'badge', 'Departments': 'badge' };
// Written independently of the Blade mapping on purpose: page -> [active link, starting pose].
const pagePose = {
  rep: { '/': ['Home', 'wave'], '/rooms': ['Free rooms', 'door'], '/book': ['Book', 'calendar'], '/my-bookings': ['My bookings', 'clipboard'], '/my-bookings/9': ['My bookings', 'clipboard'], '/account': [null, 'idle'] },
  staff: { '/': ['Home', 'wave'], '/rooms': ['Free rooms', 'door'], '/today': ['Today', 'calendar'], '/requests': ['Requests', 'clipboard'], '/manage/rooms': ['Manage rooms', 'key'], '/manage/timetable': ['Manage rooms', 'key'], '/manage/equipment': ['Equipment', 'tool'], '/account': [null, 'idle'] },
  admin: { '/': ['Home', 'wave'], '/accounts': ['Accounts', 'badge'], '/manage/equipment': ['Equipment', 'tool'], '/requests': ['Requests', 'clipboard'] },
  college: { '/college': ['Departments', 'badge'], '/account': [null, 'idle'] },
};
const link = (page, label) => page.locator('#rb-navigation a.rb-nav-item', { hasText: new RegExp('^\\s*' + label + '\\b') }).first();
const pose = (page) => page.locator('[data-rb-mascot]').getAttribute('data-pose');
async function open(browser, site, options = {}) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, ...options });
  await serve(ctx, site);
  const page = await ctx.newPage();
  page.errors = [];
  page.on('pageerror', (e) => page.errors.push(e.message));
  return { ctx, page };
}
const settle = async (page) => { await page.waitForLoadState('load'); await page.evaluate(() => document.fonts.ready); await wait(300); };

(async () => {
  const browser = await chromium.launch();
  console.log('Chromium', browser.version());
  for (const site of Object.keys(pagePose)) {
    const { ctx, page } = await open(browser, site);
    for (const [url, [active, expected]] of Object.entries(pagePose[site])) {
      await page.goto(origin + url); await settle(page);
      assert.equal(await pose(page), expected, `${site} ${url}: starts in the ${expected} pose`);
      assert.equal(await page.locator('#rb-navigation a[aria-current="page"]').count(), active ? 1 : 0, `${site} ${url}: aria-current only for a real nav destination`);
    }
    ok(`${site}: every page starts with its own navigation item's pose (child pages inherit; pages without one are idle)`);
    await page.goto(origin + Object.keys(pagePose[site])[0]); await settle(page);
    assert.deepEqual(await page.$$eval('#rb-navigation a.rb-nav-item', (a) => a.map((x) => x.querySelector('.rb-nav-item__label').textContent.trim())), links[site]);
    for (const label of links[site]) { await link(page, label).hover(); assert.equal(await pose(page), poseOf[label], `${site}: hovering ${label}`); }
    ok(`${site}: hovering each of its ${links[site].length} link(s) shows that link's pose`);
    assert.deepEqual(page.errors, []);
    await ctx.close();
  }
  {
    const { ctx, page } = await open(browser, 'admin');
    await page.goto(origin + '/rooms'); await settle(page);
    assert.equal(await pose(page), 'door');
    await link(page, 'Equipment').hover(); await wait(60);
    assert.equal(await pose(page), 'tool');
    await page.mouse.move(800, 500); await wait(400);
    assert.equal(await pose(page), 'tool', 'pointer leaving the sidebar keeps the hovered pose');
    await page.locator('main h1').first().click(); await page.keyboard.press('Escape'); await wait(300);
    assert.equal(await pose(page), 'tool', 'clicking inside the page keeps it');
    await link(page, 'Requests').hover(); await page.mouse.move(900, 400); await wait(300);
    assert.equal(await pose(page), 'clipboard');
    await Promise.all([page.waitForURL('**/requests'), link(page, 'Requests').click()]); await settle(page);
    assert.equal(await pose(page), 'clipboard', 'the destination page starts from its own pose');
    await link(page, 'Accounts').hover(); await page.goto(origin + '/today'); await settle(page);
    assert.equal(await pose(page), 'calendar', 'a hovered pose never survives into an unrelated page load');
    ok('hover persists after the pointer leaves and after clicks in the page; the next page load resets to its own pose');
    await page.goto(origin + '/rooms'); await settle(page);
    const seen = [];
    for (let i = 0; i < 40 && seen.length < 7; i++) {
      await page.keyboard.press('Tab');
      const f = await page.evaluate(() => { const a = document.activeElement; return a.closest('#rb-navigation') ? a.querySelector('.rb-nav-item__label').textContent.trim() : null; });
      if (f) { seen.push(f); assert.equal(await pose(page), poseOf[f], `keyboard focus on ${f}`); }
    }
    assert.deepEqual(seen, links.admin, 'Tab reaches every sidebar link in order');
    for (let i = 0; i < 6; i++) await page.keyboard.press('Tab');
    assert.equal(await pose(page), 'badge', 'moving focus on into the page keeps the last pose');
    ok('keyboard focus changes the pose and it persists when focus moves on');
    const inert = await page.evaluate(() => { const m = document.querySelector('[data-rb-mascot]'); return { hidden: m.getAttribute('aria-hidden'), pe: getComputedStyle(m).pointerEvents, focusables: m.querySelectorAll('a,button,[tabindex]').length }; });
    assert.deepEqual(inert, { hidden: 'true', pe: 'none', focusables: 0 });
    ok('the mascot stays aria-hidden, non-interactive and outside the tab order');
    await ctx.close();
  }
  {
    const { ctx, page } = await open(browser, 'admin');
    await page.goto(origin + '/'); await settle(page);
    const measure = () => page.evaluate(() => {
      const r = (sel) => { const b = document.querySelector(sel).getBoundingClientRect(); return [b.x, b.y, b.width, b.height].map((n) => Math.round(n * 100) / 100); };
      return { stage: r('.rb-mascot__stage'), art: r('.rb-mascot__art'), body: r('.rbm-maroon[rx="25"]'), face: r('.rbm-cream[rx="17"]'), aerial: r('.rbm-aerial'), shadow: r('.rbm-shadow'), pill: r('.rbm-pill') };
    });
    await page.addStyleTag({ content: '.rb-mascot *{transition:none!important;animation:none!important}' });
    const base = await measure();
    for (const p of ['idle', 'wave', 'door', 'calendar', 'clipboard', 'key', 'tool', 'badge', 'attend']) {
      await page.evaluate((p) => { document.querySelector('[data-rb-mascot]').dataset.pose = p; }, p);
      assert.deepEqual(await measure(), base, `pose ${p}: body, face, antenna, shadow, art box and footer are exactly where they were`);
    }
    ok('all 9 poses keep the same body, faceplate, antenna, shadow, artwork box and footer position');
    const size = await page.evaluate(() => document.querySelector('.rb-mascot__art').getBoundingClientRect().height);
    assert.ok(size >= 88 && size <= 92, 'about 90px tall: ' + size);
    const centre = await page.evaluate(() => { const a = document.querySelector('.rb-mascot__art').getBoundingClientRect(); const s = document.querySelector('.rb-sidebar').getBoundingClientRect(); const cs = getComputedStyle(document.querySelector('.rb-sidebar')); return { c: a.x + a.width / 2, s: (s.x + parseFloat(cs.paddingLeft) + s.right - parseFloat(cs.paddingRight)) / 2 }; });
    assert.ok(Math.abs(centre.c - centre.s) < 1, 'centred in the sidebar footer');
    ok(`figure is ${size}px tall and centred in the sidebar content`);
    for (const [w, h, shown] of [[1440, 900, true], [1440, 700, true], [1440, 640, false], [1440, 600, false], [1280, 720, true], [1024, 768, false], [768, 1024, false], [390, 844, false], [320, 640, false]]) {
      await page.setViewportSize({ width: w, height: h }); await wait(150);
      const s = await page.evaluate(() => { const st = document.querySelector('.rb-mascot__stage'); const sb = document.querySelector('#rb-navigation'); const last = [...sb.querySelectorAll('a.rb-nav-item')].pop().getBoundingClientRect(); const bs = st.getBoundingClientRect(); return { visible: getComputedStyle(st).display !== 'none' && bs.height > 0 && getComputedStyle(sb).display !== 'none', overlap: bs.height > 0 && bs.top < last.bottom, overflow: document.documentElement.scrollWidth - innerWidth }; });
      assert.equal(s.visible, shown, `${w}x${h}: mascot ${shown ? 'shown' : 'hidden'}`);
      assert.ok(!s.overlap && s.overflow <= 0, `${w}x${h}: never overlaps a link, no horizontal overflow`);
    }
    ok('mascot shows only with room (desktop, tall enough); hidden on tablet, phone and short windows; never overlaps links');
    await ctx.close();
  }
  {
    // The door is held, not pointed at: the gripping hand turns with the right arm through the 180ms change (never detached) and
    // ends on the door's edge, and the door sits on the body, not beside it.
    const { ctx, page } = await open(browser, 'admin');
    await page.goto(origin + '/'); await settle(page);
    await link(page, 'Free rooms').hover();
    const samples = [];
    for (let i = 0; i < 9; i++) {
      samples.push(await page.evaluate(() => { const t = (s) => getComputedStyle(document.querySelector(s)).transform; return { arm: t('.rbm-arm--r:not(.rbm-grip)'), grip: t('.rbm-grip') }; }));
      await wait(25);
    }
    assert.ok(samples.every((s) => s.arm === s.grip), 'the gripping hand always has exactly the right arm\'s transform');
    assert.ok(new Set(samples.map((s) => s.arm)).size > 1, 'and the arm really moved during the change');
    await wait(300);
    const geo = await page.evaluate(() => { const hand = document.querySelector('.rbm-grip circle').getBoundingClientRect(); const door = document.querySelector('.rbm-prop--door rect').getBoundingClientRect(); const body = document.querySelector('.rbm-maroon[rx="25"]').getBoundingClientRect(); const op = getComputedStyle(document.querySelector('.rbm-grip')).opacity; return { hx: hand.x + hand.width / 2, hy: hand.y + hand.height / 2, door: [door.x, door.y, door.right, door.bottom], body: [body.x, body.y, body.right, body.bottom], op }; });
    assert.equal(geo.op, '1');
    assert.ok(geo.hx >= geo.door[2] - 6 && geo.hx <= geo.door[2] + 3 && geo.hy > geo.door[1] && geo.hy < geo.door[3], 'the hand sits on the door\'s right edge: ' + JSON.stringify(geo));
    assert.ok(geo.door[0] > geo.body[0] && geo.door[2] <= geo.body[2] + 1 && geo.door[3] <= geo.body[3] + 14, 'the door is in front of the body, not beside it');
    await page.mouse.move(800, 500); await link(page, 'Home').hover(); await wait(300);
    assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector('.rbm-grip')).opacity), '0', 'the gripping hand is gone in other poses');
    ok('door pose: the hand turns with the arm through the transition, grips the door edge, and the door is held in front of the body');
    await ctx.close();
  }
  for (const site of ['rep', 'staff', 'admin', 'college']) {
    const shown = {};
    for (const zoom of [0.9, 1, 1.25, 1.5, 2]) {
      const { ctx, page } = await open(browser, site, { viewport: { width: Math.round(1440 / zoom), height: Math.round(900 / zoom) }, deviceScaleFactor: zoom });
      await page.goto(origin + Object.keys(pagePose[site])[0]); await settle(page);
      const m = await page.evaluate(() => { const st = document.querySelector('.rb-mascot__stage'); const sb = document.querySelector('#rb-navigation'); const last = [...sb.querySelectorAll('a.rb-nav-item')].pop().getBoundingClientRect(); const b = st.getBoundingClientRect(); return { visible: getComputedStyle(st).display !== 'none' && b.height > 0, overlap: b.height > 0 && b.top < last.bottom, sbScroll: sb.scrollHeight > sb.clientHeight + 1, overflow: document.documentElement.scrollWidth - innerWidth }; });
      shown[zoom] = m.visible;
      assert.ok(!m.overlap && !m.sbScroll && m.overflow <= 0, `${site} zoom ${zoom * 100}%: ${JSON.stringify(m)}`);
      await ctx.close();
    }
    ok(`${site}: 90/100/125/150/200% zoom, footer shown only when it fits ${JSON.stringify(shown)}; never overlaps links or scrolls the sidebar`);
  }
  {
    const { ctx, page } = await open(browser, 'admin', { reducedMotion: 'reduce' });
    await page.goto(origin + '/'); await settle(page);
    await link(page, 'Equipment').hover();
    assert.equal(await pose(page), 'tool');
    const dur = await page.evaluate(() => getComputedStyle(document.querySelector('.rbm-arm--r')).transitionDuration);
    assert.ok(/^0s/.test(dur), 'reduced motion: instant pose change (' + dur + ')');
    await ctx.close();
    const m = await open(browser, 'admin');
    await m.page.goto(origin + '/'); await settle(m.page);
    const normal = await m.page.evaluate(() => getComputedStyle(document.querySelector('.rbm-arm--r')).transitionDuration);
    assert.equal(normal, '0.18s', 'normal motion: the mascot\'s own 180ms transition');
    const unrelated = await m.page.evaluate(() => ({ vt: getComputedStyle(document.documentElement).getPropertyValue('view-transition-name'), sb: getComputedStyle(document.querySelector('.rb-shell__content')).scrollBehavior, nav: getComputedStyle(document.querySelector('.rb-nav-item')).transitionDuration, btn: getComputedStyle(document.querySelector('.rb-button, button')).transitionDuration }));
    assert.equal(unrelated.sb, 'auto', 'scrolling is untouched');
    assert.ok(/^0s/.test(unrelated.nav) && /^0s/.test(unrelated.btn), 'no global link/button motion was added: ' + JSON.stringify(unrelated));
    ok('reduced motion is instant; normal motion is only the mascot\'s own 180ms; scrolling and global controls are untouched');
    await m.ctx.close();
  }
  console.log(`\n${checks} checks passed (Chromium ${browser.version()})`);
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
