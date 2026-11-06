# F-07: hand-out and no-show guard for future-day bookings

Branch `feat/frontend-design-13-booking-guard` (from `feat/frontend-design-12-security`). Source: `docs/frontend/INDEPENDENT_QA_REPORT.md`, F-07: staff could hand out (and return) a booking days before it started by posting to `/bookings/{id}/hand-out`.

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

| Run | Result |
|---|---|
| Gated, disposable MariaDB 10.11 (`tests/Support/run-db-tests.sh`) | 141 tests, **136 passed, 0 failures, 0 errors**, 5 skipped (no GD/zip) |
| Default (gates off) | 141 tests, 88 passed, 53 skipped by design, 0 failures |
| Booking, staff, class-check and availability filters, gated | 54 tests, 54 passed |

The 2 pre-existing "risky" flags are unchanged. Class-check, booking and availability workflows are covered by the full green run above.

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
