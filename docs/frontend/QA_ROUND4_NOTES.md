# Frontend QA round 4: notes

## Booking detail navigation (issues 12 and 13)

- `GET /bookings/{reservation}` (`bookings.show`) is the only `/bookings` URL. There is **no** general `GET /bookings` list and
  none was added: representatives use `/my-bookings` (`bookings.mine`), staff use Requests and Today. `/bookings` answers 404, and
  no navigation or page links to it (covered by `FrontendQaRound4Test`).
- Sidebar entry on a booking page: representatives keep **My bookings**. Staff and admins keep the entry they came through:
  `?from=requests` highlights **Requests**, `?from=today` highlights **Today**. Only those two values are honoured; anything
  else is ignored and the entry is chosen from the booking (waiting for approval: Requests; otherwise Today). The mascot takes its
  pose from the active entry, so it follows (clipboard for Requests, calendar for Today).

## Calendar selection (issues 9 to 11)

The selected date is the server's `?date=`. Each pickable day is one link, with the event count inside it, so hover, focus and
selection paint the whole cell. The event details panel shows that same date, so refresh, Back/Forward and direct links agree
without a script (`public/js/availability.js` was removed). Days outside the booking window or on closed days remain unselectable;
their events still show inside the cell, but not in the details panel.

## Pagination

Rooms: 12 per page, ordered by name then id. Equipment: 12 items per page in category order, grouped under their category
heading (a category continues on the next page); `?q=` searches item and category names on the server; categories with no items
are listed after the last page. Today's Classes: 15 per page (`classes_page`). Accounts: unchanged (20). The data model has no floor
column (rooms have a free-text `building` only), so there is no floor filter.

## Test commands

- PHPUnit incl. DB-gated suites: `tests/Support/run-db-tests.sh`
- Chromium: `tests/Support/qa-round4-browser.sh /path/to/node_modules/playwright-core [output-dir]`
