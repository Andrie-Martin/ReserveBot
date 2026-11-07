// Lists rendered fixtures that still show the legacy violet (#6D28D9 family, including its pale tints) on visible elements.
//
//   node tests/Support/legacy-colour-scan.cjs <playwright-core> <public dir> [options] <fixture dir> [<fixture dir> ...]
//
//   --states               also check :focus-visible and :hover colours of interactive elements (slower)
//   --known-legacy=a,b     fixture directories (by name) that are KNOWN to contain legacy styling, e.g. the generic
//                          shell fixtures in layout/. They are still scanned and listed, but do not fail the run.
//
// Exit code: 0 = no active violations (known-legacy findings may exist, they are listed), 1 = active violations,
// 2 = usage or environment error. CHROMIUM_PATH (optional) points at a browser executable.
// Fixtures are served from disk; every request to a host other than reservebot.test is aborted, so nothing leaves the machine.
//
// What it does NOT check (state this when quoting a clean result): pseudo-elements, gradients, box-shadows, SVG fill/stroke,
// closed <details> content (it opens them only under --states), :active, one viewport (1438x900), 100% text. Hover and focus
// are checked only under --states and only on a, button, summary, input, select, textarea, [tabindex].
const fs = require('node:fs');
const path = require('node:path');

const args = process.argv.slice(2);
const flags = args.filter(a => a.startsWith('--'));
const pos = args.filter(a => !a.startsWith('--'));
if (pos.length < 3) {
  console.error('usage: node legacy-colour-scan.cjs <playwright-core> <public dir> [--states] [--known-legacy=dir,dir] <fixture dir>...');
  process.exit(2);
}
let chromium;
try { ({ chromium } = require(pos[0])); } catch (e) { console.error('cannot load playwright-core from ' + pos[0]); process.exit(2); }
const pub = pos[1];
const dirs = pos.slice(2);
const states = flags.includes('--states');
const known = new Set(((flags.find(f => f.startsWith('--known-legacy=')) || '').split('=')[1] || '').split(',').filter(Boolean));
for (const d of [pub, ...dirs]) if (!fs.existsSync(d)) { console.error('missing directory: ' + d); process.exit(2); }

// Violet family = hue 245..290 degrees with blue dominant and any visible chroma, so pale tints (#EDE9FE, #F5F3FF, #DDD6FE,
// #F3EEFF) count as well as the strong colours. Greys, the PUP maroon/gold and the cream surfaces are far outside this hue.
function pageSide() {
  const rgba = s => {
    if (!s) return null;
    let m = s.match(/^color\(srgb\s+([\d.e-]+)\s+([\d.e-]+)\s+([\d.e-]+)(?:\s*\/\s*([\d.e-]+))?\)/);
    if (m) return { r: +m[1] * 255, g: +m[2] * 255, b: +m[3] * 255, a: m[4] === undefined ? 1 : +m[4] };
    m = s.match(/^rgba?\(([^)]+)\)/);
    if (!m) return null;
    const v = m[1].split(/[ ,\/]+/).filter(Boolean).map(Number);
    return { r: v[0], g: v[1], b: v[2], a: v[3] === undefined ? 1 : v[3] };
  };
  const isViolet = s => {
    const c = rgba(s);
    if (!c || c.a < 0.3) return false;
    const mx = Math.max(c.r, c.g, c.b), mn = Math.min(c.r, c.g, c.b), d = mx - mn;
    if (d < 8 || mx !== c.b) return false;
    const h = (((c.r - c.g) / d) + 4) * 60;
    return h >= 245 && h <= 290;
  };
  const label = e => e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (e.getAttribute('class') ? '.' + e.getAttribute('class').trim().split(/\s+/).slice(0, 2).join('.') : '');
  const colourProps = ['color', 'backgroundColor', 'borderTopColor', 'borderRightColor', 'borderBottomColor', 'borderLeftColor', 'outlineColor', 'textDecorationColor', 'accentColor'];
  const check = (e, state) => {
    const cs = getComputedStyle(e), out = [];
    for (const k of colourProps) {
      if (k.startsWith('border') && cs[k.replace('Color', 'Style')] === 'none') continue;
      if (k === 'outlineColor' && cs.outlineStyle === 'none') continue;
      if (k === 'textDecorationColor' && cs.textDecorationLine === 'none') continue;
      if (isViolet(cs[k])) out.push(`${state}${label(e)} ${k}=${cs[k]}`);
    }
    return out;
  };
  return { check, label };
}

