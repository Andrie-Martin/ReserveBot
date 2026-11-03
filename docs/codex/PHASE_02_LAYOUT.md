# Phase 02 — Global Layout and Navigation

Implementation date: 2026-11-04. Branch: `feat/frontend-02-layout`. Starting HEAD: `472a54d` (exported Figma references, following merged Phase 01). Scope: approved replacement plan plus the user's implementation refinements. Status: implemented for review; the limitations below remain acceptance/release review items. No Phase 03 work, staging, commit, push, PR or merge was performed.

## Result and architecture

The shared layout now has the deep institutional strip, unofficial PU monogram, white authenticated header, white desktop sidebar, warm canvas, active navigation, and representative mobile bottom links shown in the supplied exports. Guest/error contexts use a warm brand header. Page content retains legacy typography, controls, tables, form markup and business behavior.

`resources/views/layouts/app.blade.php` owns one navigation definition. It builds the representative four-link set or staff six-link set, with Accounts added only for admins. There is one navigation DOM and one account/logout form, used at all widths. The existing department-scoped `Reservation::waitingCountFor` call runs once for eligible staff/admin renders; no polling or client-provided count is introduced.

Presentation is composed from six layout partials and two small anonymous Blade components. The partials own the shell's current conditional rendering. `x-ui.brand` and `x-ui.nav-item` receive presentation props and forward attributes without querying records. Existing `x-avatar`, `x-ui.button`, `x-ui.card` and `x-ui.alert` provide account imagery, native actions, advisory card and email notice. Both new assets use independent `filemtime` cache keys.

Only shell subtrees opt into `.rb-ui`. The main content is outside them. Scoped `rb-` selectors and additive `--rb-` tokens avoid changing legacy variables. The menu script is local, deferred, framework-free and limited to disclosure, breakpoint focus and bottom-space measurement. Without JavaScript, native account/logout and staff navigation remain visible; the inactive toggle stays hidden.

## Verified behavioral trace and protections

Source refreshed against the current checkout before implementation:

| Contract | Current source | Preservation/evidence |
|---|---|---|
| Role home and root redirect | `routes/web.php:54`, `app/Models/User.php:76` | Home remains `url('/')`; backend chooses the role home. |
| Main account gate, onboarding | `routes/web.php:52`, `app/Http/Middleware/AccountChecks.php:16` | Onboarding shows name/logout, no workspace/account link/email notice. No middleware change. |
| Representative booking access | `routes/web.php:74` and `:76` | Existing Book and My bookings routes; connected-email enforcement remains server-owned. |
| Staff and admin menus | `routes/web.php:94`, `:114`, `:118`, `:152`, `:157`, `:162`, `:167` | Equipment uses its existing authorized route. No rep staff links, no staff Accounts link. |
| Waiting count | `app/Models/Reservation.php:114` | Actual Eloquent query compiled in tests; `Pending` and department 73 bindings checked; one query/render, zero for guest/rep/onboarding/hidden. Test-only connection has no PDO. |
| Logout | `routes/web.php:36`; new `layouts/partials/account.blade.php` | One POST form with CSRF and native submit button. |
| Account/photo | `app/Models/User.php:92`, `:104`; existing `components/avatar.blade.php` | Account/password active states, protected photo route/cache key, escaped real name and role preserved. |
| Email resend/flash | `routes/web.php:65`; `app/Http/Controllers/EmailLinkController.php:39` | Same POST action/CSRF; suppression on account, onboarding or connected email; `connect_note` replaces resend instead of duplicating it. Backend throttle/send behavior untouched. |
| Hidden-user errors | `resources/views/errors/413.blade.php`, `500.blade.php`; `layouts/app.blade.php:18` | Guard precedes authentication; throwing-guard test proves these templates do not resolve auth. |
| Page-owned tabs/flashes | `account/_tabs.blade.php`, `manage/_tabs.blade.php` | Unchanged; no duplicate global `status`/`problem` display. Rules and Timetable remain accessible through their current tabs. |
| Conditional forms | `public/css/style.css:291`, `:295`, `:661`, `:837`; existing bookings/account/timetable templates | Selectors, field names, checked values, ancestor classes, errors, old input and enabled hidden controls unchanged. Browser checks cover all nine conditional states and the show-all fallback. |

