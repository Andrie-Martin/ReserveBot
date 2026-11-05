# Application-shell scrolling correction

Base `f693cea` (Stage 1 merge), branch `fix/frontend-main-scroll`. Approved scope: stationary desktop application shell, native behavior preservation, measured short-height fallback. No commits, pushes, merges, deployment or later frontend phases.

## Root cause and implementation

The previous body had only a minimum viewport height. Main content expanded the document, and a sticky sidebar resized through JavaScript based on the disappearing header. Its automated checks correctly verified that old behavior, which was rejected after review of real scrolling.

The authenticated desktop body is now a viewport-height grid: two automatic masthead/header rows and a `minmax(0, 1fr)` content row. Sidebar and `.rb-shell__content` have `min-height: 0`; only the latter scrolls application content. The sidebar's own overflow is independent. Its advisory uses remaining flex space. `<main>` and all page content remain naturally sized and unchanged. A workspace-only root overflow boundary prevents a competing document scrollbar. Positioned containment prevents absolutely positioned screen-reader text from contributing an invisible root/body scroll range. This was caught by actual geometry and attempted document scrolling, not screenshots.

Removed: sticky sidebar positioning, `--rb-sidebar-height`, header/masthead sizing observers, scroll-position sizing callbacks. Retained: existing mobile menu, Escape/focus transfer, single navigation DOM, representative bottom-bar observer and safe-area clearance. No fixed desktop elements, new packages, global theme, content redesign or backend changes.

The existing content wrapper gains `role="region"`, `aria-label="Page content"`, `tabindex="0"` only for workspace users. The main landmark and `#rb-main` target remain unchanged. Guests/onboarding/reduced error shells remain natural document flow.

## Measured fallback

Desktop mode: `(min-width: 80rem) and (min-height: 39.5rem)` in both CSS and JavaScript. At default browser sizing this is at least 1280px wide and 632px high. Below either boundary, the existing responsive layout and natural page scrolling apply, including the existing rep bottom bar and staff/admin menu. No global document lock affects those modes.

The threshold is based on fixed browser fixtures, not physical display pixels:

- Initial header measurements across 1280/1366/1438/1440 widths: 200% long-name header bottom = 473px; navigation row = 92px; original sidebar padding = 144px.
- Compact sidebar padding at laptop heights becomes 32px total at 200% text.
- A 608px candidate accommodated navigation but failed a real dashboard shortcut: its enlarged wrapped action was 150px tall inside a 135px panel.
- 473px header + 150px action + 8px focus clearance = 631px; the chosen boundary is 632px. Tests exercise 631/632/633px and primary 1438×638 at 100/125/200% root text size.
- At 1438×638 and 200% long-name text, the panel is 165px tall and the 150px action remains reachable. The same viewport at ordinary text has substantially more space (see below).

The fallback is intentionally conservative across the verified fixtures. Arbitrarily larger user fonts/content are not claimed to fit every fixed viewport. Actual browser toolbar zoom was not automated; CSS viewport sizing and root text enlargement are recorded separately from device scale.

## Native history finding and narrowly scoped correction

Before adding history assistance, Chromium passed direct fragments but failed panel offset restoration. For an existing same-document fixture link, Back returned to the right URL while the panel stayed at 236px instead of its previous 866px. Cross-document Back also failed its saved-offset assertion. Waiting for native restoration did not correct it. The approved brief explicitly permits custom restoration only after such a demonstrated failure.

`layout.js` therefore stores `{url, top, left}` under `history.state.reservebotContentScroll`, preserving unrelated state. Writes are debounced and flushed on click/pagehide. New fragment entries record their own offsets. Popstate/pageshow restore the matching entry after font/layout readiness, with a generation guard against stale rapid traversals and finite-coordinate checks. Native link activation, native history entries, query strings, hashes and `history.scrollRestoration = auto` remain intact. No click is cancelled, no URL is rewritten, and no custom routing, localStorage, account preference or database state is introduced.

Browser checks prove Back/Forward for same-document anchors, cross-document Back/Forward and reload, preservation of unrelated history state, and unchanged native restoration mode. Without JavaScript, native navigation/forms/fragments/disclosures remain functional; exact element-offset restoration remains browser-dependent. This enhancement is separate from, and does not reintroduce, scroll-dependent sidebar sizing.

## Complete changed-file inventory

