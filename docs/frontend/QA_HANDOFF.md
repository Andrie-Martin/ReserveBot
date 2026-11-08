# Frontend redesign: QA handoff (phases 01-10)

Status as of the Phase 10 QA corrections. Branch order and commit hashes are in `docs/frontend/OVERNIGHT_PROGRESS.md`.

## What is merged and what is not

| Work | State |
|---|---|
| Phases 01-09 (shell, auth, availability, booking, lab operations, accounts, rooms and equipment, timetable, class check and error pages, with their independent-QA corrections) | **Merged into `dev`** (latest merge: PR #21, Phase 09). |
| Phase 10 (this branch: booking link colours, the colour scan, this handoff) | Local branch, **not merged**. |
| Phase 11 (`feat/frontend-design-11-qa-fixes`): mobile calendar fit, shortcut label wrapping, roadmap card removal, test fixes | Local, **not merged**. |
| Phase 12 (`feat/frontend-design-12-security`): login throttling per account+IP and per IP | Local, **not merged**. |
| Phase 13 (`feat/frontend-design-13-booking-guard`): hand-out and no-show refused for bookings on a future day | Local, **not merged**. |

Phases 11-13 are stacked on Phase 10 and do not contain `dev`. **Nothing on Phases 10-13 is deployed.** Until they are merged and released,
`dev` has no login throttling, still allows staff to hand out or mark no-show on a future-day booking, and still shows the Phase 11 defects.
The running Docker app serves the code baked into its image and was never used as test evidence.

## What has been tested, and how

- **Authenticated HTTP and browser testing has happened**, but only against disposable infrastructure (a throwaway MariaDB and `php artisan serve` on a private port),
  never against the running Docker app or its database (the Phase 08 and 09 records state a disposable app and MariaDB explicitly; the Phase 05-07 records mention authenticated checks but say less about the setup): the independent QA of Phases 05-09, and the Phase 10 audit (rep, lab staff and admin sessions, role/status matrix,
  forged and wrong-role POSTs, approve, reject, cancel, and rendering at 1438x900, 1438x638, 390x844 and 320x640 at 100% and genuine 200% text).
- **PHPUnit** (all gates on, disposable MariaDB, Phase 10 tree): 109 tests, 102 passed, 1 failure, 1 error, 5 skipped, 2 risky. Merged with `dev`: 113 tests, 106 passed, 1 failure, 1 error, 5 skipped, 3 risky.
  The two failures are **pre-existing on `dev`** and not caused by Phase 10: `AvailabilityWorkflowTest::test_dashboard_data_shortcuts_and_answer_controls` (stale assertion) and
  `FoundationsTest::test_presentation_contracts_without_database_access` (test-order pollution, "No hint path defined for [__components]"). Phase 11 (`719c09f`) fixes both; that is not merged.
  A plain `phpunit` run skips the database-gated suites; use `tests/Support/run-db-tests.sh` (on Phase 11 and later) or an equivalent disposable database.
- **Fixtures** (`tests/Support/*-runtime.php`, `render-*.php --check`, `*-browser.cjs`) need no database and are fast, but they prove rendering, not workflows.
- **Never exercised:** real email delivery and real signed class-check links in a mail client.

## Open risks and decisions (none introduced by Phase 10)

1. **`APP_DEBUG=true`** is the default in `.env.example` and the committed compose file. A real 500 then shows the exception class and SQL error text (observed in a throwaway server; the DB password and `APP_KEY` were not in the page).
   Needs a **deployment review**; this branch does not change configuration.
2. **Login throttling** is addressed only on Phase 12 (local, not merged). On `dev` it is absent.
3. **Future-day hand-out and no-show** are addressed only on Phase 13 (local, not merged). On `dev` staff can hand out a booking for tomorrow.
4. **Future-dated semester availability is unresolved** and no branch addresses it. The class timetable blocks rooms only for dates inside a semester (`RoomAvailability::inTerm`). With the sample semester ending 2027-01-31,
   a Tuesday after that date shows no class blocks, so rooms look free during class hours and can be booked until the next semester is entered. Needs an owner decision (block, warn, or require a semester).
5. **Legacy violet styling may still exist.** `public/css/style.css` still defines the old violet palette and rules; the redesigned screens override most of it. Known at the time of writing:
   the class-check "Jump to the answer buttons" link (`a.check-jump`, added by Phase 09 QA) was violet on `dev` when focused; the Reject and Cancel reason fields and the busy-rooms hover were violet on `dev` too. All three are corrected on this branch (not on `dev` until it is merged).
6. `/rep` at 1438x638: the first meeting-answer buttons start about 694px down (below the fold); visible at 1438x900. Follows the approved prototype order. Needs a human decision.

## What to review by hand (authenticated, real data)

Automated passes exist for most of this; a human should still confirm it on realistic data and in a real mail client:

1. Representative: `/rep` (fold decision above), Free rooms, Book (both steps), My bookings, booking detail, cancel.
2. Lab staff and admin: dashboard, Today (hand out, no-show confirm, returned note, class answers), Requests (approve, reject with reason), booking detail staff actions.
3. Admin: Accounts list and edit (add, update, new password, delete/turn off with its confirmation).
4. Lab staff and admin: Manage rooms, equipment (categories, items), booking rules and semester dates.
5. Timetable: overview, upload (use a copy of a file, never the live one), start/continue/discard a draft, edit rows, check, semester save (the second submit button), backups. Do NOT press "Replace" on a database you care about.
6. Reminder email link: open the class-check link, confirm opening it alone does not answer, then press a button.
7. Error pages: trigger 403/404/419/429 as a rep, and look at 413/500 as a guest.

## Known limitations of the test tools

- Tables on phones (equipment, rooms, timetable rows) are shown as labelled rows below 40rem with CSS only; the table markup is unchanged.
- `foundations-browser.cjs` compares computed styles with and without the foundation CSS on the shared layout fixtures. Since Phase 04 the booking fixture is styled by those foundations on purpose, so it passes against the pre-Phase-04 layout export and reports that difference against the current one. `visual-refinement-browser.cjs` asserts byte-identical HTML for CSS-only changes and is run from a copy that skips parity only for intentionally restructured documents.
- The fixture-browser suites simulate large text with `html{font-size:200%}`; the Phase 10 checks also used the browser's real default-font-size setting (a different mechanism).
- Generic shell fixtures in `layout-runtime.php` contain legacy placeholder content, so the colour scan flags them. Pass `--known-legacy=layout` to list them without failing the run.
- Prototype-only items were deliberately not added: booking "Booking rules" side card, "Check free rooms" header button, source-contract strip.

## Re-running the checks

Each phase has `tests/Support/<name>-runtime.php` (database-free fixtures), `render-<name>.php` (`--check`, `--export <dir>`) and
`<name>-browser.cjs` (needs an installed Playwright and a browser). Export fixtures from a throwaway checkout or container; never against the live container.

`legacy-colour-scan.cjs` reports rendered fixtures that show the legacy violet family (hue 245-290, including pale tints such as `#EDE9FE`, `#F5F3FF`, `#DDD6FE`):

```
node tests/Support/legacy-colour-scan.cjs <playwright-core> <public> [--states] [--known-legacy=layout] <fixture dir> [...]
```

Exit 0 = no active violation, 1 = active violations (all listed, nothing truncated), 2 = usage error. `--states` also checks `:focus-visible` and `:hover` of links, buttons, summaries and form fields (about 5 minutes for all fixtures).
A static run misses focus and hover, which is how the violet Reject/Cancel focus ring went unnoticed. It does not check pseudo-elements, gradients, shadows, SVG, `:active`, other viewports or text sizes; a clean result is not proof of no legacy styling.