Equipment/categories activate Equipment; other `manage.*` destinations activate Manage rooms as the group entry. Booking detail activates My bookings for representatives, as before. Staff detail behavior is unchanged (no invented Requests activation). Accounts stays visible to admins despite its omission from the images. Signed routes, permission rules and controllers were not changed or exercised as authenticated end-to-end workflows.

## Visual evidence and adaptations

No Figma MCP calls were made for this implementation. Previous live-access restrictions remain unresolved. All 14 supplied PNGs were visually reviewed during replacement planning and remain unchanged. Exports establish only static pictured states, not live node dimensions, breakpoints or interactions.

The foundations export labels the palette, Inter, 8px spacing rhythm, 10px controls and 16px cards. The new secondary text `#736C6D` and border `#E9E2DA` come from those labels. Active rose `#F8EDF0` comes from desktop raster pixels. Existing Phase 01 fallback/status/control tokens remain intact.

| Shell feature | Export evidence | Rendered result / deliberate difference |
|---|---|---|
| Desktop strip/header | Exports 02–11: 30px deep strip; white header to y106 | 30px strip + 77px header including border; sidebar starts y107. |
| Sidebar | Desktop border at x251, content from x252 | 252px sidebar; no copied page imagery. |
| Content gutter | Content begins around x298 in desktop exports | 48px gutter, starts x300; 2px difference follows shared spacing rhythm. |
| Navigation | Letter tiles, rose active row, maroon text | Same pattern; 46px minimum row, 8px gap, inherited legacy list margins explicitly reset. 12px Workspace caption is larger than export microcopy. |
| Brand | PU motif, gold dash, ReserveBot + institutional subtitle | CSS/text monogram. Subtitle uses readable 12px rather than the tiny raster label; brand/descriptor footprint is consequently wider. Not an official seal. |
| Account/actions | Export sample BR/LS/AD and Help | Real account photo/initials/name/role; native Log out replaces unsupported Help. No dead action or fake user. |
| Advisory | Cream footer card | Same card treatment; neutral rules/availability copy replaces an unsupported blanket approval claim. |
| Mobile representative | Export 12: 390×844; strip about 27px, header to y94, four bottom labels | 27px strip, 69px header including border; bottom bar 57px including border at y787. Approximately 1px boundary differences; readable subtitle/touch targets retained. |
| Guest | Export 01: warm header, wider brand inset, 48px PU mark | Warm 90px header, 48px gutter/mark. Uses “Unofficial design concept” instead of the contradictory “Official services” claim. Login illustration/form remain Phase 03. |
| Admin | Export 10 selects Manage rooms | Same shared shell and active-item styling, plus existing Accounts route. Default admin screenshot uses Home; route-specific activation is separately asserted. |

The approved 80rem breakpoint, fixed rep bottom bar, expanded account menu, staff/admin mobile menu, Escape/focus behavior and no-JS fallback are implementation decisions. The PNGs do not depict those expanded states. No staff/admin bottom bar was invented.

Screenshots are local review artifacts in `/tmp/reservebot-phase02-evidence/`, reproducible with the commands below; they are not staged repository assets:

