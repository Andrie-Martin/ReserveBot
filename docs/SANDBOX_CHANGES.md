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

## Issue 6: block accounts (the account belongs to the block, with a changeable representative)

- **Before:** a "Block rep" account was a person's account. When a new representative was elected, the block needed
  a new account, and the old bookings stayed with a person who no longer stood for the block.
- **Now:** the account is the **block's** ("Block account"). It goes by the block's name everywhere (BSCPE 4-5), and
  the block's **representative** is named on it.
  - **One account per block.** Adding a second account for a block is refused: "BSCPE 4-5 already has a block account
    (representative: Bea Prieto). Each block has one account: edit that one instead…". The check locks the block's row,
    so two admins saving at the same moment can't both give a block an account.
  - **The representative can be changed** by the department's admin (Accounts → Edit → "Representative's name") and
    by the block itself (My account → "Representative's name"). Same account, same login, same bookings.
  - **Bookings keep the representative they were made under.** A new column, `reservations.rep_name`, stores the
    representative's name when the booking is made, so changing the representative later doesn't rewrite older
    bookings. Lab staff see "BSCPE 4-5 · Rep: Bea Prieto" on requests, on Today and on the booking's page.
- **Kept as it was:** the role's key in the database and the code is still `rep`, and the pages are still at `/rep`,
  so no data has to be converted. `users.name` holds the representative's name for a block account. A block
  account's email must still be Gmail or PUP webmail, and it still books only after its email is connected.
- **Database:** new migration `2026_11_10_000021_add_rep_name_to_reservations` (adds `rep_name`, and fills it for
  the bookings that already exist). Run `php artisan migrate` (Docker does it when it starts).
- **Main files:** `app/Models/User.php` (`isBlock`, `displayName`, `representativeName`), `app/Models/Reservation.php`
  (`representativeName`, `bookedBy`), `app/Http/Controllers/AccountsController.php` (`oneAccountPerBlock`),
  `AccountController.php`, `app/Services/Bookings.php`, the account, accounts, home, requests, today and booking views.
  Tests: `tests/Feature/BlockAccountNamingTest.php` and `tests/Feature/BlockAccountWorkflowTest.php`.
- **Check it:**
  1. Log in as rep1: the top bar and the home page say "BSCPE 4-5", with "Representative: Bea Prieto".
  2. My account → change "Representative's name" → Save: "BSCPE 4-5's representative is now …".
  3. As admin, Accounts → "+ Add an account" → Block account → pick BSCPE 4-5: refused, it already has one.
  4. As lab staff, open a booking made before the change: it still shows the old representative.

## Issue 7: no ID number on profiles; the borrower's name and ID number on equipment bookings

- **Removed:** the "School ID number" box is gone from every profile: My account, the first-login page and the
  admin's Accounts pages (add, edit, and the list's column). The `users.school_id` column is dropped.
- **Added:** when a booking includes equipment ("Room and equipment" or "Equipment only", listed items or typed-in
  ones), the form asks **"Who will borrow the equipment?"**: the borrower's name and ID number, both required.
  A "Room only" booking asks for neither; the section hides with the equipment.
  - The ID number must look like a PUP student number, 2021-00123-MN-0 (the same rule the profile used). It is
    tidied as before: `202100123mn0` is saved as `2021-00123-MN-0`.
  - The borrower does not have to be the block's representative.
- **Where lab staff see it:** on Requests ("Borrower"), on the booking's page, and on the Today card next to the
  hand-out button ("Borrower: Juan Dela Cruz · ID 2021-00123-MN-0"), so the ID can be checked when the items are
  picked up. The hand-out message names the borrower too. A room-only booking shows "Room key only".
- **Database:** migration `2026_11_10_000022_move_id_number_from_accounts_to_equipment_bookings` adds
  `reservations.borrower_name` and `borrower_id_number`, with a CHECK (`chk_reservations_borrower`) that keeps the
  two together: both filled or both empty. Equipment bookings that already existed get their account's representative
  and School ID as the borrower before the old column is dropped, so nothing known is lost. Older equipment bookings
  with no ID on record show "Not recorded". Undoing the migration brings the column back empty.
