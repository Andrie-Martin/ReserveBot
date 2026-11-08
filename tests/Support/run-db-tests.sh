#!/usr/bin/env bash
# Runs the whole PHPUnit suite INCLUDING the DB-gated HTTP workflow suites (Auth, Availability, Manage), against a
# brand-new disposable MariaDB container. It never needs your .env, never uses your application database, and fails
# closed (exits before migrating or running anything) if the connection it would use is not the throwaway one.
#
#   tests/Support/run-db-tests.sh [phpunit arguments]
#
# A plain `php artisan test` / `phpunit` run skips those suites (see their setUp() guards); use this script, or
# provision an equivalent database yourself, whenever you need to verify them. Requires Docker.
#
# Isolation:
#  * the container name, root password and APP_KEY are generated per run and never printed; the password reaches Docker
#    through a mode-0600 env file and reaches PHP only through the environment (never on a command line);
#  * the database name is fixed and must end in _test; the port must be free and is published on 127.0.0.1 only;
#  * every variable that could redirect the connection (DB_*, DB_SOCKET, DB_URL, DATABASE_URL) is overridden or cleared,
#    and drivers that could reach other services (cache, session, mail, queue) are forced to in-process ones;
#  * before migrating, the booted application must report exactly this host, port and database, no socket, and the server
#    must answer from the container's hostname. Any mismatch aborts.
set -euo pipefail
cd "$(dirname "$0")/../.."

die() { echo "run-db-tests: $*" >&2; exit 1; }

command -v docker >/dev/null 2>&1 || die "Docker is required (it starts the disposable database)."
[ -f vendor/autoload.php ] || die "vendor/ is missing; run composer install first."

PORT="${RB_TEST_DB_PORT:-33399}"
[[ "$PORT" =~ ^[0-9]+$ ]] && [ "$PORT" -ge 1024 ] && [ "$PORT" -le 65535 ] || die "RB_TEST_DB_PORT must be a port between 1024 and 65535."
DB="reservebot_phpunit_test"
case "$DB" in *_test) ;; *) die "the test schema name must end in _test." ;; esac
if (exec 3<>"/dev/tcp/127.0.0.1/$PORT") 2>/dev/null; then
  die "port $PORT is already in use; refusing to guess which server it is. Set RB_TEST_DB_PORT to a free port."
fi

NAME="rb-phpunit-db-$$-$RANDOM"
SECRETS="$(mktemp)"                      # created with mode 0600
chmod 600 "$SECRETS"
PASS="$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 24)"
[ -n "$PASS" ] || die "could not generate a throwaway password."
printf 'MARIADB_ROOT_PASSWORD=%s\nMARIADB_DATABASE=%s\n' "$PASS" "$DB" > "$SECRETS"
APP_KEY_VALUE="base64:$(head -c 32 /dev/urandom | base64)"

cleanup() { docker rm -f "$NAME" >/dev/null 2>&1 || true; rm -f "$SECRETS"; }
trap cleanup EXIT INT TERM

# --hostname 127.0.0.1 makes @@hostname equal the host the suites compare against.
docker run -d --name "$NAME" --hostname 127.0.0.1 --env-file "$SECRETS" -p "127.0.0.1:${PORT}:3306" mariadb:10.11 >/dev/null
ready=0
for _ in $(seq 1 60); do
  if docker exec "$NAME" sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "select 1"' >/dev/null 2>&1; then ready=1; break; fi
  sleep 2
done
[ "$ready" = 1 ] || die "the disposable database did not become ready."

# Nothing from the caller's environment or .env may steer the connection.
unset DB_SOCKET DATABASE_URL DB_READ_HOST DB_WRITE_HOST MYSQL_ATTR_SSL_CA
export APP_ENV=testing APP_DEBUG=false APP_KEY="$APP_KEY_VALUE" \
  DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT="$PORT" DB_DATABASE="$DB" DB_USERNAME=root DB_PASSWORD="$PASS" DB_URL= DB_SOCKET= \
  CACHE_STORE=array SESSION_DRIVER=array MAIL_MAILER=array QUEUE_CONNECTION=sync \
  RB_AVAILABILITY_HTTP_TESTS=1 RB_AVAILABILITY_TEST_DATABASE="$DB" RB_AVAILABILITY_TEST_HOST=127.0.0.1 \
  RB_AUTH_HTTP_TESTS=1 RB_AUTH_TEST_DATABASE="$DB" \
  RB_MANAGE_HTTP_TESTS=1 RB_MANAGE_TEST_DATABASE="$DB"

# Fail closed: prove the application will talk to this container and nothing else. Prints no credentials.
PORT_EXPECTED="$PORT" DB_EXPECTED="$DB" php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c = $app["config"]->get("database.connections.mysql");
$ok = $app["config"]->get("database.default") === "mysql"
  && ($c["host"] ?? null) === "127.0.0.1" && (string) ($c["port"] ?? "") === getenv("PORT_EXPECTED")
  && ($c["database"] ?? null) === getenv("DB_EXPECTED") && substr((string) $c["database"], -5) === "_test"
  && empty($c["unix_socket"]) && empty($c["url"]);
if (! $ok) { fwrite(STDERR, "configuration does not point at the disposable database\n"); exit(1); }
$row = $app["db"]->connection()->selectOne("select @@hostname as h, database() as d");
if ($row->h !== "127.0.0.1" || $row->d !== getenv("DB_EXPECTED")) { fwrite(STDERR, "the server that answered is not the disposable database\n"); exit(1); }
echo "isolation check passed: disposable MariaDB on 127.0.0.1:" . getenv("PORT_EXPECTED") . ", schema " . $row->d . "\n";
' || die "isolation check failed; nothing was migrated or run."

php artisan migrate --force --no-interaction >/dev/null
vendor/bin/phpunit "$@"
