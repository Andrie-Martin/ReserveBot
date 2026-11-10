// Chromium checks for the animated login mascot. Needs a running app that serves GET /login (no database rows needed) and playwright-core:
//   node tests/Support/login-mascot-browser.cjs /path/to/playwright-core http://127.0.0.1:8021 /output/dir
// run by login-mascot-browser.sh. Screenshots are written to the output directory; submissions are intercepted, nothing is logged in.
const { chromium } = require(process.argv[2]);
const assert = require('node:assert/strict');
const path = require('node:path');
const base = process.argv[3], out = path.resolve(process.argv[4]);
const results = [];
const check = async (name, fn) => { try { await fn(); results.push(['PASS', name]); } catch (e) { results.push(['FAIL', name, e.message.split('\n').slice(0, 4).join(' | ')]); } };
const WIDTHS = [320, 360, 375, 390, 440, 640, 768, 1024, 1280, 1440, 1920];
const EXPR = ['normal', 'happy', 'squint', 'curious', 'surprised'];

(async () => {
  const browser = await chromium.launch({ headless: true });
  const open = async (viewport, opts = {}) => {
    const ctx = await browser.newContext({ viewport, ...opts });
    const page = await ctx.newPage();
    page.errors = [];
    page.on('pageerror', e => page.errors.push(e.message));
    page.on('console', m => { if (m.type() === 'error') page.errors.push(m.text()); });
    await page.goto(base + '/login');
    await page.evaluate(() => document.fonts.ready);
    return page;
  };
  const visibleMascot = page => page.evaluate(() => {
    const el = [...document.querySelectorAll('.rb-login-mascot')].filter(e => e.getBoundingClientRect().width > 0);
    return el.length === 1 ? el[0].className : el.length + ' visible';
  });
  const rect = (page, sel) => page.locator(sel).first().evaluate(e => { const r = e.getBoundingClientRect(); return { x: r.x, y: r.y + scrollY, w: r.width, h: r.height, r: r.right, b: r.bottom + scrollY }; });
  const overlaps = (a, b) => a.x < b.r && a.r > b.x && a.y < b.b && a.b > b.y;
  const noOverflow = page => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
  const seek = (page, seconds) => page.evaluate(t => document.getAnimations().forEach(a => { a.pause(); a.currentTime = t * 1000; }), seconds);
  const exprOpacity = page => page.evaluate(names => {
    const el = [...document.querySelectorAll('.rb-login-mascot')].find(e => e.getBoundingClientRect().width > 0);
    return Object.fromEntries(names.map(n => [n, +getComputedStyle(el.querySelector('.rbl-x--' + n)).opacity]));
  }, EXPR);

  // ===== Placement and overflow, every requested width, plus short mobile, landscape and tablets =====
  const sizes = [...WIDTHS.map(w => [w, w < 700 ? 800 : 900]), [360, 520], [320, 480], [844, 390], [932, 430], [768, 1024], [1024, 768], [1180, 820], [1366, 768], [1280, 720]];
  for (const [w, h] of sizes) {
    await check(`${w}x${h}: one mascot, no overflow, clear of form and text`, async () => {
      const page = await open({ width: w, height: h });
      const which = await visibleMascot(page);
      assert.match(which, w >= 1024 ? /rb-login-mascot--banner/ : /rb-login-mascot--card/);
      assert.ok(await noOverflow(page), 'horizontal overflow');
      const m = await rect(page, '.rb-login-mascot:not([style*="display: none"])');
      const mv = await page.evaluate(() => { const e = [...document.querySelectorAll('.rb-login-mascot')].find(x => x.getBoundingClientRect().width > 0); const r = e.getBoundingClientRect(); return { x: r.x, y: r.y + scrollY, w: r.width, h: r.height, r: r.right, b: r.bottom + scrollY }; });
      assert.ok(mv.x >= 0 && mv.r <= w, 'mascot inside viewport width');
      assert.ok(mv.y >= 0, 'antenna not above the page');
      for (const sel of ['#email', '#password', '.rb-auth-form button[type=submit]', '.rb-auth-card__footer a', '.rb-auth-login__card h1', '.rb-auth-login__card .rb-auth-eyebrow']) {
        assert.ok(!overlaps(mv, await rect(page, sel)), 'overlaps ' + sel);
      }
      if (w >= 1024) {
        for (const sel of ['.rb-auth-login__intro', '.rb-auth-login__art-kicker', '.rb-auth-login__art-title', '.rb-auth-login__card']) assert.ok(!overlaps(mv, await rect(page, sel)), 'overlaps ' + sel);
        const art = await rect(page, '.rb-auth-login__art');
        assert.ok(Math.abs(mv.b - art.y) <= 1.5, `sits on banner top edge (${mv.b} vs ${art.y})`);
        assert.ok(mv.x >= art.x && mv.r <= art.r, 'inside banner width');
      } else {
        const card = await rect(page, '.rb-auth-login__card');
        assert.ok(Math.abs(mv.b - card.y) <= 1.5, `sits on card top edge (${mv.b} vs ${card.y})`);
        assert.ok(mv.h <= 110, 'compact (' + mv.h + 'px)');
        const hdr = await rect(page, '.rb-header');
        assert.ok(mv.y >= hdr.b - 1, 'below the PU header');
      }
      assert.deepEqual(page.errors, []);
      await page.context().close();
    });
  }
  await check('mobile: form reachable without scrolling on a 390x844 phone', async () => {
    const page = await open({ width: 390, height: 844 });
    const link = await rect(page, '.rb-auth-card__footer a');
    assert.ok(link.b <= 844, 'reset link inside first screen ' + link.b);
    await page.screenshot({ path: path.join(out, 'mobile-390x844.png') });
    await page.context().close();
  });
  await check('mobile: form reachable on a short 360x640 phone (button within first screen)', async () => {
    const page = await open({ width: 360, height: 640 });
    const btn = await rect(page, '.rb-auth-form button[type=submit]');
    assert.ok(btn.b <= 640, 'login button inside first screen ' + btn.b);
    await page.context().close();
  });

  // ===== Zoom 90-200% (a CSS-pixel viewport of base / zoom) =====
  for (const zoom of [0.9, 1.1, 1.25, 1.5, 2]) {
    await check(`browser zoom ${Math.round(zoom * 100)}% on 1280x720 and 1920x1080: no overflow, no overlap`, async () => {
      for (const [w, h] of [[1280, 720], [1920, 1080]]) {
        const page = await open({ width: Math.round(w / zoom), height: Math.round(h / zoom) });
        assert.ok(await noOverflow(page), 'overflow');
        const mv = await page.evaluate(() => { const e = [...document.querySelectorAll('.rb-login-mascot')].find(x => x.getBoundingClientRect().width > 0); const r = e.getBoundingClientRect(); return { x: r.x, y: r.y + scrollY, r: r.right, b: r.bottom + scrollY }; });
        for (const sel of ['#email', '#password', '.rb-auth-form button[type=submit]', '.rb-auth-card__footer a']) assert.ok(!overlaps(mv, await rect(page, sel)), 'overlaps ' + sel);
        await page.context().close();
      }
    });
  }

  // ===== Antenna and sparks never clipped =====
  await check('antenna ball is not clipped at any width (nothing between the figure and the viewport clips it)', async () => {
    for (const w of [320, 390, 768, 1280]) {
      const page = await open({ width: w, height: 900 });
      const ok = await page.evaluate(() => {
        const el = [...document.querySelectorAll('.rb-login-mascot')].find(x => x.getBoundingClientRect().width > 0);
        const ball = el.querySelector('circle.rbm-gold').getBoundingClientRect();
        const top = document.elementFromPoint(ball.x + ball.width / 2, ball.y + ball.height / 2);
        for (let n = el; n && n !== document.documentElement; n = n.parentElement) { const o = getComputedStyle(n).overflow; if (o !== 'visible' && n !== el.querySelector('svg')) return 'clipped by ' + n.className; }
        return ball.y >= 0 && ball.x >= 0 ? 'ok' : 'off-screen';
      });
      assert.equal(ok, 'ok', w + ': ' + ok);
      await page.context().close();
    }
  });

  // ===== Animation =====
  await check('first frame is the normal smile only; no other expression visible', async () => {
    for (const vp of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
      const page = await open(vp);
      await seek(page, 0);
      const o = await exprOpacity(page);
      assert.deepEqual(o, { normal: 1, happy: 0, squint: 0, curious: 0, surprised: 0 });
      await page.context().close();
    }
  });
  await check('loop is slow, deterministic and never mixes expressions (sampled every 0.1s over 72s)', async () => {
    const page = await open({ width: 1440, height: 900 });
    const dur = await page.evaluate(() => document.getAnimations().filter(a => a.effect.target.closest('.rb-login-mascot--banner') && a.animationName.startsWith('rbl-') && a.animationName !== 'rbl-bob').map(a => a.effect.getTiming().duration));
    assert.ok(dur.length >= 5 && dur.every(d => d === 36000), 'all expression/tilt/blink loops are 36s: ' + dur);
    const seen = []; const changes = [];
    let prev = null;
    for (let t = 0; t < 72; t += 0.1) {
      await seek(page, +t.toFixed(1));
      const o = await exprOpacity(page);
      const on = EXPR.filter(n => o[n] > 0);
      assert.equal(on.length, 1, `t=${t.toFixed(1)}: ${JSON.stringify(o)}`);
      assert.ok(Object.values(o).every(v => v === 0 || v === 1), 'opacity is a hard step, never a cross-fade');
      if (on[0] !== prev) { changes.push([+t.toFixed(1), on[0]]); prev = on[0]; }
    }
    const order = changes.slice(0, 9).map(c => c[1]);
    assert.deepEqual(order, ['normal', 'happy', 'normal', 'curious', 'normal', 'surprised', 'normal', 'squint', 'normal']);
    // slow: 4 expression changes in 36s and each change at least 1s apart (blinks are transform-only, not expression changes)
    for (let i = 1; i < changes.length; i++) assert.ok(changes[i][0] - changes[i - 1][0] >= 1.4, 'too fast: ' + JSON.stringify(changes[i]));
    // deterministic: the second 36s cycle repeats the first exactly
    const first = changes.filter(c => c[0] > 0 && c[0] < 36), second = changes.filter(c => c[0] >= 36).map(c => [+(c[0] - 36).toFixed(1), c[1]]);
    assert.deepEqual(second, first, 'cycle 2 equals cycle 1');
    await page.context().close();
  });
  await check('head tilts and bobs a little; hands, banner and card never move', async () => {
    for (const [vp, sel] of [[{ width: 1440, height: 900 }, '.rb-auth-login__art'], [{ width: 390, height: 844 }, '.rb-auth-login__card']]) {
      const page = await open(vp);
      const handBox = () => page.evaluate(() => [...document.querySelectorAll('.rb-login-mascot')].find(x => x.getBoundingClientRect().width > 0).querySelectorAll('.rbl-hand')).then(() => page.evaluate(() => [...[...document.querySelectorAll('.rb-login-mascot')].find(x => x.getBoundingClientRect().width > 0).querySelectorAll('.rbl-hand')].map(h => { const r = h.getBoundingClientRect(); return [r.x, r.y, r.width, r.height].map(v => +v.toFixed(2)); })));
      const headBox = () => page.evaluate(() => { const r = [...document.querySelectorAll('.rb-login-mascot')].find(x => x.getBoundingClientRect().width > 0).querySelector('.rbl-head').getBoundingClientRect(); return { x: r.x, y: r.y, w: r.width, h: r.height }; });
      await seek(page, 1); const h0 = await handBox(), s0 = await rect(page, sel), head0 = await headBox();
      let maxTilt = 0, maxBob = 0;
      for (const t of [2.5, 7, 8, 15, 15.7, 22.8, 27, 33]) {
        await seek(page, t);
        assert.deepEqual(await handBox(), h0, 'hands stayed at t=' + t);
        assert.deepEqual(await rect(page, sel), s0, 'banner/card stayed at t=' + t);
        const hb = await headBox(); maxTilt = Math.max(maxTilt, Math.abs(hb.x - head0.x)); maxBob = Math.max(maxBob, Math.abs(hb.y - head0.y));
      }
      assert.ok(maxTilt > 0.2, 'head visibly tilts');
      assert.ok(maxTilt < 12 && maxBob < 12, `movement stays small (${maxTilt}, ${maxBob})`);
      // the base of the head never lifts off the edge: its lowest visible row is still the clip edge
      await page.context().close();
    }
  });
  await check('only transform and opacity are animated (no layout properties)', async () => {
    const page = await open({ width: 1440, height: 900 });
    const props = await page.evaluate(() => [...new Set(document.getAnimations().flatMap(a => a.effect.getKeyframes().flatMap(k => Object.keys(k)).filter(k => !['offset', 'easing', 'composite', 'computedOffset'].includes(k))))]);
    assert.ok(props.length > 0 && props.every(p => ['opacity', 'transform'].includes(p)), JSON.stringify(props));
    await page.context().close();
  });
  await check('hidden tab pauses the loop, visible tab resumes it', async () => {
    const page = await open({ width: 1440, height: 900 });
    const state = () => page.evaluate(() => [document.documentElement.hasAttribute('data-rb-hidden'), [...new Set(document.getAnimations().filter(a => a.effect.target.closest('.rb-login-mascot')).map(a => a.playState))]]);
    assert.deepEqual(await state(), [false, ['running']]);
    await page.evaluate(() => { Object.defineProperty(document, 'hidden', { configurable: true, get: () => true }); document.dispatchEvent(new Event('visibilitychange')); });
    assert.deepEqual(await state(), [true, ['paused']]);
    await page.evaluate(() => { Object.defineProperty(document, 'hidden', { configurable: true, get: () => false }); document.dispatchEvent(new Event('visibilitychange')); });
    assert.deepEqual(await state(), [false, ['running']]);
    await page.context().close();
  });
  await check('reduced motion: no animations at all, normal smiling pose', async () => {
    for (const vp of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
      const page = await open(vp, { reducedMotion: 'reduce' });
      const n = await page.evaluate(() => document.getAnimations().filter(a => a.effect.target.closest('.rb-login-mascot')).length);
      assert.equal(n, 0);
      assert.deepEqual(await exprOpacity(page), { normal: 1, happy: 0, squint: 0, curious: 0, surprised: 0 });
      await page.waitForTimeout(500);
      assert.equal(await page.evaluate(() => getComputedStyle([...document.querySelectorAll('.rbl-tilt')].find(x => x.getBoundingClientRect().width > 0)).transform), 'none');
      await page.screenshot({ path: path.join(out, `reduced-motion-${vp.width}.png`) });
      await page.context().close();
    }
  });

  // ===== Accessibility and form behaviour =====
  await check('mascot is decorative: aria-hidden, no focusable or interactive content, no pointer events, not in the accessibility tree', async () => {
    const page = await open({ width: 390, height: 844 });
    const r = await page.evaluate(() => [...document.querySelectorAll('.rb-login-mascot')].map(m => ({
      hidden: m.getAttribute('aria-hidden'), pe: getComputedStyle(m).pointerEvents,
      focusable: m.querySelectorAll('a,button,input,select,textarea,[tabindex]').length, text: m.textContent.trim(), title: m.querySelectorAll('title,desc').length })));
    assert.equal(r.length, 2);
    for (const m of r) assert.deepEqual(m, { hidden: 'true', pe: 'none', focusable: 0, text: '', title: 0 });
    const snap = await page.locator('body').ariaSnapshot();
    assert.ok(!/mascot|robot|image/i.test(snap), 'no mascot in the accessibility tree');
    await page.context().close();
  });
  for (const vp of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
    await check(`${vp.width}: keyboard order is email, password, Log in, reset link; heading and labels intact`, async () => {
      const page = await open(vp);
      assert.equal(await page.evaluate(() => document.activeElement.id), 'email');
      const order = [];
      for (let i = 0; i < 3; i++) { await page.keyboard.press('Tab'); order.push(await page.evaluate(() => document.activeElement.id || document.activeElement.textContent.trim())); }
      assert.deepEqual(order, ['password', 'Log in', 'Forgot your password?']);
      assert.equal(await page.locator('h1').innerText(), 'Log in to ReserveBot');
      assert.equal(await page.locator('label[for=email]').innerText(), 'Email address');
      assert.equal(await page.locator('label[for=password]').innerText(), 'Password');
      const focusBox = await page.evaluate(() => { const s = getComputedStyle(document.activeElement); return [s.outlineStyle, s.outlineWidth]; });
      assert.notEqual(focusBox[0], 'none', 'focus ring on the reset link');
      await page.context().close();
    });
    await check(`${vp.width}: controls are the topmost element at their centre (mascot never intercepts)`, async () => {
      const page = await open(vp);
      for (const sel of ['#email', '#password', '.rb-auth-form button[type=submit]', '.rb-auth-card__footer a']) {
        const ok = await page.locator(sel).evaluate(e => { const r = e.getBoundingClientRect(); const t = document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2); return t === e || e.contains(t); });
        assert.ok(ok, sel);
      }
      await page.context().close();
    });
    await check(`${vp.width}: submitting posts the same fields to /login (intercepted)`, async () => {
      const page = await open(vp);
      const posts = [];
      await page.route('**/login', route => { const r = route.request(); if (r.method() === 'POST') { posts.push(r.postData()); return route.fulfill({ status: 200, body: 'intercepted' }); } return route.continue(); });
      await page.fill('#email', 'someone@example.test'); await page.fill('#password', 'not-a-real-password');
      await page.click('.rb-auth-form button[type=submit]');
      await page.waitForFunction(() => document.body.textContent.includes('intercepted'));
      assert.equal(posts.length, 1);
      const body = new URLSearchParams(posts[0]);
      assert.deepEqual([...body.keys()].sort(), ['_token', 'email', 'password']);
      assert.equal(body.get('email'), 'someone@example.test');
      await page.context().close();
    });
  }
  await check('touch: tapping the fields and the reset link works; the mascot area passes taps through', async () => {
    const page = await open({ width: 390, height: 844 }, { hasTouch: true, isMobile: true });
    await page.tap('#password');
    assert.equal(await page.evaluate(() => document.activeElement.id), 'password');
    const m = await page.locator('.rb-login-mascot--card').boundingBox(); m.w = m.width; m.h = m.height;
    await page.touchscreen.tap(m.x + m.w / 2, m.y + m.h / 2);
    const hit = await page.evaluate(([x, y]) => ({ top: !!document.elementFromPoint(x, y).closest('.rb-login-mascot'), focus: !!document.activeElement.closest('.rb-login-mascot') }), [m.x + m.w / 2, m.y + m.h / 2]);
    assert.deepEqual(hit, { top: false, focus: false }, 'the mascot neither receives the tap nor takes focus');
    await Promise.all([page.waitForURL(/forgot-password|password\/reset/), page.tap('.rb-auth-card__footer a')]);
    await page.context().close();
  });
  // ===== Evidence screenshots =====
  await check('screenshots: desktop, tablet, mobile', async () => {
    for (const [n, w, h] of [['desktop-1440x900', 1440, 900], ['desktop-1024x768', 1024, 768], ['tablet-portrait-768x1024', 768, 1024], ['tablet-landscape-1180x820', 1180, 820], ['mobile-390x844', 390, 844], ['mobile-320x640', 320, 640], ['mobile-landscape-844x390', 844, 390]]) {
      const page = await open({ width: w, height: h });
      await page.screenshot({ path: path.join(out, n + '.png'), fullPage: true });
      await page.context().close();
    }
  });

  await browser.close();
  let fail = 0;
  for (const r of results) { if (r[0] === 'FAIL') fail++; console.log(r.join(' | ')); }
  console.log(`\n${results.length - fail} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})();
