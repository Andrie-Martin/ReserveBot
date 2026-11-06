#!/usr/bin/env bash
# Runs the whole PHPUnit suite INCLUDING the DB-gated HTTP workflow suites (Auth, Availability), against a brand-new
# disposable MariaDB container. Nothing here touches the application's database, .env or docker-compose files.
#
#   tests/Support/run-db-tests.sh
#
# A plain `php artisan test` / `phpunit` run skips those suites (see their setUp() guards); use this script, or
# provision an equivalent database yourself, whenever you need to verify them.
set -euo pipefail
cd "$(dirname "$0")/../.."

NAME="rb-phpunit-db-$$"
PORT="${RB_TEST_DB_PORT:-33399}"
DB="reservebot_phpunit_test"          # must end in _test: the suites assert it
PASS="phpunit-$$"

cleanup() { docker rm -f "$NAME" >/dev/null 2>&1 || true; }
trap cleanup EXIT

# --hostname 127.0.0.1 makes @@hostname equal the host the suites compare against.
docker run -d --name "$NAME" --hostname 127.0.0.1 -e MARIADB_ROOT_PASSWORD="$PASS" -e MARIADB_DATABASE="$DB" \
  -p "127.0.0.1:${PORT}:3306" mariadb:10.11 >/dev/null
for _ in $(seq 1 60); do
  docker exec "$NAME" mariadb -uroot -p"$PASS" -e 'select 1' >/dev/null 2>&1 && break
  sleep 2
done

export APP_ENV=testing DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT="$PORT" DB_DATABASE="$DB" \
  DB_USERNAME=root DB_PASSWORD="$PASS" DB_URL= \
  RB_AVAILABILITY_HTTP_TESTS=1 RB_AVAILABILITY_TEST_DATABASE="$DB" RB_AVAILABILITY_TEST_HOST=127.0.0.1 \
  RB_AUTH_HTTP_TESTS=1 RB_AUTH_TEST_DATABASE="$DB"

php artisan migrate --force --no-interaction >/dev/null
vendor/bin/phpunit "$@"