- **Main files:** `app/Http/Controllers/BookingController.php` (the checks), `app/Services/Bookings.php`,
  `resources/views/bookings/create.blade.php`, `public/js/booking.js` (makes the two boxes required in the browser
  only while equipment is part of the booking), `public/css/booking.css`, the requests, today and booking views; the
  account controllers, views and `AccountFields` lose the School ID. Tests: `tests/Feature/BorrowerOnEquipmentBookingTest.php`.
- **Check it:**
  1. Log in as rep1 → Book → pick a time → "Equipment only" → pick an item → Send booking with the borrower boxes
     empty: the browser points at "Borrower's name".
  2. Type a name and `2021-123` as the ID: "An ID number looks like 2021-00123-MN-0."
  3. With a proper ID the booking is sent and its page shows "Borrower". As lab staff, see it on Requests and Today.
  4. Choose "Room only": the borrower boxes disappear and the booking goes through without them.
  5. My account and the admin's Accounts pages no longer show an ID number anywhere.

## Issue 8: the College admin (Option B of the multi-department discussion)

The discussion paper ("ReserveBot Multi-Department Admin Discussion", 9 Nov 2026) asks one question: **who creates
and supervises the departments' Admin accounts?** It offers Option A (department Admins only, the first one set up
by hand) and Option B (a separate college-wide role). The project owner chose **Option B**, with the narrowest
set of powers: the College admin looks after Admin accounts and nothing else.

- **New role: College admin.** It is college-wide and belongs to no department.
  - **Sees** every department on one page ("Departments"): how many Admins, lab staff, block accounts and rooms it
    has, how many requests are waiting, and a warning when a department has no Admin who can log in.
  - **Does**, for each department's Admin accounts: make one (with a starting password the person replaces at the
    first login), edit the name and email, turn it off and on, set a new password for someone locked out, and delete
    it (an Admin who has approved, rejected or changed bookings is turned off instead, so that history stays).
  - **Cannot** do anything inside a department: every department page answers "403 Forbidden" (Free rooms, Today,
    Requests, Manage, Accounts, booking pages). It cannot approve a booking, change a room, or make block or lab staff
    accounts; those stay with the department's own Admins. It cannot edit another College admin either.
- **Department Admins are unchanged**, except that their Accounts page now says "Admin accounts are made and looked
  after by the College admin, not here." They still cannot make Admins themselves.
