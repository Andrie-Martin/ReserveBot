# Stage 2 — task-focused UX refinement

Base: `c4c40b5` (merged shell correction), branch `feat/frontend-task-ux`. Scope: login, representative dashboard, room availability. All implementation remains uncommitted for review. No Stage 3/4, Phase 05, PR, push, merge or deployment.

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
