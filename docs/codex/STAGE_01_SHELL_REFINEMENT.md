# Stage 1 — shared shell refinement

**Historical report:** Stage 1 was merged in `f693cea`. Its sticky/document-scrolling architecture is superseded by the approved [application-shell scrolling correction](SHELL_SCROLLING_ARCHITECTURE.md). The original results below remain evidence of that earlier implementation, not the current scrolling contract.

Base: `008479a`, branch `fix/frontend-shell-refinement`. Scope: approved Stage 1 only. Work remains uncommitted. No Stage 2–4, theme functionality or Phase 05 implementation.

## Root causes and correction

The workspace grid stretches its sidebar to the main-content row height; the advisory's automatic top margin consequently places it at the bottom of long documents. Desktop sidebar now uses independent alignment, sticky positioning, viewport-limited height and overflow only for its own contents. Header and main content retain normal document flow.

A fixed header offset is unreliable: names and text enlargement change its height. The existing local layout script measures the header's remaining visible bottom and sets the sidebar height to the remaining viewport, scheduled once per animation frame on scrolling/resizing and observed header/masthead size changes. It does not intercept scrolling or create a main-content scrollbar. Header flex wrapping fixes a long-name column detected at 200% text. CSS remains usable without JS. At extreme short/enlarged states where the header fills almost the whole viewport, natural document scrolling reveals the navigation; minimum padding is not clipped to force a zero-height panel.

Letter tiles were decorative text rather than useful iconography. A shared Blade icon component now renders nine local outline SVGs from an explicit path allowlist. Every original label, URL, counter and active-route expression remains intact. The existing mobile representative navigation remains text-only. Neutral hover, rose/maroon current-page emphasis with an inset edge, and the keyboard outline are visually separate.

Prominent prototype labels are removed. Guest header text describes the reservation system; the accurate login unofficial-site disclaimer and existing unofficial PU motif are retained. No official endorsement is asserted.

## Changed-file inventory

| File | Change |
|---|---|
| `public/css/layout.css` | Sidebar sizing/overflow, header wrapping, icon sizing, current/hover treatments; remove obsolete concept/tile rules. |
| `public/js/layout.js` | Header-aware sidebar sizing; existing mobile menu/focus code retained. |
| `resources/views/layouts/app.blade.php` | Replace decorative tile values with icon names; route/role/count expressions unchanged. |
| `resources/views/layouts/partials/navigation.blade.php` | Forward icon prop. |
| `resources/views/layouts/partials/masthead.blade.php` | Remove prominent Concept label. |
| `resources/views/layouts/partials/header.blade.php` | Functional product description for guests and signed-in users. |
| `resources/views/components/ui/nav-item.blade.php` | Compose decorative SVG instead of letter tile. |
| `resources/views/components/ui/icon.blade.php` (new) | Presentation-only local outline SVG vocabulary. |
| `tests/Support/layout-runtime.php` | Additional icon, no-tile and no-prototype assertions for every role/count combination. |
| `tests/Support/layout-browser.cjs` | Expect sticky desktop positioning and unchanged Accounts label without decorative letter. |
| `tests/Support/auth-browser.cjs` | Exclude only decorative old/new icon nodes when comparing link text; all actual labels, URLs and form comparisons retained. |
| `tests/Support/availability-browser.cjs` | Same narrowly scoped decorative-icon comparison adjustment. |
| `tests/Support/shell-refinement-browser.cjs` (new) | Exact viewport pairs, text enlargement, sidebar scroll geometry, keyboard access, advisory reachability, states and version-correct before/after screenshots. |
| `docs/frontend/DESIGN_SYSTEM.md` | Record shell/API changes and sizing fallback. |
| `docs/codex/STAGE_01_SHELL_REFINEMENT.md` (new) | This report, parity matrix and verification details. |

The two Docker files are pre-existing user changes, not part of this inventory.

## Functional parity matrix

| Contract | Before → after | Evidence |
|---|---|---|
| Guest, rep, staff, admin navigation | Same links, label text, order, route conditions and role visibility | Rendering contracts; browser form/link parity |
| Current route, pending request count | Same `aria-current`, conditions and one department-scoped count query | Rendering route/count tests; distinct hover/current browser assertion |
| Logout, account, email resend | Same native forms/actions/methods/CSRF, account URLs and message conditions | Rendering and native FormData checks |
| Onboarding/reduced error shell | Same navigation suppression; `hideUser` still checked before authentication | Existing auth/query-avoidance renderer assertions |
| Mobile navigation | Same single DOM, four rep links, staff/account disclosure, Escape/focus/safe-area behavior | Existing layout checks, new viewport/focus checks |
| Login/account forms and validation | Unchanged page templates, bags, old values and field constraints | 60 auth fixtures, complete native form comparison, intercepted submissions |
| Dashboard/availability | Unchanged page content, every meeting, row, query URL, booking/answer form and disclosure | 42 availability fixtures and information/form/link comparison |
| Legacy conditional fields | No legacy stylesheet/selector or form changes | Foundations browser nine-state checks, no-`:has()` fallback, FormData |
| Advisory | Same content; near sidebar bottom when space allows, internally reachable when needed | Sidebar measurements and advisory-bottom assertion |

These are presentation/fixture proofs, not new HTTP authorization or database-workflow certification. No controller, service, model, route, auth policy, schema, business rule or database was modified.

## Environment and commands