| File | Purpose |
|---|---|
| `public/css/layout.css` | Viewport grid, scoped overflow, containment, content focus outline, synchronized short-height fallback and compact sidebar spacing. |
| `public/js/layout.js` | Remove dynamic sidebar sizing; synchronize mode query; add evidenced history-coordinate restoration while preserving mobile behavior. |
| `resources/views/layouts/app.blade.php` | Named focusable content region for workspace users only. |
| `tests/Support/layout-runtime.php` | Workspace scroll-region and reduced-shell assertions; existing role/query/CSRF tests retained. |
| `tests/Support/layout-browser.cjs` | Desktop sidebar expected to be a static grid item instead of sticky. All other parity checks retained. |
| `tests/Support/shell-refinement-browser.cjs` | Stage 1 suite updated to stationary geometry/content scrolling, primary viewport, measured fallback boundaries, final controls/records, version-specific screenshots. |
| `tests/Support/shell-navigation-browser.cjs` (new) | Fragments, query navigation, history/restoration, skip/focus/validation, disclosures, tables, no-JS, reduced shells and content-length independence. |
| `docs/frontend/DESIGN_SYSTEM.md` | Current scrolling contract, measured fallback, history enhancement and no-JS limits. |
| `docs/codex/STAGE_01_SHELL_REFINEMENT.md` | Mark old scrolling report as historical; preserve its original evidence. |
| `docs/codex/SHELL_SCROLLING_ARCHITECTURE.md` (new) | This report. |

Docker files are pre-existing user changes and excluded from this inventory.

## Functional Parity Matrix

| Contract | Before → after | Evidence |
|---|---|---|
| Menu labels/icons/URLs/current states | Unchanged navigation definitions and Blade components | Existing renderer/menu/active-route tests; browser link comparisons |
| Rep/staff/admin permissions and counts | Same conditions, query count and department bindings | 279 shell rendering assertions including existing query recorder |
| Login/account/logout/CSRF | Same native forms, values, bags, old input, actions and methods | Auth rendering/browser suite; native intercepted submission checks |
| Hidden-user errors/onboarding | Same auth avoidance/navigation suppression; natural scrolling | Rendering privacy checks; explicit 413/500 and onboarding browser cases |
| Representative mobile bottom bar | Same DOM, links, measured safe space and focus clearance | Layout suite and new mobile cases |
| Staff/admin mobile menu | Same disclosure, Escape, no-JS availability and focus transfer | Width and height boundary tests |
| Dashboard meetings/answers/timetable | Every form, status, meeting and row retained | Unchanged page files; availability parity suite; final-record geometry |
| Room list/calendar/agenda/search | Every URL, query field, permitted booking action, status and disclosure retained | Unchanged page files; availability suite; native GET/filter navigation |
| `/rooms#day` and same-document anchors | Same IDs, hashes and links; correct scroll container moves | Direct load/reload, real fixture day link, existing same-document anchor tests |
| History | Native entries/URLs preserved; element offsets restored by narrow enhancement after demonstrated native failure | Back/Forward/reload assertions; unrelated state preservation |
| Keyboard and validation | Skip target retained; panel can scroll by keyboard; browser invalid-field focus and error-field focus reveal controls | New navigation suite plus existing auth tests |
| No JS | CSS shell, forms, fragments, disclosures and navigation usable; exact panel history offset remains browser-dependent | Desktop rep/staff/admin and mobile/short-height no-JS checks |
| Conditional forms | No legacy selector/field/ancestor changes | Foundations nine conditional states and FormData checks |

## Geometry at the user's 1438×638 CSS viewport

The primary native-navigation context also uses deviceScaleFactor 2. Viewport acceptance is based on CSS dimensions, never inferred from physical resolution. The main geometry suite uses CSS dimensions at the browser's default device scale; its results are below.

| Fixture, 100% text | Masthead rectangle (x,y,w,h) | Header rectangle | Sidebar rectangle | Content scrollTop: top / middle / bottom | Document scrollTop |
|---|---|---|---|---|---|
| Long-name representative dashboard | 0,0,1438,30 | 0,30,1438,165 | 0,195,252,443 | 0 / 745 / 1489 | 0 / 0 / 0 |
| Long-name room availability | 0,0,1438,30 | 0,30,1438,165 | 0,195,252,443 | 0 / 1167 / 2333 | 0 / 0 / 0 |
| Staff availability | 0,0,1438,30 | 0,30,1438,77 | 0,107,252,531 | 0 / 681 / 1361 | 0 / 0 / 0 |
| Admin search | 0,0,1438,30 | 0,30,1438,77 | 0,107,252,531 | 0 / 833 / 1665 | 0 / 0 / 0 |

Every listed fixed rectangle is identical at all three scroll positions. Document height is 638px. All desktop measurements also assert that content scrollWidth does not exceed clientWidth, so the root overflow boundary cannot conceal horizontal panel overflow. Adding 10,000px of fixture-only main content does not change header/sidebar height or the document scroll range. Last room/timetable record positions are reachable; a room list may end above a taller adjacent calendar, so its actual position is measured rather than assuming every last record is at the bottom of the entire panel. Oversized rows can be read through normal panel scrolling; assertions do not require an entire tall record to fit at once.

## Verification results

