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
- PHP's **zip** extension turned on: in the XAMPP Control Panel, Apache row, click
  **Config** → **PHP (php.ini)**, find `;extension=zip`, delete the `;`, and save

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
| Run the automatic tests | `php artisan test` |

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
| `app/Services/` | Reading the timetable and bookings, and saving bookings |
| `app/Support/` | Time-slot math, the booking rules, the account checks and photo resizing (no database, so they're easy to test) |
| `app/Notifications/` | The emails: connect your email, pick a new password |
| `app/Http/Middleware/` | Keeps each role to its own pages, logs out turned-off accounts, asks for a new password first |
| `app/Models/` | One class per table |
| `resources/views/` | HTML templates (Blade) |
| `public/css/style.css` | Styles |
| `database/migrations/` | Table definitions |
| `database/seeders/` | BSCpE data, test accounts, template for a new department |
| `tests/` | Automatic tests (`php artisan test`) |
| `routes/api.php` | Addresses other programs call: the Messenger webhook and `/test-db` |
| `Dockerfile`, `docker-compose.yml`, `docker/` | The Docker setup |
| `storage/app/private/photos/` | Profile photos (never committed) |
| `.env` | Your own settings and secrets. Never committed. |

## Messenger bot (paused)

The webhook from the first version is still at `/webhook`. It reads `VERIFY_TOKEN` and
`PAGE_ACCESS_TOKEN` from `.env`, and writes incoming messages to `storage/logs/laravel.log`
(in Docker, to the `docker compose up` window).
