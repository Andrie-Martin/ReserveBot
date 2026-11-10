#!/usr/bin/env bash
# Chromium regression run for frontend QA round 4, against a throwaway MariaDB container and a local PHP server on 127.0.0.1.
# Never touches your .env, application database or running containers. Needs Docker, PHP and a playwright-core install:
#   tests/Support/qa-round4-browser.sh /path/to/node_modules/playwright-core [output-dir]
set -euo pipefail
cd "$(dirname "$0")/../.."
PW="${1:?path to playwright-core}"; OUT="${2:-${TMPDIR:-/tmp}/rb-qa-round4}"; mkdir -p "$OUT"
DBPORT="${RB_QA_DB_PORT:-33398}"; WEB="${RB_QA_WEB_PORT:-8011}"; NAME="rb-qa4-db-$$"
SECRETS="$(mktemp)"; chmod 600 "$SECRETS"; PASS="$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 24)"
printf 'MARIADB_ROOT_PASSWORD=%s\nMARIADB_DATABASE=reservebot_qa4_test\n' "$PASS" > "$SECRETS"
cleanup() { [ -n "${SRV:-}" ] && kill "$SRV" 2>/dev/null || true; docker rm -f "$NAME" >/dev/null 2>&1 || true; rm -rf "$SECRETS"; }
trap cleanup EXIT INT TERM
docker run -d --name "$NAME" --hostname 127.0.0.1 --env-file "$SECRETS" -p "127.0.0.1:${DBPORT}:3306" mariadb:10.11 >/dev/null
for _ in $(seq 1 60); do docker exec "$NAME" sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "select 1"' >/dev/null 2>&1 && break; sleep 2; done
unset DB_SOCKET DATABASE_URL
export APP_ENV=testing APP_DEBUG=false APP_URL="http://127.0.0.1:$WEB" APP_KEY="base64:$(head -c 32 /dev/urandom | base64)" \
  DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT="$DBPORT" DB_DATABASE=reservebot_qa4_test DB_USERNAME=root DB_PASSWORD="$PASS" DB_URL= \
  CACHE_STORE=array SESSION_DRIVER=file MAIL_MAILER=log LOG_CHANNEL=stderr QUEUE_CONNECTION=sync
php artisan migrate --force --no-interaction >/dev/null
php tests/Support/qa-round4-seed.php
( cd public && exec php -S "127.0.0.1:$WEB" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php >"$OUT/server.log" 2>&1 ) &
SRV=$!
for _ in $(seq 1 30); do curl -fs "http://127.0.0.1:$WEB/login" >/dev/null 2>&1 && break; sleep 1; done
node tests/Support/qa-round4-browser.cjs "$PW" "http://127.0.0.1:$WEB" "$OUT"
