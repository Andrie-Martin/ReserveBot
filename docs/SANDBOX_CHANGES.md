# Changes on the `sandbox` branch

`sandbox` starts from `dev` at `897d478` (PR #32). Each issue below was fixed in its own commit and pushed on its own,
so the commit history is the record. This file says, in plain words, what changed and how to check it.
`sandbox` is not merged into `main` or `dev` yet.

The issue numbers are the ones from the project owner's list of 2026-11-10. Issues 3 (keep Delete for rooms and
equipment) and 4 (do not merge into `main` yet) needed no code change.

## Issue 1: Login lockout and the visitor's real address (ready, switched off)

- **The risk:** once the site is opened through a tunnel such as ngrok, every visitor reaches Laravel from the
  tunnel's own address. The login lockout counts wrong passwords per address, so 20 wrong passwords from anyone would
  lock everyone out for a minute, and 5 wrong guesses at one person's email would lock that person out.
- **What was added:** a `TRUSTED_PROXIES` setting in `.env`. When it holds the tunnel's address, Laravel reads each
  visitor's real address from the tunnel, so every visitor is counted on their own again.
- **It is off by default.** With the setting empty (as on your PC today) nothing changes at all. The right address
  can only be found on the PC that runs the tunnel, so it is filled in when the site goes online: README, "Going online".
- **`*` is refused.** Only exact addresses and ranges are accepted, so nobody can switch on "trust everyone" by habit.
- **Files:** new `config/trustedproxy.php` and `app/Support/TrustedProxies.php`; `.env.example`, `docker-compose.yml`
  (passes the setting to the container), `README.md`; tests in `tests/Unit/TrustedProxiesTest.php` and
  `tests/Feature/TrustedProxySettingTest.php`.
- **Check it:** `vendor/bin/phpunit --filter TrustedProx` passes. Nothing to see on the site until it is online.

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

## Issue 9 (QA AUTH-01): after a wrong password, pressing Log in again logged in

- **What the tester saw:** a wrong password showed "Wrong email or password.", but pressing Log in again let them
  in. It only happened in a normal browser window, not in a private (incognito) one.
- **The cause:** the site never accepted the wrong password. The browser had the account's real password saved, and
  after the error it quietly typed the saved password back into the box. Pressing Log in again then sent the saved,
  correct password. A private window has no saved passwords, so it behaved.
- **Reproduced:** in a real Chromium 153 with rep1's password saved, on `dev` as it was: the box was refilled after
  the error and the second press landed on `/rep`.
- **The fix:** after a refused attempt the password box is marked `autocomplete="new-password"`, the standard way to
  tell browsers not to fill a saved password in. The box stays empty, so pressing Log in again does nothing until a
  password is typed. A first visit to the page is unchanged, so saved logins still fill in as usual.
- **Checked with the fix, same browser:** the box stays empty after the error and the second press stays on `/login`.
- **Files:** `resources/views/auth/login.blade.php`; tests in `tests/Feature/Frontend/LoginRetryPresentationTest.php`
  and `AuthWorkflowTest` (the same wrong password is refused three times in a row).
- **Check it:** in Chrome or Edge with the password saved, type a wrong password and press Log in. The password box
  is empty and pressing Log in again shows "Please fill out this field".

## Issue 11: The password reset link lasts 20 minutes

- **Before:** the link emailed by "Forgot your password?" worked for 60 minutes.
- **Now:** it works for 20 minutes, once. The message on the page and the email both say "20 minutes".
- **Not changed:** the "Connect my email" link in the welcome email is a different link and still works for 3 days
  (`User::EMAIL_LINK_DAYS`). Asking for another reset link still needs a 60-second wait.
- **Files:** `config/auth.php` (`passwords.users.expire`), `app/Http/Controllers/PasswordResetController.php`,
  `app/Notifications/ResetPasswordLink.php`; tests in `tests/Feature/ResetLinkLifetimeTest.php` and `AuthWorkflowTest`.
- **Check it:** on the login page click "Forgot your password?", send a link, and read the message: "It works for
  20 minutes." Open the link after more than 20 minutes: "This link has expired or was already used…".
