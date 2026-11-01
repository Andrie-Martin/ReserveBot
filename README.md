# ReserveBot

Room and equipment reservation website for the BSCpE Department (CMPE 453 project).
Built with Laravel (PHP) and Blade pages, on a MariaDB/MySQL database.

The earlier Node.js version is saved on the `node-version` branch.

## Run it with Docker (easiest)

You only need **Docker Desktop**. No XAMPP, PHP, Composer, or importing anything by hand.

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
| Reset the database (rebuilds it with the test data) | `docker compose down -v`, then `docker compose up --build` |

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

All use the password `Password123!`

| Email | Role |
|---|---|
| rep1@reservebot.test | Block rep, BSCPE 4-5 (Bea Prieto) |
| rep2@reservebot.test | Block rep, BSCPE 3-1 (Maria Santos) |
| staff@reservebot.test | Lab staff |
| admin@reservebot.test | Admin |

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
| `app/Http/Controllers/` | The code behind each page (login, rep, staff, admin, webhook) |
| `app/Http/Middleware/RequireRole.php` | Keeps each role to its own pages |
| `app/Models/` | One class per table |
| `resources/views/` | HTML templates (Blade) |
| `public/css/style.css` | Styles |
| `database/migrations/` | Table definitions |
| `database/seeders/` | BSCpE data, test accounts, template for a new department |
| `routes/api.php` | Addresses other programs call: the Messenger webhook and `/test-db` |
| `Dockerfile`, `docker-compose.yml`, `docker/` | The Docker setup |
| `.env` | Your own settings and secrets. Never committed. |

## Messenger bot (paused)

The webhook from the first version is still at `/webhook`. It reads `VERIFY_TOKEN` and
`PAGE_ACCESS_TOKEN` from `.env`, and writes incoming messages to `storage/logs/laravel.log`
(in Docker, to the `docker compose up` window).
