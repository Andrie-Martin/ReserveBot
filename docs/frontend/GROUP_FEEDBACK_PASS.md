# Groupmate feedback pass (13 items)

Branch `fix/frontend-group-feedback`, from `origin/dev` `8cacc0c` (PR #30 and #31 merged). Palette, tokens, typography, sidebar and
navigation are unchanged; every fix is in a shared component or pattern, not a one-off page patch.

Backend note: this is a presentation pass with **one** deliberate, user-requested backend addition, the guarded Delete for rooms and
equipment (item 5). Display-only data for the calendar arrows is the only other controller change.

## What changed, by item

| # | Item | Result |
|---|------|--------|
| 1 | Student-design disclaimer | Removed from the login page and its CSS. It existed nowhere else. Truthful PUP College of Engineering identity kept. |
| 2 | Profile photo actions | Replace/Upload and Remove share one grid row (same height/radius). Remove is the new `danger-outline` button inside its **own** DELETE form. Wraps to an equal-width stack below ~20rem. |
| 3 | Connected email | Compact `x-ui.status-note` row: icon, "Email connected", address and "since" date. |
| 4A | Today highlight | Today = filled maroon disc; selected = ring + gold base bar (different shapes, never colour alone). Both show when equal. Screen-reader text says "today"/"selected". Local date comes from the app timezone (`Asia/Manila`), not UTC. |
| 4B | Month arrows | See "Calendar navigation" below. |
| 5 | Delete rooms/equipment | See "Deletion" below. |
| 6 | Header descriptor | The floating "Room & equipment reservation system" is removed (it already repeated the login copy, and was hidden in the signed-in header at every width but tablet). Brand + masthead carry the identity. |
| 7 | Disconnected email | Same `x-ui.status-note` (warning tone): message and the existing resend button on one row; message above a full-width button on phones. Resend route, CSRF, rate limits and the booking gate are untouched. |
| 8 | My bookings (phone) | Stacked card per booking (date/time + status, purpose + summary, full-width cancel). Root cause: legacy `style.css` phone rules pinned the date to `grid-column: 1 / -1` and the side to column 2 while `booking.css` collapsed the grid to one column, producing a 0px date column whose text spilled over the title. Desktop unchanged. |
| 9 | Phone timetable | One card per room: state tag (text), bar on the same % scale, start/middle/end scale, free-time chips, Book + Schedule. No sideways scrolling. |
| 10 | Desktop timetable | One column definition (`--tt-*`) shared by the axis and rows; Book/Schedule are fixed-width cells whose buttons fill them; tick marks line up with the axis labels. "View schedule" is now **Schedule** (calendar icon; accessible name "View Schedule for CEA300, 3 entries"). |
| 11 | Drawer | "Scheduled (n)" and "Free times" sections split by one `--rb-border-warm` divider; an empty free list says "No free time …". |
| 12 | Today review | `Review request` is an anchor styled as the shared secondary button (also "Cancel or move" and "Details" on the same card). Navigation only. |
| 13 | Consistency | Audit below. |

## Shared styles/components introduced or reused

* **New** `x-ui.status-note` (`.rb-status`): compact non-critical status. Errors/destructive warnings stay on `x-ui.alert`.
* **New** button/link-button variant `danger-outline` (`.rb-button--danger-outline`), matching the existing `.btn-ghost--danger` look.
* **Reused** `x-ui.link-button` (Today, calendar), `x-ui.button`, `x-ui.field`, `x-ui.drawer`, `--rb-*` tokens, `.rb-card`.
* **New** `manage/_delete.blade.php`, the one confirmation section used by both edit pages; `App\Support\ResourceDeletion`, the one place that decides "can this be deleted".
* Removed as unused: `.rb-auth-disclaimer`, `.rb-header__description`, the old `.rb-account-email` padding, three duplicate timetable column definitions and the old sticky narrow-board rules.

### Audit (item 13)

* Status panels: only the two email panels were oversized. The other flash/notice messages (`.success`, `.notice`) are already compact legacy paragraphs and were left alone.
* Bare action links: the three on the Today card were converted. Requests/Home/Booking Details already use outlined buttons. Plain "Open" links inside data tables (manage edit pages) remain table links.
* `x-ui.alert` (login success, email-link result) is unchanged on purpose.

## Calendar navigation (4B)

The ‹ › controls are **month** arrows. Each goes to the *first pickable day* of the neighbouring month (`monthUrl()` in
`AvailabilityController`), so the visible month always follows the selected date. A month with no pickable day (already past, or beyond
`max_days_ahead`) has no target, so the arrow was a silent, `aria-hidden` dead control. That is why both looked broken: on Nov 9 with a
14-day window, October is past and December is beyond the window.

Now: a reachable arrow is a link named "Previous/Next month, <Month Year>". An unreachable one is a focusable `aria-disabled` button with
a visible hint ("You can look from today up to Nov 23") and an `aria-describedby` reason. The booking window, eligibility and query
handling are unchanged. `from`/`to` search filters are kept (`dayUrl()` already merged them). "Today" goes to the first pickable day
(today, or the next open day if the department is closed today).

## Deletion (5)

* Routes: `DELETE /manage/rooms/{room}`, `DELETE /manage/equipment/{equipment}` inside the existing `role:lab_staff,admin` group. No role
  changes. Another department's resource is a 404 (same `checkDepartment` as editing). Reps get 403; guests are sent to log in.
* Deletable only if nothing has ever referred to it:
  * **Room**: not shared with another department, no booking of **any** status, no timetable class (any semester).
  * **Equipment**: on no booking of any status. Categories are out of scope (no category deletion was added).
* Otherwise refused with plain reasons and pointed at the existing "untick Can be booked" workflow. No archive mechanism, migration or
  cascade was introduced; the restrict foreign keys remain a backstop (tested).
* Concurrency: the checks repeat inside a transaction with the row locked (`lockForUpdate`); an FK `QueryException` is caught and
  reported without changing anything.
* UI: the list shows a compact **Delete** beside Edit only where possible; it is a GET link to a named confirmation section on the edit
  page. That section holds the real `DELETE` form (CSRF, method spoofing) with a required "Yes, delete NAME" box, which the server also
  enforces. No JavaScript needed.
* Who may delete: **Lab Staff and Admins of the owning department both can, today.** That follows the existing `role:lab_staff,admin` group
  that already lets them add and edit rooms and equipment; it was not a new decision and no role was changed. Whether Lab Staff should
  keep it is open and **needs group approval**; until then the permission is deliberately unchanged. (To restrict it later, move the two
  `destroy` routes into an admin-only group; `tests/Feature/GroupFeedbackTest.php` currently asserts both roles can delete and will need updating with that decision.)
* An unreferenced room's timetable drafts cannot be stranded: the timetable replace step creates missing rooms by name.

## Deletion safety review (follow-up)

Checked against the real migrated schema, not just the migration files.

**Foreign keys that point at a room or an item (all `RESTRICT`; no cascade, no `SET NULL`):**

| Column | References | Why it matters |
|---|---|---|
| `reservations.room_id` (nullable) | `rooms` | booking history; `NULL` only for equipment-only bookings |
| `class_schedules.room_id` | `rooms` | timetable, every semester |
| `department_room.room_id` | `rooms` | which department lists a room; detached by the delete itself, in its transaction |
| `reservation_equipment.equipment_id` | `equipment` | equipment on bookings |

* Nothing else refers to a room or item: no other table has a `room_id`/`equipment_id`; `timetable_view` is a read-only view; timetable drafts
  and backups hold room **names** in JSON, and restoring one re-creates a missing room by name (`Timetables::ids()`).
* The only cascades near this are `reservation_equipment.reservation_id` (a booking's own item lines) and a department's list entries and
  timetable drafts. They fire only if a *booking* or *department* is deleted, and no code deletes either (a test scans for it).
* `tests/Feature/ResourceDeletionIntegrityTest.php` reads `information_schema` and fails if a key to rooms/equipment becomes `CASCADE`/`SET NULL`, a
  new reference appears, a `room_id`/`equipment_id` column has no key, or the tables aren't InnoDB.

**Concurrency** (real two-connection tests, `tests/Feature/ResourceDeletionConcurrencyTest.php`). `Bookings::place()` and the delete both take
`SELECT ... FOR UPDATE` on the room/item row, and an `INSERT` into `reservations`/`reservation_equipment` takes a shared lock on it, so they serialise:

* booking first: the delete waits; if it can't get the lock it gives up and changes nothing; once the booking commits the delete is refused;
* delete first: the booking waits; after the delete commits the foreign key refuses the booking row. No orphan is possible either way.

**Two corrections made in this review:**

1. `Bookings::place()` and `move()` ignored a room/item that no longer existed after locking it (it was impossible before Delete existed). A form
   checked just before a delete and saved just after hit a database error or a PHP warning (500). They now answer with the form's own
   "Pick a free room from the list." / "Pick equipment from the list.". Booking rules are untouched.
2. The delete's catch-all `QueryException` handler said "Something started using it" for **any** database error, including a lock timeout or a
   dropped connection. `ResourceDeletion::messageForFailure()` now tells them apart: foreign-key refusal (in use), busy (lock wait/deadlock,
   "try again"), anything else (generic "problem on our side", logged at error level). Nothing technical reaches the page, and nothing is changed in any case.

**Residual, not fixed (no data risk):** the timetable import resolves rooms by name before saving; if a *never-used* room is deleted in
that same instant the foreign key rejects the import transaction and the user sees the generic error page. Nothing is lost or orphaned,
and it needs a delete of an unused room during an import by the same department.

## Verification

* Baseline (unchanged `origin/dev`, gated suite): 258 tests, 253 passed, 5 skipped.
* After: 307 tests, 302 passed, 5 skipped (the same five), 0 failures. New tests in `tests/Feature/GroupFeedbackTest.php` (gated, disposable MariaDB),
  `tests/Feature/Frontend/GroupFeedbackPresentationTest.php` (source-level) and the deletion-safety suites
  `ResourceDeletionIntegrityTest.php` / `ResourceDeletionConcurrencyTest.php` (see the review above).
* `tests/Support/group-feedback-browser.cjs`: real Chromium at 1438×900, 390×844, 320×640, 200% text and 200% zoom (719 CSS px), as rep,
  lab staff and admin, against a disposable app and database. It also checks overlap, alignment, focus, keyboard and the drawer.
* Not covered by older scripts: `tests/Support/timeline-browser.cjs` and `visual-refinement-browser.cjs` assert that a narrow board
  scrolls sideways; item 9 replaces that with cards, so those expectations are superseded for narrow widths.

## Known limitations

* Next-month arrow enabled state is covered by PHPUnit (time-travelled), not by the browser run, because the seeded window never crosses
  a month boundary.
* "Delete" links to the edit page rather than confirming in place; chosen so nothing can be deleted by a mis-click or without script.
* Photo upload cannot be resized in the disposable environment (no GD); the code falls back to storing the file as uploaded.
