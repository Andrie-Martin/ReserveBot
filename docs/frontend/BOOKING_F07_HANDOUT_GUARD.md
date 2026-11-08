# F-07: hand-out and no-show guard for future-day bookings

**Status: awaiting its pull request into `dev` as branch `feat/frontend-design-13-booking-guard-integration` (see "Integration with dev" at the end). Phase 12 (login throttling) is already merged into `dev` (PR #24). The original branch `feat/frontend-design-13-booking-guard` is based on Phase 12's *original* commit and must not be merged directly.**

Original branch `feat/frontend-design-13-booking-guard` (from `feat/frontend-design-12-security` as it was at `c74c9b4`). Source: `docs/frontend/INDEPENDENT_QA_REPORT.md`, F-07: staff could hand out (and return) a booking days before it started by posting to `/bookings/{id}/hand-out`.

## The rule conflict, and the decisions taken

The code, docs and UI all said the opposite of the fix: `StaffRules` ("Hand out, no-show and returned work at any time, so lab staff can handle any situation"), `docs/architecture/SYSTEM_MAP.md`, a footer hint on the Today page ("The buttons work at any time"), and a unit test asserting hand-out at 06:00 for an 11:30 booking. The Today page also has a day picker, and on a "Coming day" it showed live Hand out buttons for future bookings (so the QA report's "UI only offers these on the Today page" understated it). This was reported before changing, and these decisions were made:

| Question | Decision |
|---|---|
| Hand-out rule | Refuse only when the booking's start **calendar day** (Asia/Manila) is after today. Same-day, even hours early, and any past day stay allowed. |
| Future-day UI | Hide Hand out and No-show on future days and show a note; the server also refuses crafted POSTs. |
| No-show | Same rule as hand-out. |
| Past bookings | Hand-out stays allowed (late pick-up). No new restriction. |
| Returns | No date restriction (no existing business rule requires one). |

## Implementation

- `app/Support/StaffRules.php`: new `isFutureDay($start)` compares calendar days in the clock's timezone (the app's `Asia/Manila`); `canHandOut` and `canMarkNoShow` now take the booking start and require `Confirmed` and not a future day. `canMarkReturned`, `isLate`, `isOverdue`, `canChange` are unchanged. Docblock updated.
- `app/Http/Controllers/TodayController.php`: hand-out and no-show pass `start_time`; a refusal says "Booking #N is on Tuesday, November 10, so it can’t be handed out yet." (or "…a no-show yet."), otherwise the existing status message. Routes, middleware, validation, CSRF and `StaffDesk` are untouched.
- `resources/views/bookings/_staff_actions.blade.php` (shared by the Today cards and the booking detail page): on a future day shows "Hand out and no-show open on Tuesday, November 10." instead of the two buttons. Return, cancel and move are unchanged.
- `resources/views/today/index.blade.php`: footer hint reworded; `docs/architecture/SYSTEM_MAP.md` line updated.
- No migration, config, `.env`, Docker or compose change.

## Tests

- `tests/Unit/StaffRulesTest.php` (8 tests): same-day early/during/late allowed; earlier days allowed; future days (incl. midnight boundary 23:59 vs 00:00) refused; day decided in Manila time, not UTC; status gates; returns have no date restriction.
- `tests/Feature/StaffBookingGuardTest.php` (new, 14 tests, DB-gated by `RB_STAFF_HTTP_TESTS`, same isolation guards as the other suites; `run-db-tests.sh` now sets it): crafted POST for future bookings (hand-out and no-show) leaves the status unchanged and writes no log row; same-day hand-out at 00:30, 09:00 and 23:00 (clock 09:05); past-day hand-out and no-show; Manila-midnight boundary; return on time, days late and with an odd date, with note validation preserved; full hand-out → return cycle; invalid transitions (Pending, InUse, Completed, NoShow, Cancelled, Rejected, double hand-out); guest redirected, rep 403, other department 404, admin bound by the same rule; UI note and hidden buttons on future days, buttons kept today and on the booking page.
- **Negative check:** with the original production files restored, 5 of the 14 feature tests fail (the future-day cases and the UI test); the other 9 are preservation tests.

Results of the **original branch**, before it was integrated with `dev` (superseded by the table in "Integration with dev"):

| Run | Result |
|---|---|
| Gated, disposable MariaDB 10.11 (`tests/Support/run-db-tests.sh`) | 141 tests, **136 passed, 0 failures, 0 errors**, 5 skipped (no GD/zip) |
| Default (gates off) | 141 tests, 88 passed, 53 skipped by design, 0 failures |
| Booking, staff, class-check and availability filters, gated | 54 tests, 54 passed |

The 2 "risky" flags on the original branch came from the Phase 11 base (dev has none). Class-check, booking and availability workflows are covered by the full green run above.

## Live check (disposable MariaDB + `php artisan serve` on 127.0.0.1:8124, real CSRF)

- Staff, crafted POST hand-out and no-show on a Confirmed booking dated Nov 10: both 302 back to Today, status stays Confirmed, message "Booking #1 is on Tuesday, November 10, so it can’t be a no-show yet."
- Same-day booking: hand-out → InUse, returned → Completed.
- Rep: 403. Staff POST without a token: 419.

Screenshots (scratchpad `/tmp/claude-1000/-home-james-projects-ReserveBot/0274b1c5-723b-4aff-a775-d464c9ab2059/scratchpad/f07/`): `before-*` and `after-*`, for the future day and today, at 1438×900, 1438×638, 390×844 and 320×640, at 100% and 200% text. No horizontal overflow in all 32 captures. A first "after" set was discarded because the app served a stale compiled view right after I restored files; the set was regenerated and then viewed (desktop future-day, mobile 200% future-day, mobile today).

## Left for review

- Any new rule that blocks hand-out on a past booking after it ended was not added (decision above).
- Test-harness quirk (cause not determined): after a first request, switching to a second `actingAs()` user in the same PHPUnit test produced an unauthenticated request even with the guards reset, so each role test uses a single actor. The production behaviour for that role passes when tested this way.
- Not changed: F-05, F-06 and other findings.
- The rule treats "today" in the app timezone (`APP_TIMEZONE`, default `Asia/Manila`); a deployment with a different `APP_TIMEZONE` follows that setting.

## Integration with dev

**Why a separate branch.** `feat/frontend-design-13-booking-guard` has two commits on Phase 12's original commit `c74c9b4`: `553e0b3` (hashed login-throttle key) and `9f5875e` (this guard). `dev` now contains Phase 12 in its final form (PR #24: hashed keys, account identity that ignores invisible characters, atomic counting, per-account turns and in-flight admission). Merging the original branch conflicts in six files, and resolving them in favour of the branch would silently remove that security work (measured in the independent audit: 611 lines, with the branch's own tests still green because the Phase 12 tests would be deleted with it).

