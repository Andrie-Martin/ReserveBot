# ReserveBot system architecture — Phase 00

## Evidence and scope

Audit date: **2026-11-03**. Reviewer: Codex. Source commit: **`e07be8f939ae6b1932150483d3770be3af2c64d9`**. Branch: **`docs/frontend-00-audit`**.

This is an architecture baseline for presentation-only frontend work, not a certification that existing behavior is correct. The user approved these documentation updates after the read-only audit. Phase 01 has not started; Phase 00 still needs review and merge into `dev`.

- **VERIFIED** means inspected source, or an explicitly identified runtime observation. Source verification does not imply an executed end-to-end test.
- **UNVERIFIED** means evidence is missing, inaccessible, or insufficient. Do not fill these gaps from a mockup.
- **KNOWN ISSUE** identifies a demonstrated discrepancy or integration risk. It does not authorize a fix or a new behavior contract.

All behavioral sections below are **VERIFIED from source** unless marked otherwise. Source links use repository-relative paths and line anchors at the audit commit. The detailed screen/action contracts are in [SCREEN_INVENTORY.md](../frontend/SCREEN_INVENTORY.md); phase ownership and future gates are in [PHASE_PLAN.md](../frontend/PHASE_PLAN.md).

### Runtime evidence from the read-only audit

| Observation | Evidence and limit |
|---|---|
| Laravel 13.39.0; container PHP 8.5.12; Composer 2.11.1 | `docker compose exec -T app php artisan about --only=environment,cache,drivers`; dependency lock agrees on Laravel. Local CLI was PHP 8.3.6. |
| Environment `local`, debug enabled, timezone `Asia/Manila`, locale `en` | Same `about` command; a snapshot, not a production assertion. |
| MariaDB; database-backed session/cache/queue; mail `log` | Same command. Mail delivery was not tested. Notifications below are synchronous despite the configured queue driver. |
| 85 runtime route entries, including framework routes | `docker compose exec -T app php artisan route:list --json`; GET/HEAD may share one entry. |
| App, database, scheduler, phpMyAdmin running; database healthy | `docker compose ps`; no service was started, stopped, or rebuilt by the audit. |
| Container source matches checkout | SHA-256 comparison: 151 files under `app`, `routes`, `resources/views`, `public/css`, `config`, and `database/migrations`; zero differences. This is not a database-schema comparison. |
| Syntax baseline | Local `php -l` on 121 PHP files under app/routes/database/config/bootstrap/tests: zero failures. Blade rendering and workflows were not tested by lint. |
| Worktree baseline | Existing modified `docker-compose.yml` and untracked `docker-compose.yml.save`; preserved. No application edits made. |

## Application structure and request lifecycle

[composer.json](../../composer.json) requires PHP `^8.3` and Laravel `^13.17`; [composer.lock](../../composer.lock) pins the versions above. [Dockerfile](../../Dockerfile) builds PHP/Apache with MySQL, GD, EXIF, and ZIP support and installs production Composer dependencies with `--no-dev`. No npm manifest, Vite build, SPA framework, remote font, or CDN dependency is used by the current UI. The `VITE_APP_NAME` example environment entry is not evidence of a build pipeline.

