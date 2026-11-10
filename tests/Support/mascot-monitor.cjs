// Frame-by-frame invariants for the sidebar mascot, evaluated INSIDE the page on every animation frame, so a pose change that
// is interrupted (or any cross-fade between poses) is caught while it is happening, not only once it has settled.
//
// The rule being enforced: at every frame, everything that is visible belongs to the pose selected LAST (data-pose), and nothing
// of an older pose lingers: at most one prop, never both eye styles, sparks and happy eyes only in wave/attend, hands only where
// the current pose has them, a visible hand always turns exactly with its arm (same transform every frame, so it can never be
// detached from it), and a prop appears only once the arms have arrived (never an object before the hands are on it). Page
// ARRIVAL must be still: no transition or animation may start on the mascot before the first preview. The expected content per
// pose is written here independently of the stylesheet.
const expected = {
  idle: { prop: null, happy: false, sparkR: false, sparkL: false, hands: [false, false] },
  wave: { prop: null, happy: true, sparkR: true, sparkL: false, hands: [false, true] },
  attend: { prop: null, happy: true, sparkR: true, sparkL: true, hands: [true, true] },
  door: { prop: 'door', happy: false, sparkR: false, sparkL: false, hands: [true, true] },
  calendar: { prop: 'calendar', happy: false, sparkR: false, sparkL: false, hands: [true, true] },
  clipboard: { prop: 'clipboard', happy: false, sparkR: false, sparkL: false, hands: [true, true] },
  key: { prop: 'key', happy: false, sparkR: false, sparkL: false, hands: [true, false] },
  tool: { prop: 'tool', happy: false, sparkR: false, sparkL: false, hands: [true, false] },
  badge: { prop: 'badge', happy: false, sparkR: false, sparkL: false, hands: [true, false] },
};
// Runs in the page. With auto, it starts itself at `load` (when every stylesheet is in, so it cannot disturb the first style
// calculation) and files its findings in sessionStorage when the page is left, so rapid real navigations can be audited afterwards.
const inPage = ({ expected, auto }) => {
  const arrival = [];
  for (const t of ['transitionrun', 'animationstart']) addEventListener(t, (e) => { if (!window.__interacted && e.target.closest?.('[data-rb-mascot]')) arrival.push(`${t}:${e.propertyName || ''}:${e.target.getAttribute('class')}`); }, true);
  addEventListener('pointermove', () => { window.__interacted = true; }, { capture: true, passive: true, once: true });
  const flush = () => { if (!window.__mascotMonitor) return; /* left before it finished loading: nothing was shown, nothing to audit */ try { const all = JSON.parse(sessionStorage.getItem('mascotMon') || '[]'); const s = window.__mascotMonitor; all.push({ path: location.pathname, pose: window.__arrivalPose, frames: s ? s.frames : 0, violations: s ? s.violations : [], arrival }); sessionStorage.setItem('mascotMon', JSON.stringify(all)); } catch {} };
  window.__startMonitor = () => {
    const m = document.querySelector('[data-rb-mascot]');
    window.__arrivalPose = m.dataset.pose;
    const arm = (s) => m.querySelector(`.rbm-arm--${s}:not(.rbm-hands)`);
    const op = (sel) => Number(getComputedStyle(m.querySelector(sel)).opacity);
    const style = document.createElement('style'); style.textContent = '.rb-mascot *{transition:none!important;animation:none!important}'; document.head.append(style);
    const start = m.dataset.pose, settled = {};
    for (const pose of Object.keys(expected)) { m.dataset.pose = pose; settled[pose] = ['l', 'r'].map((s) => getComputedStyle(arm(s)).transform); }
    m.dataset.pose = start; getComputedStyle(m).transform; style.remove();
    const same = (a, b) => { const x = a.match(/-?[\d.]+/g).map(Number), y = b.match(/-?[\d.]+/g).map(Number); return x.length === y.length && x.every((v, i) => Math.abs(v - y[i]) < 0.004); };
    const st = { frames: 0, violations: [], poses: new Set(), running: true };
    window.__mascotMonitor = st;
    const tick = () => {
      if (!st.running) return;
      const cur = m.dataset.pose, e = expected[cur]; st.poses.add(cur); st.frames++;
      const bad = (why) => { if (st.violations.length < 25) st.violations.push(`frame ${st.frames} pose=${cur}: ${why}`); };
      const armsHome = [0, 1].every((i) => same(getComputedStyle(arm(['l', 'r'][i])).transform, settled[cur][i]));
      const props = [...m.querySelectorAll('.rbm-prop')].filter((p) => Number(getComputedStyle(p).opacity) > 0.001).map((p) => p.className.baseVal.replace('rbm-prop rbm-prop--', ''));
      if (props.length && !armsHome) bad(`a prop (${props.join('+')}) is visible before the arms have arrived`);
      if (props.length > 1) bad('two props visible at once: ' + props.join('+'));
      if (props.some((p) => p !== e.prop)) bad(`prop of another pose visible: ${props.join('+')}`);
      const happy = op('.rbm-eyes--happy'), open = op('.rbm-eyes--open');
      if (happy > 0.001 && open > 0.001) bad(`both eye styles visible (${open.toFixed(2)} / ${happy.toFixed(2)})`);
      if (happy > 0.001 && !e.happy) bad(`happy eyes visible (${happy.toFixed(2)})`);
      const sr = op('.rbm-sparks--r'), sl = op('.rbm-sparks--l');
      if (sr > 0.001 && !e.sparkR) bad(`right sparks visible (${sr.toFixed(2)})`);
      if (sl > 0.001 && !e.sparkL) bad(`left sparks visible (${sl.toFixed(2)})`);
      ['l', 'r'].forEach((s, i) => {
        const g = m.querySelector(`.rbm-hands.rbm-arm--${s}`);
        const vis = ['.rbm-hand', '.rbm-hand-edge'].some((k) => Number(getComputedStyle(g.querySelector(k)).opacity) > 0.001) || Number(getComputedStyle(arm(s).querySelector('.rbm-limb-edge')).opacity) > 0.001;
        if (vis && !e.hands[i]) bad(`${s} hand/limb edge visible`);
        if (vis && getComputedStyle(arm(s)).transform !== getComputedStyle(g).transform) bad(`${s} hand is not on its arm's tip (detached)`);
      });
      requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  };
  if (auto) { addEventListener('load', () => window.__startMonitor()); addEventListener('pagehide', flush); }
  window.__flushMonitor = flush;
};
const install = async (page) => { await page.evaluate(inPage, { expected, auto: false }); await page.evaluate(() => window.__startMonitor()); };
const read = (page) => page.evaluate(() => { const s = window.__mascotMonitor; return { frames: s.frames, violations: s.violations, poses: [...s.poses] }; });
// For rapid real navigations: put the monitor in every document, then read what each one filed when it was left (and the last one).
const autoInstall = (ctx) => ctx.addInitScript(inPage, { expected, auto: true });
const readAll = async (page) => { await page.evaluate(() => window.__flushMonitor?.()); return page.evaluate(() => JSON.parse(sessionStorage.getItem('mascotMon') || '[]')); };
module.exports = { expected, install, read, autoInstall, readAll };