(async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {}) });
  const context = await browser.newContext({ viewport: { width: 1438, height: 900 } });
  let current;
  await context.route('**/*', r => r.abort());
  await context.route('http://reservebot.test/**', r => {
    const rel = decodeURIComponent(new URL(r.request().url()).pathname).slice(1);
    const file = /^(css|js|fonts)\//.test(rel) ? path.join(pub, rel) : path.join(current, rel);
    try {
      r.fulfill({ body: fs.readFileSync(file), contentType: file.endsWith('.css') ? 'text/css' : file.endsWith('.js') ? 'text/javascript' : file.endsWith('.woff2') ? 'font/woff2' : 'text/html' });
    } catch (e) { r.fulfill({ status: 404, body: '' }); }
  });
  const page = await context.newPage();
  await page.addInitScript(`window.__scan = (${pageSide.toString()})();`);

  const SEL = 'a[href], button, summary, input, select, textarea, [tabindex]';
  const active = [], legacy = [];
  let documents = 0, stateChecks = 0;
  for (const dir of dirs) {
    current = dir;
    const isKnown = known.has(path.basename(dir));
    for (const name of fs.readdirSync(dir).filter(f => f.endsWith('.html')).sort()) {
      documents++;
      await page.goto('http://reservebot.test/' + name);
      const hits = new Set(await page.evaluate(() => {
        const out = [];
        for (const e of document.querySelectorAll('body *')) {
          if (!e.getBoundingClientRect().width) continue;
          out.push(...window.__scan.check(e, ''));
        }
        return out;
      }));
      if (states) {
        const n = await page.evaluate(sel => {
          document.querySelectorAll('details').forEach(d => { d.open = true; });
          return [...document.querySelectorAll(sel)].filter(e => e.getBoundingClientRect().width && !e.disabled).length;
        }, SEL);
        await page.keyboard.press('Tab'); // keyboard modality, so programmatic focus matches :focus-visible
        for (let i = 0; i < n; i++) {
          const handle = (await page.evaluateHandle(([sel, i]) => [...document.querySelectorAll(sel)].filter(e => e.getBoundingClientRect().width && !e.disabled)[i], [SEL, i])).asElement();
          if (!handle) continue;
          stateChecks += 2;
          for (const h of await handle.evaluate(e => { e.focus(); const o = window.__scan.check(e, 'focus '); e.blur(); return o; })) hits.add(h);
          await handle.scrollIntoViewIfNeeded();
          const box = await handle.boundingBox();
          if (box) {
            await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
            for (const h of await handle.evaluate(e => window.__scan.check(e, 'hover '))) hits.add(h);
            await page.mouse.move(0, 0);
          }
        }
      }
      if (hits.size) (isKnown ? legacy : active).push([path.basename(dir) + '/' + name, [...hits].sort()]);
    }
  }
  const print = (title, list) => {
    console.log(`${title}: ${list.length} document(s)`);
    for (const [doc, hs] of list) { console.log('  ' + doc); for (const h of hs) console.log('    ' + h); }
  };
  const distinct = list => {
    const m = new Map();
    for (const [, hs] of list) for (const h of hs) { const k = h.replace(/=.*/, '').replace(/#[\w-]+/, ''); m.set(k, (m.get(k) || 0) + 1); }
    return [...m].sort((a, b) => b[1] - a[1] || (a[0] < b[0] ? -1 : 1));
  };
  print('ACTIVE violations', active);
  if (active.length) { console.log('Distinct active findings (state element property: documents):'); for (const [k, n] of distinct(active)) console.log(`  ${n}  ${k}`); }
  if (known.size) print('Known legacy fixtures (listed, not failing; ' + [...known].join(', ') + ')', legacy);
  console.log(`Scanned ${documents} document(s) at 1438x900, 100% text. ` + (states
    ? `Focus and hover checked on interactive elements (${stateChecks} state checks).`
    : 'Focus and hover states were NOT checked (use --states).') +
    ' Not covered: pseudo-elements, gradients, box-shadow, SVG, :active, other viewports and text sizes.');
  await browser.close();
  process.exitCode = active.length ? 1 : 0;
})().catch(e => { console.error(e.message); process.exit(2); });
