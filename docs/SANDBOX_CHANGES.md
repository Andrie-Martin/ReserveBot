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

### Login counter

- The login lockout keeps a short-lived count of login attempts "in flight" for each network address. Giving up a
  place used to be two steps (read the count, then lower it), so two attempts finishing at the same moment could act
  on the same reading. It is now one atomic step down; if that step ever takes the count below zero it is given
  straight back, so the count never stays negative.
- **Files:** `app/Http/Controllers/Auth/LoginController.php` (`leaveIp`), new `tests/Feature/LoginInFlightCounterTest.php`.
- **Check it:** `vendor/bin/phpunit --filter LoginInFlightCounterTest` passes. Logging in behaves the same as before.

### Wording

- The lab staff home page said "This is where you will review requests and run the day's room schedule.", which read
  like a placeholder. It now says "Review requests and run the day's room schedule."
- **File:** `app/Http/Controllers/StaffController.php`.

## Issue 11: The password reset link lasts 20 minutes

- **Before:** the link emailed by "Forgot your password?" worked for 60 minutes.
- **Now:** it works for 20 minutes, once. The message on the page and the email both say "20 minutes".
- **Not changed:** the "Connect my email" link in the welcome email is a different link and still works for 3 days
  (`User::EMAIL_LINK_DAYS`). Asking for another reset link still needs a 60-second wait.
- **Files:** `config/auth.php` (`passwords.users.expire`), `app/Http/Controllers/PasswordResetController.php`,
  `app/Notifications/ResetPasswordLink.php`; tests in `tests/Feature/ResetLinkLifetimeTest.php` and `AuthWorkflowTest`.
- **Check it:** on the login page click "Forgot your password?", send a link, and read the message: "It works for
  20 minutes." Open the link after more than 20 minutes: "This link has expired or was already used…".
