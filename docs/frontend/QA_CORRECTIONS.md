# QA Correction Pass 1 (and the follow-up pass after the independent Phase 11 review)

Branch `feat/frontend-design-11-qa-fixes`, created from `feat/frontend-design-10-integration-qa` (`5e3b5e4`). Source: `docs/frontend/INDEPENDENT_QA_REPORT.md`. Out of scope and untouched: F-01 (login throttling), F-07 (hand-out/return date guard). No backend, route, controller, model, migration, `.env` or Docker file was changed. `docker-compose.yml` and `docker-compose.yml.save` were never staged.

## Commits (one per finding)

| Finding | Commit |
|---|---|
| F-02 | `c91b296` fix(frontend): fit mobile Free rooms calendar inside its card |
| F-03 | `276382e` fix(frontend): wrap dashboard shortcut labels by word at large text |
| F-04 | `ad6fac8` fix(frontend): remove internal roadmap card from home pages |
| F-08 | `719c09f` test: fix stale dashboard assertion, test-order pollution and DB-gated verification |
| F-08 follow-up: pollution after merging with dev | `2c90897` test: clear leaked standalone-app state before every test |
| F-08 follow-up: risky tests | `b24b0d4` test: remove the error and exception handlers the standalone app installs |
| F-02 follow-up: narrow phones | `8c95713` fix(frontend): widen the mobile calendar columns on the narrowest phones |
| F-03 follow-up: 300% text | `bedec64` fix(frontend): keep dashboard shortcut rows inside the card at 300% text and above |
| F-04 follow-up: browser suite | `78d5368` test(frontend): update the availability browser suite for the Phase 11 interface |
| F-08 follow-up: database runner | `51c4b2a`, `0ee10ed` test: make run-db-tests.sh self-contained and fail closed; port handling |
| Documentation | this commit |

## F-02 · Mobile calendar clips Saturday, wraps "events"

- **Root cause:** `@media (max-width: 40rem)` forced `.rb-availability__calendar { min-width: 24.5rem }` (392px) inside a 322px card, so the scroll wrapper silently hid Saturday. The visible "2 events" link was then too wide for a ~40px cell. At 320px the later tiled-calendar paddings (declared after the media query) also split day numbers digit by digit.
- **Corrections:**
  - `public/css/availability.css`: min-width reduced to `15.5rem`, so seven columns fit at normal text; enlarged text still falls back to the existing labelled scroll region.
  - `public/css/availability.css` (new block at end of file so it wins the cascade): `nowrap` day numbers and weekday headers, month prefix ("Dec") on its own line, tighter cell padding, 2px spacing.
  - `resources/views/rooms/_calendar-day.blade.php`: the count link's word is wrapped in `.rb-availability__event-word`, visually hidden below 40rem but still in the accessible name. The count and word share one inline span so desktop spacing is unchanged (the first attempt produced "1event" on desktop; caught in screenshot review and fixed).
- **Measured** (calendar scroll width / visible width): 390px 322/322 (was 392/322), 320px 252/252 (was 392/252). Desktop unchanged (501/501).
- **Follow-up (`8c95713`):** up to 22.5rem the calendar card uses smaller side padding and 1px gaps. The event-count link, which fills its cell, is 35px wide at 320px (it was 32), 41px at 360px and 42px at 390px, 44px high. A 44px-wide target per column is not possible with seven columns at 320px; the browser suite requires 24px (WCAG 2.5.8) by 44px.
- **Remaining:** at 200% text on mobile the calendar still scrolls horizontally (496/258). That is expected for a 7-column grid and is announced by the region label; it is not silent clipping at normal text.

## F-03 · Dashboard shortcut labels break letter by letter at large text

