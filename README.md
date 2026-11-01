# ReserveBot

Room and equipment reservation website for the BSCpE Department (CMPE 453 project).
Node.js + Express + EJS, with a MariaDB/MySQL database.

## Run it with Docker (easiest)

You only need **Docker Desktop**. No XAMPP, no Node.js, no `npm install`,
and no importing the database by hand.

1. Install [Docker Desktop](https://www.docker.com/products/docker-desktop/) and open it.
   On Windows the installer sets up WSL 2 for you.
2. Get the code and start everything:
   ```
   git clone https://github.com/Andrie-Martin/ReserveBot.git
   cd ReserveBot
   docker compose up --build
   ```
   The first run downloads a few hundred MB and loads the database, so give it a minute or two.
3. Open:
   - **http://localhost:3000**: the website
   - **http://localhost:8080**: phpMyAdmin, already logged in

| To | Run |
|---|---|
| Stop | `Ctrl+C`, or `docker compose down` in another window |
| See your code changes | `Ctrl+C`, then `docker compose up --build` |
| Reset the database to the `.sql` files | `docker compose down -v`, then `docker compose up --build` |

The `.sql` files only load into an empty database. After someone changes
`database/schema.sql` or `database/bscpe_seed.sql`, run the reset.

If port 3000 is taken, stop any `node app.js` you have running first.

## Test accounts

All use the password `Password123!`

| Email | Role |
|---|---|
| rep1@reservebot.test | Block rep, BSCPE 4-5 (Bea Prieto) |
| rep2@reservebot.test | Block rep, BSCPE 3-1 (Maria Santos) |
| staff@reservebot.test | Lab staff |
| admin@reservebot.test | Admin |

## Run it without Docker (XAMPP + Node.js)

1. In XAMPP, start **MySQL**. In phpMyAdmin's **Import** tab, import
   `database/schema.sql`, then `database/bscpe_seed.sql`.
2. Install Node.js 22 or newer, then run `npm install` in this folder.
3. Create a file named `.env` in this folder:
   ```
   PORT=3000
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_USER=root
   DB_PASSWORD=
   DB_NAME=reservebot
   SESSION_SECRET=any-long-random-text
   ```
4. Run `node app.js` and open http://localhost:3000

**Mixing both:** to edit code quickly with the database in Docker, run
`docker compose up db phpmyadmin`, then `node app.js` with these `.env` values:
`DB_PORT=3307`, `DB_USER=reservebot`, `DB_PASSWORD=reservebot`.

## Folders

| Path | What's in it |
|---|---|
| `app.js` | Starts the server |
| `db.js` | Database connection |
| `middleware/` | Who can open which page |
| `routes/` | Pages and form handlers |
| `views/` | HTML templates (EJS) |
| `public/` | CSS |
| `database/` | `schema.sql`, `bscpe_seed.sql`, example queries, template for adding a department |
