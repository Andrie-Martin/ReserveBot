# Stage 2 — task-focused UX refinement

Initial Stage 2 baseline: `c4c40b5` (merged shell correction), branch `feat/frontend-task-ux`. The initial implementation below was subsequently committed locally as `7662632`. Its results remain historical evidence. The [Free Rooms follow-up correction](#free-rooms-follow-up-correction) at the end supersedes the original availability placement and in-cell disclosure design. That correction was committed locally as `39450c0`; the [contextual event panel correction](#contextual-event-panel-correction) below is the current uncommitted work; no Stage 3/4, Phase 05, PR, push, merge or deployment.

## Source-verified findings and implementation

`routes/web.php:26` retains guest GET/POST login and recovery routes. `Auth/LoginController.php:19` normalizes email, attempts active-account authentication, preserves only email on failure, regenerates the session on success and uses the existing role destination. The form-first template changes none of these contracts. It retains the existing field components, error bag, autofocus/autocomplete and native submission. Supporting copy and the unofficial-site disclaimer follow the card in logical reading order. Artwork is hidden on short laptops; validation and enlarged text use natural document scrolling.

`routes/web.php:52` applies auth/auth.session/account to workspace routes. `RepController.php:12` supplies identity, current semester classes, `ClassChecks::forBlock(section,7)` meetings and all three shortcuts. `ClassChecks.php:111` supplies non-ended meetings; `classes/_answer.blade.php:5` separately suppresses ended/booked forms and disables current answers. The latter partial is byte-identical. A shared `_meeting` partial renders every record once, composing that original form. The template sorts supplied records, partitions today's plus non-ended waiting/freed meetings from other meetings, and wraps only the latter group in native disclosure. The full timetable retains every column and row in a second disclosure. The synthetic ended fixture tests defensive presentation; it does not imply the controller normally supplies ended meetings.

`AvailabilityController.php:29` resolves rules, dates, slots and native search. Its `:57` room mapping supplies free ranges, busy records, match/clash/all-day states and permitted URLs. `:82` retains class-specific booking eligibility. `RoomAvailability` remains responsible for role-filtered agenda/privacy. The new lower results section consumes the exact existing search/room partial content after the upper calendar/day section. Room rows replace large cards through scoped CSS; all free intervals remain visible. Original busy details stay native disclosures. Calendar date links retain their original accessible previews and URL/query/fragment values, while a separate native disclosure exposes all supplied events, including overflow and dates without a selectable link. No hidden information is fetched or inferred.

No application JavaScript was added. The existing shell's CSS, JS, layout and navigation partials are unchanged. `:has()` conditionals, backend code, route definitions, models, authorization, database, dependencies and Figma PNGs are unchanged. The architecture source map's backend traces remain current; the presentation inventory is updated separately.

## Functional Parity Matrix — login

| Existing capability/state | Before → after location/behavior | Verification |
|---|---|---|
| Email/password, POST login, CSRF | Same native form/components; now first content block | Full form/control/FormData comparison; intercepted keyboard submission |
| Required types, names, IDs, autocomplete, autofocus | Exact original controls and attributes | Auth rendering and browser contracts |
| Old email, empty password, default errors, status | Same field/error/status conditions inside card | Normal/error/status fixtures; error and enlarged-text reachability |
| Password recovery | Same `password.request` URL in card footer | Link parity; initially visible at three laptop sizes; focus reachability |
| Identity, instructions, introductory copy | All retained; promotional heading becomes supporting paragraph below form | Auth paragraph parity and source diff |
| Unofficial-site disclaimer | Retained immediately after card | Rendering/source and screenshot review |
| Artwork | Same local decorative SVG, hidden at short heights | Responsive visibility; no functional content inside decoration |
| Real authentication/session/security | Controller, middleware, routes unchanged | Protected-file verification; HTTP workflow **not rerun** |

## Functional Parity Matrix — representative dashboard

| Existing capability/state | Before → after location/behavior | Verification |
|---|---|---|
| Name, department, optional section, introduction, optional stat | Large identity card → compact header; conditions retained | Complete text parity, long/no-section fixtures |
| All three shortcuts | Side column → immediately after identity, exact URLs/labels | Link multiset parity and three-route renderer checks |
| Every meeting/date/time/course/room/status | One shared partial per supplied record; chronological within each group | Full record multiset comparison, ten-record priority fixture, exact counts/order |
| Non-ended waiting/freed and today's meetings | Always outside disclosure | Seven priority records covering all five today's states plus future waiting/freed |
| Other upcoming meetings | Counted native disclosure | Remaining three test records retained; keyboard/Space/Enter/no-JS checks |
| Class answer POST, CSRF, class/date URL, submitter values | Same unchanged `classes._answer` include | Full form multiset parity; eight priority forms; intercepted POST |
| Selected/disabled answers, ended/booked suppression | Same partial conditions | All-state fixtures; existing rendering checks |
| `#face-to-face`, feedback, email/reminder guidance | Same heading target and messages above groups | Information parity; shell native-fragment tests; feedback fixture |
| Complete semester timetable | Counted disclosure containing original table semantics/columns/rows | Full row multiset parity; ten visible rows after expansion; horizontal keyboard scroll |
| Empty classes/meetings, no section, unconnected email | Original conditions/messages and shared shell retained | Rendering/browser fixtures |
| Staff/admin dashboard | Unchanged original branch, no new dashboard stylesheet | Exact main HTML equality assertion |
| Authorization, confirmation transitions and emails | Unchanged backend | Protected-file verification; HTTP workflow **not rerun** |

## Functional Parity Matrix — room availability

| Existing capability/state | Before → after location/behavior | Verification |
|---|---|---|
| Department, booking horizon, heading | Compact full-width upper heading | Original text parity |
| Month arrows, Today, previous/next day, disabled boundaries | Same supplied URLs and conditions in upper section | Complete link/ARIA parity; boundary fixtures |
| Date selection, current/today/closed/past/later markers | Same table/date anchor/IDs/attributes | Rendering and original information parity, target-size/keyboard checks |
| Preview time/text/room/equipment/title/check/status, overflow count | Original accessible preview retained; compact indicators and full native details added | Original title/text parity; four-event overflow disclosure keyboard/no-JS test |
| Complete selected-day agenda, booking/detail links | Same `_agenda` include in upper `#day` section | Link/text parity for rep/staff/admin; fragment navigation |
| Native GET date/from/to, slot values, selected/required state | Exact extracted form below upper section, full width | Full control/options/FormData parity; intercepted GET and Clear URL checks |
| Search counts, successful/conflict/started/invalid messages | Same extracted feedback following form | Search/no-match/started/error fixtures and information parity |
| Every room/name/capacity/Book action | Compact full-width row with original `_room` markup/conditions | Complete record/link multiset comparison; four-row targeted assertion |
| Every free interval | Still visible without disclosure | Four visible fixture intervals; complete room-record parity |
| Busy schedules, status, clashes, all-day explanations | Original native busy disclosures retained | Original disclosure multiset subset check; record/text parity |
| Closed/outside-term/invalid-date/empty/long states | Original branches; closed days still omit search/results | All-role fixture matrix and responsive tests |
| Role-specific privacy/booking permissions | Same supplied service data, no new query or eligibility calculation | Renderer no-extra-query and non-rep Book suppression; per-role parity. Service/HTTP security **not rerun** |
| `#day`, query navigation, history, focus | Same target and links; unchanged stationary shell | Both shell suites, native GET/fragment/Back/Forward/reload/focus tests |

Parity comparisons use complete multisets for reordered forms, links, tooltip titles, meeting rows, timetable rows and room records. Duplicate counts matter. Every original text fragment remains and every original disclosure must still occur; new disclosures are tested independently. This preserves equivalence while permitting the approved DOM order/nesting changes.

## Complete changed-file inventory

| File | Purpose |
|---|---|
| `public/css/auth.css` | Form-first login spacing, width and short-height artwork rule |
| `public/css/dashboard.css` | Compact identity, early shortcuts, meeting density, wrapping |
| `public/css/availability.css` | Upper calendar/agenda grid, full-width results, compact rows and calendar |
| `public/css/foundations/components.css` | Scoped native disclosure styles/focus/target sizing |
| `resources/views/auth/login.blade.php` | Form-first DOM order, retained supporting copy |
| `resources/views/home/_representative.blade.php` | Early shortcuts, chronological partition, counted disclosures |
| `resources/views/home/_meeting.blade.php` (new) | Single shared meeting renderer composing original answer partial |
| `resources/views/components/ui/disclosure.blade.php` (new) | Presentation-only native disclosure with slots/attributes |
| `resources/views/rooms/index.blade.php` | Compose full-width heading, upper sections, lower results |
| `resources/views/rooms/_calendar.blade.php` | Move page introduction to full-width parent |
| `resources/views/rooms/_calendar-day.blade.php` | Counted, accessible event disclosure beside each date link |
| `resources/views/rooms/_calendar-events.blade.php` (new) | Every supplied calendar event, escaped text/details |
| `resources/views/rooms/_day.blade.php` | Preserve upper day target/navigation/agenda; extract lower content |
| `resources/views/rooms/_search.blade.php` (new) | Exact existing native finder and feedback |
| `resources/views/rooms/_results.blade.php` (new) | Lower search/results composition, original room loop |
| `tests/Support/foundations-runtime.php` | Disclosure attribute, escaping, native/default/open contracts |
| `tests/Support/availability-runtime.php` | Rich all-status/today/future/ended fixture with ten timetable rows |
| `tests/Support/availability-browser.cjs` | Reordering-aware complete multiset parity, retained disclosure checks |
| `tests/Support/layout-browser.cjs` | Exempt intentional login restyling from unchanged-style assertion; retain native form parity and other-screen style checks |
| `tests/Support/shell-refinement-browser.cjs` | Open native ancestor disclosures before final-control/record reachability checks |
| `tests/Support/shell-navigation-browser.cjs` | Open timetable natively before horizontal keyboard-scroll test |
| `tests/Support/task-ux-browser.cjs` (new) | Required laptop geometry, records, disclosures, 306 responsive cases, version-correct screenshots |
| `docs/frontend/DESIGN_SYSTEM.md` | Record Stage 2 defaults and reusable disclosure contract |
| `docs/frontend/SCREEN_INVENTORY.md` | S01/S08/S11 presentation update |
| `docs/codex/STAGE_02_TASK_UX.md` (new) | This report, matrices, results and limitations |

The user's modified `docker-compose.yml` and untracked `docker-compose.yml.save` are excluded: both retain their starting hashes.

## Isolated execution and reproduction

Baseline is a tracked archive of `c4c40b5` under `/tmp/rb-task-ux/before`; current source is copied to `after` without `.env` or Docker files. Both snapshots reuse the existing container's `/var/www/html/vendor`. The same expanded fixture generator is copied to both snapshots, without changing baseline application files. Harnesses disable database connections/use the no-PDO recorder and temporary compiled views with array session/cache/mail. No new dependencies, service restart, HTTP application traffic, database provisioning/mutation or mail delivery.

Inside `reservebot-app-1`, run `php /tmp/rb-task-ux/after/tests/Support/render-{foundations,layout,auth,availability}.php --check` (one command per renderer). Export both snapshots with `render-{layout,auth,availability}.php --export /tmp/rb-task-ux/{before,after}-{layout,auth,availability}` and copy outputs to host. From repository root:

```text
node tests/Support/foundations-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-task-ux/after-layout
node tests/Support/layout-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-task-ux/after-layout /tmp/rb-task-ux/before-layout
node tests/Support/auth-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-task-ux/after-auth /tmp/rb-task-ux/before-auth
node tests/Support/availability-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-task-ux/after-availability /tmp/rb-task-ux/before-availability
node tests/Support/shell-refinement-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-task-ux
node tests/Support/shell-navigation-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-task-ux
node tests/Support/task-ux-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-task-ux
```

All browser traffic is intercepted. Sample native submissions are recorded rather than sent to the application. Stage 2 and shell screenshots use each snapshot's own CSS/JS/fonts; older comparison suites use current assets for DOM parity and their captures alone are not historical styling evidence.

## Verification results

| Check | Observed result |
|---|---|
| Foundations rendering | PASS — 63 assertions, including four new disclosure contracts |
| Layout rendering | PASS — 279 assertions |
| Auth rendering | PASS — 1,318 assertions |
| Availability rendering | PASS — 848 assertions, 43 fixtures including priority records |
| Foundations browser | PASS — nine legacy conditional states, native FormData, local fonts, contrast/focus/enlargement |
| Layout browser | PASS — 72 role/state/width combinations plus menu, no-JS, focus and unchanged conditional/booking style/payload comparisons |
| Auth browser | PASS — 60 fixtures, 420 responsive states; all native fields/forms/links, named bags, intercepted submissions and no-JS |
| Availability browser | PASS — 43 fixtures, 258 responsive states; complete record/form/link multiset parity and original information/disclosures |
| Shell geometry browser | PASS — 351 combinations, 324 stationary top/middle/bottom measurements |
| Shell navigation browser | PASS — nine groups: fragments, Back/Forward/reload/restoration, focus/validation, short/mobile fallback, no-JS and content-length independence |
| Stage 2 browser | PASS — 306 viewport/text/fixture combinations, DPR 2, initial laptop login visibility, all-status grouping, timetable parity, keyboard/no-JS details and full-width results |
| Syntax/integrity | Changed CJS syntax checks, PHP rendering, `git diff --check`; 125 protected tracked files identical to HEAD |
| PHPUnit unit suite | NOT RUN — attempted command failed because `vendor/bin/phpunit` is absent |

Stage 2 viewport pairs: 1438×638, 1440×900, 1366×768, 1280×720, 1024×600, 768×1024, 390×844, 375×812, 320×640; additional 1438×400/631/632, 1023/1024/1025×800 and 1279/1281×720. All run at 100%, 125% and 200% root text on normal/error login, priority/long dashboard, normal/long availability. The shell suite additionally covers staff/admin, unconnected, guest/onboarding/reduced errors and the 633px height boundary. Focus/action reachability is measured separately from initial visibility.

The first browser pass detected long unbroken identity overflow after removing the original card; wrapping and an explicit shrinkable grid column corrected it. Screenshot review shortened repetitive calendar disclosure dates to accessible text and fixed singular event wording. Tests were rerun after those corrections. The only test expectation adaptations concern approved reordering/disclosure nesting and intentional login styling; original field, record and URL equivalence remains enforced.

## Geometry at 1438×638 CSS pixels

Normal fixture, 100% text, DPR 2:

| Measurement | Before | After |
|---|---:|---:|
| Login document height | 1,042px | 761px |
| Login email rectangle y / height | Below initial form introduction | 291.5 / 44px |
| Login password rectangle y / height | Below initial viewport | 388.5 / 44px |
| Login submit rectangle y / height | Below initial viewport | 456.5 / 44px |
| Login recovery rectangle y / height | Below initial viewport | 524.5 / 44px |
| Dashboard content height | 1,008px | 799px |
| Availability content height | 1,916px | 1,494px |
| Authenticated document height | 638px | 638px |

The normal authenticated content region is x=252, y=107, width=1,186, height=531px. Room results occupy x=300, width=1,090px, exactly the available page grid width; upper calendar and agenda are each 537px wide. Calendar top=224.5, bottom=966.5; agenda top=224.5, bottom=953.5; lower results top=982.5px in the initial viewport coordinate system. The lower section is reached by the existing content-region scrolling; it is not forced into a fixed-height panel.

Long-name fixtures expand the header naturally: masthead rectangle `(0,0,1438,30)`, header `(0,30,1438,165)`, sidebar `(0,195,252,443)`. Every rectangle is unchanged at top/middle/bottom. Main panel width/scrollWidth both 1,186px, document height 638px and document scrollTop 0. Dashboard panel offsets are 0/318/636px; availability offsets 0/972/1943px. Both final controls and final records remain reachable, opening timetable disclosure natively when needed. These observations prove the preserved stationary shell rather than inferring it from CSS.

## Screenshots and evidence

Temporary evidence root: `/tmp/rb-task-ux` (not committed; may be cleared). Nine identical-fixture before/after pairs cover all three screens at 1438×638, 1440×900 and 390×844, all DPR 2. The filename pattern is `stage2-{auth-login,availability-home-rep-normal,availability-rooms-rep-normal}-{width}x{height}-{before,after}.png`.

| Screen | Laptop before / after | Desktop before / after | Mobile before / after |
|---|---|---|---|
| Login | [Before](/tmp/rb-task-ux/stage2-auth-login-1438x638-before.png) · [After](/tmp/rb-task-ux/stage2-auth-login-1438x638-after.png) | [Before](/tmp/rb-task-ux/stage2-auth-login-1440x900-before.png) · [After](/tmp/rb-task-ux/stage2-auth-login-1440x900-after.png) | [Before](/tmp/rb-task-ux/stage2-auth-login-390x844-before.png) · [After](/tmp/rb-task-ux/stage2-auth-login-390x844-after.png) |
| Dashboard | [Before](/tmp/rb-task-ux/stage2-availability-home-rep-normal-1438x638-before.png) · [After](/tmp/rb-task-ux/stage2-availability-home-rep-normal-1438x638-after.png) | [Before](/tmp/rb-task-ux/stage2-availability-home-rep-normal-1440x900-before.png) · [After](/tmp/rb-task-ux/stage2-availability-home-rep-normal-1440x900-after.png) | [Before](/tmp/rb-task-ux/stage2-availability-home-rep-normal-390x844-before.png) · [After](/tmp/rb-task-ux/stage2-availability-home-rep-normal-390x844-after.png) |
| Availability | [Before](/tmp/rb-task-ux/stage2-availability-rooms-rep-normal-1438x638-before.png) · [After](/tmp/rb-task-ux/stage2-availability-rooms-rep-normal-1438x638-after.png) | [Before](/tmp/rb-task-ux/stage2-availability-rooms-rep-normal-1440x900-before.png) · [After](/tmp/rb-task-ux/stage2-availability-rooms-rep-normal-1440x900-after.png) | [Before](/tmp/rb-task-ux/stage2-availability-rooms-rep-normal-390x844-before.png) · [After](/tmp/rb-task-ux/stage2-availability-rooms-rep-normal-390x844-after.png) |

Laptop login/dashboard/availability and mobile dashboard/calendar captures were visually inspected. The shell suite also captures long-content before/after views and middle/bottom positions. Machine-readable evidence: [Stage 2 geometry](/tmp/rb-task-ux/task-ux-results.json), [shell geometry](/tmp/rb-task-ux/shell-results.json), [navigation](/tmp/rb-task-ux/navigation-results.json), [integrity](/tmp/rb-task-ux/integrity.json). Each `after-{layout,auth,availability}` directory contains its existing suite JSON and additional screenshots.

Protected-file comparison covers backend, routes, database/config/bootstrap, Figma PNGs, public JS, layouts, class-answer partials, legacy/shell CSS and dependency manifests. Both Docker hashes match the starting values:

```text
ab8f57755ba295dc937cffd7b5c0b746a5b21a0397612d2e6ecb14bfa67b62e8  docker-compose.yml
45a1e6e6f347925d60b2898ed08efe15953e7b669345b7b67781a13883dbcf79  docker-compose.yml.save
```

## Limitations and deviations

No HTTP workflow/database test was necessary for these presentation changes or executed. `vendor/bin/phpunit --testsuite Unit` in the isolated snapshot could not run: the container has Laravel runtime dependencies but no PHPUnit executable. No test database was provisioned; existing authentication/authorization/availability HTTP suites remain unexecuted in this task. Unchanged source and supplied-data fixtures are not security or service-privacy certification.

Chromium only. No real browser-toolbar zoom (125/200% root-text enlargement was tested), physical-device, Safari/Firefox, screen-reader or live-Figma inspection claim. Native exact panel-history restoration without JavaScript remains browser-dependent as documented by the unchanged shell. Expanded calendar details grow their table row naturally; narrow calendars and timetables use the existing horizontal region scrolling. Supporting login copy, enlarged/error states and long records may require natural scrolling. Today/unanswered records remain undisclosed, but arbitrarily many records cannot all fit in one viewport.

No scope deviation into backend or later stages. Original introductory copy is retained below the form; free intervals remain visible rather than adding unnecessary extra disclosures. Native calendar event disclosures are the accessibility adaptation required by the brief. Manual QA remains assigned to the user's two groupmates after the complete frontend redesign. Work stops after Stage 2 verification and stays uncommitted.

## Free Rooms follow-up correction

Baseline: **`766263276feeb68a421663dfc7fbdab4856974e6`**, branch **`feat/frontend-task-ux`**. The original Stage 2 commit is preserved. This correction addresses the user-observed Computer Engineering admin layout without reading or modifying live application data.

### Correction and current information architecture

The previous full-event disclosures were children of calendar table cells. Long descriptions/equipment expanded those rows, and the room search came after the calendar/agenda. Both were presentation problems; `AvailabilityController::index` (`:29`, room mapping `:57`, role-aware class links `:82`) and `RoomAvailability` still supply all data, rules, statuses and permitted URLs through the unchanged protected `/rooms` route (`routes/web.php:71`, auth/auth.session/account group at `:52`).

The current page order is:

1. Page heading, selected date and original previous/next-day navigation.
2. The unchanged native time-search form and feedback.
3. The unchanged compact, full-width room results.
4. Calendar and selected-day agenda as secondary content, reached directly through a native “Calendar and agenda” fragment link beside the date controls.
5. Full-width event details by date, outside the calendar table.

Each calendar cell retains its original date link, indicators, states, preview text and event count. The event-count link targets a unique `events-YYYY-MM-DD` disclosure below the calendar. Native summary activation reveals the same full event list, including time, room/equipment details, description, status and class-check information. There is no JavaScript, overlay, modal, clipping, fixed-height detail panel, duplicate search form or extra permission calculation. Expanding multiple dates affects only the separate detail area. The selected-day agenda and every existing month/Today/date URL remain intact.

The calendar and agenda remain in two columns above 64rem and stack below it. Search label and controls share a row on sufficiently wide screens; narrow/enlarged layouts wrap naturally. Every free interval stays visible, and original busy-schedule disclosures and permitted Book links remain unchanged.

### Follow-up functional parity matrix

| Contract from `7662632` | Corrected placement/behavior | Evidence |
|---|---|---|
| Selected date, day navigation and `#day` | First task section, same ID and supplied URLs | Exact original links/ARIA parity, direct fragment/reload/day-navigation tests |
| Native GET `date/from/to`, options, validation, Clear, feedback | Immediately after selected date, before calendar/agenda | Complete forms/options/FormData parity, intercepted GET, all-role rendering |
| Every room/capacity/free interval/status/clash/action | Same `_results`, `_room`, `_search` content; now first primary results | Exact record/form/link comparison; source byte identity |
| Month arrows, Today, date links, horizon/closed/past/current states | Secondary calendar with direct shortcut | Exact original link/tooltip/text comparison; boundary states |
| Calendar full event details and counts | Same event content in full-width per-date disclosures outside table | Full event arrays compared by date against baseline, including order and duplicate counts for rep/staff/admin |
| Calendar count controls | In-cell disclosure replaced by native link to unique external disclosure | Every new count link resolves to exactly one disclosure/summary; no table contains a `details` element |
| Selected-day agenda and authorized action links | Same `_agenda`, moved after primary results beside calendar | Original information/link parity and unchanged source |
| Keyboard/touch/no-JS | Native anchors and summaries, visible focus, natural detail flow | One/two-date Enter activation, no-JS all-role checks, focus geometry |
| History and scrolling | Existing stationary shell unchanged; native fragment entries added | Existing shell Back/Forward/reload/`#day` checks plus new fragment Back/Forward tests |
| Login/dashboard, backend, privacy and database behavior | Unchanged | 301 tracked-file byte comparisons plus original regression suites; no live HTTP/security certification |

The existing link multiset comparison excludes **only** the newly added `.rb-availability__event-count` links and `#calendar-and-agenda` shortcut. Every original link remains subject to exact comparison. The targeted suite separately verifies all new destinations, keyboard activation and native history. Every original disclosure's normalized content remains present. Role-specific event arrays are compared against their own baseline; no cross-role data is introduced.

### Follow-up changed-file inventory

| File | Change |
|---|---|
| `resources/views/rooms/index.blade.php` | Availability-first composition and named secondary section |
| `resources/views/rooms/_day.blade.php` | Direct calendar/agenda shortcut; move agenda out of first task section |
| `resources/views/rooms/_calendar-day.blade.php` | Replace table-cell disclosure with counted native target link |
| `resources/views/rooms/_calendar-details.blade.php` (new) | Full-width per-date disclosures, composing the unchanged event-list partial |
| `public/css/availability.css` | Single primary column, secondary grid, inline desktop search label, scoped count links and naturally sized external details |
| `tests/Support/availability-runtime.php` | Three role-specific long-event fixtures, including November 3 and 5 descriptions/equipment |
| `tests/Support/availability-browser.cjs` | Preserve exact old-link comparison while identifying the two new fragment-link categories |
| `tests/Support/rooms-correction-browser.cjs` (new) | Event parity, one/multiple expansion geometry, initial visibility, focus/history/no-JS, screenshots |
| `docs/frontend/DESIGN_SYSTEM.md` | Replace superseded calendar/search placement and disclosure guidance |
| `docs/frontend/SCREEN_INVENTORY.md` | Update S11 presentation placement |
| `docs/codex/STAGE_02_TASK_UX.md` | Historical baseline clarification and this correction report |

No changes to login/dashboard code, room-row/search/agenda/event-content partials, shared disclosure component, shell CSS/JS/layouts, backend/routes/database, dependencies, Docker configuration or reference PNGs. The pre-existing Docker modifications are not part of this inventory.

### Executed verification

Evidence root: **`/tmp/rb-rooms-correction`**, separate from initial Stage 2 evidence. `before` is a tracked archive of `7662632`; `after` is the corrected source snapshot. Identical richer test fixture data is used on both sides; baseline application sources are untouched. Existing container dependencies are reused through a vendor symlink. No `.env` copy, application HTTP requests, service restart, database connection/mutation, migration, seed, install or mail operation.

Commands follow the initial report's reproduction procedure, replacing `/tmp/rb-task-ux` with `/tmp/rb-rooms-correction`. All four `render-*.php --check` commands and all seven existing browser scripts were executed. Additionally:

```text
node tests/Support/rooms-correction-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-rooms-correction
```

| Suite | Observed result |
|---|---|
| Foundations/layout/auth/availability rendering | PASS — 63 / 279 / 1,318 / 2,137 assertions |
| Foundations browser | PASS — nine legacy conditional states, native controls, fonts/focus/enlargement |
| Layout browser | PASS — 72 role/state/width cases and existing keyboard/no-JS/geometry checks |
| Auth browser | PASS — 60 fixtures / 420 responsive states |
| Availability browser | PASS — 46 fixtures / 276 responsive states; exact original record/form/link/disclosure parity |
| Shell geometry | PASS — 351 cases / 324 stationary measurements |
| Shell navigation | PASS — nine groups, including native GET/day navigation, `#day`, Back/Forward/reload/restoration, focus/validation, no-JS and mobile |
| Existing Stage 2 targeted suite | PASS — 306 combinations; login/dashboard successes retained |
| New correction suite | PASS — 99 combinations; 3 roles × 11 viewports × 3 text sizes, each opening one then two long-event dates |
| Syntax and whitespace | PASS — changed PHP/CJS syntax and `git diff --check` |
| Protected-source integrity | PASS — 301 tracked files outside the change allowlist byte-identical to `7662632`; both Docker hashes unchanged |

Correction viewports: 1438×638 (DPR 2), 1440×900, 1366×768, 1280×720, 1024×600, 768×1024, 390×844, 375×812, 320×640 and 1438×631/632; each at 100%, 125% and 200% root text. Existing suites retain additional boundary, invalid/closed/started/no-match/empty/long, privacy-presentation and navigation cases. The final color-only adjustment sets count links to the existing maroon token; the targeted suite was rerun afterward.

### Measured laptop geometry

At **1438×638 CSS pixels**, normal fixture at 100% text, all three roles:

| Element | Top | Height | Bottom |
|---|---:|---:|---:|
| Search form | 334.5px | 76px | 410.5px |
| Start/end/select and Find rooms controls | 350.5px | 44px | 394.5px |
| First room row — admin/staff | 512.5px | 62px | 574.5px |
| First room row — representative (Book action) | 512.5px | 77px | 589.5px |
| Main content viewport | 107px | 531px | 638px |

Initial content-panel scrollTop=0, document scrollTop=0, document dimensions=1438×638. Search and the complete first room row fit without scrolling in these normal fixtures. Enlarged text and exceptional states remain scrollable rather than being clipped to meet the normal-state target.

Admin long-description/equipment fixture:

| Measurement | All closed | November 3 open | November 3 + 5 open |
|---|---:|---:|---:|
| Calendar table height | 567px | 567px | 567px |
| Calendar card height | 737px | 737px | 737px |
| Header row height | 34.5px | 34.5px | 34.5px |
| Week row heights | 96, 122, 122, 96, 96px | Identical | Identical |

All calendar/card/row-height equality checks also pass at every tested role/viewport/text combination. Keyboard focus on the outside detail summaries scrolls only the content panel in desktop mode; the observed admin offsets after opening November 3 and November 5 are 1,655px and 3,145px. Document scrollTop remains 0 and document height 638px. Detail bodies use visible/natural overflow; page width never exceeds viewport width. Long descriptions can increase detail-section height, but cannot participate in table layout.

### Screenshots and machine-readable evidence

The new suite captures the actual stationary shell and scroll position without restyling it for screenshots:

- [Initial admin Free Rooms page](/tmp/rb-rooms-correction/correction-initial-admin-1438x638.png).
- [Search controls and first room rows](/tmp/rb-rooms-correction/correction-search-rooms-1438x638.png).
- [Calendar, details closed](/tmp/rb-rooms-correction/correction-calendar-closed-1438x638.png).
- [Calendar with November 3 and 5 expanded outside it](/tmp/rb-rooms-correction/correction-calendar-multiple-open-1438x638.png).
- [Full-width expanded event details](/tmp/rb-rooms-correction/correction-expanded-detail-1438x638.png).
- [Mobile layout](/tmp/rb-rooms-correction/correction-mobile-390x844.png).
- [Measured geometry and complete correction results](/tmp/rb-rooms-correction/rooms-correction-results.json), [shell results](/tmp/rb-rooms-correction/shell-results.json), [navigation results](/tmp/rb-rooms-correction/navigation-results.json), [integrity](/tmp/rb-rooms-correction/integrity.json).

The existing Stage 2 suite also captured committed-baseline/current screenshots with each snapshot's own assets at 1438×638, 1440×900 and 390×844. Initial admin, calendar and full-detail captures were visually inspected. Expanded detail content is below the calendar; the separate detail screenshot demonstrates its full-width layout. `/tmp` evidence is temporary and uncommitted.

### Limits and handoff

These are isolated rendering and Chromium tests over explicit synthetic records, including long authorized-description fixtures. They are not a live reproduction of the user's private bookings or proof of backend privacy/authorization. No production/dev database or HTTP workflow tests ran. PHPUnit remains unavailable in the container (file availability checked). Actual browser-toolbar zoom, physical-device, screen-reader and other-browser testing were not performed; root-text enlargement and native keyboard/no-JS behavior were.

No deviation from the approved follow-up scope. Native fragment links avoid adding JavaScript state. Calendar/agenda are secondary content, not hidden or removed. Both Docker files retain SHA-256 values recorded earlier in this report. The original `7662632` commit remains unchanged, and all follow-up work stays uncommitted for review. No PR, push, merge, deployment or subsequent phase.

## Contextual event panel correction

Baseline **`39450c0`**, branch **`feat/frontend-task-ux`**. The previous correction is committed; this follow-up remains uncommitted. It addresses the user's observed jump to a collapsed disclosure after clicking a calendar event count. Earlier report sections describe their historical implementations and evidence, not this interaction.

### Verified structure and solution

Previously `_calendar-day` linked to a closed `details` element in `_calendar-details`, positioned after both the calendar and selected-day agenda. Native fragment navigation consequently scrolled to the end of that section and still required summary activation. CSS alone could not reliably provide immediate contextual selection and focus while preserving a native fallback.

`rooms.index` now composes calendar, contextual event panel, then the separate selected-day agenda. The calendar and panel share the two desktop columns; on mobile the panel follows the calendar. The agenda occupies the full width below them. It still calls the original `_agenda` partial with `$dayItems`; its booking/detail links are not replaced with calendar records. The event panel still renders `$calendar['weeks'][…]['items']` through the original `_calendar-events` partial. Both partials are unchanged.

Source authority remains `routes/web.php:52/71`, `AvailabilityController.php:29/57/82` and `RoomAvailability.php:158`. They own authentication, date/search rules, permitted booking links, and role-filtered records. The templates/script do not query, fetch, reconstruct permission rules, combine the calendar and agenda datasets, or invent data.

A small deferred `public/js/availability.js` loads only from the rooms view through its existing head stack. It enhances count links to accessible button semantics (`role=button`, `aria-controls`, `aria-expanded`), supports native Enter and explicit Space activation, and shows exactly one existing server-rendered group. Selecting a date opens its native disclosure immediately and focuses the summary using ordinary browser focus. A polite status message identifies the selected date. Native collapse updates the triggering control's expanded state. There are no scroll offsets, `scrollTo` calls, history mutations, fetches, frameworks, overlays or dependencies in this script.

Ordinary selection changes only panel state; it does not navigate or add history entries. Original link `href` values remain available for no-JS/modified activation and bookmarks. On direct event-fragment loads or native hash/history changes, the matching event group is revealed. Date/month/Today/search links, `#day` and the shared shell's existing Back/Forward/restoration implementation remain untouched.

Without JavaScript every per-date disclosure is open in the server HTML. One native event-count link therefore navigates directly to readable details without a second activation. This fallback can be long; it deliberately retains all information and avoids a script-dependent hidden interface. With JavaScript, an initial prompt occupies the panel until a date is selected. Long details use natural content scrolling; they cannot change calendar table-row heights.

### Functional parity matrix

| Contract at `39450c0` | Current behavior | Evidence |
|---|---|---|
| Search-first date, native GET controls, room rows and free intervals | Unchanged | Rendering, exact form/record/link comparisons, initial laptop geometry |
| Calendar dates/counts/status/current/closed/out-of-range markers | Same records and native date URLs | Existing availability comparison and rendering contracts |
| Event-count activation | Bottom fragment jump + second click → immediate contextual panel with JS | Pointer, Enter, Space, consecutive dates and no-downward-jump measurements |
| Complete calendar event details | Same per-date ordered event list, only selected group exposed with JS | Full baseline/current record arrays for each role, including long descriptions/equipment |
| Selected-day agenda | Same independent `$dayItems` and original action links, now below calendar/panel pair | Unchanged `_agenda`; original text/link parity |
| No JS | Count link reaches an already-open date group | All three roles, two dates, one native click per date |
| Mobile/touch | Panel follows calendar; normal focus makes selected details reachable | Touch-enabled Chromium tap, panel/focus geometry |
| Screen-reader/keyboard semantics | Controlled-region relationship, expanded state, native summary focus, polite announcement | DOM/keyboard assertions and source review; no actual screen-reader certification |
| Calendar table sizing | Event content stays outside cells | Equal calendar/card/every-row height across consecutive selections and text sizes |
| URLs/history/shell | Native navigation unchanged; local selections add no history entries | Same URL/history length across selection; original shell `#day`, Back/Forward/reload tests; direct event bookmark test |
| Authorization/privacy/backend | Unchanged source and role-specific supplied data | Protected-file comparison; HTTP/security workflows not rerun |

The obsolete screen-reader suffix “view details below calendar” was removed from event-count labels, since the desktop details are now adjacent. The availability information comparison normalizes **only that obsolete suffix inside event-count controls**. Every actual event description, status, time, record, form and original application URL remains compared. Existing Stage 2 and correction tests now activate the count control instead of trying to focus a hidden date summary; no-JS assertions expect the deliberately open fallback. Multiple-date testing now verifies replacement of the contextual selection rather than simultaneous expanded panels.

### Changed files

| File | Purpose |
|---|---|
| `public/js/availability.js` (new) | Minimal page-only contextual selection, keyboard/ARIA state, native fragment compatibility |
| `public/css/availability.css` | Adjacent panel placement, separate full-width agenda, hidden-group enforcement and selected count styling |
| `resources/views/rooms/index.blade.php` | Load deferred script and compose distinct calendar/panel/agenda |
| `resources/views/rooms/_calendar-details.blade.php` | Open native fallback, initial enhancement prompt and status announcement |
| `resources/views/rooms/_calendar-day.blade.php` | Remove obsolete placement instruction from accessible count name; native href unchanged |
| `tests/Support/availability-browser.cjs` | Narrow normalization of obsolete event-count helper text |
| `tests/Support/task-ux-browser.cjs` | Test enhanced count activation and open no-JS fallback |
| `tests/Support/rooms-correction-browser.cjs` | Consecutive selection, unchanged row heights, no extra history/jump, focus, desktop/mobile geometry, touch and fallback |
| `docs/frontend/DESIGN_SYSTEM.md` | Current panel/progressive-enhancement contract |
| `docs/frontend/SCREEN_INVENTORY.md` | Current S11 interaction/placement |
| `docs/codex/STAGE_02_TASK_UX.md` | This correction and evidence |

No login, dashboard, backend, route, database, dependency, Docker or shared-shell source changes. Existing search, room-row, selected-day agenda and event-list partials remain byte-identical to baseline. No subsequent stage, commit, push or PR.

### Executed verification and geometry

Evidence root **`/tmp/rb-calendar-panel`**. Baseline is a tracked archive of `39450c0`; current snapshot excludes environment and local Docker files. The existing temporary/container rendering harness disables database connections/uses its no-PDO recorder. Browser requests are fulfilled from isolated HTML/assets; test submissions are intercepted. Nothing was sent to the application or database.

All commands from the prior correction were run with `/tmp/rb-calendar-panel` substituted for its evidence root. Results:

| Suite | Actual result |
|---|---|
| Foundations/layout/auth/availability renderers | PASS: 63 / 279 / 1,318 / 2,137 assertions |
| Foundations browser | PASS: nine conditional states plus native controls, fonts, focus and enlargement |
| Layout browser | PASS: 72 role/state/width cases and original menu/focus/no-JS checks |
| Auth browser | PASS: 60 fixtures / 420 responsive combinations |
| Availability browser | PASS: 46 fixtures / 276 responsive combinations, original data/form/link/disclosure parity |
| Shell geometry | PASS: 351 cases / 324 stationary measurements |
| Shell navigation | PASS: nine groups covering `#day`, native GET/date navigation, Back/Forward/reload/restoration, focus/validation and fallback |
| Existing Stage 2 suite | PASS: 306 responsive combinations |
| Updated room correction suite | PASS: 99 role/viewport/text combinations, plus touch, direct bookmark and no-JS checks |
| Syntax/whitespace | PASS: new/changed JS syntax and `git diff --check` |

The 99-case matrix retains representative/staff/admin, the required laptop/desktop/tablet/mobile sizes and short-height boundaries, and 100/125/200% root text. Exact event arrays are compared against baseline per role. Table/card/row heights remain identical before and after selection. The existing shell suite separately verifies fixed masthead/header/sidebar geometry and sole main-panel scrolling.

At **1438×638**, normal admin fixture, DPR 2:

- Search controls: y=350.5 to 394.5px; first room row: y=512.5 to 574.5px. Initial panel scrollTop=0; document height=638px and document scrollTop=0.
- Calendar rectangle after navigating to the calendar section: x=300, y=130.5, width=537, height=737px.
- Selected contextual panel: x=853, y=130.5, width=537px. Selected-day agenda is below both columns, width=1,090px.
- Clicking November 3: panel scrollTop **743 → 743px**. Clicking November 5: **743 → 743px**. URL and history length are unchanged by either selection.
- Calendar table remains 567px tall; row heights remain 34.5, 96, 122, 122, 96, 96px even with the long-description fixture selected. The right-side content's natural height changes independently.
- Touch-enabled mobile at 390×844: calendar bottom=338.5px, contextual panel top=354.5px after native focus scrolling; selected summary lies at y=400–444px and is reachable. These viewport coordinates reflect the actual focused state, not a scroll-position script.

### Screenshots and limitations

- [Contextual details after consecutive date selections](/tmp/rb-calendar-panel/correction-calendar-multiple-open-1438x638.png) (the inherited filename says “multiple-open”; the image shows the latest selected date).
- [November 3 contextual details](/tmp/rb-calendar-panel/correction-expanded-detail-1438x638.png).
- [Initial search-first layout](/tmp/rb-calendar-panel/correction-initial-admin-1438x638.png).
- [Calendar with initial selection prompt](/tmp/rb-calendar-panel/correction-calendar-closed-1438x638.png).
- [Mobile layout](/tmp/rb-calendar-panel/correction-mobile-390x844.png) and [selected details after touch activation](/tmp/rb-calendar-panel/contextual-mobile-selected-390x844.png).
- [Complete selection/geometry results](/tmp/rb-calendar-panel/rooms-correction-results.json), [shell geometry](/tmp/rb-calendar-panel/shell-results.json), [native navigation](/tmp/rb-calendar-panel/navigation-results.json).

Desktop contextual screenshots were visually inspected. The existing Stage 2 suite also supplies baseline/current screenshots using the correct per-snapshot assets. Evidence is temporary under `/tmp` and uncommitted.

These are synthetic authorized-data fixtures and Chromium checks, not live private-booking reproduction or backend privacy/security certification. No database/HTTP workflow suite, screen reader, physical device or other browser was used. Touch is browser-emulated; 125/200% means root-text enlargement, not browser-toolbar zoom. Native focus may scroll to reveal a selected summary, especially on mobile or when selecting lower calendar rows; no custom scroll positioning is introduced. Local selection intentionally creates no Back/Forward entry, while all original navigation keeps native URLs/history and the unchanged shell restoration behavior. Long descriptions and the no-JS all-open fallback remain naturally scrollable.

Final integrity check: **302 tracked files** outside the explicit change allowlist are byte-identical to `39450c0`, including backend, routes, database, dependencies, shell, login/dashboard and original event/agenda/search/room partials. Both Docker hashes match their recorded starting values. [Integrity result](/tmp/rb-calendar-panel/integrity.json).

## Stage 2 room availability timeline

Approved brief: room availability timeline, baseline **`5a50af1`**, branch **`feat/frontend-task-ux`**. This section supersedes the earlier room-list presentation descriptions only. Implementation remains uncommitted. It does not begin another stage or Phase 05.

### Source trace and architecture

The current route chain remains `routes/web.php:52` (auth/auth.session/account), `:71` (`GET /rooms`) → `AvailabilityController::index` (`app/Http/Controllers/AvailabilityController.php:29`). The controller supplies selectable slots (`:41`), active rooms ordered by name (`:48`), role-worded busy records (`:49`), free ranges and match/clash/all-day state (`:57`), and permitted Book URLs (`:66`). `GET /book` remains representative-only and email-gated (`routes/web.php:74`). `RoomAvailability::busy` (`app/Services/RoomAvailability.php:47`, privacy wording `:128`) and `TimeSlots::freeRanges` (`app/Support/TimeSlots.php:29`) are unchanged. Search (`AvailabilityController.php:229`) tests busy overlaps independently of elapsed-slot suppression; `started` remains a separate warning (`:265`). No frontend inference replaces these rules.

`rooms._results` derives one axis from **first supplied slot** to **last supplied slot + supplied step**. It validates integer, ordered uniform slots and positive duration within a day. The label is **Selectable time window**. For supplied `[from, to]`, `x-ui.availability-track` renders:

```text
left  = 100 × (from − axisStart) / (axisEnd − axisStart)
width = 100 × (to − from)       / (axisEnd − axisStart)
```

The component validates integer, positive, ordered, nonoverlapping, in-window ranges before rendering any graph for a room; adjacent supplied intervals are retained separately. It neither merges, clamps nor recomputes intervals. Empty/invalid axes omit the board/graphs and use textual rows. A malformed room range collection suppresses its entire graph; format-safe integer pairs retain the original `TimeSlots::span` text, and unformattable values receive “Availability time unavailable.” Existing valid inputs render identical interval boundaries.

Green means the server reports free time. Neutral means not reported free; it can include elapsed slots, partial-slot conflicts and pending reservations. It never claims physical occupancy. A visible legend explains this and that booking rules/final validation still apply. Exact times remain visible by default, while decorative bands are `aria-hidden` and not interactive. No new JavaScript, token palette, dependency or production route is needed.

### Functional Parity Matrix

| Existing capability / condition | Before | After / regression evidence |
|---|---|---|
| Active rooms, order and capacity | Original ordered room list | Same ordered loop and supplied names/capacities; aligned identity column, sticky within horizontal board |
| Every free interval and boundary | Visible chips | Same visible `TimeSlots::span` text plus proportional bands; no collapsed-only exact times |
| No free time / fully blocked reason | Visible room text | Identical supplied explanation; neutral graph where valid, never an inferred occupancy label |
| Busy classes/bookings, statuses, reasons and authorized details | Native per-room disclosure | Same loop/conditions/text in native disclosure; expanded content spans row, no fixed height |
| Representative-only Book action | Existing caller-provided URL beside identity | Same native link, accessible room name and exact URL in aligned action column; absent when original caller omits it |
| GET search, date/from/to, selected options and Clear | Native form and original URLs | Search partial byte-identical; complete form/FormData/link contracts checked |
| Match/clash and already-started warning | Original feedback independent of free ranges | Same feedback; a past match never creates a green interval |
| Invalid time/date, closed/outside-term day, no match, empty rooms | Existing messages and conditionals | Preserved, included in existing availability fixtures |
| Calendar selected date/count/panel, agenda and permissions | Existing contextual selection with native fallback | Calendar/agenda partials and script byte-identical; panel, counts, all authorized records and role conditions checked |
| Native `#day`, date URLs, Back/Forward and shell | Existing URL/history/focus and main-panel scrolling | No shell/navigation/JS edits; existing shell/navigation suites retained |
| Mobile / keyboard / no JS | Text rows and native controls | One horizontal timeline region, sticky identity, visible focus, native disclosures/Book links; exact text and all room data retained |

The existing availability record comparison now compares a complete per-room multiset of text nodes because moving the Book link to its own column intentionally changes text order. Duplicates remain counted. Full forms, links, titles, original disclosure strings and all original information remain asserted. The new timeline suite separately compares **ordered** room name, capacity, exact interval text, Book URL/label, match/clash and busy-detail records against the baseline. No old fixture or assertion about business behavior was removed.

### Changed-file inventory

| File | Purpose |
|---|---|
| `resources/views/components/ui/availability-track.blade.php` (new) | Reusable decorative graph with supplied-data validation and exact percentage geometry |
| `resources/views/rooms/_results.blade.php` | Shared slot axis, legend and single accessible scroll region |
| `resources/views/rooms/_room.blade.php` | Compose graph with existing text; align Book and native details; safe malformed-text handling |
| `public/css/availability.css` | Scoped board/track geometry, sticky identity, natural row height, focus and responsive horizontal scrolling |
| `tests/Support/availability-runtime.php` | Optional fixture transform, leaving existing 46 cases unchanged |
| `tests/Support/render-timeline.php` (new) | Isolated coherent TimeSlots fixtures, separate adjacent-range and malformed-input cases |
| `tests/Support/timeline-browser.cjs` (new) | Pixel geometry, ordered parity, fallback, role, keyboard, no-JS and responsive checks; before/after evidence |
| `tests/Support/availability-browser.cjs` | Account for approved Book relocation while comparing all original content |
| `docs/frontend/DESIGN_SYSTEM.md` | Timeline semantics, component contract and explicit implementation defaults |
| `docs/frontend/SCREEN_INVENTORY.md` | S11 presentation/interaction placement update |
| `docs/codex/STAGE_02_TASK_UX.md` | This implementation report, parity matrix, commands, results and limits |

No backend, route, model, migration, service, authentication, dashboard, foundation, shell, calendar JavaScript or dependency file is modified. The local Docker changes predate this task and are excluded from this inventory.

### Fixture method and reproduction

Evidence root: `/tmp/rb-timeline`. The baseline is an archive of `5a50af1`; current files are copied to `after`, excluding `.env` and both local Docker files. Both isolated snapshots reuse `/var/www/html/vendor` from the existing `reservebot-app-1` container. The harness disables database connections/uses its no-PDO recorder and temporary compiled views with array session/cache/mail. All Chromium requests and native submissions are fulfilled/intercepted locally, never sent to the application.

Use the existing reproduction commands above with `/tmp/rb-timeline` substituted as the evidence root. Additional commands:

```text
php /tmp/rb-timeline/after/tests/Support/render-timeline.php /tmp/rb-timeline/after-timeline
php /tmp/rb-timeline/before/tests/Support/render-timeline.php /tmp/rb-timeline/before-timeline --baseline
node tests/Support/rooms-correction-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-timeline
node tests/Support/timeline-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-timeline
```

Run PHP commands inside the existing container, then copy generated fixture directories to the host before browser checks. Copy only the current `availability-runtime.php` and `render-timeline.php` test helpers to the baseline snapshot; baseline application assets/templates remain unchanged.

The new fixtures use `TimeSlots::freeRanges` with opens=450, closes=1270, step=30 and now=545. The exact axis is **450–1260**, not the configured closing minute 1270. An unrestricted room reports **570–1260**. An off-grid class **500–610** plus pending **780–840**, confirmed **900–960** and in-use **1020–1080** restrictions produces **630–780, 840–900, 960–1020, 1080–1260**. Fully blocked and all-day class rooms have no free intervals. Separate adjacent-range fixtures deliberately supply unmerged adjacent ranges to verify preservation; they are not represented as native freeRanges output. A started search uses **450–480** and retains later free ranges independently. Empty slots, zero step, reversed slot bounds, reversed/nonnumeric/out-of-axis/overlapping ranges and a non-array collection exercise safe fallback. Existing arbitrary-content fixtures remain separate.

### Executed verification

| Suite | Observed result |
|---|---|
| Foundations/layout/auth/availability renderers | PASS: 63 / 279 / 1,318 / 2,137 assertions; availability rerun after final markup change |
| Foundations browser | PASS: conditional forms, native controls/FormData, fonts, focus, contrast and enlargement |
| Layout browser | PASS: 72 role/state/width cases and existing focus/menu/fallback checks |
| Auth browser | PASS: 60 fixtures / 420 responsive combinations |
| Availability browser | PASS: 46 fixtures / 276 responsive combinations; original form/link/information/disclosure parity |
| Shell geometry | PASS: 351 cases / 324 stationary sidebar measurements |
| Shell navigation | PASS: nine groups covering native fragments/GET/date/history, restoration, focus, keyboard and no-JS |
| Existing Stage 2 suite | PASS: 306 responsive combinations |
| Contextual-calendar/room correction | PASS: 99 role/viewport/text combinations plus touch, bookmark, history and no-JS |
| New timeline suite | PASS: 21 fixtures, 72 role/viewport/text combinations, 2,316 band-position/width comparisons, ordered baseline parity, invalid-input fallbacks, native controls and no-JS |
| Syntax/whitespace | PASS: PHP syntax for changed fixture helpers, JS syntax for timeline browser, `git diff --check` |

The new matrix is all three roles × eight required sizes × 100/125/200% root text: **1438×638, 1440×900, 1366×768, 1280×720, 1024×600, 768×1024, 390×844, 320×640**. Existing suites additionally retain their boundary/long-content/short-height cases. Pixel comparisons allow less than one CSS pixel for layout rounding. The first full room must fit initially at 1438×638 for all roles at normal text size. Focused Book links must be fully within the board and not covered by the identity area at every size/text combination; mobile tests also use actual Tab navigation and arrow-key scrolling. Native Enter/Space opens/closes busy disclosures. No-JS fixtures cover all roles. Every recorded browser request remains local.

The viewport-only mobile draft exposed sticky identity overlap and partially clipped keyboard targets at enlarged text. The final CSS responds to **board width** with a 50rem container threshold: narrow/enlarged boards put sticky identity and native actions on their own rows around the aligned time track. This preserves the desktop four-column layout and lets mobile actions remain readable while the timeline scrolls. No JavaScript focus workaround was introduced. Axis spacing was also reduced after screenshot inspection so the first room is fully visible in the initial laptop viewport.

### Geometry at 1438×638

Coherent representative fixture, normal root text, DPR 2:

| Measurement | Actual CSS pixels |
|---|---|
| Document width / height | 1,438 / 638; no document scrolling in stationary desktop mode |
| Main scroll panel | x=252, y=107, width=1,186, height=531 |
| Board | x=300, y=533.5, width=1,090; all rows grow in the existing main panel |
| Shared time coordinates | x=484 through 1,182; width=698 for every track and the axis |
| Identity column | 176 wide |
| First room | y=584.5 through 637.5; 53 high, entirely visible initially |
| Ordinary collapsed rows | 51–53 high (all 12 fixture rooms); no fixed height |
| Track height | 10 |
| First free range 570–1260 | x=587.40625, width=594.578125; equals the 120-minute offset / 690-minute duration within an 810-minute axis, within rounding tolerance |

Staff/admin first rows are 51px high and end at y=635.5; all three roles share the same x coordinates. The matched baseline's repeating four row types measured **77 / 114 / 60 / 109px**, compared with **53 / 53 / 51 / 53px** after. Expanded disclosures, long names/descriptions, search messages and enlarged text deliberately exceed these ordinary-row heights. No row clips content to meet a density target. At normal 390px width the board is 358px wide with a 400px time coordinate system; it scrolls as one region, while identities and native controls stay within its visible width.

### Before/after evidence

Each image uses its own snapshot's assets and actual container/native scroll position; none is a restyled mockup. Samples are explicitly synthetic test data.

| View | Before | After |
|---|---|---|
| Initial laptop viewport | [Before](/tmp/rb-timeline/timeline-before-initial-1438x638.png) | [After](/tmp/rb-timeline/timeline-after-initial-1438x638.png) |
| First rows | [Before](/tmp/rb-timeline/timeline-before-first-1438x638.png) | [After](/tmp/rb-timeline/timeline-after-first-1438x638.png) |
| Middle of list | [Before](/tmp/rb-timeline/timeline-before-middle-1438x638.png) | [After](/tmp/rb-timeline/timeline-after-middle-1438x638.png) |
| Expanded schedule | [Before](/tmp/rb-timeline/timeline-before-expanded-1438x638.png) | [After](/tmp/rb-timeline/timeline-after-expanded-1438x638.png) |
| Mobile 390×844 | [Before](/tmp/rb-timeline/timeline-before-mobile-390x844.png) | [After](/tmp/rb-timeline/timeline-after-mobile-390x844.png) |

Baseline first rows and current initial/first/expanded/mobile screenshots were visually inspected. Machine-readable evidence: [timeline geometry/results](/tmp/rb-timeline/timeline-results.json), [baseline/current row heights](/tmp/rb-timeline/row-metrics.json), [calendar results](/tmp/rb-timeline/rooms-correction-results.json), [shell geometry](/tmp/rb-timeline/shell-results.json), [navigation results](/tmp/rb-timeline/navigation-results.json), [integrity manifest](/tmp/rb-timeline/integrity.json). Evidence under `/tmp` is temporary and is not committed.

### Limits, integrity and scope

These are isolated Laravel rendering and Chromium fixture checks, **not authenticated HTTP workflows or database/privacy/security certification**. Existing service/controller privacy logic is unchanged and the authorized role-specific fixture text is preserved, but no live private booking or email flow was reproduced. PHPUnit is absent from the existing container vendor; no dependency was installed and no database-reset suite was run. Screen-reader testing, physical-device testing, Firefox/WebKit and browser-toolbar zoom were not performed. Root-text enlargement and emulated touch are explicitly distinguished from those checks. Container-query/scrolling behavior is verified in the available Chromium only. Horizontal panning is intentional when the time axis cannot fit; on narrow boards it is required to see the entire axis, not to find native actions.

No feature was removed, no availability rule was inferred, and no behavior-scope deviation is introduced. The optional search-range overlay was omitted. Responsive stacking of identity/actions and the decorative track component are presentation choices within the approved brief. New visual dimensions are implementation defaults, not measurements from live Figma.

Integrity comparison against `5a50af1`: **305 tracked files outside the explicit change allowlist are byte-identical**, including backend, routes, dependencies, legacy/foundation styles, shell, auth/dashboard and calendar/agenda/search source. `public/js/availability.js` is unchanged. `docker-compose.yml` remains SHA-256 `ab8f57755ba295dc937cffd7b5c0b746a5b21a0397612d2e6ecb14bfa67b62e8`; `docker-compose.yml.save` remains `45a1e6e6f347925d60b2898ed08efe15953e7b669345b7b67781a13883dbcf79`. Nothing is staged; HEAD and branch are unchanged. No commit, push, merge, deployment, production route or PR was created.
