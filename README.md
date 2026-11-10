# ReserveBot

Room and equipment reservation website for the BSCpE Department (CMPE 453 project).
Built with Laravel (PHP) and Blade pages, on a MariaDB/MySQL database.

The earlier Node.js version is saved on the `node-version` branch.

## Run it with Docker (easiest)

It needs **Docker Desktop**. No XAMPP, PHP, Composer, or importing anything by hand.

1. Install [Docker Desktop](https://www.docker.com/products/docker-desktop/) and open it.
   On Windows the installer sets up WSL 2 for you.
2. Get the code and start everything:
   ```
   git clone https://github.com/Andrie-Martin/ReserveBot.git
   cd ReserveBot
   docker compose up --build
   ```
   The first run downloads a few hundred MB and builds the database, so give it a few minutes.
3. Open:
   - **http://localhost:8000**: the website
   - **http://localhost:8080**: phpMyAdmin, already logged in

| To | Run |
|---|---|
| Stop | `Ctrl+C`, or `docker compose down` in another window |
| See your code changes | `Ctrl+C`, then `docker compose up --build` |
| Reset the database (rebuilds it with the test data; also deletes uploaded photos) | `docker compose down -v`, then `docker compose up --build` |

Docker has its own database, separate from XAMPP's, so XAMPP's MySQL can stay on.
If port 8000 is taken, stop any `php artisan serve` you have running first.

**Mixing both:** to edit code without rebuilding, run only the database in Docker
(`docker compose up db phpmyadmin`) and the website with `php artisan serve`, using these
`.env` values: `DB_PORT=3307`, `DB_USERNAME=reservebot`, `DB_PASSWORD=reservebot`.

## Run it on your PC (XAMPP + Composer)

You need:
- **XAMPP** with PHP 8.3 or newer, for PHP and the MySQL/MariaDB database
- **Composer**, from https://getcomposer.org/download/ (when it asks for PHP, pick `C:\xampp\php\php.exe`)
- PHP's **zip** extension turned on (Composer and the timetable's Excel files need it): in the
  XAMPP Control Panel, Apache row, click **Config** → **PHP (php.ini)**, find `;extension=zip`,
  delete the `;`, save, and restart Apache and `php artisan serve`

No Node.js or `npm install` needed.

**First time:**
1. In the XAMPP Control Panel, start **MySQL**.
2. Get the code and install Laravel's packages:
   ```
   git clone https://github.com/Andrie-Martin/ReserveBot.git
   cd ReserveBot
   composer install
   ```
3. Make your settings file and give it a secret key:
   ```
   copy .env.example .env
   php artisan key:generate
   ```
4. Build the database (tables + BSCpE data + test accounts). If it asks to create the `reservebot` database, answer **yes**:
   ```
   php artisan migrate --seed
   ```
5. Start the website and open http://localhost:8000
   ```
   php artisan serve
   ```

**Every day after that:** start MySQL in XAMPP, then run `php artisan serve` in this folder.

| To | Run |
|---|---|
| Start the website | `php artisan serve` (stop it with `Ctrl+C`) |
| Rebuild the database from scratch (deletes everything and reloads the test data) | `php artisan migrate:fresh --drop-views --seed` |
| Check that the website can reach the database | open http://localhost:8000/test-db |
| Run the automatic tests | `php artisan test` (the database-backed login, availability and management suites are skipped in this run) |
| Run every test, including those database-backed suites, on a throwaway database | `tests/Support/run-db-tests.sh` (needs Docker; starts its own database with a throwaway key and password, needs no `.env`, and aborts unless the application resolves to that database) |

If your `reservebot` database still has the Node.js version's tables, clear them once with
`php artisan db:wipe --drop-views`, then run `php artisan migrate --seed`. (The rebuild
command only clears tables after Laravel has built the database at least once.)

## Test accounts

