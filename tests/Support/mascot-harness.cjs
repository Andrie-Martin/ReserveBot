// Shared by mascot-browser.cjs and typography-browser.cjs: serves the REAL shell (rendered by render-mascot.php, no database, no
// app server, no Docker) and the real public/ assets from memory through request interception on a made-up host, so every
// navigation in the browser is a genuine page load. Nothing listens on a port.
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const root = path.resolve(__dirname, '..', '..');
const origin = 'http://reservebot.test';
const types = { css: 'text/css', js: 'text/javascript', woff2: 'font/woff2', svg: 'image/svg+xml', png: 'image/png', ico: 'image/x-icon' };

// pathname -> route name, per role. Mirrors the real routes the shell cares about.
const shared = { '/rooms': 'rooms', '/today': 'today', '/requests': 'requests', '/manage/rooms': 'manage.rooms', '/manage/timetable': 'manage.timetable', '/manage/equipment': 'manage.equipment', '/account': 'account' };
const sites = {
  rep: { role: 'rep', pages: { '/': 'rep.home', '/rooms': 'rooms', '/book': 'book', '/my-bookings': 'bookings.mine', '/my-bookings/9': 'bookings.show', '/account': 'account' } },
  staff: { role: 'lab_staff', pages: { ...shared, '/': 'staff.home' } },
  admin: { role: 'admin', pages: { ...shared, '/': 'admin.home', '/accounts': 'accounts.index' } },
  guest: { role: 'guest', pages: { '/login': 'login' } },
  college: { role: 'college_admin', pages: { '/college': 'college.home', '/account': 'account' } },
};
const cache = new Map();
function render(siteKey, key) {
  const id = siteKey + key;
  if (!cache.has(id)) cache.set(id, execFileSync('php', [path.join(__dirname, 'render-mascot.php'), sites[siteKey].role, sites[siteKey].pages[key]], { cwd: root, encoding: 'utf8', maxBuffer: 64 << 20 }));
  return cache.get(id);
}
async function serve(ctx, siteKey, { fontDelayMs = 0, cssDelayMs = 0, transformHtml } = {}) {
  await ctx.route(origin + '/**', async (route) => {
    const url = new URL(route.request().url());
    if (sites[siteKey].pages[url.pathname]) return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: transformHtml ? transformHtml(render(siteKey, url.pathname)) : render(siteKey, url.pathname) });
    const file = path.join(root, 'public', path.normalize(decodeURIComponent(url.pathname)).replace(/^(\.\.[/\\])+/, ''));
    if (!file.startsWith(path.join(root, 'public')) || !fs.existsSync(file) || fs.statSync(file).isDirectory()) return route.fulfill({ status: 404, body: '' });
    if (cssDelayMs && file.endsWith('.css')) await new Promise((r) => setTimeout(r, cssDelayMs));
    if (fontDelayMs && file.endsWith('.woff2')) await new Promise((r) => setTimeout(r, fontDelayMs));
    return route.fulfill({ status: 200, contentType: types[path.extname(file).slice(1)] || 'application/octet-stream', body: fs.readFileSync(file), headers: file.endsWith('.woff2') ? { 'cache-control': 'max-age=3600', 'access-control-allow-origin': '*' } : {} });
  });
}
module.exports = { origin, serve, sites, root };
