# Changes on the `sandbox` branch

`sandbox` starts from `dev` at `897d478` (PR #32). Each issue below was fixed in its own commit and pushed on its own,
so the commit history is the record. This file says, in plain words, what changed and how to check it.
`sandbox` is not merged into `main` or `dev` yet.

The issue numbers are the ones from the project owner's list of 2026-11-10. Issues 3 (keep Delete for rooms and
equipment) and 4 (do not merge into `main` yet) needed no code change.

## Issue 2: Hand out and No-show work on any day again

- **Before (on `dev`):** for a booking on a future day the two buttons were hidden, the page said
  "Hand out and no-show open on Monday, November 16.", and the server refused the request.
- **Now:** the buttons always show for an approved booking, on any day, as they do on `main`. Lab staff and
  admins can step in by hand whenever something goes wrong.
- **Kept:** only an approved booking can be handed out or marked a no-show; only lab staff and admins of the
  booking's own department can do it; the booking row is still locked while its status changes (PR #26), so two
  people acting at the same moment can't both change it.
- **Files:** `app/Support/StaffRules.php`, `app/Http/Controllers/TodayController.php`,
  `resources/views/bookings/_staff_actions.blade.php`, `resources/views/today/index.blade.php`.
- **Check it:** as lab staff, open a booking that is approved for next week. "Hand out key and items" and
  "No-show" are there and work. The Today page says "The buttons work at any time, on any day."

## Issue 5: Tidy-up

### Code style

- Ran Laravel Pint (`vendor/bin/pint`) on the whole project. It reformatted the 36 files it flagged: 2 in `app/`
  (`AccountsController.php`, `Auth/LoginController.php`) and 34 under `tests/`. Formatting only: no behaviour changed,
  and all 308 tests pass before and after.
- **Check it:** `vendor/bin/pint --test` reports `passed`.
