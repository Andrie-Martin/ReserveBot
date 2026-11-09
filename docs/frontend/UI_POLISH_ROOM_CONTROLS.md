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

## Final refinements (A-F)

Presentation only. The one backend addition is read-only: `App\Services\DeskStats` (three dashboard counts). No routes, policies, validation, migrations, stock or booking rules changed.

- **A. Secondary navigation:** `.rb-subnav` (`components.css`) replaces the pill tabs on Manage (Rooms, Equipment, Booking rules, Timetable) and is reused by Requests. One hairline under the row, the current link maroon with a 3px underline, hover and `:focus-visible` states, and sideways scrolling inside the nav on narrow screens (`ui.js` brings the current link into view; the page itself never scrolls sideways). Links are ordinary routes with `aria-current="page"`. The links carry `rb-subnav__link` so booking.css's "unclassed link is maroon" rule does not recolour idle links.
- **B. Requests tabs:** Room requests (room only, room and equipment) and Equipment-only requests, each with its pending count. Selected by `?tab=equipment` (default Room requests; unknown values fall back to it), so it works without a script, survives refresh and Back, and a refused rejection (`back()` to the same URL) stays on the category. If a rejection error exists for a card in the other category the view switches to it so the error is never hidden. Each queue is still soonest first; Recently decided is separate. No controller change.
- **C. Free rooms colours:** green = reported free, neutral = not reported as free, yellow (striped, so not colour-only) = unavailable all day. Yellow appears only when `AvailabilityController::blockedAllDay` finds a blocked entry covering the whole opening window; the label stays "Unavailable all day — Reason not specified / Assigned to ECE". A compact legend sits under the "Free times in each room" heading.
- **D. Capacity:** the Free rooms overview (and the schedule drawer sub-line) show capacity only when recorded ("40 seats", "1 seat"). Book a room and the Manage forms are unchanged.
- **E. Calendar details:** no nested "N events on DATE" dropdown. Each date with events is a section (date heading, event count, chronological cards with time, title, room, details and tags). With script, the day picked in the calendar (or a clicked date / `#events-DATE`) is shown at once and focus moves to its heading; with no script every date is listed and the calendar's count links jump to them. The list scrolls inside the card on desktop; cards stack on phones.
- **F. Dashboard statistics (lab staff and admin, same three cards):** Requests waiting for approval (pending, own department; the former admin count), Bookings approved or in use today (Confirmed/InUse starting today, own department; the former staff count), and **Equipment units in use**. The third is the sum of `qty_requested` over listed equipment on bookings with status `InUse` (set only by the staff hand-out action, cleared by Returned) in the viewer's department. It counts units, not bookings; free-text "other items" have no quantity and are not counted. Cards are equal width on desktop, two columns below 56rem and one below 40rem.

### Tests and known limits

- `UiPolishPresentationTest` (source contracts), `PaginationWorkflowTest` (DB-gated: tabs, counts, refused rejection stays on its tab, department scoping of the three counts), and `tests/Support/ui-refinements-browser.cjs` (31 real-browser checks, changes no data).
- The phase-era fixture scripts `rooms-correction-browser.cjs`, `task-ux-browser.cjs`, `availability-browser.cjs` and `visual-refinement-browser.cjs` assert expandable event details and other markup replaced earlier in this branch; they were not updated.
- `render-manage.php --check` fails on the equipment search (`eq-filter-status` described-by contract) both before and after these changes.
- At 320px with 200% text the Requests card content is still narrow (nested page, panel and card padding); the facts now stack in one column instead of two 53px ones.

## Booking Details action layout (`fix/booking-detail-action-layout`)

`/bookings/{id}` no longer hides its main actions in dropdowns.

- **Lab staff and admins, Pending booking:** two always-visible white panels (`.review-panels`, about 1/3 and 2/3 on desktop, stacked at 56rem and below). *Approve booking* is the filled primary action. *Reject booking* holds the required reason field, with an outlined destructive button. Both actions sit at the foot of their panels so they line up. The rejection error (bag `reject{id}`) renders directly under the field with `aria-invalid` / `aria-describedby`, and focus lands on the field.
- **Representative, cancellable booking:** one card (`#cancel.cancel-card`) with the deadline and a short explanation on the left, and the required reason plus the submit button on the right (stacked on narrow screens). The `#cancel` anchor used by "Cancel…" on My bookings still works. After the deadline only the existing explanation shows.
- Unchanged: routes, POST methods, `@csrf`, field names, validation messages and bags, role/department checks, the staff Move / Cancel reveal workflow for other statuses. No controller, model, policy or migration change. Nothing is ever sent by revealing, focusing or typing in a field; there is no confirmation step.
- The Reject / Cancel disclosure CSS (`details.reject`, `details.cancel-box`, `.review-actions`) was removed from `booking.css` and `style.css`.

Tests: `tests/Feature/BookingActionLayoutTest.php` (DB-gated, 13 tests), `UiPolishPresentationTest::test_booking_detail_actions_are_always_visible_panels_not_dropdowns`, and the real-browser script `tests/Support/booking-actions-browser.cjs` (31 checks; `RB_MUTATE=1` also performs one real reject, approve and cancel on a disposable database).

Known limits: `tests/Support/booking-browser.cjs` (fixture script) already failed at its equipment step on `origin/dev`; its cancel step was updated for the new markup but could not be reached. Text-only enlargement does not trigger the rem-based media queries (only page zoom does), but the layout was checked not to overflow either way.
