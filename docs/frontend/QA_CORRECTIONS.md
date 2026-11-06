# QA Correction Pass 1

Branch `feat/frontend-design-11-qa-fixes`, created from `feat/frontend-design-10-integration-qa` (`5e3b5e4`). Source: `docs/frontend/INDEPENDENT_QA_REPORT.md`. Out of scope and untouched: F-01 (login throttling), F-07 (hand-out/return date guard). No backend, route, controller, model, migration, `.env` or Docker file was changed. `docker-compose.yml` and `docker-compose.yml.save` were never staged.

## Commits (one per finding)

| Finding | Commit |
|---|---|
| F-02 | `c91b296` fix(frontend): fit mobile Free rooms calendar inside its card |
| F-03 | `276382e` fix(frontend): wrap dashboard shortcut labels by word at large text |
| F-04 | `ad6fac8` fix(frontend): remove internal roadmap card from home pages |
| F-08 | `719c09f` test: fix stale dashboard assertion, test-order pollution and DB-gated verification |

## F-02 · Mobile calendar clips Saturday, wraps "events"

- **Root cause:** `@media (max-width: 40rem)` forced `.rb-availability__calendar { min-width: 24.5rem }` (392px) inside a 322px card, so the scroll wrapper silently hid Saturday. The visible "2 events" link was then too wide for a ~40px cell. At 320px the later tiled-calendar paddings (declared after the media query) also split day numbers digit by digit.
- **Corrections:**
  - `public/css/availability.css`: min-width reduced to `15.5rem`, so seven columns fit at normal text; enlarged text still falls back to the existing labelled scroll region.
  - `public/css/availability.css` (new block at end of file so it wins the cascade): `nowrap` day numbers and weekday headers, month prefix ("Dec") on its own line, tighter cell padding, 2px spacing.
  - `resources/views/rooms/_calendar-day.blade.php`: the count link's word is wrapped in `.rb-availability__event-word`, visually hidden below 40rem but still in the accessible name. The count and word share one inline span so desktop spacing is unchanged (the first attempt produced "1event" on desktop; caught in screenshot review and fixed).
- **Measured** (calendar scroll width / visible width): 390px 322/322 (was 392/322), 320px 252/252 (was 392/252). Desktop unchanged (501/501).
- **Remaining:** at 200% text on mobile the calendar still scrolls horizontally (496/258). That is expected for a 7-column grid and is announced by the region label; it is not silent clipping at normal text.

## F-03 · Dashboard shortcut labels break letter by letter at 200%

- **Root cause:** `.rb-dashboard__action-label` used `overflow-wrap: anywhere` and `min-width: 0`. Beside a fixed icon and arrow, the label's minimum width collapsed to ~0, so the flex row gave it ~25px.
- **Corrections** (`public/css/dashboard.css`): `overflow-wrap: break-word` with a `min-content` floor; the action row now wraps and the label has a `7rem` basis, so the label drops under the icon when it cannot sit beside it; the decorative `aria-hidden` arrow is hidden below 40rem.
- **Result:** labels wrap by whole word on staff, admin and rep homes. At 100% and 390px the cards stay inline; at 200% and at 320px they stack uniformly. The rep home (untested by QA) was checked too.

## F-04 · "Coming in the next phases" shown to users

- **Root cause:** `home.blade.php` and `home/_representative.blade.php` rendered whatever the controllers put in `$comingNext`. Staff and admin controllers pass a Messenger-reminder roadmap item.
- **Corrections:** removed both view blocks and the dead `.rb-dashboard__next` CSS. A live check for rep, staff and admin homes finds 0 occurrences of "next phases" or "Messenger".
- **Decision for you:** the `comingNext` arrays in `AdminController` (line 37) and `StaffController` (line 34), and `RepController` (line 36, empty), remain as unused data. Removing them is a controller change, which AGENTS.md reserves for separate approval. It is harmless dead data.

## F-08 · Test suite