- **How it was observed (corrected):** with the repo suites' simulated 200% (`html{font-size:200%}`) and at genuine 300% and 400% text (browser default font size 48px/64px). At **genuine 200%** (default font size 32px) the pre-fix fixtures were already readable (labels 119-147px wide, longest word 64-76px), so the original report's "200%" refers to the simulation, not the real browser setting.
- **Root cause:** `.rb-dashboard__action-label` used `overflow-wrap: anywhere` and `min-width: 0`. Beside a fixed icon and arrow, the label's minimum width collapsed to ~0, so the flex row gave it ~25px.
- **Corrections** (`public/css/dashboard.css`): `overflow-wrap: break-word` with a `min-content` floor; the action row now wraps and the label has a `7rem` basis, so the label drops under the icon when it cannot sit beside it; the decorative `aria-hidden` arrow is hidden below 40rem.
- **Follow-up (`bedec64`):** at genuine 300% on a phone the `min-content` floor made a whole long word overflow its card. The label now has `min-width: 0` (the 7rem basis still wraps it under the icon first), and the row padding and icon size are capped in px so they stop growing with the text. A single word wider than the card breaks only as a last resort. Measured at 390px and 320px: no shortcut row overflows at 100%, 200% or 300%; at 400% only the 320px phone (card squeezed to 62px by the page's rem padding) still clips.
- **Result:** labels wrap by whole word on staff, admin and rep homes. At 100% and 390px the cards stay inline; at 200% and at 320px they stack uniformly. The rep home (untested by QA) was checked too.

## F-04 · "Coming in the next phases" shown to users

- **Root cause:** `home.blade.php` and `home/_representative.blade.php` rendered whatever the controllers put in `$comingNext`. Staff and admin controllers pass a Messenger-reminder roadmap item.
- **Corrections:** removed both view blocks and the dead `.rb-dashboard__next` CSS. A live check for rep, staff and admin homes finds 0 occurrences of "next phases" or "Messenger".
- **Browser suite (`78d5368`):** `availability-browser.cjs` compared against the baseline and required every old text to survive, so it failed on the removal. It now requires the roadmap copy to be absent and everything else (forms, links, titles, records, disclosures, information) to match the baseline exactly.
- **Decision for you:** the `comingNext` arrays in `AdminController` (line 37) and `StaffController` (line 34), and `RepController` (line 36, empty), remain as unused data. Removing them is a controller change, which AGENTS.md reserves for separate approval. It is harmless dead data.

## F-08 · Test suite

1. **Stale assertion.** `home.blade.php` pushes `css/dashboard.css` for every role because staff and admin render `rb-dashboard` markup. The test asserted the opposite. It now asserts the stylesheet and `rb-dashboard__shortcuts` are present, and that the roadmap copy is absent. The production behaviour was correct; the test was stale.
2. **Order pollution.** `foundationsApplication()` boots a second Laravel app in the same PHPUnit process and nothing released it. The leaked state that breaks `FoundationsTest` ("No hint path defined for [__components]") is `Illuminate\View\Component`'s static view factory (and its compiled-view cache), which stays bound to the first standalone app's view finder; resetting only the facade root and container does not fix it, resetting only the Component statics does. `719c09f` added `foundationsRelease()` and called it from the `tearDown` of the two Phase 11 test classes. After merging with dev that was not enough: dev's `ClassCheckPresentationTest` (Phase 09) also boots a standalone app and has no `tearDown`, and runs before `FoundationsTest`, so the error returned. `2c90897` makes the fix independent of any test class: `foundationsApplication()` first releases any earlier standalone boot, and `Tests\TestCase::setUp()` releases leftovers before every Laravel test creates its own application. Limitation: a plain-PHPUnit test class that boots a standalone app still leaves its state behind until the next `Tests\TestCase` test starts; nothing observable in this suite depends on that.
3. **Risky tests.** `Kernel::bootstrap()` installs Laravel's error and exception handlers; PHPUnit reported every caller as risky ("did not remove its own error handlers / exception handlers"). `b24b0d4`: under PHPUnit only, `foundationsApplication()` pops exactly those two handlers and restores `error_reporting`, so PHPUnit's own handlers keep reporting warnings and deprecations. CLI scripts (`render-*.php`) keep Laravel's handlers.
4. **Silent skips and the runner.** `tests/Support/run-db-tests.sh` starts a new MariaDB 10.11 container, migrates and runs PHPUnit with every gate enabled (Auth, Availability and Manage). It needs no `.env`: it generates a throwaway `APP_KEY` and root password per run (never printed; the password goes to Docker through a 0600 env file), clears `DB_SOCKET`, `DB_URL` and `DATABASE_URL`, forces array cache/session/mail and a sync queue, refuses a busy or invalid port (a silent listener counts as busy), and, before migrating, boots the application and requires it to resolve to exactly the disposable server (127.0.0.1, the chosen port, a `*_test` schema, no socket, the container's hostname) or aborts without migrating. The container and the secret file are removed on exit. No isolation guard in the suites was weakened.

New regression guards in `AvailabilityPresentationTest`: calendar min-width and accessible event word (F-02); shortcut labels keep a rem flex-basis, the row wraps and padding/icon are px-capped, and `overflow-wrap: anywhere` stays out (F-03); roadmap copy absent from the home views (F-04). These three are source-text checks, not rendering tests; the rendering evidence is the browser suite and the viewport measurements.

## Tests and results

| Run | Result |
|---|---|
| Baseline, gated, before any fix | 109 tests: 102 passed, **1 failure** (stale dashboard.css), **1 error** (Foundations), 5 skipped, 2 risky |
| Phase 11 branch, gated, via `run-db-tests.sh` in a fresh clone without `.env` | 112 tests, **107 passed, 0 failures, 0 errors**, 5 skipped, **0 risky** |
| Phase 11 branch, default run (SQLite memory, gates off) | 112 tests, 81 passed, 31 skipped (DB-gated by design), 0 failures, 0 errors, 0 risky |
| `origin/dev` (`063a2c6`) + Phase 11, gated via the script | 116 tests, **111 passed, 0 failures, 0 errors**, 5 skipped, 0 risky |
| `origin/dev` + Phase 11, default run | 116 tests, 82 passed, 34 skipped, 0 failures, 0 errors, 0 risky |
| Same merged tree, orders | default, `--order-by=reverse` and random seeds 1, 7, 42 all identical (111 passed); `FoundationsTest` alone, `ClassCheckPresentationTest` alone, both together in both orders, and the five presentation classes together in both orders all pass |

The 5 skips are the photo tests (`PhotoTest` and one `AuthWorkflowTest` case; no GD extension) and are unrelated to this branch. The two failures that existed at baseline are fixed on the branch and on the merged tree.

Isolation: a new `mariadb:10.11` container on 127.0.0.1 with its own data, never attached to the project's `reservebot-db-1`. The app on :8000 was not used or modified; screenshots were taken from `php artisan serve` on 127.0.0.1:8124 with environment overrides pointing at the disposable DB (no `.env` edits). Disposable containers and the dev server were removed afterwards. `composer install` created the gitignored `vendor/`, which was absent.

## Screenshot evidence

Scratch directory: `/tmp/claude-1000/-home-james-projects-ReserveBot/0274b1c5-723b-4aff-a775-d464c9ab2059/scratchpad/` with `before/` and `after/` subfolders. Pages: `rep-rooms`, `rep-home`, `staff-home`, `admin-home` (admin: after only), each at 1438×900, 1438×638, 390×844, 320×640, at 100% and 200% text (`html{font-size:200%}`), named `<page>-<w>x<h>-<zoom>.png`. Calendar crops: `before/cal-390.png`; `after/cal-1438.png`, `cal-390.png`, `cal-320.png`, `cal-390-200.png`.

Viewed and assessed by eye: calendar at 1438/390/320 (after) and 390 (before); staff home at 1438×900 and 390/320 at 200% (after); rep home 390×844 (after); before staff home 390 at 200%. Other captures were checked numerically only: all 32 after captures have no horizontal page overflow.

## Unresolved

- F-01 and F-07 deferred as instructed.
- F-05, F-06 and the P3 observations were not in this pass.
- Seven-column calendar still scrolls horizontally at 200% on mobile (by design, inside a labelled, keyboard-reachable region). At 320px the event-count link is 35px wide, not 44px (seven columns cannot allow it).
- At genuine 300% text and above the page itself still scrolls horizontally on phones. The same fixtures overflow on `origin/dev` before Phase 11: the shell header brand and the home page's timetable table; this is outside Phase 11's scope. At 400% on a 320px phone the dashboard card is squeezed to about 62px by the page's rem-based padding and its shortcut rows still clip.
- The pollution fix covers Laravel `Tests\TestCase` tests; a plain-PHPUnit test class that boots a standalone app leaves its state until the next Laravel test starts.
- Handler removal applies only under PHPUnit; the CLI render scripts keep Laravel's handlers.
- The runner needs Docker and pulls `mariadb:10.11` on first use; it uses port 33399 (or `RB_TEST_DB_PORT`).
- Photo-upload and zip-dependent tests remain skipped here (no GD).
- `comingNext` controller data left in place pending backend approval.
- This report is not committed with the findings report; review both before the PR.
