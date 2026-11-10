# Frontend redesign: QA handoff

**Status as of `origin/dev` @ `0a30297` (2026-11-08).** Every row below was checked against `git log --first-parent origin/dev`.
Read this file first; the [documentation index](../README.md) says where everything else is.

## 1. What is on `dev`

The frontend redesign (design phases 01-13) and three follow-up correction pull requests are **merged into `dev`**.
Nothing in the frontend redesign is waiting for a pull request any more.

| Work | PR | Merge commit | State |
|---|---|---|---|
| Design phase 01: shell (header, sidebar, mobile menu) | #11 | `c449168` | Merged |
| Design phase 02: sign-in and account pages | #12 | `7db0f1c` | Merged |
| Design phase 03: representative dashboard and Free rooms | #13 | `eab461d` | Merged |
| Design phase 04: representative booking and My bookings | #14 | `b054fa9` | Merged |
| Design phase 05: lab staff operations (Today, Requests) | #17 | `14478e3` | Merged |
| Design phase 06: admin accounts | #18 | `06774c7` | Merged |
| Design phase 07: rooms and equipment | #19 | `b07d9e2` | Merged |
| Design phase 08: timetable | #20 | `cc20f92` | Merged |
| Design phase 09: class check and error pages, with their independent-QA corrections | #21 | `771fe95` | Merged |
| Design phase 10: integration QA, booking link colours, colour scan | #22 | `063a2c6` | Merged |
| Design phase 11: QA corrections (mobile calendar, shortcut labels, roadmap card, test fixes, database test runner) | #23 | `5c14c79` | Merged |
| Design phase 12: login throttling (QA F-01) | #24 | `208ff71` | Merged |
| Design phase 13: hand-out and no-show refused for bookings on a future day (QA F-07) | #25 | `b1c104d` | Merged |
| Correction: hand-out / no-show versus a concurrent move (audit F-B) | #26 | `17720fb` | Merged |
| Correction: login maroon card hidden at 100% zoom | #27 | `1fc9d99` | Merged |
| Correction: page-load flicker and layout shift | #28 | `0a30297` | Merged |

Two numbering schemes exist and are easy to confuse:

