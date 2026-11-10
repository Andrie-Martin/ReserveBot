#!/usr/bin/env bash
# Chromium checks for the animated login mascot, against a local PHP server on 127.0.0.1 with a throwaway in-memory SQLite database
# and a temporary session folder. No Docker, no .env, no application database. Needs PHP and a playwright-core install:
#   tests/Support/login-mascot-browser.sh /path/to/node_modules/playwright-core [output-dir]
set -euo pipefail
cd "$(dirname "$0")/../.."
PW="${1:?path to playwright-core}"; OUT="${2:-${TMPDIR:-/tmp}/rb-login-mascot}"; mkdir -p "$OUT"
WEB="${RB_MASCOT_WEB_PORT:-8021}"; SESS="$(mktemp -d)"
cleanup() { [ -n "${SRV:-}" ] && kill "$SRV" 2>/dev/null || true; rm -rf "$SESS"; }
trap cleanup EXIT INT TERM
unset DB_SOCKET DATABASE_URL
export APP_ENV=testing APP_DEBUG=false APP_URL="http://127.0.0.1:$WEB" APP_KEY="base64:$(head -c 32 /dev/urandom | base64)" \
  DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= CACHE_STORE=array SESSION_DRIVER=file SESSION_FILES="$SESS" MAIL_MAILER=log LOG_CHANNEL=stderr QUEUE_CONNECTION=sync
( cd public && exec php -S "127.0.0.1:$WEB" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php >"$OUT/server.log" 2>&1 ) &
SRV=$!
for _ in $(seq 1 30); do curl -fs "http://127.0.0.1:$WEB/login" >/dev/null 2>&1 && break; sleep 1; done
node tests/Support/login-mascot-browser.cjs "$PW" "http://127.0.0.1:$WEB" "$OUT"
