# ReserveBot — Independent QA & UX Audit

> **Historical audit of the Phase 10 tree (2026-11-07).** Its verdict ("not release-ready") and findings describe that tree. Since then F-01, F-02, F-03, F-04, F-07 and F-08 have been fixed on `dev`; F-05 and F-06 are still open. Current state: [QA_HANDOFF.md](QA_HANDOFF.md) section 3. The text below is unchanged.

Audit only. No application source, `.env`, `docker-compose.yml*`, Git state or existing data was changed. Date: 2026-11-07. Branch audited: `feat/frontend-design-10-integration-qa` (HEAD `5e3b5e4`, Phase 10).

## 1. Executive summary

**Overall: a coherent, professionally finished product with sound backend behaviour. It is not yet release-ready.** One security hardening gap (no login throttling), several finish/UX defects on mobile and at enlarged text, and unfinished placeholder copy remain. Nothing found was P0 or P1.

What held up well (all reproduced live, not inferred):
- **Authorization:** guests are redirected to login. Reps get 403 on staff, admin and manage routes, and 404 on other reps' bookings and photos. A rep cannot approve, cancel or mark other people's items even with a valid CSRF token. Staff get 403 on `/accounts` and `/admin`.
- **CSRF:** all tested POSTs without a token return 419.
- **Booking integrity:** a stale step-2 form competing for the same room is refused with a clear message ("CEA315 is busy then"). Rejection requires a reason. Approve-after-reject and double-approve don't corrupt state.
- **Signed class-check links:** expired, tampered-date and tampered-query links return 403. A POST without CSRF returns 419. An ended class shows "This class has ended" with no buttonQs.
- **Forgot-password:** the response doesn't reveal whether an account exists. The mail was written to the log. Server-side validation errors render correctly.
- **Accessibility basics across 23 role pages:** `lang` set, exactly one `<h1>`, all fields labelled, no duplicate IDs, a skip link, a visible focus ring on all tabbed controls, and no console errors.
- **Layout:** no horizontal page overflow in 140 page×viewport captures.

## 2. Verification method and limits (read first)