- **Early phases** (the `PHASE_01_` to `PHASE_04_` reports and the Stage 01-03 refinements in `docs/codex/`, PRs #3-#10, 2026-11-03 to 2026-11-06) built the first foundations, layout, auth and availability work.
- **Design phases 01-13** (PRs #11-#25) redid every screen against the approved prototype. Those are the "phases 01-13" in this file.

"Merged into `dev`" is not "deployed". Nothing here says what is released or running anywhere.
The Docker app on a developer's machine serves the code baked into its image; rebuild it to see `dev` (`docker compose up --build`, see the README).

## 2. Fixes recorded after phase 13

| Fix | Documents |
|---|---|
| Future-day hand-out and no-show refused (server and UI) | [BOOKING_F07_HANDOUT_GUARD.md](BOOKING_F07_HANDOUT_GUARD.md) |
| Hand-out / no-show re-checked on the locked row, so a concurrent move cannot leave a future-day booking `InUse` or `NoShow` | [BOOKING_FB_STATUS_MOVE_RACE.md](BOOKING_FB_STATUS_MOVE_RACE.md) |
| Maroon promotional card on the login page hidden on short desktop viewports | [LOGIN_CARD_AND_PAGE_LOAD_FIXES.md](LOGIN_CARD_AND_PAGE_LOAD_FIXES.md) |
| Page-load flicker: mobile menu jumping after the script ran, and late font swap | [LOGIN_CARD_AND_PAGE_LOAD_FIXES.md](LOGIN_CARD_AND_PAGE_LOAD_FIXES.md) |

## 3. Independent QA findings (report: [INDEPENDENT_QA_REPORT.md](INDEPENDENT_QA_REPORT.md), Phase 10 tree, 2026-11-07)

| Finding | Topic | State on `dev` |
|---|---|---|
| F-01 | No login throttling | **Fixed** (PR #24). Known limits in [SECURITY_F01_LOGIN_THROTTLE.md](SECURITY_F01_LOGIN_THROTTLE.md). |
| F-02 | Mobile calendar clips Saturday | **Fixed** (PR #23; see [QA_CORRECTIONS.md](QA_CORRECTIONS.md)). Still scrolls sideways at 200% text on phones, by design. |
| F-03 | 200% text breaks shortcut labels letter by letter | **Fixed** (PR #23). At 300% text and above the page can still scroll sideways on phones. |
| F-04 | Roadmap copy shown to users | **Fixed** (PR #23). The unused `comingNext` arrays remain in three controllers (needs backend approval to remove). |
| F-05 | Developer wording shown to admins (mail log, zip extension) | **Not addressed.** No commit on `dev` touches it. |
| F-06 | Manage sidebar highlights the wrong item; generic `<h1>` | **Not addressed.** No commit on `dev` touches it. |
| F-07 | Hand-out / return not guarded by date | Fixed for hand-out and no-show in PR #25 (hardened by #26), then **taken out again on `sandbox`** on the project owner's decision: the buttons work on any day so staff can step in by hand. See [SANDBOX_CHANGES.md](../SANDBOX_CHANGES.md). |
| F-08 | Failing / order-dependent tests, database suites skipping silently | **Fixed** (PR #23): `tests/Support/run-db-tests.sh`. See the caveat in section 4. |
| F-B | Hand-out vs concurrent move race (found in a later audit) | **Fixed** (PR #26). |

## 4. What has been tested, and how

- **Disposable infrastructure only.** Authenticated HTTP and browser testing used a throwaway MariaDB and `php artisan serve` on a private port,
  never the running Docker app or its database. The independent QA and the Phase 10 audit used rep, lab staff and admin sessions at 1438x900, 1438x638, 390x844 and 320x640 at 100% and genuine 200% text.
- **PHPUnit counts** (executed, all gates on, `tests/Support/run-db-tests.sh`, no `.env` needed):

  | Tree | Tests | Passed | Skipped | Failures / errors / risky |
  |---|---|---|---|---|
  | `dev` @ `208ff71` (after PR #24) | 144 | 139 | 5 | 0 / 0 / 0 |
  | Phase 13 integration branch = `dev` @ `b1c104d` (after PR #25) | 162 | 157 | 5 | 0 / 0 / 0 |

  The 5 skips are the photo tests (no GD extension in the test image). A default `phpunit` run skips the database-gated suites (70 of 162 at `b1c104d`) and looks green; use the runner.
- **After PR #25 the executed totals were not recorded in one place.** By a static count of test methods (`git grep -E "function test_|@test|#\[Test\]" -- tests`):
  `208ff71` = 144 and `b1c104d` = 162 (both match the executed totals above), then 172 after #26 (+10 in `tests/Feature/StaffStatusRaceTest.php`, DB-gated), 173 after #27 (+1) and **176 on `dev` @ `0a30297`** (+3 in `tests/Feature/Frontend/PageLoadStabilityTest.php`).
  Treat 176 as "methods present", not "tests passed". The new files were run when they were written (#26 against a disposable MariaDB with real concurrent requests; #27 and #28 as individual files), but **a full gated run of the merged `dev` has not been recorded**. Re-run it before relying on a number.
- **Caveat found during PR #28:** running the whole `tests/Feature/Frontend` directory with plain `phpunit` gives 1 error in `FoundationsTest` ("No hint path defined for [__components]") on `dev` as well as on the fix branch; each file passes when run alone.
  This matches the "Unresolved" note in [QA_CORRECTIONS.md](QA_CORRECTIONS.md) (a plain-PHPUnit class that boots a standalone app leaves state behind). It is a test-order problem, not a rendering failure.
- **Fixtures** (`tests/Support/*-runtime.php`, `render-*.php --check`, `*-browser.cjs`) need no database and are fast, but they prove rendering, not workflows.
- **Layout-shift measurements for PR #28** were taken on the real app against a throwaway database (see [LOGIN_CARD_AND_PAGE_LOAD_FIXES.md](LOGIN_CARD_AND_PAGE_LOAD_FIXES.md)); the screenshots were not committed.
- **Never exercised:** real email delivery and real signed class-check links in a mail client; a real browser on a real phone; any production-like host (caching headers, reverse proxy, HTTPS).

## 5. Open risks and decisions

1. **`APP_DEBUG=true`** is still the default in `.env.example` and in the committed `docker-compose.yml`. A real 500 then shows the exception class and SQL error text
   (observed in a throwaway server; the database password and `APP_KEY` were not in the page). Needs a **deployment review**; the frontend work did not change configuration.
2. **Login throttling** is on `dev`, with known limits (no trusted-proxy configuration, so behind a reverse proxy everyone shares one IP counter; IPv6 per full address; no distributed-attack limit;
   attempts on one account take turns; expired cache rows are not purged). **Configure trusted proxies and review `APP_DEBUG` before production.** Details: [SECURITY_F01_LOGIN_THROTTLE.md](SECURITY_F01_LOGIN_THROTTLE.md).
3. **Future-day hand-out and no-show** policy now on `dev`: calendar-day based in the app timezone; same-day hand-out is allowed even before the start; past-day approved bookings stay handable.
4. **Future-dated semester availability is unresolved.** The class timetable blocks rooms only for dates inside a semester (`RoomAvailability::inTerm`, `app/Services/RoomAvailability.php`).
   With the sample semester ending 2027-01-31, a Tuesday after that date shows no class blocks, so rooms look free during class hours and can be booked until the next semester is entered.
   Needs an owner decision (block, warn, or require a semester).
5. **Legacy violet styling may still exist.** `public/css/style.css` still defines the old violet palette; the redesigned screens override most of it. The colour scan found three cases and Phase 10 fixed them; a clean scan is not proof of none (see section 8).
6. `/rep` at 1438x638: the first meeting-answer buttons start about 694px down (below the fold); visible at 1438x900. Follows the approved prototype order. Needs a human decision.
7. **Mobile hides the login promotional card by design.** Below 64rem wide the maroon card is hidden, and below 40rem the whole left-hand introduction is hidden (task-focused mobile). Only the desktop height rule was a bug (PR #27).
8. **Font behaviour:** fonts use `font-display: optional` (PR #28). On a first, uncached visit on a slow connection the page can render in the fallback font; later pages use Inter. The Inter files are not subset (about 110KB each), so subsetting would shorten that window.
9. **If `layout.js` fails to load**, mobile users with scripting enabled see the account/navigation menu collapsed with no working toggle (PR #28 collapses it in CSS). Unlikely for a same-origin file, but it is a change from "menu open without the script".
10. F-05 and F-06 (section 3) are open.

## 6. How to review `dev` by hand (authenticated, real data)

Start the app as described in the README (`docker compose up --build`, or the XAMPP route); the README lists the test accounts. **Do not use a database you care about for the timetable "Replace" action.**

1. Representative: `/rep` (fold decision above), Free rooms, Book (both steps), My bookings, booking detail, cancel.
2. Lab staff and admin: dashboard, Today (hand out, no-show confirm, returned note, class answers; on `sandbox` the buttons show on every day, future days included), Requests (approve, reject with reason), booking detail staff actions.
3. Admin: Accounts list and edit (add, update, new password, delete/turn off with its confirmation).
4. Lab staff and admin: Manage rooms, equipment (categories, items), booking rules and semester dates.
5. Timetable: overview, upload (use a copy of a file, never the live one), start/continue/discard a draft, edit rows, check, semester save (the second submit button), backups.
6. Reminder email link: open the class-check link, confirm opening it alone does not answer, then press a button.
7. Error pages: trigger 403/404/419/429 as a rep, and look at 413/500 as a guest.
8. **Page-load stability (PR #28):** click through the sidebar and refresh on desktop and on a phone-width window as each role. The header, menu and content should not jump after the page appears.
9. **Login card (PR #27):** open `/login` at normal desktop zoom (100%) in a window shorter than 800px; the maroon "Space to learn. To build. To create." card should be visible and the page should scroll if needed.

## 7. Known limitations of the test tools

- Tables on phones (equipment, rooms, timetable rows) are shown as labelled rows below 40rem with CSS only; the table markup is unchanged.
- `foundations-browser.cjs` compares computed styles with and without the foundation CSS on the shared layout fixtures. Since Phase 04 the booking fixture is styled by those foundations on purpose, so it passes against the pre-Phase-04 layout export and reports that difference against the current one. `visual-refinement-browser.cjs` asserts byte-identical HTML for CSS-only changes and is run from a copy that skips parity only for intentionally restructured documents.
- The fixture-browser suites simulate large text with `html{font-size:200%}`; the Phase 10 checks also used the browser's real default-font-size setting (a different mechanism).
- Generic shell fixtures in `layout-runtime.php` contain legacy placeholder content, so the colour scan flags them. Pass `--known-legacy=layout` to list them without failing the run.
- Prototype-only items were deliberately not added: booking "Booking rules" side card, "Check free rooms" header button, source-contract strip.
- The browser suites need an installed Playwright and a browser; they are not part of PHPUnit.

## 8. Re-running the checks

Each phase has `tests/Support/<name>-runtime.php` (database-free fixtures), `render-<name>.php` (`--check`, `--export <dir>`) and
`<name>-browser.cjs` (needs an installed Playwright and a browser). Export fixtures from a throwaway checkout or container; never against the live container.

`legacy-colour-scan.cjs` reports rendered fixtures that show the legacy violet family (hue 245-290, including pale tints such as `#EDE9FE`, `#F5F3FF`, `#DDD6FE`):

```
node tests/Support/legacy-colour-scan.cjs <playwright-core> <public> [--states] [--known-legacy=layout] <fixture dir> [...]
```

Exit 0 = no active violation, 1 = active violations (all listed, nothing truncated), 2 = usage error. `--states` also checks `:focus-visible` and `:hover` of links, buttons, summaries and form fields (about 5 minutes for all fixtures).
A static run misses focus and hover, which is how the violet Reject/Cancel focus ring went unnoticed. It does not check pseudo-elements, gradients, shadows, SVG, `:active`, other viewports or text sizes; a clean result is not proof of no legacy styling.

## 9. Documentation caveats

- Older reports contain pointers to screenshots and JSON files under `/tmp/...` or a developer's scratch directory. Those files are not in the repository (they may no longer exist anywhere); the numbers and descriptions in the reports are the surviving record.
- [DESIGN_IMPLEMENTATION_CONTRACT.md](DESIGN_IMPLEMENTATION_CONTRACT.md) names the approved prototype at a path on one developer's machine. It is not in the repository; the exported reference images are in [references/figma/](references/figma/).
- Reports are kept as they were written. When a report disagrees with this file, this file (and Git history) is current.
