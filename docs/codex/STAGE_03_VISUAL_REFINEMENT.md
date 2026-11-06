# Stage 3 — professional visual consistency refinement

Implemented on `feat/frontend-visual-refinement`, baseline `05ffc115b2f1411f22fe556e0bd12a90d8c19fa7`, November 5–6, 2026. Changes remain uncommitted and unstaged. This is one coordinated CSS refinement of the existing frontend, not a new layout, theme or workflow.

## Findings and decisions

The audit found mixed warm/gray panel borders, inconsistent secondary-button borders, a cream timeline beside white panels, undersized legacy meeting buttons, blue freed-room badges/row washes outside the existing semantic palette, inconsistent supporting text, and redundant rounded calendar framing. Existing heading hierarchy, shell, dashboard grouping and timeline architecture were retained.

Used the [frontend-design skill](/home/james/.agents/skills/frontend-design/SKILL.md) to settle the direction before implementation: existing Inter, restrained maroon actions, cream canvas, flat white panels, warm separators, readable secondary text, 10px controls and 16px cards. No live Figma inspection or new measurement is claimed. The [design contract](../frontend/DESIGN_SYSTEM.md#stage-3--coordinated-visual-consistency) records all defaults and five additive token aliases.

Foundation styles now own panel/table borders and secondary-button treatment. The representative answer controls share foundation button declarations through a dashboard-scoped selector; their shared Blade form remains byte-identical. Compact auth cards share 22px titles and 16px gaps. Dashboard panels use 16px padding, leaving room for 44px meeting controls. Freed/waiting labels use neutral tokens; Today is a maroon date marker. Native disclosures gain hover/open styling, and account links/tabs gain hover feedback. Gold remains the existing restrained brand/calendar accent.

The timeline uses a white background, opaque white sticky identities/actions and a 16px outer radius. Its decorative border is painted with an inset shadow, consuming no layout space. No padding, axis, interval math, column width, row sizing or scroll behavior was changed. The finder adopts the same white surface. Calendar cell borders remain; only its redundant rounded outer frame is visually removed, with the existing border dimensions preserved.

## Complete changed-file inventory

| File | Change |
|---|---|
| `public/css/foundations/tokens.css` | Five aliases for panel border/padding, section gap, flat shadow and secondary border; no new colors |
| `public/css/foundations/components.css` | Shared panel/table treatment, secondary/meeting controls, native disclosure states |
| `public/css/layout.css` | Readable section label, sentence-case advisory, consistent logout border/account hover; no shell geometry changes |
| `public/css/auth.css` | Compact card titles, consistent gaps/supporting text, account-tab hover |
| `public/css/dashboard.css` | Compact panel padding, shared-control integration, neutral freed/waiting statuses, feedback and unclipped focus |
| `public/css/availability.css` | White finder/timeline and sticky surfaces, consistent controls, date marker and reduced calendar framing |
| `tests/Support/visual-refinement-browser.cjs` (new) | Complete HTML parity, version-specific legacy styles, exact timeline geometry, visual states, density and screenshots |
| `docs/frontend/DESIGN_SYSTEM.md` | Stage 3 decisions, tokens, scope and density tradeoffs |
| `docs/frontend/SCREEN_INVENTORY.md` | Current visual-only scope and unchanged functional contract |
| `docs/architecture/SYSTEM_MAP.md` | Current source trace and preserved architecture |
| `docs/codex/STAGE_03_VISUAL_REFINEMENT.md` (new) | This report |

Pre-existing Docker edits are excluded. The unrelated untracked `docs/frontend/V2_IMPLEMENTATION_ROADMAP.md`, which appeared during this session, was not edited or included in this work.

## Functional Parity Matrix

All 146 exported documents are byte-equivalent after normalizing only asset cache timestamps. No template, JavaScript, form, content ordering or permission conditional changed.

| Capability / source | Before → after | Evidence |
|---|---|---|
| Shared roles/navigation, waiting count, account/photo, POST logout, email prompt/resend, reduced onboarding/errors — `layouts/app.blade.php`, layout partials; `routes/web.php:37,52` | Same single navigation and native controls; stationary desktop and mobile fallback retained | Layout renderer/browser; 351 shell cases; nine native-navigation groups |
| Login/recovery/reset, CSRF, old input, constraints and error bags — `routes/web.php:26`; `Auth/LoginController.php:19`; auth/account views | Identical form/actions/fields; only typography, spacing and interaction colors change | 1,318 renderer assertions; 60 auth fixtures / 420 responsive states; intercepted native submissions |
| Account information, photo upload/removal, password, email connection, signed/recovery links — `routes/web.php:40,57–68` | All existing independent forms, named bags, values, URLs and conditions retained | Full HTML/form/link parity; existing auth suites, including error/result fixtures |
| Rep identity, shortcuts, priority/other meetings and complete timetable — `RepController.php:12`; `home/_representative.blade.php` | Same hierarchy, grouping, records, disclosure counts and shortcuts | Availability parity and Stage 2 suite; every record/form retained |
| Meeting confirmation, selected/disabled answers, ended/booked suppression — `routes/web.php:87`; `classes/_answer.blade.php:5` | Same POST/CSRF/name/value and state conditions; only rep buttons gain shared styling/44px targets | Complete HTML equality; existing native submission/grouping checks; new state/target checks |
| Room date/search/clear, match/clash/started/error/empty states, free ranges and permitted Book URLs — `AvailabilityController.php:29,57,66`; `RoomAvailability`; `TimeSlots` | Same supplied data, messages, URLs and visible exact intervals | 2,137 renderer assertions; 46 fixtures / 276 responsive states; 21 timeline fixtures |
| Timeline axis, free bands, sticky identity, horizontal region and native busy disclosures — `rooms/_results.blade.php`, `_room.blade.php`, `ui/availability-track.blade.php` | White surface; all geometry and semantics unchanged | 144 exact before/after geometry comparisons; 2,316 interval-position/width checks; keyboard/no-JS cases |
| Calendar event selection/details, agenda, date links, fragments and history — `rooms/_calendar*.blade.php`; `public/js/availability.js`; `public/js/layout.js` | Same architecture, selected panel, complete details and native fallback | 99 room/calendar cases plus touch/no-JS/history; shell navigation suite |
| Unfinished workflows and CSS `:has()` conditionals — legacy `style.css`, booking/account-role/timetable fixtures | Original stylesheet, templates and selectors unchanged | Nine conditional states; six version-specific full computed-style comparisons for booking stages/conditional fixture |

Backend integrity is source/hash evidence, not an executed authentication, booking, privacy or authorization workflow certification.

## Executed verification

| Check | Result |
|---|---|
| Foundations / layout / auth / availability rendering | PASS — 63 / 279 / 1,318 / 2,137 assertions |
| Foundations browser | PASS — nine conditional states, FormData, fonts, focus, disabled behavior, contrast and enlargement |
| Layout browser | PASS — 72 role/state/width combinations, menu/focus/no-JS and legacy contracts |
| Auth browser | PASS — 60 fixtures, 420 responsive states |
| Availability browser | PASS — 46 fixtures, 276 responsive states |
| Shell refinement | PASS — 351 cases, 324 stationary sidebar measurements |
| Shell navigation | PASS — nine groups: native forms/URLs, fragments, history/restoration, focus, keyboard, short/mobile/no-JS behavior |
| Stage 2 task UX | PASS — 306 responsive combinations; laptop login visibility, meetings/timetable, keyboard/no-JS |
| Room/calendar correction | PASS — 99 role/viewport/text combinations plus contextual selection, touch, bookmarks, history and no-JS |
| Timeline | PASS — 21 fixtures, 72 responsive combinations, 2,316 band geometry checks |
| Stage 3 visual refinement | PASS — 146 complete HTML comparisons, six untouched-content computed-style comparisons, 144 exact collapsed/expanded timeline comparisons, state/target/surface/density checks |
| Syntax / whitespace | PASS — `node --check tests/Support/visual-refinement-browser.cjs`; `git diff --check` |
| PHPUnit | NOT RUN — executable absent from existing container vendor; no dependency installed |

Primary viewport is **1438×638 CSS pixels**. The Stage 3 matrix also covers 1440×900, 1366×768, 1280×720, 1024×600, 768×1024, 390×844 and 320×640 at 100/125/200% root text. Existing suites retain their extra boundary/short-height/long-content cases. This is root-text enlargement, not browser-toolbar zoom.

Existing broad rendering/browser suites ran once at the final checkpoint. Only the new Stage 3 check was rerun: its first card selector accidentally selected the intentionally borderless sidebar note and was corrected to select dashboard cards; the final run added version-specific untouched-workflow style verification. Screenshot capture was normalized to put the pointer outside content in both versions. No additional application redesign followed verification.

### Measured density

Normal supplied-data fixtures, 1438×638, DPR 2:

| Measurement | Before | After |
|---|---:|---:|
| Login document height | 761px | 728px |
| Login error document height | 790px | 757px |
| Representative dashboard main height | 798.5px | 797px |
| Availability main height | 2023.5px | 2023.5px |
| Coherent timeline fixture main height | 2443.5px | 2443.5px |
| Authenticated document height | 638px | 638px |

Timeline rows retain their prior 51–53px normal collapsed heights, with exact axis/band/identity rectangles preserved. Mobile dashboard main height grows from 1089.375px to 1142.5px because answer buttons now meet the 44px minimum and supporting text is readable; this is an accessibility tradeoff, not decorative whitespace. Mobile login content shrinks by about 53px. Expanded/long/enlarged content grows naturally.

## Before/after screenshots

Matched fixtures and each version's own CSS/JS/fonts, DPR 2. Temporary evidence under `/tmp/rb-stage3` may be cleared. These are explicitly synthetic examples, not live account/booking data.

| View | 1438×638 | 390×844 |
|---|---|---|
| Login | [Before](/tmp/rb-stage3/stage3-auth-login-1438x638-before.png) · [After](/tmp/rb-stage3/stage3-auth-login-1438x638-after.png) | [Before](/tmp/rb-stage3/stage3-auth-login-390x844-before.png) · [After](/tmp/rb-stage3/stage3-auth-login-390x844-after.png) |
| Representative dashboard | [Before](/tmp/rb-stage3/stage3-availability-home-rep-normal-1438x638-before.png) · [After](/tmp/rb-stage3/stage3-availability-home-rep-normal-1438x638-after.png) | [Before](/tmp/rb-stage3/stage3-availability-home-rep-normal-390x844-before.png) · [After](/tmp/rb-stage3/stage3-availability-home-rep-normal-390x844-after.png) |
| Timeline | [Before](/tmp/rb-stage3/stage3-timeline-timeline-rep-coherent-1438x638-before.png) · [After](/tmp/rb-stage3/stage3-timeline-timeline-rep-coherent-1438x638-after.png) | [Before](/tmp/rb-stage3/stage3-timeline-timeline-rep-coherent-390x844-before.png) · [After](/tmp/rb-stage3/stage3-timeline-timeline-rep-coherent-390x844-after.png) |
| Availability initial view | [Before](/tmp/rb-stage3/stage3-availability-rooms-rep-normal-1438x638-before.png) · [After](/tmp/rb-stage3/stage3-availability-rooms-rep-normal-1438x638-after.png) | [Before](/tmp/rb-stage3/stage3-availability-rooms-rep-normal-390x844-before.png) · [After](/tmp/rb-stage3/stage3-availability-rooms-rep-normal-390x844-after.png) |
| Admin shell fixture | [Before](/tmp/rb-stage3/stage3-layout-admin-1438x638-before.png) · [After](/tmp/rb-stage3/stage3-layout-admin-1438x638-after.png) | [Before](/tmp/rb-stage3/stage3-layout-admin-390x844-before.png) · [After](/tmp/rb-stage3/stage3-layout-admin-390x844-after.png) |

The admin shell fixture deliberately includes unchanged legacy sample content; it is not a staff-dashboard redesign. Two additional login-error pairs are in the [24-image manifest and measurements](/tmp/rb-stage3/visual-refinement-results.json). Desktop/mobile login, dashboard, timeline and the laptop availability/admin shell were visually reviewed; baseline laptop login/timeline were also inspected directly.

## Reproduction and safety

`/tmp/rb-stage3/before` is a `git archive` of the baseline; `after` uses current source. Snapshots exclude `.env` and local Docker files. The existing `reservebot-app-1` vendor is reused through temporary symlinks. Existing harnesses disable database connections/use a no-PDO recording connection, array session/cache/mail and temporary compiled views. All Chromium requests/submissions are intercepted locally. No service restart, application HTTP request, database setup/mutation or mail delivery occurred.

Run each renderer in the existing container: `php /tmp/rb-stage3/after/tests/Support/render-{foundations,layout,auth,availability}.php --check` (one command per renderer). Export layout/auth/availability with `--export /tmp/rb-stage3/{before,after}-{suite}`; export timeline with `render-timeline.php /tmp/rb-stage3/{before,after}-timeline`. Copy exports to the host.

Browser commands use the existing Playwright installation:

```text
node tests/Support/foundations-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-stage3/after-layout
node tests/Support/layout-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-stage3/after-layout /tmp/rb-stage3/before-layout
node tests/Support/auth-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-stage3/after-auth /tmp/rb-stage3/before-auth
node tests/Support/availability-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-stage3/after-availability /tmp/rb-stage3/before-availability
```

Run `node tests/Support/{suite}-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/rb-stage3` separately for `shell-refinement`, `shell-navigation`, `task-ux`, `rooms-correction`, `timeline` and `visual-refinement`. Logs are `/tmp/rb-stage3/{suite}.log`; measured results include `visual-refinement-results.json`, `timeline-results.json`, `shell-results.json`, `navigation-results.json` and `rooms-correction-results.json`.

Integrity check: **309 protected files** retain starting hashes, including both Docker files. HEAD/branch are unchanged and nothing is staged. `docker-compose.yml`: `ab8f57755ba295dc937cffd7b5c0b746a5b21a0397612d2e6ecb14bfa67b62e8`; backup: `45a1e6e6f347925d60b2898ed08efe15953e7b669345b7b67781a13883dbcf79`. [Integrity evidence](/tmp/rb-stage3/integrity.json).

## Limits and deferred work

No live authenticated/database workflows, real email delivery, physical devices, screen-reader testing, Firefox/WebKit or actual browser zoom were exercised. Browser results establish isolated rendering and interaction preservation, not backend/security certification. No known blocking visual regression remains in the tested matrix.

The timeline's original content insets are deliberately retained to meet the exact-geometry constraint. Further inset/padding preferences, unrelated legacy workflow styling and broader icon/copy changes are deferred. Dark mode/theme switching belongs to Stage 4 and was not started. No backend, routes, models, services, migrations, database, dependencies, Docker configuration, GitHub PR/connector, commits, pushes, merges or deployment were changed or invoked as part of this stage.