| Suite | Result |
|---|---|
| Foundations renderer | PASS — 59 assertions |
| Shell renderer | PASS — 279 assertions |
| Auth renderer | PASS — 1,318 assertions |
| Availability renderer | PASS — 782 assertions |
| Foundations browser | PASS — nine conditional states, local fonts, native payloads, focus/disabled, text/contrast checks |
| Layout browser | PASS — 72 role/state/width cases plus existing no-JS, menu, focus, native FormData and content-style regressions |
| Auth browser | PASS — 60 fixtures / 420 responsive combinations, native intercepted submissions, validation, named bags, no-JS |
| Availability browser | PASS — 42 fixtures / 252 responsive combinations, information/form/link parity, disclosures, native GET/POST interception, horizontal scrolling |
| Updated Stage 1 geometry browser | PASS — 351 viewport/text/state cases, 324 stationary top/middle/bottom measurements |
| New navigation browser | PASS — nine groups covering fragments, history, validation/focus, no-JS, content independence, responsive boundary and reduced shells |

Matrix: 1438×638, 1440×900, 1366×768, 1280×720, 1024×600, 768×1024, 390×844, 320×640; short cases 1440×480 and 1280×400; threshold cases 1438×631/632/633. Every geometry fixture runs at 100/125/200% root text. Roles/states include long rep dashboard/rooms, staff/admin availability, long-name admin, guest, onboarding, hidden user and unconnected email. Native navigation uses a separate 1438×638 DPR2 context.

Existing form/URL/data/role assertions were not weakened. Stage 1's obsolete document-scroll/sticky-height assertions were replaced by stronger stationary-rectangle, panel-scroll and zero-document-scroll checks. Browser harness corrections distinguish actual completed destination navigation from history `replaceState` events. Header/record overflow and restoration failures were investigated, not accepted as passes.

## Reproduction and evidence

Temporary evidence: `/tmp/rb-main-scroll` (not committed). `before` is a tracked archive of `f693cea`; `after` is a current snapshot without `.env` or local Docker changes. Container snapshots reuse existing `/var/www/html/vendor`; all renderers set array services and disable database connections/use the no-PDO recorder. No installation, service restart, database provisioning, migrations, seeding or HTTP workflow suite.

Render commands in the existing container:

```text
php /tmp/rb-main-scroll/after/tests/Support/render-foundations.php --check
php /tmp/rb-main-scroll/after/tests/Support/render-layout.php --check
php /tmp/rb-main-scroll/after/tests/Support/render-auth.php --check
php /tmp/rb-main-scroll/after/tests/Support/render-availability.php --check
```

Export each snapshot with `render-{layout,auth,availability}.php --export /tmp/rb-main-scroll/{before,after}-{layout,auth,availability}`. Copy only generated fixtures to host /tmp. Browser commands from repository root, using the already installed Playwright/Chromium:

```text
node tests/Support/foundations-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-main-scroll/after-layout
node tests/Support/layout-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-main-scroll/after-layout /tmp/rb-main-scroll/before-layout
node tests/Support/auth-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-main-scroll/after-auth /tmp/rb-main-scroll/before-auth
node tests/Support/availability-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-main-scroll/after-availability /tmp/rb-main-scroll/before-availability
node tests/Support/shell-refinement-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-main-scroll
node tests/Support/shell-navigation-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-main-scroll
```

All requests are intercepted and served from local assets/fixed fixture data. `/rooms` and `/rep` navigation aliases test browser mechanics and preserved payloads, not controller-generated response content. Photos are isolated placeholders. No application requests or real submissions are sent.

Evidence files:

- `baseline-header-measurements.json`: original header/nav dimensions used to investigate fallback.
- `shell-results.json`: complete final geometry matrix.
- `navigation-results.json`: navigation assertions and intercepted request metadata.
- `after-layout/layout-browser-results.json`, `after-auth/auth-browser-results.json`, `after-availability/availability-browser-results.json`.
- 36 before/after screenshot pairs: `{before,after}-availability-{home-rep-long,rooms-rep-long}-1438x638.png` for top; matching `-scroll-0.5.png` and `-scroll-1.png` for middle/bottom at 1438×638. Further admin/guest and mobile viewport captures are included.

Before/after captures in the updated shell suite serve each snapshot's own CSS/JS/fonts. Existing suites use current assets for baseline DOM parity, so their screenshots alone are not historical styling proof. Primary desktop long-page and mobile captures were visually inspected.

## Limitations and safety

Chromium fixture verification is not HTTP authorization/database proof, screen-reader certification, Safari/Firefox coverage or physical-device testing. 125/200% text enlargement was tested directly; browser-toolbar zoom was not automated. Native element scroll restoration without JS remains browser-dependent. No application data or permissions changed. Page content density remains deferred to Stage 2.

The history enhancement is the only conditional addition to the CSS-first plan, justified by the reproduced failures and explicitly allowed by the approved brief. No scroll-dependent sidebar sizing remains.

Final checks: PHP/JS syntax and `git diff --check` pass. Backend files, page-content templates, legacy CSS, dependencies, Stage 1 icons/branding and committed Figma exports remain unchanged. Both Docker hashes match the starting values:

```text
ab8f57755ba295dc937cffd7b5c0b746a5b21a0397612d2e6ecb14bfa67b62e8  docker-compose.yml
45a1e6e6f347925d60b2898ed08efe15953e7b669345b7b67781a13883dbcf79  docker-compose.yml.save
```

Work is left uncommitted on `fix/frontend-main-scroll` for review.
