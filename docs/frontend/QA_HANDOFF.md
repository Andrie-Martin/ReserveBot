# Frontend redesign: QA handoff (phases 03-10)

Everything below is on local stacked branches; nothing is pushed, merged, rebased or squashed. Branch order and
commit hashes are in `docs/frontend/OVERNIGHT_PROGRESS.md`. The running Docker app was never used as evidence and
still serves the code baked into its image.

## What to review by hand (authenticated, real data)

None of this could be checked here (no login, no database, no real mail):

1. Representative: `/rep` (fold decision, see below), Free rooms, Book (both steps), My bookings, booking detail, cancel.
2. Lab staff and admin: dashboard, Today (hand out, no-show confirm, returned note, class answers), Requests (approve, reject with reason), booking detail staff actions.
3. Admin: Accounts list and edit (add, update, new password, delete/turn off with its confirmation).
4. Lab staff and admin: Manage rooms, equipment (categories, items), booking rules and semester dates.
5. Timetable: overview, upload (use a copy of a file, never the live one), start/continue/discard a draft, edit rows, check, semester save (the second submit button), backups. Do NOT press "Replace" on a database you care about.
6. Reminder email link: open the class-check link, confirm opening it alone does not answer, then press a button.
7. Error pages: trigger 403/404/419/429 as a rep, and look at 413/500 as a guest.

## Known limitations and open decisions

- `/rep` at 1438x638: the first meeting-answer buttons start about 694px down (below the fold); visible at 1438x900. Follows the approved prototype order. Options: accept, compact the banner, or move the meetings card up. Needs a human decision.
- Prototype-only items were deliberately not added: booking "Booking rules" side card, "Check free rooms" header button, source-contract strip.
- Tables on phones (equipment, rooms, timetable rows) are shown as labelled rows below 40rem with CSS only; the table markup is unchanged.
- `foundations-browser.cjs` compares computed styles with and without the foundation CSS on the shared layout fixtures. Since Phase 04 the booking fixture is styled by those foundations on purpose, so it passes against the pre-Phase-04 layout export and reports that difference against the current one. `visual-refinement-browser.cjs` asserts byte-identical HTML for CSS-only changes and is run from a copy that skips parity only for intentionally restructured documents (see the log).
- Generic shell fixtures in `layout-runtime.php` contain legacy placeholder content, so the colour scan flags them; it is clean for every real screen.
- Not run anywhere: PHPUnit, authenticated live app, real submissions, real email.

## Re-running the checks

Each phase has `tests/Support/<name>-runtime.php` (database-free fixtures), `render-<name>.php` (`--check`, `--export <dir>`) and
`<name>-browser.cjs` (needs an installed Playwright and a browser). Export fixtures in a throwaway container with the working
tree mounted read-only and `--network none`; never against the live container. `legacy-colour-scan.cjs` lists screens that
still show the old violet.
