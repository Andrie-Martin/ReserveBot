# Security correction F-01: login throttling

Branch `feat/frontend-design-12-security` (from `feat/frontend-design-11-qa-fixes`). Source: `docs/frontend/INDEPENDENT_QA_REPORT.md`, F-01 (`POST /login` accepted unlimited wrong passwords). This is a reviewed backend change to authentication, made at the user's request. F-07 and all other findings are untouched.

## What existed

- `LoginController::login` called `Auth::attempt` with no limit. `routes/web.php` had no `throttle` on `POST /login`.
- The app already throttles forgot-password, reset-password and email links with named limiters in `AppServiceProvider` (per IP). The cache store drives them; nothing there covers login.

## Implementation (`app/Http/Controllers/Auth/LoginController.php`, +27 lines)

Laravel's native `Illuminate\Support\Facades\RateLimiter`; no new dependency, route or middleware.

| Counter | Key | Limit |
|---|---|---|
| Per account and IP | `login\|<lower-cased, transliterated email>\|<ip>` | 5 failures per 60 s |
| Per IP | `login-ip\|<ip>` | 20 failures per 60 s |

- Only **failed** attempts are counted (`RateLimiter::hit`). The check runs **before** `Auth::attempt`, so a correct password is also refused while locked.
- Keys use the **typed** email, never whether the account exists, so a real and a made-up address behave identically (same counter, same wording, same `Retry-After`). The existing generic "Wrong email or password." message is unchanged.
- The email is trimmed, lower-cased and transliterated before keying, so `" ALICE@x "` and `alice@x` share one counter.
- The per-IP counter stops one address trying many accounts (password spraying) without letting one locked account block other users.
- A successful login clears the account counter. Session regeneration, `is_active` check and the redirect to the role home are unchanged.
- Throttled reply: redirect back to `/login` with the old email, the error `Too many login attempts. Please try again in N seconds.` on the existing email error slot, a `Retry-After` header, and Laravel's `Lockout` event. The login view is **unchanged**: it already renders that error slot.
- CSRF is unaffected (still 419 without a token; checked live).

Behind a proxy the counter uses `$request->ip()`. If production sits behind a reverse proxy, configure trusted proxies so the real client IP is used; otherwise every visitor shares one IP counter. This is a deployment note, not a code change.

## Tests (`tests/Feature/AuthWorkflowTest.php`, 7 new, DB-gated like the rest of that suite)

| Test | Covers |
|---|---|
| repeated failures throttled; right password blocked while locked | 5 plain failures, 6th throttled, case/space variants share the counter |
| unknown accounts throttled identically | no enumeration: same wording and `Retry-After` for real and made-up email |
| one locked account or IP does not lock others | independent users; same account from another IP still works |
| success clears the count, keeps session behaviour | 4 failures, login, new session id, 4 more failures not throttled |
| login works again after the retry window | `travel(61)->seconds()`, then success; `Retry-After` ≤ 60 |
| one IP spraying many emails | 21st attempt throttled, other IP unaffected |
| throttled visitor sees the retry message once | message rendered on the login page, gone on the next view |

**Negative check:** with the original controller restored, 6 of the 7 fail (the "success clears the count" test is a preservation check and passes either way). With the fix restored all pass.

## Results

| Run | Result |
|---|---|
| Gated, disposable MariaDB 10.11 via `tests/Support/run-db-tests.sh` | 119 tests, **114 passed, 0 failures, 0 errors**, 5 skipped (no GD/zip) |
| Default (gates off, SQLite memory) | 119 tests, 81 passed, 38 skipped (DB-gated by design), 0 failures |
| Before this change (previous pass) | 112 tests, 107 passed (gated) |

The 2 pre-existing "risky" flags are unchanged.

## Live check (disposable MariaDB + `php artisan serve` on 127.0.0.1:8124, real file cache, real CSRF)

- Staff, wrong password ×6: attempts 1–5 "Wrong email or password."; attempt 6 "Too many login attempts. Please try again in 59 seconds." with `Retry-After: 59`.
- Correct password while locked: throttled (no session).
- Unknown email ×6: identical sequence and wording.
- Different account from the same IP, correct password: logs in (`/rep`).
- POST without a token: 419.

Screenshots (scratchpad `/tmp/claude-1000/-home-james-projects-ReserveBot/0274b1c5-723b-4aff-a775-d464c9ab2059/scratchpad/f01/`): `throttled-1438x900.png`, `throttled-390x844.png`. The message sits under the email field in the existing error style at both sizes.

## Isolation

New `mariadb:10.11` containers and an environment-overridden dev server only. The project's Docker stack (`reservebot-db-1`, :8000), its database, `.env` and compose files were not used or modified; `docker-compose.yml` and `.save` were not staged. The disposable containers and server were removed afterwards.

## Limits and decisions for review

- 5 per minute and 20 per minute are conservative defaults; adjust the three constants at the top of `LoginController` if needed.
- The window is a fixed 60 s counted from the first failure (Laravel's `RateLimiter::hit`), not a long lockout; a determined attacker is slowed to about 5 guesses per minute per account per IP, not stopped. A distributed attack across many IPs against one account is not limited by this change.
- The cache store must be shared across app instances in production (the default `database` store is; `array` is not).
- Not covered: throttling on the other auth endpoints (already limited), account-level lockout across IPs, CAPTCHA.
