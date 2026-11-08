# Overnight autonomous frontend progress log

> **Historical build log (design phases 01-10).** Statuses in the branch table below, such as "complete, local, NOT merged", were true when written. All of these phases are now merged into `dev` (PRs #11-#25; see [QA_HANDOFF.md](QA_HANDOFF.md) for the current table). Paths under `/tmp/...` are the author's scratch folders and are not in the repository. The text below is unchanged.

Local commits only. Nothing is pushed, merged, rebased or force-pushed. Protected and never staged:
`docker-compose.yml`, `docker-compose.yml.save`, `.env`.

Verification tooling (outside the repo, in the session scratchpad):
`/tmp/claude-1000/-home-james-projects-ReserveBot/137867d6-5226-48dc-811f-1568c85a4d24/scratchpad/`.
Repo browser suites are run from patched copies that differ ONLY in the browser executable path and the asset
path. Fixtures are rendered in a throwaway container from the working tree with a read-only mount
(`--network none --entrypoint sh`); the running Docker app is never used as evidence. Baselines are exported from
`git archive <commit>` so before/after comparisons are genuine.

## Branch map and dependencies

| Branch | Based on | Status |
|---|---|---|
| `feat/frontend-design-03-rep-availability` | `dev` @ `7db0f1c` (includes merged Phase 02, PR #12) | complete, local, NOT merged |
| `feat/frontend-design-04-rep-booking` | `feat/frontend-design-03-rep-availability` @ `4d108c0` (Phase 03 is not in `dev`, so this branch is stacked; it contains all Phase 03 commits) | complete, local, NOT merged |
| `feat/frontend-design-05-lab-operations` | `feat/frontend-design-04-rep-booking` @ `6cb9c04` (stacked; contains Phases 03 and 04) | complete, local, NOT merged |
| `feat/frontend-design-06-admin-accounts` | `feat/frontend-design-05-lab-operations` @ `204a337` (stacked; contains Phases 03-05) | complete, local, NOT merged |
| `feat/frontend-design-07-rooms-equipment` | `feat/frontend-design-06-admin-accounts` @ `8e34b72` (stacked; contains Phases 03-06) | complete; merged to `dev` (PR #19) |
| `feat/frontend-design-08-timetable` | `feat/frontend-design-07-rooms-equipment` @ `9aed410` (stacked; contains Phases 03-07) | complete, local, NOT merged; independent QA corrections applied (see Phase 08 below) |
| `feat/frontend-design-09-system-pages` | `feat/frontend-design-08-timetable` @ `22c0885` (stacked; contains Phases 03-08) | complete, local, NOT merged; independent QA corrections applied (see Phase 09 below) |
| `feat/frontend-design-10-integration-qa` | `feat/frontend-design-09-system-pages` @ `c30191b` (stacked; contains Phases 03-09 as they were then; `dev` has since merged Phases 03-09 plus their QA corrections) | complete, local, NOT merged; independent QA corrections applied (see Phase 10 below) |

## Phase 03 — Representative dashboard and Free Rooms

| Part | Commit | Summary |
|---|---|---|
| 03A | `f6039ba` | Representative dashboard hierarchy (kicker, banner, icon tiles, meeting rows) |
| 03B | `291eda0` | Free Rooms hierarchy |
| 03D | `4eb5902` | Free Rooms visual fidelity (grouped finder, type scale, header, calendar tiles, agenda) and continuous timeline border |
| 03D-2 | `bfc4900` | Expanded "What's on" disclosure alignment and density |
| 03D-3 | `4d108c0` | `/rep` and `/rooms` share header-block and card-heading rhythm |

03D-3 measured result (1438 wide): kicker top 32px, title 29px at 52.5px, sub-line 14px at 96.8px, first card at
133.8px on BOTH pages; card gaps 16px; card headings 16px.

Final Phase 03 verification (final tree), unmodified suite assertions:
- Against the previous commit and against the original pre-Phase-03 code (`7db0f1c`): availability-browser,
  task-ux, timeline, rooms-correction PASS; foundations, layout, auth PASS; shell-navigation PASS (3 runs).
- 4980 keyboard stops on Free Rooms and 696 on `/rep`: 0 focus problems; no 200% text overflow; sticky labels OK.
- Structural parity of every control, label, option and link vs the previous commit: 0 mismatches.

Deliberate test exceptions (recorded, not hidden):
- `visual-refinement-browser` asserts byte-identical HTML and exact board heights for CSS-only changes. Phases 03A,
  03B and 03D intentionally changed markup/typography, so its unmodified run fails at HTML parity. An adapted
  scratchpad copy skips HTML parity ONLY for the documents with intentional markup change; it keeps computed-style
  parity, x/width of every timeline element and exact track/free-band geometry, and passes.

Known issues / open decisions:
- At 1438×638 the first meeting-answer buttons on `/rep` start at about 694px (baseline 518px), below the 638px fold;
  they are fully visible at 1438×900. This follows the approved prototype order (banner, shortcuts, meetings).
  Options: accept, compact the banner, or move the meetings card above "Ready to use". Needs a human decision.
- Live Docker app serves a copy of the code baked into its image. Refresh needs the reviewed `docker cp` procedure;
  restarting or recreating the container runs `php artisan migrate --force` (entrypoint) and must be avoided.
- NOT RUN: PHPUnit, authenticated live-app checks.

Screenshot evidence: `fr-d3after-*`, `dash-after-*`, `busy-after-*`, `edge-before.png`, `edge-after.png`,
`mock-*` (prototype) in the scratchpad directory above.

## Phase 04 — Representative booking workflows (S12 form, S13 My bookings, S14 booking detail)

Branch `feat/frontend-design-04-rep-booking`, stacked on `feat/frontend-design-03-rep-availability` @ `4d108c0`.

| Part | Commit | Summary |
|---|---|---|
| 04A | `f4f58f5` | `public/css/booking.css` (new, page-scoped on `.rb-booking`) + wrappers/classes in `bookings/{create,mine,show}.blade.php`: maroon system replaces the legacy violet; stepper, choice cards, time-summary strip, equipment disclosures, compact primary actions; My bookings as two divided panels with pill badges; detail page as header + facts + action panels + history. No form, field name, link or label changed. |
| 04B | `6cb9c04` | Verification tooling: `tests/Support/booking-runtime.php`, `render-booking.php`, `booking-browser.cjs` (31 database-free states incl. validation errors, long names, staff views) |

Verification (final tree):
- Isolated: layout 279, foundations 63, auth 1335, availability 2137, booking 536 PASS.
- Ordered structural parity vs the pre-change views (controls, labels, options, links, headings, paragraph text; only the added kicker line ignored): 31 documents, 0 mismatches. Browser contract parity (forms, fields, FormData, links, paragraphs): 31 documents PASS.
- booking-browser: 248 layout states at 100% and 200% text over 1438x638, 1438x900, 390x844, 320x640 (no page overflow, no clipped controls); 2408 keyboard stops with a visible focus indicator; the legacy CSS-only need/for show-hide still works; intercepted native submissions carry the exact field names (GET step 1: for/date/from/to; POST step 2: _token, need, room, qty[], other_items, purpose, for/date/from/to; POST cancel: reason).
- Neighbouring suites unchanged and passing against the original and previous baselines: availability, task-ux, timeline, rooms-correction, foundations, layout, auth, shell-navigation (3 runs), adapted visual-refinement.

Real defects found by the new test and fixed in CSS only (no assertion weakened): equipment "picked" badge overflowed at 200% text (summary now wraps); long unbroken room names overflowed room options (now wrap); equipment quantity select and status badge overflowed at 320px with 200% text (item row stacks on narrow phones; badge wraps).

Deliberate exceptions / notes:
- The prototype's "Booking rules" side card, "Check free rooms" header button and source-contract strip were NOT added (prototype-only or duplicate of an existing hint link).
- Staff/admin sections of the shared detail page get the same panel styling here; their states are fixture-covered for parity (`show-lab_staff-*`, `show-admin-*`) and are designed/verified in Phase 05.
- NOT RUN: PHPUnit, authenticated live app, real booking submission.

## Phase 05 — Lab operations (S09 staff dashboard, S15 Today, S16 Requests, staff actions on S14)

Branch `feat/frontend-design-05-lab-operations`, stacked on `feat/frontend-design-04-rep-booking` @ `6cb9c04`.

| Part | Commit | Summary |
|---|---|---|
| 05A | `e9e4f2b` | `public/css/operations.css` (new, `.rb-ops`), staff-action and disclosure styles in `booking.css`, staff dashboard on the `/rep` structure (`dashboard.css` now loads for all roles), wrappers/classes in `today/index`, `requests/index`, `home` (non-rep branch). Updated the stale "staff dashboard markup unchanged" guard in `availability-browser.cjs`. |
| 05B | `d1d9934` | Verification tooling: `ops-runtime.php`, `render-ops.php`, `ops-browser.cjs` (28 database-free states: Today, Requests, staff actions with validation errors, long names, late/overdue) |

Verification (final tree):
- Isolated: layout 279, foundations 63, auth 1335, availability 2137, booking 536, operations 532 PASS.
- Ordered structural parity vs the pre-change views for the 28 operations states: 0 mismatches. Staff dashboards: forms, links, titles, records and disclosures identical (availability-browser vs the previous commit AND the original `7db0f1c` PASS). The 8 Representative home documents stay byte-identical (the `@push` block reproduces the original bytes). The only paragraph that disappears is the old `<p class="ready">` wrapper whose entire text was the link labels; the links are unchanged.
- ops-browser: 224 layout states at 100%/200% text over 1438x638, 1438x900, 390x844, 320x640 with no overflow or clipped controls; 3028 keyboard stops with a visible focus indicator; intercepted POSTs carry the exact field names (approve, reject+reason, hand-out, no-show with its confirmation kept, returned+note, class answer=online); the move form reopens with old input after a validation error; an ended booking offers no cancel/move box.
- Neighbouring suites pass: task-ux, timeline, rooms-correction, foundations, layout, auth, shell-navigation (3 runs).

Deliberate exceptions / notes:
- `availability-browser.cjs`: the earlier scope guard "staff/admin dashboard markup unchanged and dashboard.css not loaded" asserted a Phase 04 scope boundary, not behaviour. It is replaced by "dashboard.css loads exactly once"; every functional comparison remains exact.
- visual-refinement-browser (HTML byte-parity assumes CSS-only changes): an adapted scratchpad copy skips parity ONLY for Free Rooms, timeline and the two staff dashboard documents; it passes with 87 documents of parity, computed-style parity and 144 timeline comparisons.
- Admin-specific dashboard content (S10) shares this structure; its role-specific checks belong to Phase 06.
- NOT RUN: PHPUnit, authenticated live app, real approvals/hand-outs.

## Phase 06 — Admin accounts (list and edit)

Branch `feat/frontend-design-06-admin-accounts`, stacked on `feat/frontend-design-05-lab-operations` @ `204a337`.

| Part | Commit | Summary |
|---|---|---|
| 06A | `7885366` | `public/css/manage.css` (new, `.rb-manage`, reused by Phases 07-08) + wrappers/classes/kicker in `accounts/index` and `accounts/edit`. No form, field, link, label or the delete confirmation changed; the `for-rep`/`for-staff` switching in `style.css` is untouched. |
| 06B | `50dfd37` | Verification tooling: `accounts-runtime.php`, `render-accounts.php`, `accounts-browser.cjs` (11 database-free states) and this log |

Verification (final tree):
- Isolated: layout 279, foundations 63, auth 1335, availability 2137, booking 536, operations 532, accounts 288 PASS (password never rendered back, unique ids, labelled controls, CSRF, delete confirmation).
- Ordered structural parity vs the pre-change views: 11 documents, 0 structure and 0 paragraph mismatches.
- accounts-browser: 88 layout states at 100%/200% text over 1438x638, 1438x900, 390x844, 320x640 (no overflow, no clipped controls); 1214 keyboard stops with visible focus; role switching still shows/hides the block and staff fields; intercepted add, update (`_method=PUT`), password and delete (`_method=DELETE`, confirmation fires once) carry exact field names; form/field/link parity with baseline.

Real defects found by the new test and fixed in CSS only: long unbroken names overflowed hint/heading text at 390px; the "Set password" row and the delete button (nowrap) overflowed at 320px with 200% text; the `…@iskolarngbayan.pup.edu.ph` nowrap hint overflowed at 200% text.

Notes: the test fixture initially picked the update form for the delete check because both share one URL; the test now selects by `_method=DELETE`. NOT RUN: PHPUnit, authenticated live app, real account changes. Neighbouring browser suites were not re-run for this phase (only new CSS file and two accounts views changed); the isolated render suites above were.

## Phase 07 — Rooms, equipment, booking rules (Manage rooms, edit room, equipment, edit item, rules and terms)

Branch `feat/frontend-design-07-rooms-equipment`, stacked on `feat/frontend-design-06-admin-accounts` @ `8e34b72`.

| Part | Commit | Summary |
|---|---|---|
| 07A | `c06aff7` | `manage.css` extended (tabs as pills, category panels, rename disclosure, rules fieldsets, term forms, over-stock rows, stacked rooms/equipment tables on phones) + wrappers/classes/kicker in the five `manage/*` views. No form, field, link, label or limit changed. |
| 07B | `dcd3bbc` | Verification tooling: `manage-runtime.php`, `render-manage.php`, `manage-browser.cjs` (46 database-free states for lab staff and admin: normal, empty, validation errors, long names, shared/off room, over-stock item) |

Verification (final tree):
- Isolated: layout 279, foundations 63, auth 1335, availability 2137, booking 536, operations 532, accounts 288, management 1052 PASS.
- Ordered structural parity vs the pre-change views: 46 documents, 0 structure and 0 paragraph mismatches.
- manage-browser: 368 layout states at 100%/200% text over 1438x638, 1438x900, 390x844, 320x640 (no overflow, no clipped controls); 5388 keyboard stops with visible focus; intercepted POSTs (add room, update room, rename category, add item, update item, rules, term dates) carry exact field names; form/field/link parity with baseline. accounts-browser re-run against the extended stylesheet: PASS.

Real defects found by the new test and fixed in CSS only: native date/time inputs showed no focus ring when the picker button took focus (`:focus-within` added); five-column equipment and four-column room tables overflowed at 200% text on phones (stacked as labelled rows below 40rem, table semantics kept in markup).

Notes: the shared `manage/_tabs` partial is restyled only inside `.rb-manage`; the Timetable pages (Phase 08) still use the legacy styling until they opt in. The room-table class `rooms-table` was added for the phone layout. NOT RUN: PHPUnit, authenticated live app, real room/stock changes.

## Phase 08 — Timetables (overview, in use, draft, edit row, check and replace)

Branch `feat/frontend-design-08-timetable`, stacked on `feat/frontend-design-07-rooms-equipment` @ `9aed410`.

| Part | Commit | Summary |
|---|---|---|
| 08A | `749c7f9` | `public/css/timetable.css` (new, `.rb-tt`, loaded after booking.css and manage.css) + wrappers/classes/kicker in `manage/timetable`, `timetable_rows`, `timetable_row`, `timetable_check`. In-use / draft / attention cards, two-ways cards, backups list, day tabs and week picker, row tables (labelled phone layout), week grid, row form, check-and-replace sections. No form, field, link, version field, `formaction` or action URL changed. The shared partials (`_timetable_*`) are untouched. |
| 08B | `1b4c4b8` | Verification tooling: `timetable-runtime.php`, `render-timetable.php`, `timetable-browser.cjs` (34 database-free states: overview empty/in use/draft/problems/clashes/backups/long, in-use day/week/block, draft day/week/fix/warnings/undo/errors/long, row edit class/blocked/problems/errors/long, check ok/problems/mostly/empty/same/bookings/errors/long) |

Verification (final tree):
- Isolated: layout 279, foundations 63, auth 1335, availability 2137, booking 536, operations 532, accounts 288, management 1052, timetable 692 PASS.
- Ordered structural parity vs the pre-change views: 34 documents, 0 structure and 0 paragraph mismatches.
- timetable-browser: 272 layout states at 100%/200% text over 1438x638, 1438x900, 390x844, 320x640 (no overflow, no clipped controls); 4620 keyboard stops with visible focus; control-text contrast >= 3:1 on every state; the CSS-only class/blocked switch still hides the class fields; intercepted submissions (never sent) carry exact targets and field names: upload (multipart `file`), start, discard, restore (with and without a draft), add / update / delete row, undo, semester (`formaction` to `/manage/timetable/semester` with `version`) and replace (`version`, term fields); Replace stays disabled while rows have problems; form/field/link parity with baseline.
- manage-browser (Phase 07) re-run against the shared `manage.css`: PASS.

Real defect found by the new contrast guard and fixed in CSS only: the primary "Check and replace" link in the draft header rendered maroon text on a maroon button (a link rule outranked the button rule).

Notes (as of 08A/08B): no live data was touched; draft, check and apply logic came from fixtures only. The "NOT RUN" items of that stage were run afterwards by the independent QA below.

### Phase 08 independent QA and corrections

Independent audit (functional, security, real-browser UX/accessibility, integration) rated Phase 08 **APPROVE WITH MINOR FIXES**; the backend was unchanged and correct. Corrections are presentation, accessibility and test tooling only: no route, controller, model, migration, validation, authorization, semester-replacement or availability code changed.

| Finding | Commit | Correction |
|---|---|---|
| T-01/T-02 refused forms | `c5db6d4` | Add/edit row, semester and upload forms mark each bad field `aria-invalid` + `aria-describedby`, show its message above it, and autofocus the first visible bad field only in the answer to a refused submit. `public/js/timetable.js` (page-only) scrolls it into view with its message; a rem `scroll-margin` grows with text size; below `22rem` (narrow for the text size) nested card padding is compacted so messages keep a readable column. |
| T-04 headings | `44d1c85` | h1 and title: Manage timetable, View timetable in use, Edit new timetable, Edit timetable row, Check and replace timetable. One h1 per page. |
| T-05 navigation | `db3cecd` | Manage tabs carry `aria-current="page"`. |
| T-06 touch targets | `86df353`, `9e73f5a` | "Open" booking links (overlap panel and check-page class-session list): at least 24px, 44x44px on narrow layouts and coarse pointers. |
| T-07 scroll regions | `8fcfabc` | Week grid and New/Removed tables: `role="region"`, `aria-label`, `tabindex="0"`, visible focus; arrow keys scroll, Tab leaves. |
| Browser-test race | `2ed94bf` | Post helpers wait for the intercepted stub page to commit (not just the request), in the timetable, manage, accounts, ops and booking suites. No delay. |
| Fixtures | `b74a089` | Timetable isolated assertions 692 -> 892 cover the corrections. |
| T-03 overflow at 200% text | none | Reproduced only on the standalone branch (max-length row, page 2005px > 1438px): the legacy `.rb-manage td.hide-sm { white-space: nowrap }` rule keeps the Instructor cell unbreakable. `origin/dev` scopes that rule to the accounts table, so the merged tree fits (1423px). No CSS change made for it. |

Verification (disposable app and MariaDB; genuine browser text size, i.e. the profile default font size 32px, which persists across redirects):
- Functional: 71/71 assertions plus the 72-row Guest/Rep/Lab staff/Admin x CSRF matrix, identical to the audit on the branch and on the merged tree.
- Real browser: refused-form checks 72/72 (24/72 before), Open links 8/8 + 8/8, scroll regions 56/56 (32/56 before), at 1438x900, 1438x638, 390x844, 320x640 at 100% and 200%.
- PHPUnit with the gated suites against disposable MariaDB: branch 109 tests, 102 passed, 1 failure, 1 error, 5 skipped; merged tree 112 tests, 102 passed, 1 failure, 1 error, 8 skipped; the untouched original has the same totals. Pre-existing: `AvailabilityWorkflowTest::test_dashboard_data_shortcuts_and_answer_controls` (failure) and `FoundationsTest::test_presentation_contracts_without_database_access` (order-dependent error; passes alone).
- Isolated render checks all PASS (timetable 892). Fixture-browser suites (timetable, ops, manage, accounts, booking) passed 19/19 repeated runs per tree with the committed scripts.
- Merge into current `origin/dev`: clean, no conflicts; no Phase 05-07 file changed.

Backend concern (not changed): replacing with a future-dated semester moves every class to the new term, so the current term's classes stop blocking rooms until its start date. The check page does state when the classes start counting.

## Phase 09 — Signed class check and the six error pages

Branch `feat/frontend-design-09-system-pages`, stacked on `feat/frontend-design-08-timetable` @ `22c0885`.

| Part | Commit | Summary |
|---|---|---|
| 09A | `7958a1a` | `public/css/check.css` (new, `.rb-check`) + classes/stylesheets in `classes/check.blade.php`: card with facts strip, state badge, two outlined answer cards (face-to-face keeps the room, online frees it), picked/current states, phone stacking. Form, `answer` values, POST-only submission and copy are unchanged. |
| 09B | `9dfb01e` | Verification tooling: `classcheck-runtime.php`, `render-classcheck.php`, `classcheck-browser.cjs` (16 database-free states for guest, rep and lab staff: waiting, face-to-face, online, freed, picked, booked, ended, wrong rep, status, problem, long names) |

Error pages (403, 404, 413, 419, 429, 500): they already share `errors/message.blade.php` with the Phase 02 auth card and `auth.css`, and are covered by the Phase 02/03 suites (`auth-runtime.php`: each code for guest, rep and admin; 413 and 500 hide the user menu). No change was needed or made; the isolated auth suite (1335 assertions) passes on the final tree.

Verification (final tree):
- Isolated: layout 279, foundations 63, auth 1335, availability 2137, booking 536, operations 532, accounts 288, management 1052, timetable 692, class check 165 PASS (answer form only while the class is open, POST only, CSRF, no answering link, status/alert roles).
- Ordered structural parity vs the pre-change view: 16 documents, 0 mismatches.
- classcheck-browser: 128 layout states at 100%/200% text over 1438x638, 1438x900, 390x844, 320x640 (no overflow, no clipped controls); 426 keyboard stops with visible focus; intercepted POSTs carry `answer=f2f` / `answer=online` with the token; ended and booked meetings offer no buttons; GET never answers; form/field/link parity.

Real defects found by the new test and fixed in CSS only: long unbroken course/room/rep names overflowed at 390px; the "Booked as a class session" badge overflowed at 320px with 200% text.

Notes: the signed-URL, throttling and expiry behaviour is server-side and unchanged (NOT exercised here; no request is sent). NOT RUN: PHPUnit, authenticated live app, real email links.

Notes (as of 09A/09B): the "NOT RUN" items of that stage (PHPUnit, authenticated app, real links) were run afterwards by the independent QA below.

### Phase 09 independent QA and corrections

Independent audit (functional, security, real-browser UX/accessibility, integration) rated Phase 09 **APPROVE WITH MINOR FIXES**; the backend was unchanged and correct. Corrections are views, CSS, one small page script and tests only: no route, controller, model, validation, authorization, signed-link or business-rule code changed.

| Finding | Commit | Correction |
|---|---|---|
| F-02 stale highlight | `cc3bdc8` | The saved answer decides which card is marked, in text ("Your answer"). The email button's `?answer=` only marks a card while nothing is saved (dashed card, "Chosen in your email"); no focus-style ring. Fixtures 165 -> 295, plus a PHPUnit presentation test. |
| F-03 422 page | `c684400` | `errors/422.blade.php` on the shared error card (rem type, header, recovery link). Status stays 422. It was byte-identical at 100% and 200% text. |
| F-11 mid-word headings and alerts | `e7fd5d3`, then the follow-up commit that added the alert fix | Error and email-result cards size the h1 from their own width (`min(h2, 17cqw)`, `overflow-wrap: break-word`). "Something"/"connected" no longer break at 320px/200%. A later full-page check showed the alert text still broke ("connect-ed."); at narrow-for-the-text widths the card and alert now use less padding, keeping `overflow-wrap: anywhere` so a long address still wraps (break-word made one overflow). 224/224 page states keep ordinary words whole (218/224 before). |
| F-01 answers and result below the fold | `db332ed` | "Jump to the answer buttons" link (visible on short windows), compact spacing, and the result message takes focus and is scrolled into view after an answer (`public/js/check.js`). |
| F-07 dead ends | `9731953` | Ended, booked and not-this-rep states end with a real link ("Open My bookings" / "Go to my home page"). |
| F-08 contrast | `33dbef1` | Caption on the saved-answer card 4.48:1 -> 14.11:1. |
| Browser-test race | `b662617`, `5546131` | The timetable, ops, manage, accounts and booking browser scripts carried in this branch's tree raced: the POST helper waited for the request and then for load state, so the stub navigation was still in flight at the next `goto`. On this branch they passed 1 of 10 runs, every failure "Navigation ... interrupted by another navigation". Each helper now waits for the stub document to commit (a line byte-identical to `origin/dev`'s Phase 08 fix, so the merge does not conflict on it): classcheck 5/5, timetable 5/5, ops 5/5, manage 3/3, accounts 3/3, booking 3/3 as committed. Phase 09's own `classcheck-browser.cjs` has the same pattern but never failed (23+ runs, stub delays 0-350ms), so its change (`5546131`) is defensive. |

Not changed (backend/config/shell, reported separately and not Phase 09 UX defects): `APP_DEBUG=true` in `.env.example` and the committed compose file (debug page on any 500); the 500 page's "check storage/logs/laravel.log" copy; unsigned requests answer 403/404 by id before the signature check; the maintenance (503) page is still the framework default; the shell's bottom-nav labels break mid-word at 320px/200%.

Verification (disposable app and MariaDB; genuine browser text size):
- Real browser at 1438x900, 1438x638, 390x844, 320x640 at 100% and 200%: error/email pages 52/52 (46 before), answer visibility/jump link/result focus 64/64 (10 before), accessible names agree with the visual state in the stale-highlight scenario (5/5).
- HTTP/permission/CSRF/status-code scripts: class-check 62/62, email results 9/9, error pages 32/34 (the 2 are the intended `APP_DEBUG=true` leak), on the branch and on the merged tree.
- PHPUnit (gated against disposable MariaDB): branch 110 tests, 103 passed, 1 failure, 1 error, 5 skipped; merged tree 113, 103, 1, 1, 8; same two pre-existing tests as `origin/dev` (`AvailabilityWorkflowTest::test_dashboard_data_shortcuts_and_answer_controls`, `FoundationsTest::test_presentation_contracts_without_database_access`).
- Render suites all pass (auth 1390, class check 414). Fixture-browser suites on the merged tree: 17/17 runs; Phase 02 auth browser 3/3 (63 cases).
- Merge into current `origin/dev`: one documentation conflict in this file (branch-map rows and the Phase 08 notes); resolve by keeping dev's text and adding Phase 09's row and section. Code merges cleanly; 36/36 pages identical to dev apart from the new card class.

## Phase 10 — Link colour consistency, colour scan and QA handoff (no new feature scope)

Branch `feat/frontend-design-10-integration-qa`, branched from `feat/frontend-design-09-system-pages` @ `c30191b`. Phase 10 was **not** integrated with `dev` when it was written; `dev` has since merged Phases 03-09 and their QA corrections (PR #21 is the latest). Phases 11-13 are stacked on this branch.

| Part | Commit | Summary |
|---|---|---|
| 10A | `121fe47`, `5e3b5e4` | CSS fix found by the cross-screen colour scan (plain inline links and `.req-link` on booking-system pages were legacy violet), first `tests/Support/legacy-colour-scan.cjs`, first `docs/frontend/QA_HANDOFF.md`. |

Verification recorded at 10A (isolated fixtures on the branch's own tree): layout 279, foundations 63, auth 1335, availability 2137, booking 536, operations 532, accounts 288, management 1052, timetable 692, class check 165 render assertions PASS; the browser suites listed in the original entry passed. Those counts are for the branch tree; merged with `dev` they are higher (auth 1390, operations 536, accounts 291, management 1464, timetable 892, class check 414).

### Phase 10 independent QA and corrections

An independent audit (fixtures, PHPUnit against a disposable MariaDB, an authenticated live app on a disposable database, real-browser checks at 1438x900, 1438x638, 390x844, 320x640 at 100% and genuine 200% text) rated Phase 10 **APPROVE WITH MINOR FIXES**. Backend, routes, authorization and migrations were not changed. Corrections are CSS, the scan script and documentation only:

| Finding | Commit | Correction |
|---|---|---|
| N-1 My bookings titles went dark to maroon and lost the hover cue; N-2 timetable week-day links went from weight 700 to 600 | `5e544c0`, `b494ebd` | The plain-link rule excludes `.brow-title a` and `.tt-dayname a`. The exclusions sit in `:where()`: a plain `:not(.brow-title a)` raised the rule's specificity to (0,2,2), which made it beat dev's dark "Recently decided" purpose links; the follow-up commit keeps it at the original (0,2,1). |
| E-1 legacy violet focus ring on the Reject and Cancel reason fields (`style.css` `details.reject textarea:focus`) | `8699f09` | Maroon focus ring and a visible border on those two fields, same as every other field. |
| Found by the new `--states` scan: violet hover on the busy-rooms disclosure (`style.css` `.busy-rooms summary:hover`) | `bdeac81` | Maroon hover. |
| Scanner limits: pale tints missed, no focus/hover, truncated output, could not separate known-legacy fixtures, raw stack trace on bad arguments | `621230c` | Hue-based detection (pale tints included), `--states` focus and hover checks, all findings printed with a distinct-findings summary, `--known-legacy=` lists but does not fail, exit 0/1/2, usage errors, external requests aborted, prints what it does not cover. |
| Stale or false handoff claims | this documentation commit | `QA_HANDOFF.md` rewritten: merged versus local branches, what was tested, open risks. |

Verification of the corrections (disposable infrastructure only):
- Targeted browser assertions on the `dev` + Phase 10 tree, 1438x900, 390x844, 320x640 at 100% and 200% text: 42 checks (title colour and hover, week-day weight equal to `dev`, plain link colour, Reject and Cancel focus ring, busy-rooms hover), 0 failures; the same checks fail 30 of 42 against the original `5e3b5e4` CSS.
- Computed-style diff `dev` versus `dev` + Phase 10 (172 fixtures, 4 viewports, 100% and 200%): only the intended changes (plain violet links to maroon; the two reason fields' focus ring); no `.brow-title`, `.tt-dayname` or purpose-link change; no new horizontal overflow at 200%.
- Colour scan: original CSS with `--states` exit 1 (29 documents with violet textarea focus, 5 with violet hover); corrected tree with `--states` and `--known-legacy=layout` exit 0, 0 active, 15 known-legacy layout fixtures listed; usage errors exit 2. Merged with `dev` the scan first exited 1 on the Phase 09 `a.check-jump` link (15 class-check fixtures); after the final correction (`public/css/check.css`: maroon colour on the base rule) the integrated tree scans exit 0, 0 active violations in 321 documents, with the 15 generic `layout/` fixtures listed as known legacy.
- PHPUnit (disposable MariaDB, all gates): branch 109 tests, 102 passed, 1 failure, 1 error, 5 skipped, 2 risky; merged with `dev` 113 tests, 106 passed, 1 failure, 1 error, 5 skipped, 3 risky. The failures are the two pre-existing `dev` tests named in `QA_HANDOFF.md`; identical before and after these corrections.
- Render checks PASS on the branch and the merged tree (layout 279, booking 536, operations 532 / 536 merged, timetable 692 / 892 merged); fixture-browser booking 31, operations 28, timetable 34 cases PASS on the merged tree.

Not changed and still open (see `QA_HANDOFF.md`): `APP_DEBUG=true` defaults (deployment review), login throttling (Phase 12, not merged), future-day hand-out (Phase 13, not merged), future-dated semester availability (unresolved), the two failing tests (fixed on Phase 11, not merged).

### Integrating with `dev`

Merging `origin/dev` (`771fe95`) into this branch conflicts in this file only: (1) branch-map rows 07-09 and 10, (2) the end of the Phase 09 section against the appended Phase 10 section. Resolution: keep `dev`'s rows 07-09 and its whole Phase 09 section (including "Phase 09 independent QA and corrections"), then keep this Phase 10 row and section after them. `public/css/booking.css` merges automatically and keeps dev's Phase 05 corrections. The merge commit is a normal two-parent merge (`origin/dev` `771fe95` into this branch). One further correction follows it: the Phase 09 `a.check-jump` link no longer shows the legacy violet when focused (`public/css/check.css`, one line).