All use the password `Password123!`. They count as having connected their email already,
so they work right away (`@reservebot.test` addresses can't receive email).

| Email | Role |
|---|---|
| rep1@reservebot.test | Block rep, BSCPE 4-5 (Bea Prieto) |
| rep2@reservebot.test | Block rep, BSCPE 3-1 (Maria Santos) |
| staff@reservebot.test | Lab staff |
| admin@reservebot.test | Admin |

## Accounts and email

- Admins make the block rep and lab staff accounts on the **Accounts** page, with a starting
  password they give to the person. The person picks their own password at the first login.
  Deleting an account that has bookings turns it off instead, so its history stays.
- Everyone has **My account** (click your name at the top): photo, name, email, School ID,
  and a Password tab. **Forgot your password?** on the login page emails a link.
- New accounts get an email with a **Connect my email** button. Block reps can't book until
  they click it, and their email must be Gmail or PUP webmail (`@iskolarngbayan.pup.edu.ph`).
- Photos are kept in `storage/app/private/photos` (in Docker, in the `photos` volume). Only the
  person and their department's lab staff and admins can see them.

### Email (Gmail)

Until this is set up, emails aren't sent: they're written to `storage/logs/laravel.log`
(in Docker, to the `docker compose up` window), so while testing you can copy a link from there.

To send them through Gmail:

1. Pick a Gmail account for the site. A new one is best, e.g. `reservebot.bscpe@gmail.com`.
2. In that account, turn on **2-Step Verification** (Google Account → Security).
3. Make an **App Password** at <https://myaccount.google.com/apppasswords>: name it `ReserveBot`
   and copy the 16 letters it shows.
4. Put these in `.env` (the App Password without spaces):
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=the-sites-address@gmail.com
   MAIL_PASSWORD=the16letterapppassword
   MAIL_FROM_ADDRESS=the-sites-address@gmail.com
   ```
5. XAMPP: run `php artisan config:clear` and restart `php artisan serve`.
   Docker: `docker compose up --build`.

Gmail sends up to about 500 emails a day. The App Password works like the account's password:
it stays in `.env`, which is never committed. Links in the emails use the address the site
was opened with, so while you test on `localhost`, open them on the same PC.

## Reminders and the face-to-face check

Block reps get emails 1 day, 5 hours and 1 hour before:

- **their approved bookings** (a reminder), and
- **their block's classes**, asking *"Is it face-to-face?"*. **Online** frees the class's room
  for that day: it shows as free on Free rooms and anyone can book it. **No answer** frees it
  too, when the 5-hour email goes out, but only if the rep got the day-before email (blocks
  without a connected rep keep their rooms). **Face-to-face** takes a freed room back unless
  someone has booked it since.

Emails due from 10 PM to 6 AM go out at 9 PM the night before. Reps can also answer on their
home page, and lab staff and admins on the Today page. Facebook Messenger can come later, once
the site is online (Meta needs approved message templates for reminders).

The emails only go out while Laravel's **scheduler** runs:

- **XAMPP:** keep a second terminal open in the project folder running `php artisan schedule:work`
  (stop it with `Ctrl+C`). Nothing is sent while your PC is off.
- **Docker:** the `scheduler` container does it.
- **Online:** a cron job that runs `php artisan schedule:run` every minute.

The links in these emails use `APP_URL` from `.env`, so it must be the address people open the
site with (e.g. `http://localhost:8000`, or the real address once it's online).
To try it without waiting, pretend it's another time: `php artisan reminders:send --at="2026-11-05 08:30"`.

## Timetable (a new semester)

Lab staff and admins change the timetable on **Manage → Timetable**, two ways:

1. **Excel:** download the template (the timetable in use, one row per class: Day, Start, End,
   Room, Course code, Course title, Block, Instructor, Note), change it in Excel or Google
   Sheets, and upload it. CSV files work too. Time a room isn't available is a row with
   `BLOCKED` as the course code and the reason in Note. The template's second sheet explains
   the rest.
2. **On the site:** start from a copy of the timetable in use and add, change or delete
   classes day by day. Each room's (or block's) week shows as a grid.

Either way it's a new timetable **in progress**: nothing changes until someone presses
**Replace the timetable** on the check page. The check page shows what changes, what must be
fixed first (a day or time it can't read), things worth a look (two classes in one room at
once, a block in two places), new rooms and blocks it adds, reps whose block has no class in
it, and upcoming bookings that overlap a new class. Those bookings are kept; Manage →
Timetable lists them until they're moved or cancelled. For a new semester, type its name and
dates there too: classes only count between those dates.

Replacing works **right away**, and the timetable it replaces is saved under **Backups**,
where **Bring back…** restores it the same way. A class that stays the same keeps its
face-to-face answers and class-session bookings.

`.xlsx` files need PHP's zip extension (see "Run it on your PC"; the Docker image has it).
Without it, the downloads are CSV files and only CSV files can be uploaded.

## Database

The tables are defined in `database/migrations/` (one file per table) and filled by
the seeders in `database/seeders/`. `database/queries.sql` has example SQL for the
core lookups (room conflicts, free rooms, available equipment).

Names follow Laravel's style. The same 14 tables and the view, renamed:

| Node.js version | Laravel version |
|---|---|
| Department | departments |
| DepartmentConfig | department_configs |
| Term | terms |
| Section | sections |
| Course | courses |
| Room | rooms |
| DepartmentRoom | department_room |
| Users | users |
| ClassSchedule | class_schedules |
| EquipmentCategory | equipment_categories |
| Equipment | equipment |
| Reservation | reservations |
| ReservationEquipment | reservation_equipment |
| OverrideLog | override_logs |
| TimetableView (view) | timetable_view (view) |