1. Apache serves `public/`; [public/index.php](../../public/index.php) boots Laravel through [bootstrap/app.php](../../bootstrap/app.php#L13).
2. `routes/web.php` uses the web middleware stack (sessions, CSRF, bindings). `routes/api.php` is mounted with an empty API prefix; its webhook and diagnostic addresses are `/webhook` and `/test-db`, not `/api/...`. `/up` is the framework health route.
3. Route middleware gates authentication, account readiness, roles, connected email, or signed links. Controllers separately enforce ownership/department constraints; hiding a button is not authorization.
4. Controllers load Eloquent data, validate requests, call domain services, and return Blade views, redirects with flash/errors, or downloads. There is no frontend availability JSON endpoint or live polling implementation.
5. [layouts/app.blade.php](../../resources/views/layouts/app.blade.php#L1) renders role-aware navigation and email prompts; pages include shared partials. It loads [public/css/style.css](../../public/css/style.css#L1) with a `filemtime` cache-busting query string.

`bootstrap/app.php` also maps invalid signatures to `auth.email_link` with HTTP 403 and context-sensitive wording. JSON exception rendering follows `api/*` or `expectsJson()`; the unprefixed API URLs are not matched by the `api/*` condition automatically.

## Authentication and authorization

Evidence: [routes/web.php](../../routes/web.php#L25), [LoginController](../../app/Http/Controllers/Auth/LoginController.php#L18), [middleware](../../app/Http/Middleware), [User](../../app/Models/User.php#L24), [AppServiceProvider](../../app/Providers/AppServiceProvider.php#L25).

- Login normalizes email, checks password plus `is_active=true`, returns a generic error under `email`, and regenerates the session. It redirects to `User::homeUrl()`, not an arbitrary intended URL. Logout is POST with `auth`, invalidates the session, and regenerates the CSRF token.
- The main group has `auth`, `auth.session`, and `account`. Password changes invalidate other sessions through Laravel's session authentication check. `AccountChecks` logs out disabled accounts and forces `must_change_password` accounts to onboarding, allowing only `welcome`, `welcome.save`, and `photos.show` inside that group. Logout remains outside the group.
- `RequireRole` rejects the wrong role with 403. Controller ownership/department failures commonly use 404 to avoid exposing inaccessible records. There are no application policy classes or gate definitions implementing these workflows; checks live in middleware/controllers/services.
- `email.connected` applies only to GET/POST `/book`, and redirects an unverified rep to `account`. It does not block availability, history, cancellation, or class answers.
- Signed email routes work without login. Their authority is the signed URL and route-specific checks, not the role of a browser's currently logged-in account.
- Password-link throttles: forgot-password 5/minute/IP, reset-password 10/minute/IP, email links 20/minute/IP. Resend has a separate 1/minute/user limiter and reports through `connect_note`. No explicit login throttle is attached in the current route/controller source; this is not evidence that one exists elsewhere in deployment.

| Exact role | Home | Allowed workflows | Scope/exclusions |
|---|---|---|---|
| `rep` (Block rep) | `rep.home` | Own account; availability; create bookings after email connection; own booking history/detail/cancel; block class answers | Cannot open requests, Today, management, or account administration. Class booking/answer checks use section membership. |
| `lab_staff` (Lab staff) | `staff.home` | Own account; availability; review, handover, movement/cancellation; department class marking; rooms/equipment/categories/rules/semesters/timetable | No booking-creation route; no account administration. Shared rooms/semesters have cross-department effects described below. |
| `admin` (Admin) | `admin.home` | All staff workflows (including `staff.home`); own account; account administration | Can administer only own-department `rep` and `lab_staff` accounts. Other admins are listed but not editable through these routes. No booking-creation route. |

A booking detail is visible to its owner, or a lab staff/admin user in the owner's department ([BookingController::show](../../app/Http/Controllers/BookingController.php#L169)). Do not infer wider access from `Reservation::user()`'s comment about possible booker roles.

## Data model and department boundaries

Schema authority: [database/migrations](../../database/migrations). Models: [app/Models](../../app/Models). No live schema/data dump was performed.

| Records/models | Relationships and behavior | Evidence |
|---|---|---|
| `Department`, `DepartmentConfig` | One configuration per department, primary key `department_id`; users, timetable entries, equipment categories belong to a department. Missing configuration returns an unsaved default model. | [Department](../../app/Models/Department.php#L15), [DepartmentConfig](../../app/Models/DepartmentConfig.php#L11) |
| `Room`, `department_room` | Rooms are building-wide; departments share them through a many-to-many pivot. Name is unique. Edits/deactivation affect all linked departments. Adding an existing name attaches that room instead of copying it. | [Room](../../app/Models/Room.php#L21), [ManageRoomsController](../../app/Http/Controllers/ManageRoomsController.php#L40) |
| `Term`, `Section`, `Course` | Shared records, not department-owned. Terms govern inclusive class date ranges; sections identify blocks/programs; courses are identified by code and title. | [Term](../../app/Models/Term.php), [Section](../../app/Models/Section.php), [Course](../../app/Models/Course.php), [course migration](../../database/migrations/2026_11_02_000005_create_courses_table.php) |
| `User` | Department required; section optional. Rep section determines class eligibility. Password hash and remember token are hidden in model serialization. | [User](../../app/Models/User.php#L41) |
| `ClassSchedule` | Belongs to department, term, room, optional course/section; weekly `Class` or `Blocked` entry. Class entries require a course; blocked entries can be room holds without a class. | [ClassSchedule](../../app/Models/ClassSchedule.php#L15), [migration](../../database/migrations/2026_11_02_000009_create_class_schedules_table.php) |
| `EquipmentCategory`, `Equipment` | Department ownership comes through category; stock, per-request limit, ordering, and active flag are stored on equipment. | [Equipment](../../app/Models/Equipment.php#L18), [EquipmentCategory](../../app/Models/EquipmentCategory.php#L15) |
| `Reservation`, `reservation_equipment` | User required; room optional for equipment-only; class link optional; purpose required after migration 16; reviewer optional; requested quantities on pivot. Department is derived through the user, not a reservation department column. | [Reservation](../../app/Models/Reservation.php#L35), [booking migration](../../database/migrations/2026_11_02_000016_update_reservations_for_booking.php) |
| `OverrideLog` | Reservation/staff change history with old/new JSON and reason. Staff handover/cancellation/movement write logs. Review and rep cancellation update reservation fields without this log path. | [OverrideLog](../../app/Models/OverrideLog.php#L16), [StaffDesk](../../app/Services/StaffDesk.php#L22), [ReviewController](../../app/Http/Controllers/ReviewController.php#L68) |
| `ClassCheck`, `reminder_log` | Unique class/date answer record; nullable answer, asked/answered timestamps and actor/source. Reminder log has unique per-recipient booking-or-class/mark keys. Deleted classes cascade checks/logs. Reminder log is accessed through DB queries, not an Eloquent model. | [migration 19](../../database/migrations/2026_11_03_000019_create_class_checks_and_reminder_log.php) |
| `TimetableDraft`, `TimetableBackup` | One shared draft per department; JSON rows, source, semester, version, actors. Backups store row names and semester metadata, not a full database snapshot. | [migration 20](../../database/migrations/2026_11_04_000020_create_timetable_drafts_and_backups.php) |

Laravel also uses sessions, password reset tokens, cache/locks, jobs/batches/failed jobs, and migrations tables. `timetable_view` is a SQL convenience view; application workflows inspected query models directly. Migrations use MariaDB/MySQL-specific SQL (`SET`, `ALTER ... ADD CONSTRAINT`, view creation); SQLite configuration alone does not prove migration compatibility.

The default [DatabaseSeeder](../../database/seeders/DatabaseSeeder.php#L16) invokes `BscpeSeeder` and `TestDataSeeder`. [DepartmentSeeder](../../database/seeders/DepartmentSeeder.php#L52) creates a department transactionally and skips an existing slug; shared room/term/section/course records are reused. Sample accounts/reservations are seed fixtures, not evidence of production data or live UI values. No seed credentials are documented here.

## Route → controller → service → view map

All named routes and form actions, including method details, are enumerated in [the screen inventory](../frontend/SCREEN_INVENTORY.md). This map groups them by dependency path; mutations return redirects unless noted.

| Route family | Controller methods | Services / data | Actual views |
|---|---|---|---|
| `login`, unnamed POST `/login`, `logout` | `Auth/LoginController::show/login/logout` | Auth/session, `User` | `auth.login` |
| `welcome`, `welcome.save`, `password.edit`, `password.change` | `PasswordController::welcome/saveWelcome/edit/update` | `AccountFields`, `User` | `account.welcome`, `account.password` |
| `account`, `account.update`, `account.photo`, `account.photo.remove`, `photos.show` | `AccountController::edit/update/photo/removePhoto`; `PhotoController::show` | `User`, `Section`, `Photos`, `Photo`, private local disk | `account.info`; binary photo response |
| `email.connect`, `email.resend`, `password.request/email/reset/update` | `EmailLinkController`; `PasswordResetController` | Signed URLs, limiter, password broker, notifications | `auth.email_link`, `auth.forgot`, `auth.reset` |
| `home`, `rep.home`, `staff.home`, `admin.home` | Root redirect; `RepController::home`, `StaffController::home`, `AdminController::home` | Classes, `ClassChecks`, department booking counts | Shared `home` |
| `rooms` | `AvailabilityController::index` | `RoomAvailability`, `ClassChecks`, `TimeSlots`, config, classes/bookings | `rooms.index` |
| `book`, `book.store`, `bookings.mine/show/cancel` | `BookingController::create/store/mine/show/cancel` | `Bookings`, `BookingRules`, `StaffRules`, reservations/resources/logs | `bookings.create`, `bookings.mine`, `bookings.show` |
| `requests`, `bookings.approve/reject` | `ReviewController::index/approve/reject` | Department reservations, conditional pending update | `requests.index`; actions also in booking detail |
| `today`, `bookings.hand-out/returned/no-show/staff-cancel/move` | `TodayController::index/handOut/returned/noShow/cancel/move` | `StaffDesk`, `Bookings`, `StaffRules`, `ClassChecks` | `today.index`; shared staff actions in detail |
| `class-check`, `classes.answer`, `classes.mark` | `ClassCheckController::email/asRep/asStaff` | `ClassChecks`, `ClassCheckRules`, classes/checks/reservations | `classes.check`; inline `classes._answer` |
| `manage.index`, `manage.rooms.*` | Redirect; `ManageRoomsController::index/store/edit/update` | Shared rooms, department pivot, upcoming bookings | `manage.rooms`, `manage.room_edit` |
| `manage.equipment.*`, `manage.categories.*` | `ManageEquipmentController::index/store/edit/update/storeCategory/updateCategory` | Categories/equipment, `StockCheck`, booking quantities | `manage.equipment`, `manage.equipment_edit` |
| `manage.rules`, `manage.rules.update`, `manage.terms.update` | `ManageRulesController::edit/update/updateTerm` | Department configuration, shared terms | `manage.rules` |
| `manage.timetable.*` | `ManageTimetableController` (all actions listed in inventory) | `Timetables`, `Spreadsheet`, `TimetableFile`, `TimetableRules`, drafts/backups/classes/resources | `manage.timetable`, `manage.timetable_rows`, `manage.timetable_row`, `manage.timetable_check`; file responses |
| `accounts.*` | `AccountsController::index/store/edit/update/password/destroy` | `AccountFields`, `User`, sections, history, photos, notifications | `accounts.index`, `accounts.edit` |

Controller source directory: [app/Http/Controllers](../../app/Http/Controllers); service source directory: [app/Services](../../app/Services). No booking approval, cancellation, or resource-management API should be invented for a visual redesign.

### Operational and non-screen endpoints

| Endpoint | Verified behavior | Frontend scope |
|---|---|---|
| GET `/test-db` (unnamed) | API middleware; queries DB time and active room summary; catches errors and returns exception text with 500. | Diagnostic, no Blade; KNOWN ISSUE K6. |
| GET/POST `/webhook` (unnamed) | GET verifies configured token/challenge; POST logs request and acknowledges `object=page` with 200, otherwise 404. No message dispatch or active booking bot. | Paused integration, no frontend implementation. |
| GET `/up` (unnamed) | Framework health route registered in bootstrap. | Operational, no product screen. |
| GET/PUT `/storage/{path}` (`storage.local`, `storage.local.upload`) | Generated because local disk has `serve=true`; framework `ServeFile`/`ReceiveFile` check relative signatures (and distinguish upload links) inside handlers. They do not use the account middleware. | No application view or current form uses these routes; do not replace `photos.show` with a public storage URL. |
| Fallback (unnamed) | Aborts 404 via web routing. | Error presentation only. |

Evidence: [routes/api.php](../../routes/api.php#L10), [WebhookController](../../app/Http/Controllers/WebhookController.php#L17), [bootstrap/app.php](../../bootstrap/app.php#L14), [filesystems configuration](../../config/filesystems.php#L33). Storage handler verification was against the running Laravel 13.39 framework source; those vendor files are not tracked application files.

## Reservation lifecycle and resource accounting

[Reservation constants](../../app/Models/Reservation.php#L15) define seven stored values; labels are separate. `Pending` = “Waiting for approval”, `Confirmed` = “Approved”, `InUse` = “In use now”, `NoShow` = “No-show”. `Rejected`, `Cancelled`, and `Completed` retain their names. Never submit display labels as status values.

| Action | From → to | Conditions / effects | Source |
|---|---|---|---|
| Create | none → `Pending` or `Confirmed` | Current `requires_approval`; validates and locks resources, inserts requested equipment quantities | [Bookings::place](../../app/Services/Bookings.php#L146) |
| Approve / reject | `Pending` → `Confirmed` / `Rejected` | Same department; reject reason required, max 255; conditional update prevents a second decision | [ReviewController::decide](../../app/Http/Controllers/ReviewController.php#L68) |
| Rep cancel | `Pending` or `Confirmed` → `Cancelled` | Owner, reason max 255, current cancellation deadline inclusive; conditional status update | [BookingController::cancel](../../app/Http/Controllers/BookingController.php#L224) |
| Hand out | `Confirmed` → `InUse` | Staff/admin department check; no time gate; override log | [TodayController::handOut](../../app/Http/Controllers/TodayController.php#L69) |
| Return | `InUse` → `Completed` | Optional note max 255; override log | [TodayController::returned](../../app/Http/Controllers/TodayController.php#L81) |
| No-show | `Confirmed` → `NoShow` | No time gate; override log; UI confirmation | [TodayController::noShow](../../app/Http/Controllers/TodayController.php#L98) |
| Staff cancel | `Confirmed` → `Cancelled` | Before end; reason required; override log | [TodayController::cancel](../../app/Http/Controllers/TodayController.php#L111) |
| Staff move | `Confirmed` → `Confirmed` | Before end; new valid slot, room/stock checks and reason; transaction/log; room bookings keep a room and equipment-only stays roomless | [TodayController::move](../../app/Http/Controllers/TodayController.php#L131), [Bookings::move](../../app/Services/Bookings.php#L201) |

No route reopens a closed booking or edits its equipment after creation. `EquipmentUpdated` exists as a log enum/description, not an exposed editing workflow.

- Blocking statuses are `Pending`, `Confirmed`, `InUse`; releasing statuses are `Rejected`, `Cancelled`, `Completed`, `NoShow`. Blocking reservations count only when their stored time overlaps the requested interval. Overdue UI does not extend the stored interval automatically.
- Overlap is `start < otherEnd && end > otherStart`, so touching intervals do not clash. Equipment usage sums requested quantities for overlapping blocking reservations; it is not a stock decrement at handout. Free text `other_items` is not counted stock.
- Creation transaction locks class, then room, then equipment ordered by ID; rechecks duplicate sessions and availability and retries deadlocks up to three times. Movement locks booking/room/equipment and checks again. Review/cancel/status transitions use conditional status updates. These mechanisms were inspected, not concurrency-tested.
- Late and overdue are derived warnings, not stored statuses. `StaffRules` allows handout/no-show for an approved booking at any time, on any day (the future-day refusal from QA F-07 was removed on the `sandbox` branch, so staff can always step in by hand); return only while in use. See [StaffRules](../../app/Support/StaffRules.php#L24) and [StaffRulesTest](../../tests/Unit/StaffRulesTest.php#L27).

## Booking and availability constraints

[BookingController](../../app/Http/Controllers/BookingController.php#L33) implements two stages on `bookings.create`: GET selects when; POST submits what is needed and repeats validation. Hidden time/session parameters and the GET “Change” link preserve the workflow. An invalid/stale session on POST redirects back to GET stage one; stock/room conflicts can return validation errors.

| Constraint | Actual rule |
|---|---|
| Booking types | `for=class` or `other`; `need=both`, `room`, or `equipment`. Every booking requires purpose, max 150; optional other items max 200. |
| Class session | Key `<class_schedules.id>_<YYYY-MM-DD>`; rep's section, Class entry, matching weekday and term dates, not already blocked by another booking of that class/date. Checks booking horizon and notice, not extra-booking hours/duration/open-day limits. |
| Extra booking | Department open days/hours, allowed start/end steps, end after start, minimum/maximum duration, horizon and notice. Room must be active and linked to department. |
| Equipment | Active items from department categories, integer quantities 0–99, individual `max_per_request`, sufficient overlapping stock; equipment/both needs at least one listed item or other-items text. |
| Movement | Same horizon/open days/slot lists; no notice or duration limit; new end must be future; same room/time is rejected. Own reservation/class is excluded from conflict checks as implemented. |
| Cancellation | Pending/confirmed owner only; now <= start minus current department cutoff. Default is 180 minutes after migration 17; do not hardcode it. |
| Availability | Active department rooms; their classes and blocking bookings across all departments. Class holds apply inside their term; online/freed meetings stop holding rooms. |
| Calendar/filter | `date=Y-m-d`, `from/to=H:i`; valid filters follow day/month links; `#day` anchor. Invalid dates fall back with a message; explicitly selected closed days show closed state. |
| Disclosure | Rep calendar shows own/block classes and bookings; staff calendar shows department bookings. Busy-room labels/details vary by role; another rep's class booking is not automatically a detail link. |

Evidence: [BookingRules](../../app/Support/BookingRules.php#L32), [Bookings::findSession](../../app/Services/Bookings.php#L74), [RoomAvailability::busy/agenda](../../app/Services/RoomAvailability.php#L47), [AvailabilityController](../../app/Http/Controllers/AvailabilityController.php#L29), [TimeSlots](../../app/Support/TimeSlots.php#L65).

Default configuration: Mon–Sat, 07:30–21:00, 30-minute step, extra bookings 30–180 minutes, 14 days ahead, 60-minute notice, 180-minute cancellation cutoff, 15-minute late grace, approval required. These are fallback defaults, not verified live department settings ([DepartmentConfig::DEFAULTS](../../app/Models/DepartmentConfig.php#L29)). Availability is a read-time snapshot, not a guarantee that submission will succeed.

## Class checks, notifications, and signed email

[ClassChecks](../../app/Services/ClassChecks.php#L59) computes `waiting`, `f2f`, `online`, `freed`, or `booked`. `booked` takes precedence for a blocking class-session reservation on that date (including an equipment-only class-session booking under the current query). It is not a stored answer. Answers stored are only `f2f`/`online`.

- Reps answer for their section; staff/admins mark classes belonging to their department. Signed email routes identify a rep and class/date; POST verifies that the identified user is active, a rep, and still assigned to the section.
- Email GET selects/displays a choice only; POST saves, with CSRF and the full signed URL intact. Links expire at the class end. Invalid signatures return `auth.email_link` with 403; invalid email POST answer returns 422; invalid authenticated answer/date uses 404. Service refusals use `problem` flash messages.
- `online` frees the class room. Unanswered classes become `freed` at the adjusted 5-hour reminder deadline only if `asked_at` is at least six hours before that deadline. No timely question means the room remains held. The state is computed on read; it does not require a release job to update a stored status.
- `f2f` can reclaim a freed room unless another blocking reservation overlaps. The service locks the room to coordinate with booking. Online is refused while the class session has a blocking booking; the booking must be cancelled first. Ended meetings cannot change.

[SendReminders](../../app/Console/Commands/SendReminders.php#L40) is scheduled every minute with `withoutOverlapping(10)` in [routes/console.php](../../routes/console.php#L13). It processes classes and confirmed future bookings. Eligible recipients are active with connected email; class recipients are the block's reps. Signed links come from `ClassChecks::link`; booking reminders link to protected details.

[ReminderTimes](../../app/Support/ReminderTimes.php#L15) uses day/5h/1h marks. Times due from 22:00 until before 06:00 shift to 21:00; after downtime only the newest due mark is selected, and reminders stop at start. Reminder logs suppress repeat marks. The first successful class-question notification sets `asked_at`. Current mail is `log`; transport success is not inbox delivery (K1).

Notifications in [app/Notifications](../../app/Notifications) use `mail` and do not implement `ShouldQueue`: `ConnectEmail`, `ResetPasswordLink`, `BookingReminder`, `ClassReminder`. Only class reminders use the custom `mail.class_reminder` Blade; others use Laravel MailMessage templates. Approval, rejection, handout, cancellation, movement, and timetable replacement contain no notification send in their inspected action paths. Do not add UI promises that those actions email users. `EmailSetup::sends()` distinguishes `log`/`array` from other mailers, not deliverability.

## Account lifecycle and photos

Evidence: [AccountController](../../app/Http/Controllers/AccountController.php#L21), [AccountsController](../../app/Http/Controllers/AccountsController.php#L38), [AccountFields](../../app/Support/AccountFields.php#L42), [PasswordController](../../app/Http/Controllers/PasswordController.php#L22), [PasswordResetController](../../app/Http/Controllers/PasswordResetController.php#L27).

- Admin-created/reset passwords set `must_change_password`; onboarding requires a different password, 8–64 characters with letters and digits and confirmation. Missing school ID is requested for managed roles, required for reps. No public registration exists.
- Own information: name/email; reps have school ID and admin-assigned block; lab staff may choose any listed section; admins do not receive the school-ID field. Changing email requires current password and clears verification before sending a connection link. Rep new emails must use Gmail or `iskolarngbayan.pup.edu.ph`; an existing allowed-to-keep address may remain.
- Email connection is a signed GET lasting three days, tied to user ID and SHA-1 of the current email. It writes `email_verified_at`. A still-valid signed URL for a changed email renders a failure message with normal view status; invalid/expired signature uses 403. Resend throttling is a flash response, not necessarily HTTP 429.
- Password recovery uses Laravel's broker (default expiry 60 minutes), active accounts only, generic existence wording, password confirmation, and token. Success changes password, rotates remember token, clears mandatory password change, and sets email verification if absent; it redirects to login. Invalid/used/mismatched tokens report an `email` error.
- Admin account creation/editing restricts roles to rep/lab_staff and department to the actor's department. Rep blocks come from that department's timetable. Delete becomes deactivate when `User::hasHistory()` finds reservations, reviewed bookings, or override logs; it is not an “any foreign-key history” check. Deactivation preserves existing bookings and does not cancel them.
- Photos: multipart `photo`, JPEG/PNG/WebP, <=5 MB, minimum 100×100 and max 50 million pixels. GD processing creates a 320×320 JPEG with orientation handling; without GD the original is retained. Private storage, randomized filenames, old-photo removal. [Photos](../../app/Services/Photos.php#L18), [Photo](../../app/Support/Photo.php#L18).
- [PhotoController::show](../../app/Http/Controllers/PhotoController.php#L17) permits owner or same-department non-rep, checks file existence, returns private cache headers, and otherwise 404. `User::photoUrl()` adds a version query; avatar fallback uses initials.

## Resource and timetable management

Room updates are global to the shared room; inactive records retain old bookings. Equipment updates can lower stock below existing demand: the page lists affected bookings via `StockCheck` instead of automatically changing bookings. Rule updates change the department config; semester-date updates change a shared term. See [ManageRoomsController](../../app/Http/Controllers/ManageRoomsController.php#L86), [ManageEquipmentController](../../app/Http/Controllers/ManageEquipmentController.php#L105), [ManageRulesController](../../app/Http/Controllers/ManageRulesController.php#L30).

Timetable management belongs to **frontend Phase 08**, although backend comments call it “Phase 11”. Those are different roadmaps.

1. **Start:** copy current entries, upload a file, or bring back a backup. There is one shared draft per department, not one per user. Upload/restore replaces an existing draft; starting from the site reuses an existing draft. Source/actors/timestamps are visible.
2. **Parse/edit:** `Spreadsheet` reads XLSX (ZIP required), CSV, or TXT; old/unsupported workbook types receive explicit errors. Upload max 5 MB; parser has row/column/unpacked-size bounds. `TimetableFile` recognizes column aliases and `BLOCKED`, normalizes rows through `TimetableRules`, and preserves problems for review. Export can use `format=csv`; downloads fall back to CSV without ZIP. Do not replace the parser as a frontend shortcut.
3. **Draft screens:** day list, `day=fix`, `week=room:<name>` or `block:<name>`; add/edit/delete a row, undo with encrypted row data, discard draft, save semester. Draft edits lock the draft and advance its version. Undo carries `back`; preserve existing server redirect checks.
4. **Check:** structural problems/empty timetable prevent replacement. Duplicate rows are deduplicated. Overlap, opening-hours, shared-room, orphan-rep, and booking-clash warnings do not automatically prevent saving. Show added/removed/changed rows, new/shared resources, retained bookings, and semester scope explicitly.
5. **Replace:** POST includes draft `version` and `term_name/start/end`. A transaction locks the draft, rejects stale versions, rechecks data, backs up a nonempty old timetable, and applies immediately. A same-name term's dates are updated globally. New rooms/sections/courses may be created or shared. Class identity matching preserves IDs for unchanged scheduling identity and permits selected metadata changes. A changed room/time can be removal plus addition; do not promise preserved class history for all edits.
6. **After replacement:** removed classes detach reservations into ordinary bookings, then are deleted (their checks/reminder logs cascade). Existing reservations remain and can conflict with new classes. The draft is deleted. Backups are timetable snapshots, not restoration of deleted class answers or all booking/account data. Restoring goes through draft/check/replace again.

Evidence: [ManageTimetableController](../../app/Http/Controllers/ManageTimetableController.php#L49), [Timetables::startDraft/editDraft/check/apply](../../app/Services/Timetables.php#L101), [TimetableRules::compare](../../app/Support/TimetableRules.php#L377), [Spreadsheet](../../app/Support/Spreadsheet.php#L23), [TimetableFile](../../app/Support/TimetableFile.php#L58), [check form](../../resources/views/manage/timetable_check.blade.php#L155).

## View data, shared pieces, and presentation contracts

| View family | Principal view data | Existing reusable pieces / sensitive states |
|---|---|---|
| `home` | `user`, title/intro, nullable stat/classes, ready links, comingNext; rep meetings | `classes._answer`; preserve different role datasets, no-class state and pending counts |
| `rooms.index` | Department, calendar weeks, selected date/window, dayItems, roomList, busy/free ranges, search/errors, navigation URLs | Calendar and list share server-generated data; private labels and booking/detail links are conditional |
| `bookings.create` | User/rules, for/when/error/picked; stage-specific sessions/days or categories/rooms/class room | Preserve stage-specific variables, old input, unavailable equipment, errors and hidden params |
| `bookings.mine/show` | Upcoming/past collections; reservation, history, review/cancel/change flags, deadlines, move lists, staff rules | `bookings._row`, `bookings._staff_actions`; do not turn state flags into guessed permissions |
| `requests.index`, `today.index` | Waiting/decided collections; day navigation, inUse/toHandOut/waiting/done, staff rules/classes | `today._card`, staff actions, `classes._answer`; per-record errors, late/overdue/empty states |
| Account/auth | User/sections, askSchoolId, reset token/email, or email result `ok/message` | `account._tabs`, `accounts._fields`, `components.avatar`, `manage._errors`; sensitive role/verification/disabled/history states |
| Resource management | Rooms/sharedWith/upcoming; categories/item/upcoming/over; config/terms | `manage._tabs`, `manage._errors`; existing bookings and sharing warnings |
| Timetable | Overview counts/draft/backups/clashes; row lists/grid/problems/warned/suggestions; check comparison; draft version | `_timetable_list`, `_timetable_week`, `_timetable_row_form`, `_timetable_datalists`, `_timetable_changes`, `_timetable_clashes` |
| Error pages | Heading/text, optional `hideUser` | `errors.message`; 413/500 hide user navigation to avoid database-dependent rendering |

- Current CSS uses purple tokens/system fonts, not the maroon/Inter [approved design contract](../frontend/DESIGN_SYSTEM.md). This is a migration baseline, not an approved visual match.
- CSS `:has()` controls conditional booking sections (lines 294–298), account-role sections (661–662), and blocked timetable fields (837). Preserve selectors or replace their behavior deliberately within an approved phase.
- JavaScript: [timetable datalists](../../resources/views/manage/_timetable_datalists.blade.php#L18) auto-fill an empty course title; no-show/account deletion have inline confirmation handlers. No fetch/AJAX/polling flow was found.
- Preserve methods/actions/route names, CSRF, method spoofing, signed query strings, multipart encoding, `old()` values, named error bags, `status/problem/connect_note/undo` flashes, and button `formaction` distinctions.
- Existing navigation queries the waiting count for staff. Onboarding suppresses main navigation; unconnected email prompts vary by role/page. Error layouts with `hideUser` must remain independent of this query.
- No application paginator exists: history takes the latest 30 past bookings, recent decisions 10, and other lists use collections. Do not invent pagination query contracts or claim a full history export.
- Preserve semantic labels, focus states, disclosure controls, table scrolling, calendar accessibility text, and status text in addition to color. Current browser/a11y quality is UNVERIFIED.

## Tests, safe verification, and limits

Source tests: 78 `test_*` methods in [tests](../../tests). Unit tests cover time slots, booking/staff/account/class-check/reminder rules, stock overlaps, photo transformation, timetable parsing/comparison/lanes, and CSV/XLSX round trips. GD/ZIP-dependent tests may skip. [Feature tests](../../tests/Feature/ExampleTest.php#L9) only check guest redirect and login-page loading; the trivial example unit test is not domain coverage.

**UNVERIFIED:** authenticated authorization, full booking lifecycle, transactional concurrency, mail delivery, timetable application/rollback effects, upload HTTP handling, browser behavior, mobile layout, and accessibility have no passing end-to-end evidence from this audit. Pure helper tests do not establish database/controller correctness.

[phpunit.xml](../../phpunit.xml) configures testing with SQLite `:memory:`, array mail/session/cache, and sync queue. PHPUnit was not run: local `vendor/autoload.php` is absent and the production container has no `vendor/bin/phpunit`. No packages were installed. Before a future test run, confirm effective isolation and compatible PHP/dependencies; do not point tests at the development/production database or assume inherited environment variables are overridden. Prefer `php vendor/bin/phpunit --do-not-cache-result` in an approved isolated test environment; this is a proposed future command, not a result. `composer test` first clears configuration and is not a read-only audit command.

Read-only commands actually used: Git status/branch/log/diff checks; source reads/searches/hashes; Docker `ps`; Artisan `route:list --json` and `about --only=environment,cache,drivers`; PHP syntax checks. No migrations, seeders, scheduler commands, mail sends, or authenticated mutations were invoked. Runtime observations are dated snapshots and must be refreshed when relevant in later phases.

[Docker entrypoint](../../docker/entrypoint.sh#L11) runs migrations on startup and may seed an empty database. Do not run `docker compose up`, rebuild/restart, Composer setup, or README reset commands as read-only discovery. The scheduler was already active; the audit did not guarantee that background application data stayed static.

## Known issues and unresolved questions

| ID / status | Evidence and consequence | Review question / next evidence |
|---|---|---|
| K1 — KNOWN ISSUE | Runtime mail `log`; `SendReminders` treats a successful `notify()` as sent, writes reminder log and `asked_at`. Room release may follow without an inbox message. | Is this acceptable for local/demo use, and what delivery semantics are required for deployment? Separate backend/operations review; no frontend delivery promises. |
| K2 — KNOWN ISSUE | Rules UI/controller says existing bookings stay as they are, but cancellation reads current configuration in `BookingController::context/cancel`. | Should cancellation retain booking-time rules or follow current rules? Document current behavior; do not change it in frontend phases. |
| K3 — KNOWN ISSUE | Room deactivation says it cannot be booked; class-session lookup uses the class room without the active-room filter used for extra bookings (`Bookings::sessions/findSession`, `BookingController::store`). | Should inactive rooms prevent class-session booking too? Requires backend decision and regression coverage. |
| K4 — KNOWN ISSUE / integration risk | Shared room edits and shared-term updates affect other departments; timetable conflicts can be warnings. Backup restoration does not restore deleted meeting history. | Preserve existing warnings; confirm desired warning language and whether broader safeguards are needed separately. |
| K5 — KNOWN ISSUE / coverage gap | Only two guest-page feature tests; no executable PHPUnit in audited environments. | Establish an isolated test environment before implementation acceptance; keep unexecuted checks visible. |
| K6 — KNOWN ISSUE | `/test-db` is unauthenticated and includes exception text; webhook POST logs payloads without an application signature check. Runtime debug is enabled locally. | Deployment exposure/logging policy needs separate review. No endpoint/configuration edits authorized here. |
| U1 — UNVERIFIED | Figma session and metadata tools denied access during audit. | Obtain permitted design access or reviewed exports before claiming any frame mapping or visual fidelity. All screen Figma coverage remains UNVERIFIED. |
| U2 — UNVERIFIED | No rendered role/state screenshots or browser checks; 500 template normally requires debug off. | Capture before/after evidence in an isolated fixture environment during each owning phase. |
| U3 — UNVERIFIED | Schema migrations inspected but live schema/configuration rows not compared; SQLite compatibility not established. | Verify relevant live schema read-only when needed and use a separate MariaDB test database for database-dependent checks if approved. |
| U4 — UNVERIFIED | Source mechanisms for locks, signatures, history retention, and account sessions inspected, not exercised end to end. | Test boundary cases before accepting affected frontend work; do not mark them passed from source reading alone. |

No issue above has been fixed in Phase 00. Backend changes need separate review and approval. Documentation completion does not authorize starting Phase 01 or changing Git branches.

## Phase 03 source refresh — 2026-11-04

The Phase 00 introduction and visual baseline above are historical. At Phase 03 base `399c507`, Phases 01/02 are merged. Authentication routes/controllers/middleware/services/models/notifications were retraced against this checkout: the authentication and action contracts above remain unchanged. Current Blade asset order is legacy CSS, foundation fonts/tokens/components, layout CSS, optional page styles, plus the existing deferred layout JS. The shared layout change in Phase 03 is solely `@stack('styles')`; migrated auth/account/error views push independently cache-busted `css/auth.css`.

S01–S07 and the shared error wrapper now compose foundation components. A presentation-only `ui.auth-card` supports compact forms/results; `auth._illustration` is local decorative SVG. Own-account photo/email/information partials retain caller-owned state and native independent forms. Account tabs still render GET links and `status/problem`; account resend keeps its button with `connect_note`, while the unchanged shell replaces its resend controls with that feedback. Hidden-user errors retain the guard before authentication.

The current code preserves login normalization/active-account check/session regeneration/role redirect; main `auth/auth.session/account` gates; mandatory onboarding and school-ID conditions; default/info/photo validation; current-password requirements; legacy rep email exception; private versioned photographs; signed connection URLs; recovery token/email and notification contracts. No backend, schema, configuration, notification, infrastructure or dependency change accompanies this update.

[Phase 03 evidence](../codex/PHASE_03_AUTH.md) distinguishes 60-state render/browser contract preservation from unexecuted real HTTP/security workflows. The runtime dependency probe confirmed container Laravel is available but PHPUnit is absent; local vendor is also absent. New HTTP tests require explicit opt-in to an independently provisioned disposable MariaDB database and never migrate or seed. Earlier runtime/source-match claims are dated observations, not certification that the running service contains this worktree. No deployment/restart was performed.

## Phase 04 source refresh — 2026-11-04

At base `239315c`, the local history includes the Phase 03 merge. Current source retains the existing `auth/auth.session/account` group for rooms, representative-role constraints on class answers/bookings and connected-email gating on booking creation. `RepController` still owns dashboard data; `AvailabilityController` and `RoomAvailability` own date/search/agenda/privacy/room decisions; `ClassChecks` owns class state and answer effects. No backend or schema changes accompany Phase 04.

The shared home now dispatches representative content to `home._representative`; staff/admin markup remains in the original fallback. `rooms.index` composes `_calendar`, `_calendar-day`, `_day`, `_agenda` and `_room`. Page styles use the existing Blade style stack, independently cache-busted, and existing foundations. The shared shell, authentication views and class-answer partial are unchanged. Native POST/GET semantics and controller-provided URLs remain the source of actions.

[Phase 04 verification](../codex/PHASE_04_AVAILABILITY.md) distinguishes no-database renderer/browser evidence from unexecuted opt-in HTTP tests. Existing container Laravel dependencies were reused only for temporary fixture snapshots; no service deployment, infrastructure modification or database provisioning occurred. Earlier audit/runtime observations above remain historical, not a claim that the running service contains this worktree.
## Stage 3 source refresh — 2026-11-06

Current baseline: `05ffc11` (merged Stage 2 timeline), branch `feat/frontend-visual-refinement`. The earlier Phase 00 runtime observations remain historical. Current source was retraced: guest login/recovery at `routes/web.php:26`, logout at `:37`, workspace gates at `:52`, rooms at `:71`, rep/email booking gates at `:74`, and class answers at `:87`. `Auth/LoginController.php:19`, `RepController.php:12`, `AvailabilityController.php:29`, `ClassChecks`, `RoomAvailability` and `TimeSlots` remain authoritative and unchanged.

Stage 3 refines the existing six scoped CSS files: foundation tokens/components, layout, auth, dashboard and availability. It changes no Blade or JavaScript files, no source-data calculation, query, authorization, workflow, asset-loading mechanism or dependency. The stationary desktop shell, mobile fallback, shared proportional timeline, contextual calendar selection and native forms/disclosures remain the Stage 2 architecture. [Stage 3 evidence](../codex/STAGE_03_VISUAL_REFINEMENT.md) distinguishes isolated rendering/browser parity from unexecuted live authenticated workflows.
