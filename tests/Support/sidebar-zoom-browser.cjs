// Real-browser regression check for the sidebar: no "Signed in as" role card at ANY browser zoom or window height, and the
// Workspace navigation stays in one place however the window is zoomed.
//
//   node tests/Support/sidebar-zoom-browser.cjs <path-to-playwright-core> <base-url> [screenshot-dir]
//
// The card used to be hidden by a height-only media query (viewport <= 48rem tall), so 90% zoom (a taller CSS viewport)
// showed it and 100% did not, which moved the navigation down by the card's height. Browser zoom is simulated the way
// Chrome does it: the CSS viewport is window/zoom and the device pixel ratio is zoom. Drives a RUNNING app on a DISPOSABLE
// database seeded with `php artisan db:seed` (admin@/staff@/rep1@reservebot.test, password Password123!). It changes no data.
const { chromium } = require(process.argv[2]);
const assert = require('node:assert/strict');
const base = (process.argv[3] || '').replace(/\/$/, '');
const shots = process.argv[4] || '';
assert.ok(/^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/.test(base), 'Refusing to run against anything but a local disposable app (127.0.0.1 / localhost).');
assert.ok(!/:(8000|8001|8002)$/.test(base), "Refusing to run against the developer's own app ports.");

const accounts = { rep: 'rep1@reservebot.test', staff: 'staff@reservebot.test', admin: 'admin@reservebot.test' };
const home = { rep: '/rep', staff: '/staff', admin: '/admin' };
const pages = { rep: [home.rep, '/my-bookings'], staff: [home.staff, '/requests'], admin: [home.admin, '/requests', '/rooms', '/accounts'] };
const zooms = [90, 100, 110, 125, 150, 200];
// Browser window content areas: a short laptop window (the one that hid the card), a common one, and a tall monitor.
const windows = [[1366, 650], [1440, 768], [1440, 900], [1920, 1080]];
const phones = [[390, 844], [320, 640]];
let checks = 0;
const ok = (n) => console.log('  PASS', n);

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.RB_BROWSER_EXECUTABLE || undefined });
  const overflow = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  const state = (page) => page.evaluate(() => {
    const r = (s) => { const e = document.querySelector(s); if (!e) return null; const b = e.getBoundingClientRect(); return { x: b.x, y: b.y, w: b.width, h: b.height, vis: getComputedStyle(e).display !== 'none' && b.width > 0 && b.height > 0 }; };
    const s = r('.rb-sidebar'), h = r('.rb-navigation__heading');
    return { sidebar: s, heading: h, brand: r('.rb-brand'), cards: document.querySelectorAll('.rb-role-card').length, text: document.querySelector('.rb-sidebar')?.innerText || '',
      kids: [...document.querySelectorAll('.rb-sidebar > *')].map(e => e.tagName.toLowerCase()),
      links: document.querySelectorAll('.rb-navigation a').length, active: document.querySelectorAll('.rb-navigation a[aria-current="page"]').length,
      activeText: document.querySelector('.rb-navigation a[aria-current="page"]')?.innerText.trim().replace(/\s+/g, ' ') };
  });
  // Nothing in the shell chrome (masthead, header, sidebar, menu) may poke past the viewport, whatever the page content does.
  const chromeOverflow = (page) => page.evaluate(() => { const W = document.documentElement.clientWidth; return [...document.querySelectorAll('.rb-masthead *, .rb-header *, .rb-sidebar, .rb-sidebar *')].filter(e => getComputedStyle(e).display !== 'none' && e.getBoundingClientRect().width > 0 && e.getBoundingClientRect().right > W + 0.5).length; });
  // Overlap check between visible, non-nested sibling controls in the sidebar navigation.
  const navOverlap = (page) => page.evaluate(() => {
    const els = [...document.querySelectorAll('.rb-navigation__list > li')].map(e => e.getBoundingClientRect());
    for (let i = 1; i < els.length; i++) if (els[i].top < els[i - 1].bottom - 0.5 && els[i].width > 0 && els[i - 1].width > 0 && els[i].left < els[i - 1].right - 0.5 && els[i].right > els[i - 1].left + 0.5) return i;
    return -1;
  });

  for (const role of Object.keys(accounts)) {
    console.log(`${role}: no sidebar card at any zoom, navigation position constant`);
    const plain = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await plain.newPage();
    await page.goto(base + '/login');
    await page.fill('input[name=email]', accounts[role]);
    await page.fill('input[name=password]', 'Password123!');
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    // A new context per size so the device scale factor can change; the session is reused through the storage state.
    const storage = await plain.storageState();
    await plain.close();

    const run = async (w, h, percent, label) => {
      const zoom = percent / 100;
      const ctx = await browser.newContext({ viewport: { width: Math.round(w / zoom), height: Math.round(h / zoom) }, deviceScaleFactor: zoom, storageState: storage });
      const p = await ctx.newPage();
      const offsets = [];
      for (const url of pages[role]) {
        await p.goto(base + url); await p.waitForLoadState('networkidle');
        const s = await state(p);
        const where = `${role} ${url} ${label} @${percent}% (${p.viewportSize().width}x${p.viewportSize().height} css px)`;
        assert.equal(s.cards, 0, `${where}: the role card must not exist`);
        assert.ok(!/signed in as/i.test(s.text), `${where}: no "Signed in as" text in the sidebar`);
        assert.deepEqual(s.kids, ['nav'], `${where}: the sidebar holds only the navigation (${s.kids})`);
        assert.ok(s.brand && s.brand.vis, `${where}: branding stays`);
        assert.ok(s.links >= 3 && s.active === 1, `${where}: links ${s.links}, active ${s.active}`);
        assert.equal(await overflow(p), 0, `${where}: no horizontal overflow`);
        assert.equal(await navOverlap(p), -1, `${where}: no overlapping navigation rows`);
        if (s.sidebar.vis && s.sidebar.w > 0 && p.viewportSize().width >= 1280 && p.viewportSize().height >= 632 && s.heading.x < 300) {
          // desktop composition: the Workspace heading sits a fixed distance below the sidebar top, whatever the zoom/height
          offsets.push({ where, top: +(s.heading.y - s.sidebar.y).toFixed(2) });
          // every link reachable by scrolling the sidebar (the sidebar is its own scroll area on desktop)
          const reach = await p.evaluate(() => { const sb = document.querySelector('.rb-sidebar'); const links = [...document.querySelectorAll('.rb-navigation a')]; const last = links[links.length - 1]; last.scrollIntoView({ block: 'nearest' }); const a = last.getBoundingClientRect(), b = sb.getBoundingClientRect(); return a.bottom <= b.bottom + 1 && a.top >= b.top - 1; });
          assert.ok(reach, `${where}: the last navigation link can be reached`);
          // identity is still shown in the header account chip
          const roleText = await p.locator('.rb-account__role').first().innerText();
          assert.ok(/\S/.test(roleText) && await p.locator('.rb-account__role').first().isVisible(), `${where}: role shown in the account chip (${roleText})`);
        }
        checks++;
      }
      if (shots) await p.goto(base + pages[role][0]).then(() => p.screenshot({ path: `${shots}/sidebar-${role}-${label}-${percent}.png` }));
      await ctx.close();
      return offsets;
    };

    const all = [];
    for (const [w, h] of windows) for (const z of zooms) { all.push(...await run(w, h, z, `win${w}x${h}`)); }
    const tops = [...new Set(all.map(o => o.top))];
    assert.ok(all.length > 0, `${role}: at least one desktop layout was measured`);
    assert.equal(tops.length, 1, `${role}: Workspace heading offset must be identical at every zoom and height, got ${JSON.stringify(all.map(o => [o.where, o.top]))}`);
    ok(`${all.length} desktop measurements, heading offset ${tops[0]}px at all of them`);

    // phones and narrow widths at 100% and 200% zoom
    for (const [w, h] of phones) for (const z of [1, 2]) {
      const ctx = await browser.newContext({ viewport: { width: Math.round(w / z), height: Math.round(h / z) }, deviceScaleFactor: z, storageState: storage, hasTouch: true, isMobile: true });
      const p = await ctx.newPage();
      for (const url of pages[role]) {
        await p.goto(base + url); await p.waitForLoadState('networkidle');
        const where = `${role} ${url} phone ${w}x${h} @${z * 100}%`;
        assert.equal(await p.locator('.rb-role-card').count(), 0, `${where}: no role card`);
        assert.equal(await chromeOverflow(p), 0, `${where}: the header and sidebar stay inside the viewport`);
        // 320px at 200% zoom is a 160px-wide viewport; page content (not the shell) may exceed it by a pixel there.
        if (w * 1 / z >= 320) assert.equal(await overflow(p), 0, `${where}: no horizontal overflow`);
        assert.ok(await p.locator('.rb-brand').first().isVisible(), `${where}: branding stays`);
        if (role === 'rep') {
          assert.ok(await p.locator('.rb-navigation a').first().isVisible(), `${where}: the bottom navigation is visible`);
          const bar = await p.locator('.rb-sidebar').boundingBox();
          assert.ok(Math.abs(bar.y + bar.height - p.viewportSize().height) < 2, `${where}: bottom navigation sits at the bottom edge`);
          const n = await p.locator('.rb-navigation a').count();
          for (let i = 0; i < n; i++) assert.ok(await p.locator('.rb-navigation a').nth(i).isVisible(), `${where}: bottom link ${i} visible`);
        } else {
          const toggle = p.locator('#rb-menu-toggle');
          await toggle.click();
          assert.equal(await toggle.getAttribute('aria-expanded'), 'true', `${where}: the menu opens`);
          const n = await p.locator('.rb-navigation a').count();
          for (let i = 0; i < n; i++) { await p.locator('.rb-navigation a').nth(i).scrollIntoViewIfNeeded(); assert.ok(await p.locator('.rb-navigation a').nth(i).isVisible(), `${where}: menu link ${i} visible`); }
          assert.ok(await p.locator('.rb-navigation__heading').isVisible(), `${where}: Workspace heading shown in the open menu`);
          assert.equal(await navOverlap(p), -1, `${where}: no overlapping rows`);
          assert.equal(await chromeOverflow(p), 0, `${where}: still nothing outside the viewport with the menu open`);
          assert.ok(await p.locator('.rb-account__role').first().isVisible(), `${where}: role shown in the account area of the open menu`);
          await p.keyboard.press('Escape');
        }
        checks++;
      }
      await ctx.close();
    }
    ok('phones/narrow at 100% and 200%: no card, no overflow, navigation works');
  }
  await browser.close();
  console.log(`\n${checks} page checks passed.`);
})().catch(e => { console.error('FAIL', e.message); process.exit(1); });