- [Representative desktop shell](/tmp/reservebot-phase02-evidence/rep-1440-shell.png), compared with export 02.
- [Staff desktop shell](/tmp/reservebot-phase02-evidence/staff-1440-shell.png), compared with export 07.
- [Admin desktop shell](/tmp/reservebot-phase02-evidence/admin-1440-shell.png), compared with export 10's shared shell.
- [Representative mobile shell](/tmp/reservebot-phase02-evidence/rep-390-shell.png), compared with export 12.
- [Guest shell](/tmp/reservebot-phase02-evidence/guest-1440-shell.png), compared with export 01's header.
- [Expanded mobile account controls](/tmp/reservebot-phase02-evidence/rep-mobile-menu.png), an implementation state absent from the PNGs.
- [No-JavaScript mobile](/tmp/reservebot-phase02-evidence/nojs-mobile.png) and `rep-text200.png`, `admin-text200.png`, `long-text200.png` show accessibility adaptations.
- `baseline-{legacy,conditional,booking-first,booking-second}-{390,1440}.png` and corresponding `current-*` images show existing content before/after the shell change.

Shell screenshots mask main content in canvas color so that later-phase content differences do not masquerade as shell fidelity failures. Full content screenshots remain separately available. Comparison is visual inspection plus recorded geometry/colors; no whole-page pixel-identical claim is made.

## Content sizing and accessibility

The authenticated shell removes the generic 960px main cap and lets `main.wide` use the available column. Existing page-specific maximum widths remain: narrow 400, welcome 440, account 820, booking 880, Today/manage 980, timetable 1180px. Browser assertions exercised 400/820/880/1180px fixtures and a wide main exceeding 1240px at 1920px viewport; remaining constraints are unchanged CSS source. Guest/onboarding retain the previous main-sizing rules. Increased available width intentionally changes wrapping, not page-owned controls or typography.

A skip link targets the focusable main. Native links/buttons, `aria-current`, disclosure `aria-expanded`/`aria-controls`, decorative tile hiding, minimum 44px actions and visible focus are retained. Long names wrap. At desktop-to-mobile resizing, an already-focused account/navigation control keeps its menu open; at mobile-to-desktop resizing, a focused disappearing toggle transfers focus to the account link. Escape closes only the mobile disclosure and returns focus to its toggle. No modal semantics or trap are used.

Bottom space is based on the measured representative bar height plus 16px, with a rem/safe-area fallback when JS is unavailable. Actual Tab-key focus to final content was checked above the bar. A 390×300 short viewport and simulated 34px safe-area inset were exercised. This is not a physical iOS keyboard/notch test.

## Executed verification

All runtime checks used a copied checkout in the **existing** container's `/tmp/reservebot-phase02-checks`, with its installed `/var/www/html/vendor` symlinked read-only by usage. No live application source, database, session, mail or service configuration was changed. The harness selects array session/cache/mail and a temporary compiled-view directory. Foundations removes database connections; layout uses a test-only recording connection that throws on PDO access. Browser HTTP requests are fulfilled from local fixtures/assets, never the running application.

| Check | Result |
|---|---|
| `php tests/Support/render-layout.php --check` in isolated tree | **PASS: 241 assertions**; roles, routes/active states, zero/nonzero counts, query bindings/multiplicity, onboarding, email conditions, named/escaped flash, native logout/CSRF, private photo URL, duplicate IDs, disclosure IDs, hidden-error auth avoidance. |
| `php tests/Support/render-foundations.php --check` in isolated tree | **PASS: 59 component/render assertions**; attributes, escaping, native controls, named validation bag, error descriptions, existing error templates and forms. |
| `node tests/Support/layout-browser.cjs <existing-playwright-core> <fixtures> <baseline-fixtures>` | **PASS**; 72 role/state/viewport combinations at 320/375/390/768/1024/1279/1280/1440, keyboard disclosure/Escape, breakpoint focus, no-JS actions, native FormData, long names/counts, 200% text at 320 CSS pixels, short viewport/simulated inset, content widths, before/after content styles/payloads. No browser errors or external requests. |
| `node tests/Support/foundations-browser.cjs <existing-playwright-core> <fixtures>` | **PASS**; nine conditional states, hidden-control FormData parity, no-`:has()` show-all fallback, datalist title behavior, named validation fixture, focus/disabled skipping, local fonts, 200% text and four viewport widths. |
| Local Inter loading | All four 400/500/600/700 WOFF2 faces loaded; original assets/license/provenance unchanged by hash. |
| Shell contrast calculations | Secondary/white 5.13:1; secondary/canvas 4.71:1; maroon/active rose 10.14:1; gold/deep 6.77:1. Existing component pairs also passed their contrast checks. |
| PHP/JS syntax | New PHP harness/test files and JS scripts pass `php -l` / `node --check`; Blade compiled by runtime rendering. |
| Protected-file hashes | Compared 258 pre-existing files to the pre-edit snapshot; only six approved existing files differ. No unexpected differences; both local Docker files and all 14 PNGs byte-identical. |
| `git diff --check` | **PASS**. |

