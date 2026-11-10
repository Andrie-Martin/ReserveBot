// Real-browser check that the sidebar brand ("ReserveBot" Inter Bold, "PUP College of Engineering" Inter Medium) is rendered in
// Inter, measured the way DevTools "Rendered Fonts" does it (CSS.getPlatformFontsForNode), not by computed font-family.
//
//   node tests/Support/typography-browser.cjs <path-to-playwright-core>
//
// Fonts use font-display: optional, so a face that has not arrived within the ~100ms block period is not used for that page view
// at all. The Medium weight used to be the only brand weight that was not <link rel=preload>ed, so a cold or slow load could
// leave the subtitle in the fallback while the heading was Inter. Same DB-free harness as mascot-browser.cjs.
const { chromium } = require(process.argv[2]);
const assert = require('node:assert/strict');
const { origin, serve } = require('./mascot-harness.cjs');
let checks = 0;
const ok = (name) => { checks++; console.log('  PASS', name); };
const withoutMediumPreload = (html) => html.replace(/<link rel="preload"[^>]*Inter-Medium[^>]*>\s*/, '');

async function rendered(page, selector) {
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('DOM.enable'); await cdp.send('CSS.enable');
  const { root } = await cdp.send('DOM.getDocument', { depth: 0 });
  const { nodeId } = await cdp.send('DOM.querySelector', { nodeId: root.nodeId, selector });
  const { fonts } = await cdp.send('CSS.getPlatformFontsForNode', { nodeId });
  await cdp.detach();
  return fonts.map((f) => f.familyName + ' ' + (f.isCustomFont ? '(web)' : '(local)')).join(', ');
}
async function load(browser, site, { delay, cssDelay = 0, preloadMedium, warmReload }) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const requests = [];
  ctx.on('request', (r) => /\.woff2/.test(r.url()) && requests.push(r.url().split('/').pop()));
  await serve(ctx, site, { fontDelayMs: delay, cssDelayMs: cssDelay, transformHtml: preloadMedium ? undefined : withoutMediumPreload });
  const page = await ctx.newPage();
  await page.goto(origin + '/rooms', { waitUntil: 'load' });
  if (warmReload) { await page.reload({ waitUntil: 'load' }); }
  await page.waitForTimeout(150);
  const result = { name: await rendered(page, '.rb-brand__name'), college: await rendered(page, '.rb-brand__college'), box: await page.$eval('.rb-brand', (e) => { const r = e.getBoundingClientRect(); return [Math.round(r.width), Math.round(r.height)]; }), requests };
  await ctx.close();
  return result;
}
(async () => {
  const browser = await chromium.launch();
  const isInter = (s) => /^Inter(?: \w+)? \(web\)$/.test(s);
  // Preload markup.
  const html = require('node:child_process').execFileSync('php', [require('node:path').join(__dirname, 'render-mascot.php'), 'admin', 'rooms'], { encoding: 'utf8', maxBuffer: 64 << 20 });
  const preloads = [...html.matchAll(/<link rel="preload" href="[^"]*\/fonts\/inter\/Inter-(\w+)\.woff2" as="font" type="font\/woff2" crossorigin>/g)].map((m) => m[1]);
  assert.deepEqual(preloads, ['Regular', 'Medium', 'SemiBold', 'Bold'], 'all four Inter weights are preloaded as woff2 with crossorigin');
  ok('Inter Regular, Medium, SemiBold and Bold are preloaded (as=font, type=font/woff2, crossorigin)');
  for (const site of ['admin', 'staff', 'rep']) {
    for (const delay of [0, 40, 400]) {
      const results = [];
      for (let i = 0; i < 6; i++) results.push(await load(browser, site, { delay, preloadMedium: true, warmReload: false }));
      for (const r of results) {
        if (delay <= 40) assert.ok(isInter(r.name) && isInter(r.college), `${site} cold load, fonts delayed ${delay}ms: heading "${r.name}", subtitle "${r.college}"`);
        // Beyond the block period font-display: optional skips ALL late fonts for the page view: both lines fall back together (never split).
        else assert.equal(isInter(r.name), isInter(r.college), `${site} fonts delayed ${delay}ms: heading and subtitle must not split ("${r.name}" / "${r.college}")`);
        assert.equal(r.requests.filter((n) => n === 'Inter-Medium.woff2').length, 1, 'Inter Medium is requested exactly once (preload is reused, no duplicate fetch)');
        assert.equal(new Set(results.map((x) => x.box.join('x'))).size, 1, 'brand block never changes size');
      }
    }
    ok(`${site}: heading and subtitle are both Inter on cold loads with fonts delayed 0 and 40ms, and never split at 400ms; brand box constant; no duplicate font requests`);
  }
  // Warm cache: reload in the same context.
  for (const site of ['admin', 'rep']) {
    const r = await load(browser, site, { delay: 0, preloadMedium: true, warmReload: true });
    assert.ok(isInter(r.name) && isInter(r.college), `${site} warm reload`);
  }
  ok('warm reload: both lines Inter');
  // What the fix changes: the same cold loads with and without the Medium preload. A "split" is one brand line in Inter and the
  // other in the fallback. CSS latency makes the un-preloaded Medium request start later than the preloaded Bold one, which is
  // how the split was reproduced.
  const splitsWith = [];
  for (const [cssDelay, delay] of [[30, 30], [50, 40], [70, 30], [90, 20]]) {
    let before = 0, after = 0; const n = 8;
    for (let i = 0; i < n; i++) {
      for (const preload of [false, true]) {
        const r = await load(browser, 'admin', { delay, cssDelay, preloadMedium: preload, warmReload: false });
        if (isInter(r.name) !== isInter(r.college)) (preload ? after++ : before++);
      }
    }
    splitsWith.push(after);
    console.log(`  INFO css ${cssDelay}ms / fonts ${delay}ms, ${n} cold loads each: split typography WITHOUT the Medium preload ${before}/${n}, WITH it ${after}/${n}`);
  }
  assert.deepEqual(splitsWith, [0, 0, 0, 0], 'with the Medium preload the brand lines never split');
  ok('with the Medium preload the brand lines never split under simulated CSS/font latency');
  // Limit, stated honestly: a font that takes longer than the block period AND longer than the page needs is skipped by
  // font-display: optional on a truly cold first visit; the preload only makes that far less likely, it cannot guarantee Inter.
  const slow = await load(browser, 'admin', { delay: 2500, preloadMedium: true, warmReload: false });
  console.log(`  INFO fonts delayed 2500ms (limit): heading "${slow.name}", subtitle "${slow.college}"`);
  console.log(`\n${checks} checks passed`);
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
