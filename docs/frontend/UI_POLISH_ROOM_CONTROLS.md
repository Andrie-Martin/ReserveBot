# UI polish: room schedule, pickers, requests, manage pages, pagination (items A-N)

Branch `fix/frontend-room-schedule-controls` (from `origin/dev` @ `1eb76c1`). Presentation only: no migrations, routes, policies, validation or booking/stock rules changed.
Backend touched only for pagination: `AccountsController::index` (20 per page, in-memory order kept, page clamped) and `BookingController::mine` (past bookings: query-level, 10 per page, `past_page`, clamped).

## Shared pieces
- `public/js/ui.js` (loaded by the layout): drawers (`<dialog class="rb-drawer">`, trigger `data-rb-drawer`), pickers (`select[data-rb-picker]`, optional `="search"`), reveal panels (`data-rb-reveal`).
  Every one has a no-script fallback: `:target` opens drawers and reveal panels; the native `<select>` stays the submitted control.
- `public/js/booking.js` (steppers, equipment search, summary) and `public/js/manage.js` (equipment search, inline rename).
- Blade: `x-ui.drawer`, `x-ui.pager`. CSS: `public/css/foundations/components.css` (select arrow, picker, drawer, pager, `.rb-stat`).

## Behaviour notes
- Search boxes, steppers' buttons and the equipment summary are hidden without script (they would not work); number inputs and full lists remain.
- Rep home: the first three "other" meetings are shown read-only as a preview; the answer buttons for those live in the "View all" drawer, so each meeting still has exactly one form.
- Accounts: group counts are totals; a group heading appears on the pages that contain its people.
- My bookings: the old "last 30" cap is gone; all past bookings are reachable by page.

## Tests
`tests/Feature/Frontend/UiPolishPresentationTest.php` (source contracts, no DB) and `tests/Feature/PaginationWorkflowTest.php` (DB-gated: run with `tests/Support/run-db-tests.sh`).

## Final corrections (after independent QA)

- **Rename:** there is no Rename button. The category name is the target: double-click (mouse), tap (touch/pen), Enter / Space / F2 (keyboard). A pencil and a hint ("Double-click the name to rename" / "Tap the name to rename") appear next to it. The field is percentage-width on phones, so it can never widen the page (QA F1).
- **Requests:** waiting requests are split into **Room requests** (room only, room and equipment) and **Equipment-only requests**, each with its own count, both still soonest first. Presentation only; approval rules and routes are unchanged.
- **Cards:** Booking rules is one white card with Save in its footer; each semester is one white card with Save dates in its footer. The password guidance is a white card matching the form.
- **Search:** the equipment search (Manage and Book) fills its card and uses an application clear button (`.rb-search`, wired in `ui.js`); the browser's own X is hidden.
- **Dashboard stat:** number and label share one font size and line height; only weight, colour and a soft tint set the number apart.
- **QA fixes:** no-JS drawer overlay is a spread shadow, not a layer over the panel (F2); drawer header is kicker + title + Close, the sub-line moved into the body (F4); `hashchange` opens a drawer and only one is open at a time (F5); ties on My bookings are oldest id first and Accounts are read in id order (F3); the unused `room-busy` CSS is removed (F6).
- **Tests:** `UiPolishPresentationTest` (20 source checks, the two ineffective assertions replaced), `PaginationWorkflowTest` (tie order, request grouping), and a real-browser script `tests/Support/ui-polish-browser.cjs` (needs Playwright and a disposable app; see its header). It changes no data.

## Final polish: Free rooms and Booking details

- **Unavailable rooms (`/rooms`):** `App\Support\BlockedReason` turns a "Blocked" timetable note into readable text: "Unavailable all day — Reason not specified" or "— Assigned to ECE". The "(yellow on timetable…)" remark is a spreadsheet cell colour carried in the seeded notes, so it is dropped on display only; the stored note and the block itself are untouched, bars keep their colour.
- **Capacity:** `App\Support\RoomCapacity::label()` shows "40 seats" / "1 seat", and "Capacity not specified" for a NULL capacity (never zero). Used on Free rooms, the drawer, Book a room and Manage rooms.
- **Calendar:** on desktop the calendar card and the Event details card are top-aligned and the same height; the event list scrolls inside its card and an empty day has a centred message. On phones they stack naturally.
- **Booking details:** a compact summary (number, title, status, facts grid, equipment chips) is separate from a "Staff actions" card. "Move booking" and "Cancel booking" are two labelled toggles; only the selected form shows (`data-rb-reveal-group="change"`). Move: Day / From / To / Room (searchable picker when many rooms) / Reason. Cancel: full-width reason plus an unnamed required confirmation checkbox (never submitted). Back closes either. Server errors reopen the right panel with the old values (`move` / `cancel` error bags). Without script the toggles open the forms through `:target`. `form[data-rb-once]` blocks a second press while a request is in flight.
- **Unchanged:** routes, methods, CSRF, authorization, rescheduling and stock rules, the future-day guard and the PR #26 concurrency fix.
- **Tests:** `tests/Unit/BlockedReasonTest.php`, `tests/Unit/RoomCapacityTest.php`, four new source-contract tests in `UiPolishPresentationTest`, and 9 more checks in `tests/Support/ui-polish-browser.cjs` (28 in total; none submits a form).
