# ReserveBot frontend screen and action inventory — Phase 00

## Evidence, notation, and phase ownership

Source baseline: **2026-11-03**, commit **`e07be8f939ae6b1932150483d3770be3af2c64d9`**, branch **`docs/frontend-00-audit`**. Status: **VERIFIED from source**, except explicit UNVERIFIED/KNOWN ISSUE notes. No frontend implementation or browser acceptance is claimed. See [SYSTEM_MAP.md](../architecture/SYSTEM_MAP.md) for architecture, evidence limits, and issue IDs; [PHASE_PLAN.md](PHASE_PLAN.md) for gates.

**Figma coverage is UNVERIFIED for every screen and state below.** The connector denied access during the audit. The design URL in [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) is a visual reference, not proof that any screen is represented or implemented. No node/frame mapping is asserted.

Notation:

- **R** = `rep`; **S** = `lab_staff`; **A** = `admin`. “Staff” below means S/A.
- **Main gate** = `auth`, `auth.session`, `account`; disabled accounts are logged out and mandatory-password accounts are redirected to welcome. Unless marked guest/signed/error, screens use this gate plus their role/record checks.
- View names resolve under `resources/views`: e.g. `account.info` = `resources/views/account/info.blade.php`. Controller names resolve under `app/Http/Controllers`, including the `Auth/` subdirectory where specified.
- P01 foundations; P02 shell; P03 auth/account/errors; P04 rep dashboard/availability; P05 bookings; P06 staff/class checks; P07 resources/accounts; P08 timetable; P09 final QA. A secondary phase owns only the stated integration, not duplicate screen markup.
- Page GET routes also accept HEAD. Route names and methods come from [routes/web.php](../../routes/web.php); source controller links identify validation/data providers. Errors use normal HTML redirects with session errors/old input unless specified otherwise. JSON negotiation can change error representation; no SPA contract is introduced.

## Screens: route, template, controller, authorization

Each screen ID joins to its inputs, states, dependencies, and errors in the next section. Action-only routes are enumerated afterward, including uploads/downloads and redirects; they are not invented standalone screens.