**What was done.** `feat/frontend-design-13-booking-guard-integration` is a new branch from `origin/dev` (`208ff71`) with **only `9f5875e` cherry-picked** (`git cherry-pick -x`; the cherry-picked commit is `1293464`). `553e0b3` was not applied: its patch is identical to dev's `305d0e2`, and dev has three further corrections on top. The original Phase 13 branch and every earlier branch are untouched; no history was rewritten.

**Conflicts (2) and their resolution.**
| File | Resolution |
|---|---|
| `resources/views/today/index.blade.php` | Kept dev's markup (including the Phase 05 review fix that moved the Done block) and changed **only the first sentence of the footer hint** ("The buttons work at any time." -> "Hand out and no-show open on the booking's own day and stay open afterwards."). The result is identical to the original branch's hint line; the Done block appears exactly as often as on dev. |
| `tests/Support/run-db-tests.sh` | Kept dev's runner whole (generated throwaway key and password, `DB_SOCKET`/`DB_URL` cleared, port checks, fail-closed isolation check, `RB_AUTH_*`, `RB_AVAILABILITY_*`, `RB_MANAGE_*`) and **added** `RB_STAFF_HTTP_TESTS=1 RB_STAFF_TEST_DATABASE="$DB"` and the header mention. Without that line the 14 `StaffBookingGuardTest` tests are silently skipped (a run then reports 162 tests, 143 passed, 19 skipped, still green). |

**Preserved from dev, byte-identical:** `app/Http/Controllers/Auth/LoginController.php`, `tests/Feature/AuthWorkflowTest.php`, `tests/Unit/LoginThrottleKeyTest.php`, `docs/frontend/SECURITY_F01_LOGIN_THROTTLE.md`. The branch differs from dev in exactly 9 files: `TodayController.php`, `StaffRules.php`, `bookings/_staff_actions.blade.php`, `today/index.blade.php`, `SYSTEM_MAP.md`, this document, `StaffBookingGuardTest.php`, `StaffRulesTest.php`, `run-db-tests.sh`, plus `QA_HANDOFF.md` in the documentation commit.

**Verification on the integration branch** (disposable MariaDB 10.11, throwaway keys; nothing from the real stack):

| Run | Result |
|---|---|
| Gated, repository runner `tests/Support/run-db-tests.sh` (no `.env`, isolation check passed) | 162 tests, **157 passed, 0 failures, 0 errors**, 5 skipped (photo tests, no GD), **0 risky** |
| Same through a manual gate harness | identical (162 / 157 / 5 / 0) |
| `StaffBookingGuardTest` + `StaffRulesTest` through the runner | 22 of 22 passed (run, not skipped) |
| Real HTTP, lab staff: 56 bookings (7 statuses x 8 start times) x hand-out and no-show = 112 requests | only approved bookings on yesterday or today were applied (including 23:59, hours before the start); tomorrow 00:00 and later refused; every other status refused; **0 deviations** |
| State on refusal (booking, `status_reason`, `updated_at`, audit log, equipment rows, equipment table) | **0 changes**; on a success only the status and one log row change |
| Authorization and malformed requests | rep 403; other-department staff and admin 404; guest redirected to login; no or bad CSRF token 419; invalid ids (`999999`, `0`, `-1`, `abc`, `1.5`) 404; forged fields ignored |
| Pinned-clock tests in `Asia/Manila`, `UTC`, `America/Los_Angeles`, `Pacific/Kiritimati`, `Pacific/Pago_Pago`, `America/New_York` (DST day) | pass: the day is the application's local calendar day |
| Booking detail and Today pages vs the server rule | 56 detail pages, **0 mismatches**; the Done block appears once; the new footer on every day |
| Phase 12 live (12-worker server, database cache) | spelling bypass refused; limit boundaries 5 and 20 exact; 8 simultaneous valid logins all sign in; 40 simultaneous wrong: exactly 5 checked; 60 across emails: exactly 20; 0 typed text in limiter keys (longest 100 characters); no locks left; CSRF 419 |

The calendar-day policy holds as designed: hand-out and no-show are allowed for approved bookings today and on earlier days, subject to the existing status rules, and refused from the next calendar day onward.

**Remaining notes.** Moving an approved booking to today is a separate, logged staff action after which it can be handed out. A booking moved by another staff member in the instant between loading and updating could in theory still be handed out (not reproduced). Approved past-day bookings stay handable by policy. `QA_HANDOFF.md` was brought up to date in the same change.