Existing container PHP/Laravel dependencies were reused in `/tmp/rb-shell-stage1/{before,after}` snapshots. Baseline is a tracked HEAD archive; current snapshot excludes local Docker modifications. No `.env` was copied. Standalone harnesses set array services, temporary view caches and remove database connections; query-recorder fixtures have no PDO connection. No service restart/rebuild, package install, migrations, seeds or HTTP/database tests.

Render commands (inside `reservebot-app-1`):

```text
php /tmp/rb-shell-stage1/after/tests/Support/render-foundations.php --check
php /tmp/rb-shell-stage1/after/tests/Support/render-layout.php --check
php /tmp/rb-shell-stage1/after/tests/Support/render-auth.php --check
php /tmp/rb-shell-stage1/after/tests/Support/render-availability.php --check
```

Export both snapshots using `render-{layout,auth,availability}.php --export /tmp/rb-shell-stage1/{before,after}-{layout,auth,availability}`. Copy only fixture exports to the host. Browser commands from repository root:

```text
node tests/Support/foundations-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-shell-stage1/after-layout
node tests/Support/layout-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-shell-stage1/after-layout /tmp/rb-shell-stage1/before-layout
node tests/Support/auth-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-shell-stage1/after-auth /tmp/rb-shell-stage1/before-auth
node tests/Support/availability-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-shell-stage1/after-availability /tmp/rb-shell-stage1/before-availability
node tests/Support/shell-refinement-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-shell-stage1
```

Browser requests are intercepted and fulfilled from local fixtures/assets. Native submissions in existing suites are intercepted, never sent to the application. The new suite uses each snapshot's own CSS/JS/fonts for before/after captures. Existing comparison suites load current assets for their historical DOM comparisons, so their screenshots alone are not presented as historical styling evidence.

## Results

| Check | Observed result |
|---|---|
| Foundations rendering | PASS, 59 assertions |
| Shell rendering | PASS, 265 assertions (241 existing + 24 icon/branding assertions) |
| Auth rendering | PASS, 1,318 assertions |
| Availability rendering | PASS, 782 assertions |
| Foundations browser | PASS, nine conditional states, native FormData, local fonts, contrast, focus, enlarged text |
| Layout browser | PASS, 72 role/state/width combinations plus menu/Escape, no-JS, focus transitions, content styles/payloads, safe-area and short-height checks |
| Auth browser | PASS, 60 states / 420 width combinations, native validation/submission, named bags, no-JS |
| Availability browser | PASS, 42 states / 252 width combinations, exact form/link/information parity, native disclosures, calendar keyboard scrolling, no-JS |
| Stage 1 browser | PASS, 243 viewport/text/state combinations; 270 top/middle/bottom sidebar measurements |
| Syntax/diff | `php -l tests/Support/layout-runtime.php`, `node --check` on changed JS/CJS, and `git diff --check` pass |

New viewport matrix: 1440×900, 1366×768, 1280×720, 1024×600, 768×1024, 390×844, 320×640, plus 1440×480 and 1280×400. Each is tested at 100%, 125%, 200% root text size across long rep dashboard, long room availability, staff/admin availability, long-name admin, guest, onboarding, hidden-user and unconnected-email fixtures. Actual document width, sidebar bounding rectangles/client/scroll heights and document scroll position are recorded. Keyboard Tab reaches the last navigation item with a visible outline; the advisory is reachable through internal scrolling. Mobile final content focus remains above the bottom bar.

During verification, sandbox browser launch restrictions required approved execution outside the sandbox. The new harness initially needed asset-host resolution corrections; these were test plumbing, not application changes. A genuine long-name/enlarged-text header squeeze was fixed by wrapping header items. Extremely tall headers at short heights are tested for natural scrolling and keyboard reachability rather than mandatory above-fold placement.

## Evidence and limitations

Temporary evidence root: `/tmp/rb-shell-stage1` (not committed; may be cleared).

- `shell-results.json`: complete measurements and matrix.
- `{before,after}-availability-home-rep-long-1366x768.png`: laptop dashboard.
- `{before,after}-availability-rooms-rep-long-390x844.png`: mobile availability.
- `{before,after}-layout-admin-1440x900.png`: admin navigation.
- `{before,after}-layout-guest-1280x720.png`: guest shell.
- `{before,after}-availability-{home-rep-long,rooms-rep-long}-scroll-{0.5,1}.png`: long-page middle/bottom.
- Other seven required viewport pairs have before/after dashboard, rooms, admin and guest captures.
- Per-suite JSON results and additional screenshots reside in `after-layout`, `after-auth`, `after-availability`.

Desktop icons/current state and laptop/mobile screenshots were visually inspected. Text enlargement at 125%/200% is directly verified; actual browser-toolbar zoom is not. Chromium only: no Safari/Firefox, physical-device, screen-reader or full accessibility certification. No new HTTP tests were necessary or run; prior HTTP results are not claimed as a new run. No live Figma file inspection or measurements. Login/dashboard/availability density issues remain explicitly deferred to Stage 2.

## Protected files and handoff

Both Docker file hashes remain the initial values:

```text
ab8f57755ba295dc937cffd7b5c0b746a5b21a0397612d2e6ecb14bfa67b62e8  docker-compose.yml
45a1e6e6f347925d60b2898ed08efe15953e7b669345b7b67781a13883dbcf79  docker-compose.yml.save
```

Backend directories, committed Figma PNG references, page-content templates, legacy CSS and dependency files remain unchanged. No commits, pushes, PRs, merges or deployment. The measured sizing addition is the approved plan's conditional minimal JavaScript refinement; no scope deviation into later stages.