1. **Stale assertion.** `home.blade.php` pushes `css/dashboard.css` for every role because staff and admin render `rb-dashboard` markup. The test asserted the opposite. It now asserts the stylesheet and `rb-dashboard__shortcuts` are present, and that the roadmap copy is absent. The production behaviour was correct; the test was stale.
2. **Order pollution.** `AuthPresentationTest` and `AvailabilityPresentationTest` call `foundationsApplication()`, which boots a second Laravel app and never releases it. That leaked the facade root, container singleton, `putenv` values and `Illuminate\View\Component` statics (a stale view factory and compiled-string cache) into `FoundationsTest`, giving "No hint path defined for [__components]". Added `foundationsRelease()` in `tests/Support/foundations-runtime.php`, called from both tests' `tearDown`. It reproduced before the fix with `--filter 'AuthPresentationTest|FoundationsTest'` and passes after.
3. **Silent skips.** Added `tests/Support/run-db-tests.sh`: starts a new, uniquely named MariaDB 10.11 container (hostname `127.0.0.1` so the suites' `@@hostname` guard matches, schema `…_test`), migrates, runs PHPUnit with every gate enabled, and removes the container on exit. README now says which suites a plain run skips and points to the script. No isolation guard was weakened.

New regression guards in `AvailabilityPresentationTest`: calendar min-width and accessible event word (F-02), shortcut label rules (F-03), roadmap copy absent from the home views (F-04).

## Tests and results

| Run | Result |
|---|---|
| Baseline, gated, before any fix | 109 tests: 102 passed, **1 failure** (stale dashboard.css), **1 error** (Foundations), 5 skipped, 2 risky |
| Default run (SQLite memory, gates off), final | 112 tests, 81 passed, 31 skipped (DB-gated by design), 0 failures, 0 errors |
| Gated run against a disposable MariaDB, final (manual and via `run-db-tests.sh`) | 112 tests, **107 passed, 0 failures, 0 errors**, 5 skipped (no GD for photo tests, zip) |

2 "risky" flags persist. They predate this pass (visible at baseline) and were not investigated further. An attempt to clear them by popping handlers made it worse, so I reverted it.

Isolation: a new `mariadb:10.11` container on 127.0.0.1 with its own data, never attached to the project's `reservebot-db-1`. The app on :8000 was not used or modified; screenshots were taken from `php artisan serve` on 127.0.0.1:8124 with environment overrides pointing at the disposable DB (no `.env` edits). Disposable containers and the dev server were removed afterwards. `composer install` created the gitignored `vendor/`, which was absent.

## Screenshot evidence

Scratch directory: `/tmp/claude-1000/-home-james-projects-ReserveBot/0274b1c5-723b-4aff-a775-d464c9ab2059/scratchpad/` with `before/` and `after/` subfolders. Pages: `rep-rooms`, `rep-home`, `staff-home`, `admin-home` (admin: after only), each at 1438×900, 1438×638, 390×844, 320×640, at 100% and 200% text (`html{font-size:200%}`), named `<page>-<w>x<h>-<zoom>.png`. Calendar crops: `before/cal-390.png`; `after/cal-1438.png`, `cal-390.png`, `cal-320.png`, `cal-390-200.png`.

Viewed and assessed by eye: calendar at 1438/390/320 (after) and 390 (before); staff home at 1438×900 and 390/320 at 200% (after); rep home 390×844 (after); before staff home 390 at 200%. Other captures were checked numerically only: all 32 after captures have no horizontal page overflow.

## Unresolved

- F-01 and F-07 deferred as instructed.
- F-05, F-06 and the P3 observations were not in this pass.
- 2 pre-existing risky-test flags (cause not investigated).
- Seven-column calendar still scrolls horizontally at 200% on mobile (by design).
- Photo-upload and zip-dependent tests remain skipped here (no GD).
- `comingNext` controller data left in place pending backend approval.
- This report is not committed; commit it when you have reviewed it.