Columns follow the same pattern. Each table's key is `id`, and other tables point to it
as `<table>_id`, e.g. `ReservationID` became `reservations.id` and `reservation_equipment.reservation_id`.
Other examples: `StartTime` → `start_time`, `PasswordHash` → `password`,
`ScheduleID` → `class_schedule_id`, `CategoryID` → `category_id`,
`ReviewedBy` → `reviewed_by`, `ChangedByUserID` → `changed_by`, `CreatedAt` → `created_at`.

Added since then: `class_checks` and `reminder_log` (the face-to-face answers and the
reminders already sent), and `timetable_drafts` and `timetable_backups` (a new timetable in
progress, and the timetables it replaced).

Laravel also keeps a few tables of its own: `migrations`, `sessions` (who is logged in),
`password_reset_tokens`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`.

### Adding a department

Copy `database/seeders/ExampleDepartmentSeeder.php` and follow the steps at the top of
that file. It ends with one command, e.g. `php artisan db:seed --class=EceSeeder`.

## Folders

| Path | What's in it |
|---|---|
| `routes/web.php` | Every page address, and who may open it |
| `app/Http/Controllers/` | The code behind each page (login, accounts, home pages, free rooms, booking, webhook) |
| `app/Services/` | Reading the timetable and bookings, saving bookings, and replacing the timetable |
| `app/Support/` | Time-slot math, the booking rules, the account checks, photo resizing, and reading and writing the timetable's Excel and CSV files (no database, so they're easy to test) |
| `app/Notifications/`, `resources/views/mail/` | The emails: connect your email, pick a new password, reminders |
| `app/Console/Commands/`, `routes/console.php` | `reminders:send` and its every-minute schedule |
| `app/Http/Middleware/` | Keeps each role to its own pages, logs out turned-off accounts, asks for a new password first |
| `app/Models/` | One class per table |
| `resources/views/` | HTML templates (Blade) |
| `public/css/` | Styles: `style.css` is the older base; `foundations/` (tokens, fonts, shared components), `layout.css` (shell) and one file per screen group restyle it |
| `database/migrations/` | Table definitions |
| `database/seeders/` | BSCpE data, test accounts, template for a new department |
| `tests/` | Automatic tests (`php artisan test`) |
| `docs/` | Architecture notes, the design system, and the frontend redesign reports. Start at [docs/README.md](docs/README.md); frontend reviewers: [docs/frontend/QA_HANDOFF.md](docs/frontend/QA_HANDOFF.md) |
| `routes/api.php` | Addresses other programs call: the Messenger webhook and `/test-db` |
| `Dockerfile`, `docker-compose.yml`, `docker/` | The Docker setup |
| `storage/app/private/photos/` | Profile photos (never committed) |
| `.env` | Your own settings and secrets. Never committed. |

## Going online (not yet)

The site still runs on one PC. Before it is opened to other people through a tunnel or a proxy (ngrok, a campus
reverse proxy), set **`TRUSTED_PROXIES`** in `.env`.

Why: the login page locks out a network address after too many wrong passwords (5 for one account, 20 in all, per
minute). Behind a tunnel every visitor reaches the site from the tunnel's own address, so they would all share one
count and could lock each other out. `TRUSTED_PROXIES` tells Laravel which address is the tunnel, so it reads each
visitor's real address from it instead.

1. Find the address the tunnel connects from:
   - **XAMPP / `php artisan serve`, ngrok on the same PC:** `127.0.0.1` (add `::1` too: `TRUSTED_PROXIES=127.0.0.1,::1`).
   - **Docker:** open the site once through the tunnel and look at the newest request line in the `docker compose up`
     window. It starts with the address that connected (usually the Docker network's gateway, like `172.18.0.1`).
2. Put it in `.env`, e.g. `TRUSTED_PROXIES=172.18.0.1`, and restart the site (`docker compose up`, or stop and start
   `php artisan serve`).
3. Check it: log in from a phone through the tunnel, then open the `sessions` table in phpMyAdmin. The newest row's
   `ip_address` should be the phone's address, not `127.0.0.1` or `172.…`.

Only exact addresses or ranges work (commas between several). `*` ("trust everyone") is refused on purpose: with it
anyone could fake their address and get around the lockout. Leave the setting empty while the site is only on your PC.

Also before going online: set `APP_DEBUG=false` and `APP_URL` to the real address, and remove or protect `/test-db`.

## Messenger bot (paused)

The webhook from the first version is still at `/webhook`. It reads `VERIFY_TOKEN` and
`PAGE_ACCESS_TOKEN` from `.env`, and writes incoming messages to `storage/logs/laravel.log`
(in Docker, to the `docker compose up` window).