- **The account.** A test account, like the others: **`college@reservebot.test`**, password `Password123!`.
  - New databases get it automatically (`migrate:fresh --seed`, or Docker's first start).
  - A database that already has data: `php artisan migrate`, then `php artisan db:seed --class=CollegeAdminSeeder`
    (Docker: `docker compose exec app php artisan db:seed --class=CollegeAdminSeeder`). Running it twice adds nothing.
  - A real College admin is made on the server, not on the website, because it is the top account:
    `php artisan college-admin:make "CEA Dean's Office" someone@gmail.com`. It asks for a starting password
    (typed twice, hidden) and the person picks their own at the first login.
- **Database:** migration `2026_11_10_000023_add_college_admin_role` adds `college_admin` to `users.role`, lets
  `users.department_id` be empty, and adds the CHECK `chk_users_department`: a College admin has no department
  and every other account has one.
- **Every change is logged** (`storage/logs/laravel.log`): which College admin did what to which Admin account.
  Never a password or an email address.

### How the paper's six worksheet questions are answered

| # | Question | On `sandbox` |
|---|---|---|
| 01 | Admin coverage: at least one Admin per department? several? | Several are allowed. The Departments page warns when a department has none who can log in. Nothing forces at least one. |
| 02 | Provisioning: who creates the first and later Admins? | The College admin, for every department. The College admin itself is made by a command on the server. |
| 03 | Account transfers between departments | **Not built.** An account stays in its department; its bookings stay with it. Still open for the team. |
| 04 | A college-wide role, or a manual process? | A college-wide role (this issue), limited to Admin accounts and a read-only overview. |
| 05 | Shared rooms and competing requests | **Unchanged.** Shared rooms are still checked against every department's timetable and bookings, and each department approves its own requests. The College admin has no say. Still open if the team wants a referee. |
| 06 | Recovery and auditing | Recovery: the College admin sets a new password or turns an account back on; a locked-out College admin is replaced with the server command. Auditing: the log lines above; there is no page for them yet. |

- **Main files:** `app/Http/Controllers/CollegeController.php`, `resources/views/college/` (three pages),
  `app/Console/Commands/MakeCollegeAdmin.php`, `database/seeders/CollegeAdminSeeder.php`, `routes/web.php`,
  `app/Models/User.php`, `resources/views/layouts/app.blade.php` (its one-item menu). Free rooms and the booking
  page are now limited to the three department roles. Tests: `tests/Feature/CollegeAdminTest.php` (database-backed)
  and `tests/Feature/CollegeAdminRoleTest.php`.
- **Check it:**
  1. Log in as `college@reservebot.test`: you land on "Departments" and the menu has only that.
  2. "Admin accounts" → "+ Add an admin" → make one, then log in as that person: they must pick a password, then
     they can open Accounts and the rest of their department.
  3. Back as the College admin, open `/today` or `/accounts`: "403".
  4. Edit an Admin → "Set a new password", or untick "Can log in": that Admin is logged out at their next click.

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

## Issue 10: two accounts logged in at the same time

- **What the tester saw:** with two accounts being logged in at the same time, only one was "entertained".
- **What is really happening:** a browser keeps **one** login per website, shared by all its tabs and windows. That
  is how every website's login works, so two accounts can never be logged in inside the same browser at once.
  When the login form was sent from a second tab, the site kept the first account and showed its home page without
  saying anything, so the second login looked ignored.
- **Two accounts at the same time already work** when each one has its own browser: a second browser (Chrome and
  Edge, say), a private (incognito) window, or another device. Checked with three accounts (block rep, lab staff,
  admin) logging in at the same moment and using the site together, five rounds, no failures.
- **The fix (the project owner chose "keep the first account and show a message"):** the first account stays logged
  in, and its home page now says, in a yellow box: "This browser is already logged in as BSCPE 4-5 (Block account), so
  the other login was not used. To switch accounts, log out first. To use two accounts at the same time, open the
  second one in a private window or in another browser."
- **Safe by design:** nothing typed in that second form is checked, so the reply is the same for real and made-up
  accounts, and it does not count towards the login lockout. Typing the email of the account that is already
  logged in just opens its home page, with no message.
- **Files:** `routes/web.php` (POST `/login` is no longer behind `guest`), `app/Http/Controllers/Auth/LoginController.php`
  (`alreadyLoggedIn`), new `resources/views/home/_login-notice.blade.php`, shown by `home.blade.php` and
  `home/_representative.blade.php`; tests in `tests/Feature/AlreadyLoggedInTest.php`.
- **Check it:** open the login page in two tabs. Log in as rep1 in the first tab, then as staff in the second. The
  second tab shows Bea's home page with the yellow message. Then open a private window and log in as staff there:
  both accounts now work side by side.

## Issue 11: The password reset link lasts 20 minutes

- **Before:** the link emailed by "Forgot your password?" worked for 60 minutes.
- **Now:** it works for 20 minutes, once. The message on the page and the email both say "20 minutes".
- **Not changed:** the "Connect my email" link in the welcome email is a different link and still works for 3 days
  (`User::EMAIL_LINK_DAYS`). Asking for another reset link still needs a 60-second wait.
- **Files:** `config/auth.php` (`passwords.users.expire`), `app/Http/Controllers/PasswordResetController.php`,
  `app/Notifications/ResetPasswordLink.php`; tests in `tests/Feature/ResetLinkLifetimeTest.php` and `AuthWorkflowTest`.
- **Check it:** on the login page click "Forgot your password?", send a link, and read the message: "It works for
  20 minutes." Open the link after more than 20 minutes: "This link has expired or was already used…".