During development, checks caught a quoted SQL-alias assumption in the test recorder, an unbound synthetic route, a breakpoint focus loss, and legacy list margins entering shell navigation. These were corrected and the affected checks rerun. The Phase 01 browser comparison now retains token CSS (the shell depends on it) and compares main descendants while toggling foundation font/component sheets. Separate HEAD-layout before/after comparisons check existing content typography/colors/borders/display and actual form payloads; geometry is intentionally allowed to change.

### Reproduction

With suitable existing Laravel dependencies, use a copied checkout and a temporary output directory (do not copy `.env` or enable a production database):

```sh
php tests/Support/render-layout.php --check
php tests/Support/render-foundations.php --check
php tests/Support/render-layout.php --export /tmp/reservebot-phase02-evidence
node tests/Support/layout-browser.cjs /path/to/existing/playwright-core /tmp/reservebot-phase02-evidence /tmp/reservebot-phase02-before
node tests/Support/foundations-browser.cjs /path/to/existing/playwright-core /tmp/reservebot-phase02-evidence
```

For the optional before/after argument, export fixtures from a separate temporary copy using HEAD's `resources/views/layouts/app.blade.php` and the same harness. The actual run used `docker compose cp`/`exec` to stage and render only under `/tmp`; it did not rebuild/restart the app. Browser tooling was the existing `/home/james/projects/ticketing/node_modules/playwright-core` and installed Chromium. Sandbox restrictions required approved external execution of that browser. No packages were installed.

The live Docker app uses a copied checkout and was **not deployed or restarted**; these results describe the worktree and isolated render, not an updated running site.

### Verification gaps

- PHPUnit is absent locally (no vendor directory) and absent from the running container's production-only vendor tree. The PHPUnit entrypoints, existing guest HTTP feature tests, and full unit suite were **not run**. Standalone assertion counts are not PHPUnit counts.
- No authenticated end-to-end login/logout, resend delivery/throttle, session-expiry, authorization denial, booking approval, account edit, timetable or database workflow was executed. Route/middleware invariants are source/hash verified, not runtime security certification.
- No Safari/Firefox, screen reader, physical touch device, real virtual keyboard/notch, or actual browser 400% zoom run. The narrow 320 CSS-pixel viewport is a reflow equivalent, and 200% root text was directly tested.
- No live Figma nodes, interaction states or exact typography measurements were inspected. Static exports do not cover all current app states. Final visual/accessibility acceptance remains the reviewer's decision.

## Complete file inventory

### Modified existing files (6)

| File | Reason |
|---|---|
| `resources/views/layouts/app.blade.php` | Shared role-aware shell, asset links, safe hidden-user guard, existing title/main-class/content yields. |
| `public/css/foundations/tokens.css` | Five additive shell color/layout tokens; existing token defaults unchanged. |
| `docs/frontend/DESIGN_SYSTEM.md` | Export evidence, component contracts, responsive assumptions, width/accessibility decisions. |
| `docs/frontend/SCREEN_INVENTORY.md` | Static export-to-screen coverage and explicitly uncovered states. |
| `tests/Support/foundations-runtime.php` | Allow authenticated shell around existing booking fixtures; permit the one expected local deferred script in escaping assertions. |
| `tests/Support/foundations-browser.cjs` | Serve local JS, compare main content independently of intentional shell changes, retain shell-required tokens. |

