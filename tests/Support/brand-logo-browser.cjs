// Real-browser check of the header brand logo (mascot head) and its mobile expression loop.
//
//   node tests/Support/brand-logo-browser.cjs <path-to-playwright-core> [screenshot-dir]
//
// DB-free and server-free (see mascot-harness.cjs). Time is driven through the Web Animations API (pause + set currentTime), so the
// expression at every moment of the 36 s loop is asserted without waiting and without relying on a frame rate.
const { chromium } = require(process.argv[2]);
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { origin, serve } = require('./mascot-harness.cjs');
const shots = process.argv[3]; if (shots) fs.mkdirSync(shots, { recursive: true });
let checks = 0;
const ok = (name) => { checks++; console.log('  PASS', name); };

async function open(browser, site, route, viewport, options = {}) {
  const ctx = await browser.newContext({ viewport, ...options });
  await serve(ctx, site);
  const page = await ctx.newPage();
  page.errors = [];
  page.on('pageerror', (e) => page.errors.push(e.message));
  await page.goto(origin + route, { waitUntil: 'load' });
  await page.evaluate(() => document.fonts.ready);
  return page;
}
// Which expression layers are visible right now (opacity 1), by name.
const visibleLayers = (page) => page.evaluate(() => [...document.querySelectorAll('.rbh-x')].filter((g) => getComputedStyle(g).opacity === '1').map((g) => g.getAttribute('class').match(/rbh-x--(\w+)/)[1]));
const seek = (page, seconds) => page.evaluate((t) => { for (const a of document.getAnimations()) { a.pause(); a.currentTime = t * 1000; } }, seconds);
const animations = (page) => page.evaluate(() => document.getAnimations().filter((a) => a.effect?.target?.classList?.contains('rbh-x')).length);
const metrics = (page) => page.evaluate(() => {
  const r = (s) => { const e = document.querySelector(s); const b = e.getBoundingClientRect(); return [b.x, b.y, b.width, b.height].map((n) => Math.round(n * 100) / 100); };
  return { header: r('.rb-header'), brand: r('.rb-brand'), logo: r('.rb-brand__logo'), words: r('.rb-brand__words'), overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth };
});

// Expected expression per moment of the 36 s loop, written independently of the stylesheet.
const timeline = [[0, []], [1, []], [2.9, []], [3.1, ['blink']], [3.3, []], [8.9, []], [10, ['happy']], [11.6, []], [15.1, ['blink']], [17, []], [20, ['curious']], [22, []],
  [24.1, ['blink']], [26, []], [28, ['surprised']], [29.5, []], [31.1, ['blink']], [32, []], [34, ['playful']], [35.2, []], [35.99, []]];