| ID / screen | GET route name and URL | Blade template | Controller method / source | Authorized audience | Phase |
|---|---|---|---|---|---|
| S01 Login | `login` — `/login` | `auth.login` | [Auth/LoginController::show](../../app/Http/Controllers/Auth/LoginController.php#L13) | Guest; authenticated users redirect to role home | P03 |
| S02 Forgot password | `password.request` — `/forgot-password` | `auth.forgot` | [PasswordResetController::request](../../app/Http/Controllers/PasswordResetController.php#L22) | Guest | P03 |
| S03 Reset password | `password.reset` — `/reset-password/{token}` | `auth.reset` | [PasswordResetController::edit](../../app/Http/Controllers/PasswordResetController.php#L44) | Guest; token validated on submit | P03 |
| S04 Mandatory onboarding | `welcome` — `/welcome` | `account.welcome` | [PasswordController::welcome](../../app/Http/Controllers/PasswordController.php#L22) | R/S/A with `must_change_password`; otherwise home redirect | P03 |
| S05 My account information/photo/email | `account` — `/account` | `account.info` | [AccountController::edit](../../app/Http/Controllers/AccountController.php#L21) | R/S/A, own account | P03 |
| S06 My password | `password.edit` — `/account/password` | `account.password` | [PasswordController::edit](../../app/Http/Controllers/PasswordController.php#L67) | R/S/A, own account | P03 |
| S07 Email connection result / invalid signed link | `email.connect` — `/connect-email/{user}/{hash}`; also signature exception rendering | `auth.email_link` | [EmailLinkController::connect](../../app/Http/Controllers/EmailLinkController.php#L18); [bootstrap exception handler](../../bootstrap/app.php#L44) | Signed URL, login not required; invalid-signature error available to requester | P03; P06 class-link integration |
| S08 Representative dashboard | `rep.home` — `/rep` | `home` | [RepController::home](../../app/Http/Controllers/RepController.php#L12) | R | P04; class-answer controls P06 |
| S09 Staff dashboard | `staff.home` — `/staff` | `home` | [StaffController::home](../../app/Http/Controllers/StaffController.php#L11) | S/A | P06 |
| S10 Admin dashboard | `admin.home` — `/admin` | `home` | [AdminController::home](../../app/Http/Controllers/AdminController.php#L11) | A | P06 |
| S11 Free rooms/calendar/day search | `rooms` — `/rooms` | `rooms.index` | [AvailabilityController::index](../../app/Http/Controllers/AvailabilityController.php#L29) | R/S/A; book links only for R, creation still email-gated | P04 |
| S12 Booking stages 1 and 2 | `book` — `/book` | `bookings.create` | [BookingController::create](../../app/Http/Controllers/BookingController.php#L33) | R plus `email.connected` | P05 |
| S13 My bookings | `bookings.mine` — `/my-bookings` | `bookings.mine` | [BookingController::mine](../../app/Http/Controllers/BookingController.php#L201) | R, own records | P05 |
| S14 Booking details / actions / history | `bookings.show` — `/bookings/{reservation}` | `bookings.show` | [BookingController::show](../../app/Http/Controllers/BookingController.php#L169) | Owner, or S/A in owner's department; other records 404 | P05; staff action integration P06 |
| S15 Approval requests | `requests` — `/requests` | `requests.index` | [ReviewController::index](../../app/Http/Controllers/ReviewController.php#L21) | S/A, own department | P06 |
| S16 Today's schedule / handover / classes | `today` — `/today` | `today.index` | [TodayController::index](../../app/Http/Controllers/TodayController.php#L31) | S/A, own department | P06 |
| S17 Signed class-check page | `class-check` — `/class-check/{class}/{date}/{user}` (also POST) | `classes.check` | [ClassCheckController::email](../../app/Http/Controllers/ClassCheckController.php#L29) | Signed URL; login not required. Saving requires identified active R still in the class section | P06 |
| S18 Rooms list/add | `manage.rooms` — `/manage/rooms` | `manage.rooms` | [ManageRoomsController::index](../../app/Http/Controllers/ManageRoomsController.php#L29) | S/A, department-linked rooms | P07 |
| S19 Room edit | `manage.rooms.edit` — `/manage/rooms/{room}` | `manage.room_edit` | [ManageRoomsController::edit](../../app/Http/Controllers/ManageRoomsController.php#L74) | S/A with room on department list; otherwise 404 | P07 |
| S20 Equipment/categories list/add/rename | `manage.equipment` — `/manage/equipment` | `manage.equipment` | [ManageEquipmentController::index](../../app/Http/Controllers/ManageEquipmentController.php#L32) | S/A, department categories | P07 |
| S21 Equipment edit | `manage.equipment.edit` — `/manage/equipment/{equipment}` | `manage.equipment_edit` | [ManageEquipmentController::edit](../../app/Http/Controllers/ManageEquipmentController.php#L91) | S/A in category's department; otherwise 404 | P07 |
| S22 Booking rules and semester dates | `manage.rules` — `/manage/rules` | `manage.rules` | [ManageRulesController::edit](../../app/Http/Controllers/ManageRulesController.php#L22) | S/A; config is department-scoped, terms are shared | P07 |
| S23 Accounts list/create | `accounts.index` — `/accounts` | `accounts.index` | [AccountsController::index](../../app/Http/Controllers/AccountsController.php#L38) | A; own-department accounts, including read-only admins | P07 |
| S24 Account edit/password/disable/delete | `accounts.edit` — `/accounts/{account}` | `accounts.edit` | [AccountsController::edit](../../app/Http/Controllers/AccountsController.php#L88) | A; target must be same-department R/S, otherwise 404 | P07 |
| S25 Timetable overview/upload/backups | `manage.timetable` — `/manage/timetable` | `manage.timetable` | [ManageTimetableController::index](../../app/Http/Controllers/ManageTimetableController.php#L49) | S/A, own department | P08 |
| S26 Timetable in use, day/week views | `manage.timetable.view` — `/manage/timetable/view` | `manage.timetable_rows` | [ManageTimetableController::view](../../app/Http/Controllers/ManageTimetableController.php#L75) | S/A, own department; read-only rows | P08 |
| S27 Timetable draft, day/week/fix/add views | `manage.timetable.draft` — `/manage/timetable/draft` | `manage.timetable_rows` | [ManageTimetableController::draft](../../app/Http/Controllers/ManageTimetableController.php#L87) | S/A sharing department draft | P08 |
| S28 Timetable row edit/delete | `manage.timetable.rows.edit` — `/manage/timetable/draft/rows/{row}` | `manage.timetable_row` | [ManageTimetableController::editRow](../../app/Http/Controllers/ManageTimetableController.php#L273) | S/A sharing department draft; numeric row ID | P08 |
| S29 Timetable check/semester/replace | `manage.timetable.check` — `/manage/timetable/check` | `manage.timetable_check` | [ManageTimetableController::check](../../app/Http/Controllers/ManageTimetableController.php#L217) | S/A sharing department draft | P08 |

## Inputs, validation, dependencies, and important states

All fields below are current names, not suggested replacements. Every mutation form retains CSRF; PUT/DELETE use method spoofing in HTML forms. See the action ledger for exact methods. All screens also need P09 verification; no current browser pass is claimed.

| Screen IDs | Important inputs / query values | Validation, errors, and states | Backend dependencies |
|---|---|---|---|
| S01 | `email`, `password` | Generic `email` error; only email old input restored; active-account check; success session regeneration/home redirect; recovery link; no registration | Auth, session, `User`, `Auth/LoginController::login` |
| S02 | `email` | Required valid email; generic success regardless of account existence; active-account lookup; mail failure error; throttle 429; `status` flash | Password broker, `AccountRules`, `ResetPasswordLink`, configured mail |
| S03 | URL `token`, query/form `email`, hidden `token`, `password`, `password_confirmation` | 8–64 letters/digits password, confirmation; invalid/used/expired token under `email`; password never repopulated; success to login | Password broker, `AccountFields`, `User`; resets mandatory-change and connects email |
| S04 | `password`, `password_confirmation`; conditional `school_id` | Default error bag; password must differ from admin-set one; missing school ID requested for R/S, required for R; no main menu while onboarding | `AccountFields`, `Hash`, `User`, `AccountChecks` |
| S05 | `name`, `email`, `current_password`, conditional `school_id`/`section_id`; multipart `photo`; remove/resend buttons | Bags `info`, `photo`; rep block not self-editable; school ID required for R; S section optional; email-change password check; email unique/domain rules with legacy keep exception; photo type/size/dimensions; `status`, `problem`, `connect_note`; connected/unconnected/missing-photo states | `User`, `Section`, `AccountFields`, `Photos`, `Photo`, private storage, connection mail |
| S06 | `current_password`, `password`, `password_confirmation` | Default errors; current password verification, different new password, confirmation; `status`; other sessions invalidated | `AccountFields`, `User`, `auth.session` |
| S07 | URL user/hash plus signature/expiry query; no connection form | GET connects email; unchanged/already-connected success; changed-email hash mismatch displays failure with ordinary view status; invalid/expired signature returns 403. Preserve `ok/message`, login/account link choice | `User::connectEmailUrl`, signed middleware, `EmailLinkController`, bootstrap exception handler |
| S08 | Inline class button `answer=f2f/online`, route class/date | Weekly class table/no-classes; upcoming meeting states `waiting/f2f/online/freed/booked`; disabled current answer; no answer controls when booked/ended; `status/problem`; active term vs next-seven-days data are different lists | `ClassSchedule`, term/course/room/section relations, `ClassChecks`, `classes.answer` |
| S09 | No page form | Confirmed/InUse reservations starting today count; role links; explicit paused Messenger future text; no fabricated analytics | `Reservation`, user department; shared `home` data contract |
| S10 | No page form | Pending count and links, including Accounts; separate data from staff dashboard; zero-count state | `Reservation`, user department; shared `home` |
| S11 | GET `date`, `from`, `to`; links preserve valid filters and `#day` | Invalid/out-of-window date fallback; closed/outside-term day; bad time selection; zero matches; started interval warning; empty agenda/room list; class/booked/freed distinctions and restricted details | `RoomAvailability`, `ClassChecks`, `TimeSlots`, department config, rooms/classes/terms/reservations |
| S12 | GET `for`, `session`, `date`, `from`, `to`, `room`, `change`; POST hidden when params, `need`, `room`, `qty[id]`, `other_items`, `purpose` | Stage 1 `whenError`; empty sessions; stage 2 default errors/old values; unavailable room/stock; mandatory reason max 150, other items max 200; needs `both/room/equipment`; stale session can return to stage 1; conflict errors after transaction checks | `BookingRules`, `Bookings`, `RoomAvailability`, classes/resources/config, `Reservation` and pivot |
| S13 | No filter/pagination form | Upcoming blocking bookings not ended; past/closed list limited to 30; empty lists; cancel eligibility/deadline links; preserve status labels | Own reservations with room/equipment/class course, `BookingRules` |
| S14 | Rep cancel `reason`; reject `reason`; return `note`; move `date/from/to/room/reason`; staff cancel `reason` | Owner/department 404; status-dependent review/handout/return/move/cancel; default rep-cancel error, `reject{id}`, `return{id}`, `move`, `cancel` bags; deadline, late/overdue, stale action, history/no-history; reasons max 255; return note optional | `BookingController`, `ReviewController`, `TodayController`, `Bookings`, `StaffDesk`, rules, reviewer/equipment/history relations |
| S15 | Approve button; reject `reason` | Pending queue and latest 10 reviewed Confirmed/Rejected records; reject bag `reject{id}`, max 255 required; per-record old input; empty queue/history; `status/problem` including concurrent decision | `ReviewController`, department reservations, reviewer/user/section/class/room/equipment |
| S16 | GET `date`; return `note`; handout/no-show; class `answer` | Date range today−7 through max-days-ahead, invalid selection falls back today; groups InUse/Confirmed/Pending/closed; late/overdue; `return{id}`; empty groups; `status/problem`; no-show confirm; class ended/booked controls | `TodayController`, `StaffRules`, `StaffDesk`, `ClassChecks`, reservations/config |
| S17 | Signed URL including class/date/user/answer/expiry/signature; POST button `answer` to `request()->fullUrl()` | GET does not answer; valid rep/open, current choice, booked, expired, ended, or changed-rep states. Invalid signature 403 via S07; invalid meeting 404; invalid POST answer 422; service refusal `problem`, success `status` | `ClassCheckController`, `ClassChecks`, room lock, class/term/check/reservation/user |
| S18 | `name`, `building`, `capacity`, `note` | Bag `add`; name required max 30; building max 60; seats nullable 0–1000; note max 200. Existing global room attaches; already-linked rejected. Active/inactive/shared counts and empty list | Department rooms pivot, shared `Room` |
| S19 | Same fields plus `is_active` | Default errors; unique name excluding current; sharing warning; upcoming blocking bookings kept after deactivation; success flash in manage tabs | Shared room/departments, bookings/user sections; KNOWN ISSUE K3 for class-session deactivation |
| S20 | Category `name`; item `category_id`, `name`, `total_qty`, `max_per_request` | Bags `category`, `category{id}`, `item`; department category checks, name uniqueness per department/category; stock 0–9999, per-request 1–99; empty categories/items; rename/add disclosures reopen on error | `EquipmentCategory`, `Equipment`, department |
| S21 | Item fields plus `is_active` | Default errors; upcoming commitments and stock shortages remain visible; reducing stock allowed with warnings, not automatic cancellations | Equipment/category, reservations/pivot quantities, `StockCheck` |
| S22 | `requires_approval`, `open_days[]`, `business_hours_start/end`, `slot_minutes`, `min_booking_minutes`, `max_booking_minutes`, `max_days_ahead`, `min_notice_minutes`, `cancel_cutoff_minutes`, `no_show_grace_minutes`; terms `start_date/end_date` | Bags `rules`, `term{id}`; day required, step 15/30/60; duration 15–720, horizon 1–90, notice/cancel 0–2880, grace 0–240; closing after opening, min/max/step consistency; term end after start; shared-term warning. K2 wording discrepancy | `DepartmentConfig`, shared `Term`, `ManageRulesController` |
| S23 | `role`, `name`, `email`, `block_id`, `section_id`, `school_id`, starting `password` | Bag `add`; managed roles only; rep block required from department timetable, school ID optional at admin creation; field rules; email availability notice; empty role groups; send failure can coexist with account-created success | `AccountsController`, `AccountFields`, `User`, `ClassSchedule`, `Section`, `EmailSetup`, `ConnectEmail` |
| S24 | Same details minus creation password, plus `is_active`; separate new `password`; DELETE confirmation | Bags `details`, `password`; target 404 guard; editable rep/lab_staff only; active/inactive; connected/unconnected; upcoming/history warnings; delete may disable; new password forces onboarding; details old values and checkbox semantics | User/history/reservations, private photos, reset-token cleanup, connection notification |
| S25 | Multipart `file`; start/discard/restore buttons; download `format=csv` | Bag `upload`; 5 MB; XLSX/CSV/TXT accepted server-side, form offers XLSX/CSV; unsupported/invalid file errors; no live timetable/draft/backups; existing draft overwrite warning; current booking clashes; ZIP-unavailable CSV notice; `status/problem` | `ManageTimetableController`, `Timetables`, `Spreadsheet`, `TimetableFile`, drafts/backups/classes |
| S26 | GET `day`, `week=room:<name>` or `block:<name>`; download format | Live day list/week grid, overlap lanes, no rows; no editing controls; preserve room/block labels and week selection | `Timetables::classes/rows`, `TimetableRules`, config, suggestions/grid arrays |
| S27 | Same filters plus `day=fix`; row fields below; DELETE `back`; undo encrypted `row` and `back` | Bag `row`; invalid rows remain visible; warnings separate from errors; missing draft redirects to overview; delete produces `undo` flash; concurrent changes can remove row/draft; shared draft attribution | Draft JSON/version and transactions, `TimetableRules`, encrypted undo, datalists |
| S28 | `day/start/end/room/type/code/title/block/instructor/note`; delete `back`; edit query `back=fix` | Bag `row`; days enumerated; start/end parsed, end after start; Class requires code; Blocked hides class fields; field length limits; missing row redirects with message; numeric route constraint | `Timetables::editDraft`, `TimetableRules`, draft row IDs, suggestions |
| S29 | `term_name`, `term_start`, `term_end`, hidden `version`; semester button alternate `formaction` | Bag `semester`; required name <=60, valid dates/end after start; missing draft redirect; blocking problems/empty disables Replace; warnings do not; stale version `problem`; compare/additions/orphan reps/clashes/retained sessions; immediate effect/shared-term warning | `Timetables::check/apply/setSemester`, drafts/backups, classes/checks/reminders, reservations, shared term/resources |

Timetable row lengths: room 30, code 20, title 100, block 30, instructor 60, note 150. Inputs come from [the shared row form](../../resources/views/manage/_timetable_row_form.blade.php) and [rowInput](../../app/Http/Controllers/ManageTimetableController.php#L502). Preserve parsing flexibility; the browser time control is not the entire server contract.

## Error and email surfaces

These are user-facing templates without dedicated page routes. “All” means any requester who reaches the condition; it does not bypass protected underlying routes. Figma coverage for these surfaces is also UNVERIFIED.

| ID / surface | Route / trigger and controller | Template | Inputs, errors, dependencies, audience | Phase |
|---|---|---|---|---|---|
| E403 Not allowed | No named route; role middleware/controller abort | `errors.403` → `errors.message` | No form; heading/text/home link; all denied requesters; framework exception handling, shared layout. Invalid signatures instead use S07 | P03, P09 |
| E404 Not found | No named route; fallback, missing binding, ownership/department checks | `errors.404` → `errors.message` | No form; all requesters; inaccessible vs absent resource intentionally not distinguished | P03, P09 |
| E413 Upload too large | No named route; request-size exception before controller | `errors.413` → `errors.message` | No form; `hideUser=true`; all requesters. Existing wording mentions photos although timetable uploads also exist; wording review needed in P08 | P03; P08 integration; P09 |
| E419 Expired page | No named route; CSRF/session failure | `errors.419` → `errors.message` | No form; refresh/retry guidance; all requesters; preserve safe navigation | P03, P09 |
| E429 Too many attempts | No named route; route throttle | `errors.429` → `errors.message` | No form; wait/retry; all requesters. Resend's own limiter instead uses `connect_note` | P03, P09 |
| E500 Server failure | No named route; framework exception, custom template when debug disabled | `errors.500` → `errors.message` | No form; `hideUser=true`, avoid database-dependent menu/counts; all requesters. Debug was enabled at audit; rendered production error UNVERIFIED | P03, P09 |
| M01 Class reminder email | No web route; `SendReminders` → `ClassReminder::toMail` | `mail.class_reminder` | Signed f2f/online links; ask/remind/freed variants; rep name, heading, intro, buttons, outro; active connected block reps | P06 contract/visual review; P09 delivery checks |
| M02 Connection/reset/booking emails | No page route; `ConnectEmail`, `ResetPasswordLink`, `BookingReminder` | Laravel MailMessage templates, no application-specific Blade for these three | Connection link, reset token/email link, protected booking-detail link; mail channel. No new custom mail template implied by this inventory | P03 account links; P06 reminder links; P09 |

Evidence: [error views](../../resources/views/errors), [bootstrap exceptions](../../bootstrap/app.php#L38), [notifications](../../app/Notifications), [mail view](../../resources/views/mail/class_reminder.blade.php). HTTP 422 from an invalid signed class answer has no custom 422 Blade file in this checkout. HTTP method errors likewise remain framework responses, not undocumented custom screens.

## Action and redirect ledger

This ledger complements the GET screen table. It covers named actions in `routes/web.php` rather than pretending every action is a separate page. Role gates and input/error contracts are inherited from the screen rows unless stated otherwise.

### Authentication and shared account actions

| Method / route name | Controller method | Screen / behavior |
|---|---|---|
| POST `/login` — **unnamed** | `Auth/LoginController::login` | S01; form uses the URL generated by GET route `login`, not a nonexistent `login.store` |
| POST `logout` | `Auth/LoginController::logout` | Auth only, outside main gate; shared shell |
| GET `home` | Closure → `User::homeUrl()` | Main gate, redirect to role home |
| PUT `welcome.save` | `PasswordController::saveWelcome` | S04 |
| PUT `account.update` | `AccountController::update` | S05 |
| POST `account.photo`; DELETE `account.photo.remove` | `AccountController::photo/removePhoto` | S05; upload and removal are separate forms |
| GET `photos.show` | `PhotoController::show` | Binary private photo; owner or same-department non-rep; no screen |
| POST `email.resend` | `EmailLinkController::resend` | S05/shared connect bar; `connect_note` |
| PUT `password.change` | `PasswordController::update` | S06 |
| POST `password.email`; POST `password.update` | `PasswordResetController::email/update` | S02/S03; guest and throttled |

### Booking, staff, and class actions

| Method / route name | Controller method | Screen / behavior |
|---|---|---|
| POST `book.store` | `BookingController::store` | S12; R plus connected email |
| POST `bookings.cancel` | `BookingController::cancel` | S14; R owner |
| POST `bookings.approve`; POST `bookings.reject` | `ReviewController::approve/reject` | S14/S15; S/A department check |
| POST `bookings.hand-out`; POST `bookings.returned`; POST `bookings.no-show` | `TodayController::handOut/returned/noShow` | S14/S16; S/A department and status checks |
| POST `bookings.staff-cancel`; POST `bookings.move` | `TodayController::cancel/move` | S14; S/A department check; separate from rep cancellation |
| POST `classes.answer` | `ClassCheckController::asRep` | S08; R section check |
| POST `classes.mark` | `ClassCheckController::asStaff` | S16; S/A class department check |
| POST `class-check` | `ClassCheckController::email` | S17; signed URL and CSRF, no login requirement |

### Resource and account administration actions

| Method / route name | Controller method | Screen / behavior |
|---|---|---|
| GET `manage.index` | Closure redirect to `manage.rooms` | S/A main gate |
| POST `manage.rooms.store`; PUT `manage.rooms.update` | `ManageRoomsController::store/update` | S18/S19 |
| POST `manage.equipment.store`; PUT `manage.equipment.update` | `ManageEquipmentController::store/update` | S20/S21 |
| POST `manage.categories.store`; PUT `manage.categories.update` | `ManageEquipmentController::storeCategory/updateCategory` | S20 |
| PUT `manage.rules.update`; PUT `manage.terms.update` | `ManageRulesController::update/updateTerm` | S22; term records are shared |
| POST `accounts.store`; PUT `accounts.update` | `AccountsController::store/update` | S23/S24; A only |
| PUT `accounts.password`; DELETE `accounts.destroy` | `AccountsController::password/destroy` | S24; password reset or delete/disable, A only |

### Timetable actions and file responses — all P08, S/A main gate

| Method / route name | Controller method | Screen / behavior |
|---|---|---|
| POST `manage.timetable.upload` | `ManageTimetableController::upload` | S25 → S29; replaces department draft after parsing |
| POST `manage.timetable.start` | `ManageTimetableController::start` | S25 → S27; existing draft reused |
| DELETE `manage.timetable.discard` | `ManageTimetableController::discard` | S25; removes draft only |
| GET `manage.timetable.download` | `ManageTimetableController::download` | S25/S26; live XLSX or CSV attachment |
| GET `manage.timetable.draft.download` | `ManageTimetableController::downloadDraft` | S25/S27/S29; missing draft redirects |
| GET `manage.timetable.backups.download` | `ManageTimetableController::downloadBackup` | S25; same-department backup check, otherwise 404 |
| POST `manage.timetable.backups.restore` | `ManageTimetableController::restore` | S25 → S29; same-department backup; creates/replaces draft, not immediate live restore |
| POST `manage.timetable.rows.store` | `ManageTimetableController::addRow` | S27; adds draft row |
| PUT `manage.timetable.rows.update` | `ManageTimetableController::updateRow` | S28; missing row message |
| DELETE `manage.timetable.rows.destroy` | `ManageTimetableController::deleteRow` | S27/S28; encrypted undo flash |
| POST `manage.timetable.rows.undo` | `ManageTimetableController::undo` | S27; invalid encrypted row 404 |
| POST `manage.timetable.semester` | `ManageTimetableController::semester` | S29; alternate button saves draft semester and checks again |
| POST `manage.timetable.apply` | `ManageTimetableController::apply` | S29; version/recheck/backup/live replacement |

Operational endpoints `/test-db`, `/webhook`, `/up`, generated `storage.local`/`storage.local.upload`, and unnamed fallback are accounted for in [SYSTEM_MAP.md](../architecture/SYSTEM_MAP.md#operational-and-non-screen-endpoints). They are outside product screen implementation; the fallback's error presentation is included above.

## Existing partials, layout, and interaction dependencies

Every Blade file is accounted for either as a screen/error/mail template above or as a reusable piece below. Partial names do not establish independent routes or permissions.

| Existing Blade piece | Consumers / current contract | Owning phase |
|---|---|---|
| `layouts.app` | Every page; title/main-class/content sections; role nav; POST logout; first-login suppression; waiting count; connect banner; `hideUser` exception path | P02; maintain through P03–P08 |
| `components.avatar` | Header/account/booking/Today/accounts; protected photo URL or initials, size props | P01/P03 |
| `account._tabs` | S05/S06; active tab and `status/problem` flashes | P03 |
| `accounts._fields` | S23/S24; role-dependent fields, prefix IDs, old values | P07 |
| `bookings._row` | S13; status/time/details/cancellation link | P05 |
| `bookings._staff_actions` | S14/S16 through Today card; handout/no-show/return, per-booking return bag | P06; preserve during P05 |
| `today._card` | S16; identity, booking link, status, late/overdue and shared actions | P06 |
| `classes._answer` | S08/S16; ended/booked suppression, current answer disabled, route argument | P06; preserve during P04 |
| `manage._tabs` | S18–S22/S25–S29; active management tab and status flash | P07; timetable nav preserved until P08 |
| `manage._errors` | Auth/account/resource/timetable forms; caller supplies error bag | P01 baseline, P03/P07/P08 integration |
| `manage._timetable_list` | S26/S27; read-only vs editable day rows, add/delete actions | P08 |
| `manage._timetable_week` | S26/S27; server-computed positions/overlap lanes, edit links only for draft | P08 |
| `manage._timetable_row_form` | S27/S28; prefix, row/default day, error bag, field names | P08 |
| `manage._timetable_datalists` | S27/S28; room/course/block suggestions and `.tt-form` title auto-fill script | P08 |
| `manage._timetable_changes` | S29 comparison rows | P08 |
| `manage._timetable_clashes` | S25/S29; booking-vs-timetable warnings and detail links | P08 |
| `errors.message` | E403–E500; shared heading/text/home link, `hideUser` | P03 |

[style.css](../../public/css/style.css) owns responsive navigation, calendars, forms/tables, status badges, and CSS `:has()` conditional sections. It is not decorative only. Preserve form wrappers/selectors until behavior is deliberately replaced. No asynchronous loading or live-status polling exists; do not fabricate loading/delivery/availability states as backend features.

## Inventory changes, known gaps, and review gates

Compared with the initial planning seed, this inventory explicitly adds admin home, all onboarding/account/email/photo/recovery states, separate signed class checks, resource edit/category/semester forms, account lifecycle actions, timetable live/draft/fix/week/check/backup/undo flows, private file responses, and six custom error pages. “Admin rooms/equipment” has been corrected to S/A permissions.

**KNOWN ISSUE references:** K1 mail transport versus delivery; K2 current-rule cancellation; K3 inactive class-room booking path; K4 shared records/timetable warning and backup semantics; K5 missing integration coverage; K6 operational endpoint exposure. See the system map for evidence and review questions. The photo-specific 413 copy also needs review for timetable uploads. None of these is fixed or silently specified as intended behavior here.

**UNVERIFIED:** Figma mapping, browser role/state rendering, responsive/a11y behavior, SMTP delivery, transaction races, and database-backed workflows. The read-only audit checked source and syntax, not every screen in a browser. Existing tests mostly cover helper logic, with only two guest-page feature checks.

Before touching any screen, trace its routes/middleware/controller/service/models/view/CSS and relevant tests against the then-current checkout. Record a field/action/error/role matrix and before screenshots; agree on any unknown behavior before code changes. After implementation, test all applicable roles, denied records, empty/populated collections, validation/old input, flash messages, stale sessions, and workflow-specific states above. Every owning phase uses the explicit regression and merge gates in the roadmap. Stop after Phase 00 documentation for review; no Phase 01 until review and merge to `dev`.

## Phase 02 export coverage update — 2026-11-04

This supplements the Phase 00 **live-Figma UNVERIFIED** statement above. Fourteen user-supplied exports are now versioned in `references/figma/` and were visually inspected for the approved Phase 02 shell. This is static-image coverage, not live-node access or verification of every state.

| Export | Inventory coverage | Phase 02 use |
|---|---|---|
| 00 Foundations | Shared visual kit | Palette, typography, spacing/radius labels, unofficial PU motif |
| 01 Login | S01 | Guest institutional strip and branding only; form redesign deferred |
| 02 Representative dashboard | S08 | Representative desktop shell |
| 03 Room availability | S11 | Same representative shell; content deferred |
| 04 New reservation | S12 | Same shell; both current form stages preserved |
| 05 My bookings | S13 | My bookings active state |
| 06 Booking details | S14 | Representative My bookings active state |
| 07 Lab staff dashboard | S09 | Staff shell, Today/Requests/Manage rooms/Equipment destinations |
| 08 Approval queue | S15 | Requests active state; real waiting count retained |
| 09 Today's schedule | S16 | Today active state |
| 10 Admin manage rooms | S18 | Admin shell; current Accounts action retained despite export omission |
| 11 Equipment inventory | S20 | Separate Equipment shortcut to existing authorized route |
| 12 Mobile representative dashboard | S08, mobile | Four bottom links and collapsed account/menu control |
| Reusable UI components | Shared visual kit | Brand, nav tiles, active row, controls/card patterns |

Expanded menus, staff/admin mobile, onboarding, email/account, signed-link, permission, validation, empty and server-error states are **not shown by these exports**. Their shell behavior follows verified current code and the approved accessibility adaptations. No new route or functional permission was inferred from an image. Page content remains owned by its later phase.

## Phase 03 implementation update — authentication, own account and errors

Current implementation is based on `399c507` on `feat/frontend-03-auth`. S01–S07 and the shared E403/E404/E413/E419/E429/E500 presentation now use Phase 01 components and scoped auth CSS inside the Phase 02 shell. All earlier route/action/field/role contracts above remain current; no backend functionality changed. The live-Figma limitation remains; only S01 has a committed screen export. Other Phase 03 screens follow documented component-based assumptions.

The mandatory before/after [Functional Parity Matrix](../codex/PHASE_03_AUTH.md#functional-parity-matrix) records every current action, visible information group and conditional state. Forms and links were compared against the actual pre-change rendered views in 60 isolated states, including all original paragraph information. No filters, search, pagination, asynchronous loading controls or additional authentication features were introduced.

Coverage includes guest recovery/reset, all three roles, missing/existing school IDs, photo present/absent, grouped staff section options, connected/unconnected email, default/info/photo errors, old input, flashes, signed-result messages and all six error templates. These are isolated rendering/browser results. Real authentication, sessions, password-broker effects, throttles, signed middleware, database/storage authorization and mail behavior remain outstanding HTTP pre-merge checks; see the report rather than treating fixtures as security evidence. Signed class content remains Phase 06, while its existing invalid-signature message remains supported by S07.

## Phase 04 implementation update — S08 and S11

Based on local merged Phase 03 commit `239315c`, S08's representative branch now uses a maroon identity card, current class meetings, the full semester schedule and all three existing shortcuts. S09/S10 staff/admin home rendering remains unchanged. S11 composes a reusable calendar/day/agenda/room presentation for all three roles and preserves the complete existing search, navigation, booking-link and disclosure workflow. No route, authorization, data source or backend rule changed.

The [Phase 04 report](../codex/PHASE_04_AVAILABILITY.md) contains complete before/after matrices and file/evidence inventories. Coverage includes 42 isolated fixture states and six standard widths, plus boundary, keyboard, no-JS and enlarged-text checks. The committed dashboard/availability/mobile exports guide appearance; no live Figma inspection is claimed. Database-mutating HTTP tests are implemented but unexecuted pending separately authorized MariaDB isolation. Fixtures must not be treated as authentication, permission, privacy-service or booking-workflow proof.

## Stage 2 presentation update — S01, S08 and S11

The current task-focused refinements preserve the route, field, state, role and service contracts inventoried above. S01 prioritizes authentication controls before supporting copy; S08 places shortcuts and unanswered/today meetings before counted native disclosures for other meetings and the complete timetable; The Stage 2 follow-up puts S11 selected-date controls, native search and compact room rows first; calendar/day agenda remain below with an in-page shortcut. Calendar counts progressively reveal a contextual event panel beside the calendar on desktop and below it on mobile. Their native links and open per-date disclosures provide a one-activation no-JS fallback. The selected-day agenda remains a separate supplied dataset below the calendar/panel pair. Calendar rows never contain expanded details. Staff/admin dashboard content is unchanged. See the three before/after matrices and verification limits in [STAGE_02_TASK_UX.md](../codex/STAGE_02_TASK_UX.md). This update does not assert new HTTP, database, authorization or live-Figma verification.

### S11 timeline presentation update

The room-results list now aligns supplied free intervals to a shared selectable-slot axis. Every room, its original order/capacity, exact interval text, match/clash line, all-day/no-free explanation, permitted Book URL and full native busy disclosure remains present. Green means reported free; the neutral remainder makes no occupancy claim. The board has a single keyboard-scrollable horizontal region with sticky room identities on narrow screens. Empty/invalid axes and malformed ranges retain textual fallbacks. This changes room-list presentation only: date/search GET parameters, role/privacy data providers, calendar contextual panel, selected-day agenda, native fragments/history and shell remain unchanged. The [timeline parity matrix](../codex/STAGE_02_TASK_UX.md#stage-2-room-availability-timeline) records source traces, fixture verification and unexecuted live-workflow checks.
## Stage 3 visual-only refresh — 2026-11-06

At baseline `05ffc11` on `feat/frontend-visual-refinement`, the Stage 2 calendar and timeline are merged. Stage 3 changes only the existing scoped CSS for the shared shell, S01–S07/error components, S08 representative content and S11. All templates, controls, data, role conditions, routes, native forms, disclosures, fragments and scripts retain their prior locations and behavior. Staff/admin home content and unfinished booking/management/timetable content are not migrated. See the [Stage 3 parity matrix and evidence](../codex/STAGE_03_VISUAL_REFINEMENT.md); historical phase inventories above remain the functional contract.
