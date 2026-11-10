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
    // The gold chest pill is body: shown in every pose and, wherever no held object lies on it, the topmost thing at its centre.
    for (const p of ['idle', 'wave', 'attend', 'key', 'tool', 'calendar', 'clipboard', 'door', 'badge']) {
      await page.evaluate((p) => { document.querySelector('[data-rb-mascot]').dataset.pose = p; }, p);
      const r = await page.evaluate(() => { const root = document.querySelector('.rb-mascot'); root.style.pointerEvents = 'auto'; const pill = document.querySelector('.rbm-pill'); const b = pill.getBoundingClientRect(); const shown = (e) => { for (let n = e; n && n.tagName !== 'svg'; n = n.parentElement) if (getComputedStyle(n).opacity === '0') return false; return true; }; const top = (x, y) => document.elementsFromPoint(x, y).find((e) => e.closest('svg') && e.tagName !== 'g' && shown(e)); const t = top(b.x + b.width / 2, b.y + b.height / 2); const l = top(b.x + b.width * 0.2, b.y + b.height / 2); root.style.pointerEvents = ''; return { opacity: getComputedStyle(pill).opacity, centreOnTop: t === pill, leftOnTop: l === pill }; });
      assert.equal(r.opacity, '1', `${p}: the chest pill is never hidden`);
      if (['idle', 'wave', 'attend', 'key', 'tool'].includes(p)) assert.ok(r.centreOnTop && r.leftOnTop, `${p}: the gold chest pill is visible (nothing lies on it)`);
    }
    ok('the gold chest pill is the same in all 9 poses and fully visible in idle, wave, attend, key and tool (only held cards and the door may cover it)');
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
    // One holding system for every accessory pose: visible hand-to-object contact and a grip that stays on the arm through the
    // 180ms change. Two-handed objects (calendar, clipboard, door) have a hand on each side edge; one-handed objects (key, tool,
    // badge) are carried by the right hand; the other poses show no hands at all.
    const { ctx, page } = await open(browser, 'admin');
    await page.goto(origin + '/'); await settle(page);
    const state = () => page.evaluate(() => {
      const ctr = (r) => ({ x: r.x + r.width / 2, y: r.y + r.height / 2, w: r.width });
      const side = (s) => { const hand = document.querySelector(`.rbm-hands.rbm-arm--${s} .rbm-hand`); const limb = document.querySelector(`.rbm-arm--${s}:not(.rbm-hands) .rbm-limb`); const pt = new DOMPoint(Number(limb.getAttribute('x2')), Number(limb.getAttribute('y2'))).matrixTransform(limb.getScreenCTM()); const shoulder = new DOMPoint(Number(limb.getAttribute('x1')), Number(limb.getAttribute('y1'))).matrixTransform(limb.getScreenCTM()); const h = ctr(hand.getBoundingClientRect()); const m = hand.getScreenCTM(); return { opacity: Number(getComputedStyle(hand).opacity), w: 2 * Number(hand.getAttribute('r')) * Math.hypot(m.a, m.b), tipGap: Math.hypot(h.x - pt.x, h.y - pt.y), reach: Math.hypot(h.x - shoulder.x, h.y - shoulder.y), x: h.x, y: h.y }; };
      const prop = [...document.querySelectorAll('.rbm-prop')].find((e) => getComputedStyle(e).opacity === '1');
      const face = document.querySelector('.rbm-cream[rx="17"]').getBoundingClientRect();
      const o = prop && prop.getBoundingClientRect();
      const frame = prop && (prop.querySelector('.rbm-frame, .rbm-gold-edge, .rbm-keyring') || prop).getBoundingClientRect();
      return { l: side('l'), r: side('r'), obj: o && { x: o.x, y: o.y, right: o.right, bottom: o.bottom, w: o.width }, frame: frame && { x: frame.x, right: frame.right }, faceCovered: (() => { const root = document.querySelector('.rb-mascot'); const was = root.style.pointerEvents; root.style.pointerEvents = 'auto'; const plate = document.querySelector('.rbm-cream[rx="17"]'); let covered = 0; for (let i = 1; i < 12; i++) for (let j = 1; j < 8; j++) { const x = face.x + face.width * i / 12, y = face.y + face.height * j / 8; const hit = document.elementsFromPoint(x, y); if (!hit.includes(plate)) continue; const top = hit.find((e) => e.closest('svg') && e.tagName !== 'g'); if (top && top.closest('.rbm-prop, .rbm-hands')) covered++; } root.style.pointerEvents = was; return covered; })(), pose: document.querySelector('[data-rb-mascot]').dataset.pose };
    });
    const unit = (await page.evaluate(() => document.querySelector('.rb-mascot__art').getBoundingClientRect().height)) / 140; // px per SVG unit
    const holding = { 'Today': 'two', 'Requests': 'two', 'Free rooms': 'two', 'Manage rooms': 'one', 'Equipment': 'one', 'Accounts': 'one', 'Home': null };
    for (const label of Object.keys(holding)) {
      if (label !== 'Home') { await page.mouse.move(800, 500); await link(page, 'Home').hover(); await wait(260); }
      await link(page, label).hover();
      const samples = [];
      for (let i = 0; i < 8; i++) { samples.push(await state()); await wait(24); }
      for (const sm of samples) for (const side of ['l', 'r']) assert.ok(sm[side].tipGap < 0.6, `${label}: the ${side} hand stays on its arm's tip through the change (gap ${sm[side].tipGap.toFixed(2)}px)`);
      await wait(300);
      const f = await state();
      const kind = holding[label];
      if (!kind) { assert.ok(f.l.opacity === 0 && f.r.opacity === 1, `${label}: wave shows only the raised right hand`); assert.ok(!f.obj, 'and holds nothing'); continue; }
      assert.equal(f.l.opacity, 1, `${label}: the left hand is shown`);
      assert.equal(f.r.opacity, kind === 'two' ? 1 : 0, `${label}: right hand only for two-handed objects`);
      assert.ok(Math.abs(f.l.w - 13.6 * unit) < 0.6 * unit, 'hands are one size');
      const o = f.obj, inside = (hnd, pad) => hnd.x >= o.x - pad && hnd.x <= o.right + pad && hnd.y >= o.y - pad && hnd.y <= o.bottom + pad;
      assert.ok(inside(f.l, 1.5 * unit), `${label}: the left hand touches the object (hand ${f.l.x.toFixed(1)},${f.l.y.toFixed(1)} object ${o.x.toFixed(1)}-${o.right.toFixed(1)})`);
      if (kind === 'two') {
        assert.ok(inside(f.r, 1.5 * unit), `${label}: the right hand touches the object too`);
        const edge = (hnd, e) => Math.abs(hnd.x - e) <= 3.5 * unit;
        assert.ok(edge(f.l, f.frame.x + 1.5 * unit) && edge(f.r, f.frame.right - 1.5 * unit), `${label}: the hands sit on the left and right edges`);
        assert.ok(Math.abs(f.l.y - f.r.y) < 0.5 * unit, `${label}: both hands at the same height`);
      }
      // Nothing held (and no hand) may be painted over the faceplate: sample a grid of points inside the real rounded plate.
      assert.equal(f.faceCovered, 0, `${label}: the object and hands leave the faceplate uncovered (${f.faceCovered} covered samples)`);
    }
    ok('every accessory pose is held: hands touch the object (both side edges for calendar/clipboard/door, the left carrier hand for key/tool/badge), stay on the arm tips through the transition, one hand size, face uncovered');
    const greet = async (p) => { await page.evaluate((p) => { document.querySelector('[data-rb-mascot]').dataset.pose = p; }, p); await wait(300); return page.evaluate(() => { const op = (sel) => Number(getComputedStyle(document.querySelector(sel)).opacity); return { happy: op('.rbm-eyes--happy'), open: op('.rbm-eyes--open'), sparkR: op('.rbm-sparks--r'), sparkL: op('.rbm-sparks--l') }; }); };
    let g = await greet('idle'); assert.deepEqual([(await state()).l.opacity, (await state()).r.opacity, g.happy, g.sparkR], [0, 0, 0, 0], 'idle: relaxed, no hands, round eyes, no sparks');
    g = await greet('wave'); assert.deepEqual([g.happy, g.open, g.sparkR, g.sparkL], [1, 0, 1, 0], 'wave: happy eyes and sparks on the raised side');
    g = await greet('attend'); const st = await state(); assert.deepEqual([g.happy, g.sparkR, g.sparkL, st.l.opacity, st.r.opacity, st.l.tipGap < 0.6 && st.r.tipGap < 0.6], [1, 1, 1, 1, 1, true], 'attend: both arms open with hands, sparks both sides, no object');
    assert.ok(!st.obj, 'attend holds nothing');
    ok('idle (relaxed, no hands), wave (right hand raised, happy eyes, sparks) and attend (both arms open, sparks) match the pose sheet and carry no accessory');
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