(async () => {
  const browser = await chromium.launch();

  // 1. Mobile workspace: the loop exists, starts on the normal smile, shows one expression at a time, in the right order.
  for (const [site, route] of [['rep', '/'], ['staff', '/today'], ['admin', '/accounts']]) {
    const page = await open(browser, site, route, { width: 390, height: 844 });
    assert.equal(await page.locator('.rb-brand .rbh').count(), 1, `${site}: one logo in the brand link`);
    assert.equal(await page.locator('.rb-brand__monogram').count(), 0, `${site}: PU monogram is gone`);
    assert.equal(await page.locator('.rbh[data-rb-head-expressive]').count(), 1);
    assert.equal(await animations(page), 5, `${site}: five expression overlays animate`);
    assert.deepEqual(await visibleLayers(page), [], `${site}: first paint is the normal smile (no overlay)`);
    for (const [t, want] of timeline) { await seek(page, t); assert.deepEqual(await visibleLayers(page), want, `${site}: ${t}s shows ${want.join('+') || 'normal'}`); }
    // Per-animation-frame sweep: never more than one overlay, and the normal face is never uncovered (overlays are whole-face).
    const worst = await page.evaluate(() => { let max = 0; for (let t = 0; t < 36000; t += 50) { for (const a of document.getAnimations()) { a.pause(); a.currentTime = t; } max = Math.max(max, [...document.querySelectorAll('.rbh-x')].filter((g) => getComputedStyle(g).opacity !== '0').length); } return max; });
    assert.equal(worst, 1, `${site}: never two expressions at once`);
    assert.deepEqual(page.errors, []);
    await page.context().close();
  }
  ok('mobile workspace (rep, staff, admin): normal first, blink/happy/curious/surprised/playful in order, one at a time');

  // 2. Breakpoint: animated exactly where the sidebar mascot is hidden; static where it shows.
  for (const [w, h, animated] of [[1279, 800, true], [1280, 800, false], [1440, 900, false], [1920, 1080, false], [1280, 631, true], [1440, 500, true], [1024, 768, true], [768, 1024, true], [320, 568, true]]) {
    const page = await open(browser, 'staff', '/today', { width: w, height: h });
    const mascotShown = await page.evaluate(() => getComputedStyle(document.querySelector('.rb-sidebar > .rb-mascot')).display !== 'none');
    assert.equal(await animations(page) > 0, animated, `${w}x${h}: animated=${animated}`);
    assert.equal(mascotShown, !animated, `${w}x${h}: loop runs exactly when the sidebar mascot is hidden`);
    assert.deepEqual(await visibleLayers(page), [], `${w}x${h}: first paint normal`);
    await page.context().close();
  }
  ok('loop runs only where the sidebar mascot is hidden (<1280px wide or <632px tall); desktop stays static');

  // 3. Guest/login shell: static, no data hook, no animation.
  for (const [w, h] of [[390, 844], [768, 1024], [1440, 900]]) {
    const page = await open(browser, 'guest', '/login', { width: w, height: h });
    assert.equal(await page.locator('.rbh[data-rb-head-expressive]').count(), 0, `guest ${w}: not expressive`);
    assert.equal(await page.locator('.rbh-x').count(), 0, `guest ${w}: no overlay markup`);
    assert.equal(await animations(page), 0, `guest ${w}: nothing animates`);
    assert.equal(await page.locator('.rb-brand .rbh').count(), 1, `guest ${w}: has the mascot-head logo`);
    await page.context().close();
  }
  ok('guest/login header uses the logo but stays static');

  // 4. Reduced motion: static normal face even on mobile workspace.
  { const page = await open(browser, 'rep', '/', { width: 390, height: 844 }, { reducedMotion: 'reduce' });
    assert.equal(await animations(page), 0); assert.deepEqual(await visibleLayers(page), []);
    await page.context().close(); ok('prefers-reduced-motion: reduce shows the static normal expression'); }

  // 5. Layout is untouched by the loop: identical metrics at every moment; no overflow; logo sized; no clipping of the antenna.
  for (const w of [320, 360, 375, 390, 440, 640, 768, 1024, 1280, 1440, 1920]) {
    const h = w < 700 ? 740 : 900;
    const page = await open(browser, 'rep', '/', { width: w, height: h });
    const base = await metrics(page);
    assert.equal(base.overflow, false, `${w}: no horizontal overflow`);
    for (const t of [0, 3.1, 10, 20, 28, 34]) { await seek(page, t); assert.deepEqual(await metrics(page), base, `${w}: metrics identical at ${t}s`); }
    const clip = await page.evaluate(() => { const s = document.querySelector('.rbh').getBoundingClientRect(); return s.top >= 0 && s.left >= 0 && s.right <= innerWidth; });
    assert.ok(clip, `${w}: logo inside the viewport`);
    const g = await page.evaluate(() => { const b = (s) => document.querySelector(s).getBoundingClientRect(); const l = b('.rb-brand__logo'), t = b('.rb-brand__words'), m = document.querySelector('#rb-menu-toggle'); const mt = m && getComputedStyle(m).display !== 'none' ? m.getBoundingClientRect() : null; return { gap: t.left - l.right, vert: Math.abs((l.top + l.height / 2) - (t.top + t.height / 2)), toggleGap: mt ? mt.left - t.right : 99 }; });
    assert.ok(g.gap >= 4, `${w}: logo does not touch the wordmark (${g.gap})`); assert.ok(g.vert <= 2, `${w}: logo and wordmark vertically aligned (${g.vert})`); assert.ok(g.toggleGap >= 0, `${w}: wordmark clear of the menu toggle`);
    await page.context().close();
  }
  ok('11 widths: no overflow, header/brand/logo metrics identical across the loop, aligned, no collisions');

  // 6. Zoom: 90..200% (device scale factor emulation via CSS viewport shrink keeps media queries honest).
  for (const z of [0.9, 1.25, 1.5, 2]) {
    for (const base of [390, 1440]) {
      const page = await open(browser, 'rep', '/', { width: Math.round(base / z), height: Math.round(800 / z), deviceScaleFactor: z });
      const m = await metrics(page); assert.equal(m.overflow, false, `${base}@${z * 100}%: no overflow`);
      await page.context().close();
    }
  }
  ok('zoom 90/125/150/200% at phone and desktop widths: no horizontal overflow');

  // 7. Link behaviour: href, click navigates, keyboard focus ring, accessible name, decorative SVG.
  { const page = await open(browser, 'rep', '/rooms', { width: 390, height: 844 });
    assert.equal(await page.locator('.rb-brand').getAttribute('href'), origin);
    assert.equal(await page.locator('.rbh').getAttribute('aria-hidden'), 'true'); assert.equal(await page.locator('.rbh').getAttribute('focusable'), 'false');
    assert.equal(await page.locator('.rb-brand').evaluate((a) => a.textContent.trim()), 'ReserveBotPUP College of Engineering', 'link text is only the wordmark and subtitle (SVG adds none)');
    await page.keyboard.press('Tab'); await page.keyboard.press('Tab');
    assert.equal(await page.evaluate(() => document.activeElement.classList.contains('rb-brand')), true, 'brand is the second Tab stop (after the skip link)');
    const outline = await page.locator('.rb-brand').evaluate((a) => { const c = getComputedStyle(a); return [c.outlineStyle, parseFloat(c.outlineWidth)]; });
    assert.notEqual(outline[0], 'none'); assert.ok(outline[1] >= 2, 'visible focus outline kept');
    if (shots) await page.screenshot({ path: path.join(shots, 'focus-mobile.png'), clip: { x: 0, y: 0, width: 390, height: 140 } });
    await page.keyboard.press('Enter'); await page.waitForURL(origin + '/');
    await page.context().close(); }
  { const page = await open(browser, 'rep', '/rooms', { width: 390, height: 844 }, { hasTouch: true, isMobile: true });
    await page.locator('.rb-brand__logo').tap(); await page.waitForURL(origin + '/'); await page.context().close(); }
  ok('brand link: href is the site root, keyboard + touch navigate, focus outline visible, SVG decorative, accessible name unchanged');

  // 8. Hidden tab pauses the loop.
  { const page = await open(browser, 'rep', '/', { width: 390, height: 844 });
    await page.evaluate(() => { Object.defineProperty(document, 'hidden', { configurable: true, get: () => true }); document.dispatchEvent(new Event('visibilitychange')); });
    assert.equal(await page.evaluate(() => document.documentElement.hasAttribute('data-rb-hidden')), true);
    assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector('.rbh-x--blink')).animationPlayState), 'paused');
    await page.evaluate(() => { Object.defineProperty(document, 'hidden', { configurable: true, get: () => false }); document.dispatchEvent(new Event('visibilitychange')); });
    assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector('.rbh-x--blink')).animationPlayState), 'running');
    await page.context().close(); ok('hidden tab pauses the loop, visible tab resumes it'); }

  // 9. Screenshots.
  if (shots) {
    for (const [name, site, route, vp] of [['desktop-1440', 'staff', '/today', [1440, 900]], ['desktop-1280', 'rep', '/', [1280, 800]], ['tablet-768', 'staff', '/today', [768, 1024]], ['tablet-landscape-1024', 'staff', '/today', [1024, 768]], ['mobile-390', 'rep', '/', [390, 844]], ['mobile-320', 'rep', '/', [320, 568]], ['mobile-landscape-844', 'rep', '/', [844, 390]], ['login-390', 'guest', '/login', [390, 844]], ['login-1440', 'guest', '/login', [1440, 900]]]) {
      const page = await open(browser, site, route, { width: vp[0], height: vp[1] });
      await seek(page, 0); await page.screenshot({ path: path.join(shots, name + '.png') }); await page.context().close();
    }
    // Expression sheet: each moment of the loop, header crop at 2x.
    const sheet = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 3 }); await serve(sheet, 'rep');
    const page = await sheet.newPage(); await page.goto(origin + '/'); await page.evaluate(() => document.fonts.ready);
    for (const [label, t] of [['1-normal', 0], ['2-blink', 3.1], ['3-happy', 10], ['4-curious', 20], ['5-surprised', 28], ['6-playful', 34]]) {
      await seek(page, t); await page.locator('.rb-brand').screenshot({ path: path.join(shots, 'expr-' + label + '.png') });
    }
    await sheet.close();
  }
  await browser.close();
  console.log(`\n${checks} check groups passed.`);
})().catch((e) => { console.error(e); process.exit(1); });
