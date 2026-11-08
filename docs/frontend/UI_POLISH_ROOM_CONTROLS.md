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