### Added files (16)

| File | Purpose |
|---|---|
| `public/css/layout.css` | Scoped desktop/mobile shell styling, reduced guest shell, focus, safe space and content layout. |
| `public/js/layout.js` | Progressive menu disclosure, Escape, resize focus and bottom-bar measurement. |
| `resources/views/components/ui/brand.blade.php` | Reusable unofficial text/CSS PU brand with forwarded attributes. |
| `resources/views/components/ui/nav-item.blade.php` | Reusable native navigation link, active state, mobile label and waiting count. |
| `resources/views/layouts/partials/masthead.blade.php` | Institutional strip and concept label. |
| `resources/views/layouts/partials/header.blade.php` | Shared brand/descriptor, disclosure and account composition. |
| `resources/views/layouts/partials/account.blade.php` | One native account/logout surface, onboarding condition. |
| `resources/views/layouts/partials/navigation.blade.php` | One navigation list from shared data plus advisory. |
| `resources/views/layouts/partials/workspace-note.blade.php` | Existing card component with neutral advisory copy. |
| `resources/views/layouts/partials/email-notice.blade.php` | Existing alert/button composition preserving resend/flash semantics. |
| `tests/Feature/Frontend/LayoutTest.php` | PHPUnit entrypoint for isolated shell contracts; not executed without PHPUnit. |
| `tests/Fixtures/views/layout.blade.php` | Clearly labelled sample content, native form and final focus target; no production route. |
| `tests/Support/layout-runtime.php` | Test-only in-memory users/session, route context, no-PDO query recorder and assertions. |
| `tests/Support/render-layout.php` | CLI contract runner and fixture export. |
| `tests/Support/layout-browser.cjs` | Local-only responsive/keyboard/visual/content regression suite. |
| `docs/codex/PHASE_02_LAYOUT.md` | This implementation inventory, evidence and limitations. |

### Reference documentation assets (14, already committed at starting HEAD; unchanged)

All paths below are under `docs/frontend/references/figma/`:

1. `00 • Foundations — Brand, color, type and reusable UI.png`
2. `01 • Login — Desktop.png`
3. `02 • Representative — Dashboard.png`
4. `03 • Representative — Room availability.png`
5. `04 • Representative — New reservation.png`
6. `05 • Representative — My bookings.png`
7. `06 • Representative — Booking details.png`
8. `07 • Lab staff — Dashboard.png`
9. `08 • Lab staff — Approval queue.png`
10. `09 • Lab staff — Today’s schedule.png`
11. `10 • Admin — Manage rooms.png`
12. `11 • Admin — Equipment inventory.png`
13. `12 • Mobile — Representative dashboard.png`
14. `Reusable UI components.png`

The pre-existing modified `docker-compose.yml` and untracked `docker-compose.yml.save` are excluded from this work and unchanged from the pre-edit snapshot. Font binaries/license and every pre-existing screen template except the shared layout are unchanged. No backend, legacy CSS, infrastructure or dependency manifest edits occurred.

## Plan deviations and review gate

No functional scope deviation was introduced. Approved adaptations are visible above: real logout instead of unsupported Help, preserved Accounts/Timetable capabilities, neutral advisory wording, readable microcopy, and responsive interactions not depicted in exports. Exact pixel identity is not claimed. The main implementation adjustment discovered in testing was to let JavaScript own toggle visibility so CSS cannot hide it before focus transfer.

Review the shell screenshots, deliberate visual differences, content-width behavior and verification gaps before acceptance. Full authenticated workflow and broader accessibility checks remain unrun; this report does not substitute isolated rendering for them. Stop here: no Phase 03, staging, commit, push, PR or merge is authorized by this handoff.