| Layer | What it was |
|---|---|
| **Live integration** | A `git archive HEAD` copy of the repo in a scratch directory (not the working tree, so the uncommitted compose files are excluded). It ran against a **new disposable MariaDB 10.11 container** (`rbqa-mariadb`, 127.0.0.1:33307, unrelated to the project's `reservebot-db-1` and never attached to its network). It was seeded with the project's own demo seeder and served on 127.0.0.1:8123. Mail driver was `log`. This is the full Phase 10 frontend. The Docker app on :8000 (Phase 04 preview) was **not touched or tested**. |
| **PHPUnit** | Run in the scratch copy, in two modes. Default (SQLite in-memory): 77 passed, 31 skipped (the DB-gated suites), 1 error. Gated suites enabled against the disposable MariaDB, using the repo's own isolation guards: 109 tests, **102 passed, 1 failed, 1 errored**, 5 skipped/risky (see F-08). |
| **Browser** | Chromium (Playwright) as Rep, Staff, Admin and Guest at 1438×900, 1438×638, 390×844 and 320×640, plus a 200% text-size emulation (`html{font-size:200%}`). Screenshots were taken for all 140 captures. I **critically viewed a sample** (login, rep home, rooms, book, requests, today, accounts, timetable, class-check, the mobile pages cited below). The remaining captures were checked **numerically** (overflow, status, console), not by eye. |
| **Blind review** | I did not open the Figma, the prototype, reference screenshots or progress reports. I read only `CLAUDE.md` and `AGENTS.md`, plus Laravel source. |

Limits:
- PHP in this environment has no GD, so photo-upload tests were skipped.
- Emails were only observed in the log driver. No real delivery was tested.
- The scratch app's own `.env` (a throwaway) was edited, which is the app copy's, not the repo's.

## 3. Screen coverage matrix

Legend: **L** = live in browser with authenticated session; **L+F** = also form/state-transition exercised; **S** = source-inspected only; **–** = not tested.

| Area | Screens | Desktop | Mobile 390/320 | 200% text | Functional |
|---|---|---|---|---|---|
| Auth | Login, forgot password, error pages 403/404 | L | L | L | L+F (bad login, CSRF, enumeration, throttle) |
| Auth | Reset-password link, `/welcome` onboarding, email-connect link | – | – | – | S (reset mail seen in log) |
| Account | My account (Info, Password) | L | L | L | L+F (password validation); info/photo save **–** |
| Rep | Home, Free rooms (+calendar), Book (steps 1–2), My bookings, Booking detail | L | L | L (home only) | L+F (create, conflict, reject, validation) |
| Rep | Cancel booking | L | L | – | **–** (no cancellable booking existed; guarded by 419/404 only) |
| Staff | Home, Today, Requests, Booking detail | L | L | L (home) | L+F (approve, reject, hand-out/return/no-show via POST) |
| Staff | Today UI: hand-out, move, staff-cancel, class mark | L (empty/class list) | L | – | **–** (UI buttons not exercised) |
| Admin | Home, Accounts list, Account edit | L | L | L | List only. **Create, update, password reset and delete not exercised.** |
| Manage | Rooms, Equipment, Booking rules (lists/edit forms) | L | L | L | **–** (saves not exercised) |
| Timetable | Index, view, check, upload | L | L | L | L+F (bad CSV refused with a clear message); draft editing, replace, restore, **–** |
| Class-check | Signed page (valid / expired / ended / tampered) | L | L | – | L+F |
| Errors | 403, 404 | L | – | – | L; 413/419/429/500 pages **S only** |

## 4. Actual failures (objective, reproduced)

### F-01 · P2 (security hardening) · `POST /login`: no brute-force protection
- **Repro:** submit 8 wrong passwords for `staff@reservebot.test` in a row.
- **Expected:** throttle or lockout after a handful of attempts, as the app already does for forgot-password, reset-password and email links.
- **Actual:** all 8 returned "Wrong email or password"; no throttle or lockout. `routes/web.php:25-26` has no `throttle` on login, and `LoginController.php:27` just calls `Auth::attempt`. The rate limiters in `AppServiceProvider.php:27-29` don't cover login.
- **Why it matters:** this is the one door to a system that approves room and equipment handouts.
- **Fix:** add a per-email+IP limiter, e.g. 5/min.
- **Status:** reproduced + code-inspected.

### F-02 · P2 · `/rooms` on mobile: calendar clips the Saturday column and wraps "events" mid-word
- **Repro:** rep login → `/rooms` at 390×844 → scroll to the November calendar.
- **Expected:** all 7 weekdays visible, or an obvious scroll affordance.
- **Actual:** Saturday is cut off (the grid is wider than its card), and "2 events" wraps as "event / s". The page itself doesn't overflow, so the clip is silent. Tapping Saturday is impossible without a hidden scroll.
- **Evidence:** `scratchpad/shots/cal_m390_viewport.png`, `rep__rooms_m390.png`.
- **Fix:** let the day cells shrink (`minmax(0,1fr)`), use a dot or count only at narrow widths, and drop "events" text under ~480px.
- **Status:** reproduced.

### F-03 · P2 · 200% text on mobile: dashboard link cards break words letter-by-letter
- **Repro:** staff login → `/staff`, 390px width, text enlarged to 200%.
- **Expected:** labels wrap by word.
- **Actual:** "Today's schedule", "Review requests" etc. break as "To / da / y's / sc / he / dul / e", because the fixed icon and arrow leave ~25px for the label. Tested on the staff home; the rep home uses the same "Ready to use" card pattern but was **not** tested at 200% (untested).
- **Why it matters:** a WCAG 1.4.4/1.4.10 reflow failure; those links are the main navigation of the home page.
- **Evidence:** `shots/zoom200_staff__staff_m390.png`.
- **Fix:** let the card stack icon above text, or hide the arrow, below a width; use `overflow-wrap:break-word`, not `anywhere`.
- **Status:** reproduced.

### F-04 · P2 · Roadmap copy shipped in the product UI
- Rep and staff homes show a card titled **"Coming in the next phases"**; the staff version lists "Reminders by Facebook Messenger, once the site is online" (`resources/views/home.blade.php:106`, `home/_representative.blade.php:106`).
- This is internal project language, and it advertises an unimplemented feature to end users. `AGENTS.md` says never to fake live status.
- **Fix:** remove it or hide it behind a config flag.
- **Status:** reproduced (staff) + code-inspected (rep).

### F-05 · P3 · Developer text visible to admins
- `/accounts` shows "Emails aren't sent yet… `storage/logs/laravel.log` … (MAIL_MAILER=log)… see the README" (`accounts/index.blade.php:27`). `/manage/timetable` can show "This computer's PHP can't open or make Excel files yet: its zip extension is off" (`manage/timetable.blade.php:120`).
- Both are conditional on environment, so they are correct warnings in a misconfigured deployment. But the wording is for developers. Rephrase for an office admin ("Email isn't set up. Ask your IT contact."), and keep the detail in logs.
- **Status:** reproduced (accounts banner); the zip notice is code-inspected only.

### F-06 · P3 · Wayfinding in Manage
- On `/manage/timetable` (also Booking rules) the sidebar highlights **"Manage rooms"**; the page's `<h1>` is the generic "Manage" for all four tabs, while the breadcrumb says "Timetable". The sidebar has no Timetable or Booking rules entries. They are reachable only through the in-page tabs.
- **Evidence:** `shots/admin__manage_timetable_d900.png`.
- **Fix:** give the section one nav item with a correct active state, and make the `<h1>` match the tab.
- **Status:** reproduced.

### F-07 · P3 · Server doesn't guard hand-out/return by date
- **Repro:** as staff, POST `/bookings/5/hand-out` then `/returned` for a booking starting 3 days in the future.
- **Actual:** both succeed and the booking becomes **Completed** days before it starts. `StaffRules::canHandOut` checks only `status === 'Confirmed'` (`app/Support/StaffRules.php:26-28`).
- **Mitigation:** the UI only offers these actions on the Today page, so this is reachable only by a crafted POST by trusted staff.
- **Status:** reproduced. The fix touches backend rules, so it needs separate approval per `AGENTS.md`.

### F-08 · P3 · Test-suite defects (actual failures)
1. `AvailabilityWorkflowTest::test_dashboard_data_shortcuts_and_answer_controls` (line 92) fails. It asserts the staff and admin homes do **not** load `css/dashboard.css`, but `home.blade.php:8` loads it for them. Either the test is stale or a stylesheet is loaded needlessly.
2. `FoundationsTest::test_presentation_contracts_without_database_access` errors in the full run ("No hint path defined for [__components]") but **passes when run alone**. That points to test-order pollution.
3. With no env flags, 31 tests (the entire DB-backed HTTP workflow suites) silently skip. A default `phpunit` run is therefore green-ish but proves little.
- **Status:** reproduced.

## 5. Visual / UX observations (subjective unless stated)

These are professional-judgment items, not defects.

- **P3 · Touch targets.** At 390px, standalone "Edit" links in Manage/Accounts tables measure 27×17px, and checkboxes and radios are 18×18 (`manage/rules`, `accounts`, `book`). The "Details" and inline booking links are about 17–21px tall. These are borderline for WCAG 2.5.8 (24px), which spacing may excuse. They fall short of comfortable touch size. Consider a taller tap area.
- **P3 · Today list density.** `/today` lists every class (43 shown in the seed data) as individual rows, each with its own Face-to-face/Online buttons. It is about 7,000px tall at 320px. A group-by-time or a "needs answer" filter would help staff.
- **P3 · Repeated placeholder data.** "Seats not recorded" appears on every room on Free rooms and Manage rooms. When a value is missing for all rooms, it's noise. Show it only when some rooms have data.
- **P3 · Dashed-border buttons.** The disabled "‹ Previous day" and calendar "‹ ›" buttons use a dashed border that looks like a placeholder and resembles the "+ Add an account" affordance (which *is* actionable). Dashed means two different things in the app.
- **P3 · Duplicate validation text.** The password page shows each error twice (summary and inline).
- **P3 · "Recently decided" row** on Requests: the meta line sits above the baseline of the status badge and title, so the row reads misaligned (`shots/staff__requests_d900.png`).
- **Positive:** the booking flow (two clear steps, plain-language errors, availability explained: "11 rooms are busy then (rooms are shared…)") is a strength. Mobile layouts for Requests, Manage and Book are clean and un-cramped.

## 6. Accessibility findings

Passes (measured on 23 role/page combinations): language attribute, single `h1`, labelled form controls, no unnamed buttons or links, no duplicate IDs, a skip link, a visible focus indicator on every tabbed control (up to 45 tab stops per page), and no horizontal overflow at 200% text on desktop.

Issues: F-03 (reflow at 200%, mobile), F-02 (hidden clipped column), touch targets (§5). Minor: pages with two `<nav>` landmarks (sidebar + tab bar) should have distinct `aria-label`s (code not checked for labels; **untested**), and one heading-level skip on `/bookings/{id}` for staff.

Not tested: screen-reader output, colour contrast computed per token (visually fine in the samples I viewed; Free-rooms "green on neutral" legend relies on colour plus text, which looks adequate), and prefers-reduced-motion.

## 7. Cross-screen consistency

- Consistent: shell, masthead, breadcrumb, card style, status pills (On / Approved / Not confirmed yet), burgundy primary actions, form-field style across account, booking and timetable.
- Inconsistent:
  - Dashed controls mean two things (§5).
  - Section heading vs. nav highlight (F-06).
  - Home "Ready to use" cards are the only place that breaks at large text (F-03).
  - Rep and staff mobile use a bottom tab bar, while the Manage section is reached only via tabs.

## 8. Missing or blocked tests

| Not done | Reason |
|---|---|
| Real email delivery (reset, connect-email, class reminders, scheduler) | Log driver only. No SMTP, by design. |
| Photo upload/removal | No GD in this PHP; tests skip. |
| Admin account create/edit/password/delete; Manage room/equipment/rules saves; timetable draft/replace/restore/xlsx upload; Today hand-out/move/cancel UI; rep cancel | Time. Source and PHPUnit only, not browser-driven. |
| `/welcome` first-login flow, email-connect gate (`/book` redirect for unconnected reps) | Seed accounts are pre-connected. Not exercised live. |
| 413/419/429/500 page appearance | Not triggered. |
| Per-page eyeballing of all 140 screenshots | Sampled; the remainder are numerically checked only. |
| Live Docker app (Phase 04 preview) | Out of scope by instruction. |

## 9. Evidence references

Screenshots (all under `/tmp/claude-1000/-home-james-projects-ReserveBot/388cc7d0-7ff5-43cc-a8b7-b0987f938c8a/scratchpad/shots/`; scripts are in the parent `scratchpad/`, e.g. `shoot.cjs`, `func.cjs`, `f4.cjs`, `f7.cjs`, `a11y.cjs`):
- `cal_m390_viewport.png` (F-02)
- `zoom200_staff__staff_m390.png` (F-03)
- `admin__manage_timetable_d900.png` (F-06)
- `staff__requests_d900.png`
- `book_conflict_d900.png`
- `cc_3_d900.png` (ended class)
- `tt_badupload_d900.png`
- `pw_server_errors_d900.png`

Data: `scratchpad/report.json` (140-capture matrix with status, overflow, touch-target data).

## 10. Prioritized correction backlog

1. **F-01** throttle `POST /login` (P2, security, small).
2. **F-04** remove "Coming in the next phases" (P2, trivial).
3. **F-03** fix home link-card reflow at large text (P2).
4. **F-02** fix mobile calendar clipping/wrapping (P2).
5. **F-08** repair or retire the stale `dashboard.css` test, de-pollute the Foundations test, and make the DB-gated suites part of CI against a disposable DB.
6. **F-06** fix the Manage nav highlight and `<h1>`.
7. **F-07** date guard on hand-out/return (needs backend approval).
8. **F-05**, touch targets, duplicate error text, dashed-button semantics, Today filtering (P3).

## 11. Release-readiness assessment

**Not ready for production as-is; ready for a staged pilot after items 1–4.**
- The authenticated happy path (login → book → conflict → approve/reject → class-check) works end to end on a real MariaDB, and the security boundaries tested held.
- But I did **not** verify real email delivery, photo upload, admin account CRUD, the timetable replace/restore cycle, Today hand-out UI, or rep cancellation in a browser. Until those are run, production readiness can't be claimed.

## 12. Top findings (confirmed)

1. F-01 No login throttling (P2, security)
2. F-02 Mobile calendar clips Saturday and splits "events" (P2)
3. F-03 200% text breaks home link labels letter by letter (P2)
4. F-04 "Coming in the next phases" roadmap copy shown to users (P2)
5. F-08 One failing and one order-dependent test; DB workflow suites skip by default (P3)
6. F-06 Manage nav highlights the wrong item; generic `<h1>` (P3)
7. F-07 Hand-out and return accepted days before the booking (P3, code-level)
8. F-05 Developer wording in admin banners (P3)
9. Touch targets under 24px on mobile table actions (P3)
10. Today lists every class with no filtering (P3, subjective)
