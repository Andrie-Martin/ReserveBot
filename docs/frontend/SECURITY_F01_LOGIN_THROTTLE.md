# Security correction F-01: login throttling

**Contents.** The original Phase 12 implementation (`c74c9b4`) is described first, as shipped. Three follow-up corrections made after the independent security audit (`/tmp/reservebot-phase12-review.md`) come after it, each in its own commit: hashed keys (`305d0e2`, a cherry-pick of Phase 13's `553e0b3`), one counter for every spelling of an account (`404ffdd`) and atomic counting (`47fc419`). The sections "Current behaviour", "Follow-up fixes" and "Remaining risks" describe the code as it is now.

Branch `feat/frontend-design-12-security` (from `feat/frontend-design-11-qa-fixes`). Source: `docs/frontend/INDEPENDENT_QA_REPORT.md`, F-01 (`POST /login` accepted unlimited wrong passwords). This is a reviewed backend change to authentication, made at the user's request. F-07 and all other findings are untouched.

## What existed

- `LoginController::login` called `Auth::attempt` with no limit. `routes/web.php` had no `throttle` on `POST /login`.
- The app already throttles forgot-password, reset-password and email links with named limiters in `AppServiceProvider` (per IP). The cache store drives them; nothing there covers login.

## Original implementation, as shipped in `c74c9b4` (`LoginController.php`, +27 lines)

Laravel's native `Illuminate\Support\Facades\RateLimiter`; no new dependency, route or middleware.

| Counter | Key | Limit |
|---|---|---|
| Per account and IP | `login\|<lower-cased, transliterated email>\|<ip>` (raw text; see follow-up 1) | 5 failures per 60 s |
| Per IP | `login-ip\|<ip>` | 20 failures per 60 s |

- Only **failed** attempts are counted (`RateLimiter::hit`). The check runs **before** `Auth::attempt`, so a correct password is also refused while locked. (The original checked first and counted after the password check; see follow-up 3.)
- Keys use the **typed** email, never whether the account exists, so a real and a made-up address behave identically (same counter, same wording, same `Retry-After`). The existing generic "Wrong email or password." message is unchanged.
- The email is trimmed, lower-cased and transliterated before keying, so `" ALICE@x "` and `alice@x` share one counter. (This did not cover every spelling the database accepts; see follow-up 2.)
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

## Results of the original implementation (`c74c9b4`)

| Run | Result |
|---|---|
| Gated, disposable MariaDB 10.11 | 119 tests, **114 passed, 0 failures, 0 errors**, 5 skipped (no GD/zip) |
| Default (gates off, SQLite memory) | 119 tests, 81 passed, 38 skipped (DB-gated by design), 0 failures |
| Before this change | 112 tests, 107 passed (gated) |

The 2 "risky" flags came from the Phase 11 base and are not caused by this change (current `dev` has none).

## Live check (disposable MariaDB + `php artisan serve` on 127.0.0.1:8124, real file cache, real CSRF)

- Staff, wrong password ×6: attempts 1–5 "Wrong email or password."; attempt 6 "Too many login attempts. Please try again in 59 seconds." with `Retry-After: 59`.
- Correct password while locked: throttled (no session).
- Unknown email ×6: identical sequence and wording.
- Different account from the same IP, correct password: logs in (`/rep`).
- POST without a token: 419.

Screenshots (scratchpad `/tmp/claude-1000/-home-james-projects-ReserveBot/0274b1c5-723b-4aff-a775-d464c9ab2059/scratchpad/f01/`): `throttled-1438x900.png`, `throttled-390x844.png`. The message sits under the email field in the existing error style at both sizes.

## Isolation

New `mariadb:10.11` containers and an environment-overridden dev server only. The project's Docker stack (`reservebot-db-1`, :8000), its database, `.env` and compose files were not used or modified; `docker-compose.yml` and `.save` were not staged. The disposable containers and server were removed afterwards.

## Limits of the original implementation (superseded where noted below)

- 5 per minute and 20 per minute are conservative defaults; adjust the three constants at the top of `LoginController` if needed.
- The window is a fixed 60 s counted from the first failure (Laravel's `RateLimiter::hit`), not a long lockout; a determined attacker is slowed to about 5 guesses per minute per account per IP, not stopped. A distributed attack across many IPs against one account is not limited by this change.
- The cache store must be shared across app instances in production (the default `database` store is; `array` is not).
- Not covered: throttling on the other auth endpoints (already limited), account-level lockout across IPs, CAPTCHA.

## Follow-up fixes after the independent audit

### 1. Hashed limiter keys (`305d0e2`, cherry-picked from Phase 13's `553e0b3`)

The commit touches only the limiter key, one feature test, a new unit test and this document, so it was cherry-picked unchanged (`-x`); the Phase 13 branch was not modified and no booking-guard change is included. The account key became `login|sha256(...)|<ip>`, built by `LoginController::accountKey()`.

Why it was needed (audit findings F-2 and F-3): the raw key contained the typed address, and anything typed into the email box (a mistyped password, someone else's address) was written in plaintext to the persistent `cache` table, where the row stayed after the window ended because nothing prunes the table. With the production `database` cache store (`cache.key` is `varchar(255)`) a very long padded address also made the key too long; the audit observed the per-account counter simply not being stored (12 wrong attempts with a 245-character address were never throttled under MariaDB's strict SQL mode), while the IP counter still counted.

Verified live on the database cache store after typing an email, a password-like string, a made-up address, a 300-character padded address and 5000 characters: 5 account counters, each `login|<64 hex characters>|<ip>`, the longest key 100 characters, **0 rows containing any typed fragment** (`@`, `hunter2`, `jane`, the domain). Thresholds, window and messages are unchanged.

### 2. One counter for every spelling of an account (`404ffdd`, audit finding F-1)

**Root cause.** `users.email` is compared with `utf8mb4_unicode_ci` (checked with `information_schema`). Under that collation zero-width and bidi characters, control characters and combining marks carry no weight, so these insertions inside the address still match the same account: U+200B, U+200C, U+200D, U+2060, U+FEFF, U+200E, U+202E, U+0001, U+007F, U+0300, U+0301, U+034F, U+180E (accented, full-width and upper-case spellings match too). The limiter only lower-cased, trimmed and transliterated the typed text, so each such spelling got its own counter (`rep2 @x` and `rep2  @x` were different keys). Reproduced live: with `rep2@audit.test` locked, `rep2<ZWSP>@audit.test` returned ordinary wrong-password answers, 20 guesses in 4 variants never reached the account limit, and **the correct password signed in during the lockout**. (Leading and trailing invisible characters are trimmed by Laravel's `TrimStrings`, so only characters inside the address matter.)

**Fix.** The login now finds the account with the same lookup `Auth::attempt` uses and keys the counter on its id, hashed (`sha256('account:<id>')`), so every spelling the database accepts shares one bucket by construction. An address that matches nothing is keyed on its canonical form: lower-cased, transliterated, and with all format, control, combining-mark and space characters removed, so spellings of a made-up address also share one counter, exactly like a real one (otherwise a prober could tell real from made-up addresses by whether variants share a counter). Stored emails and authentication rules are not changed; no email or account id appears in the cache.

**Verified live** (database cache store, real HTTP): after 6 wrong attempts, the correct password through ZWNJ, word joiner, control character, combining accent, upper-case with spaces, `rép2` and full-width spellings is refused; five different spellings of one address use one counter and the sixth is refused; the same holds for a made-up address; a different account, typed with the same decoration, is not merged and still signs in; staff and admin sign in normally from the same IP.

**Regression tests** (gated, real MariaDB collation): `test_the_database_really_treats_these_spellings_as_the_same_account` (guards against a vacuous test), `test_no_alternate_spelling_can_bypass_an_active_lockout` (13 spellings, wrong and right password, guest afterwards), `test_failures_through_different_spellings_share_one_counter`, `test_spellings_of_an_unknown_address_behave_exactly_like_those_of_a_real_one`, `test_different_accounts_are_never_merged_by_the_spelling_rules`, `test_limiter_keys_hold_no_typed_address_or_password_and_fit_the_cache_column`; unit tests for canonical form, id-keyed buckets and malformed input. With the previous controller 6 of these fail.

### 3. Atomic counting (`47fc419`, audit finding F-4)

**Reproduction** (disposable MariaDB, database cache, `php -S` with 12 real workers, barrier-released bursts): checking first and counting after the ~220 ms password check let simultaneous requests all pass the check.

| Burst | Limit | Original (checked, then counted) | RateLimiter::increment first | Final |
|---|---|---|---|---|
| 40 parallel wrong passwords, one account | 5 | 13, 13, 25 (audit); 13, 12, 13 (this pass) | 9, 9, 9 | **5, 5, 5** |
| 60 parallel wrong passwords, 60 emails, one IP | 20 | 24, 27 (audit); 32, 35 (this pass) | 20, 22 | **20, 20, 20** |

**Fix.** Attempts are counted first with the cache's atomic `add` and `increment` (row lock in the database store), and only the first N reach the password check. Laravel's own `RateLimiter::increment()` was tried first and is not exact: when two requests create a counter together, the loser writes the value 1 back and erases increments the others made (9 allowed instead of 5), so the controller uses the cache directly with the same key and `:timer` layout (so `availableIn()` and `clear()` keep working). A success clears the account counter and gives its IP slot back; a refusal charges neither counter and hammering a locked account does not raise the IP counter, so the thresholds still mean "failures" and a locked account cannot push a shared IP towards its limit.

**Cost.** One extra indexed lookup and two cache operations per attempt. Single-login latency, median of 12: wrong password 224 -> 230 ms, correct password 177 -> 203 ms (bcrypt cost 12 dominates). Legitimate bursts: 12 simultaneous correct logins over 5 accounts from one IP all signed in; 3 simultaneous same-account submissions all signed in; counters afterwards: account cleared, IP at 0.

**Residual.** Attempts in flight are counted until they finish, so more than 5 *simultaneous* logins for one account can be refused even with the right password (8 parallel: 6 signed in, 2 refused), and more than 20 simultaneous attempts from one IP likewise. This needs more simultaneous requests than the server has workers in normal use. Counting is exact for the shared `database` store; a per-process store such as `array` or `file` without locking is not suitable.

**Tests** (gated): successes do not use the IP allowance (19 failures + 3 logins, the 20th failure is still allowed, the 21st refused); a refusal for the IP is not charged to the account or the IP counter; hammering a locked account does not raise the IP counter. True concurrency cannot be reproduced inside one PHPUnit process, so it was verified with the multi-worker bursts above (manual, not automated).

## Current behaviour

| Counter | Key | Limit |
|---|---|---|
| Per account and IP | `login\|sha256(account:<id> or typed:<canonical address>)\|<ip>` | 5 failures per 60 s |
| Per IP | `login-ip\|<ip>` | 20 failures per 60 s |

Unchanged from the original: thresholds, the fixed 60 s window from the first failure, wording, `Retry-After`, `Lockout` event, CSRF, session regeneration, `is_active` handling and the role redirects. Verified again after the fixes on the corrected branch (real HTTP): the 6th failure for an account and the 21st for an IP are refused; a correct password is refused while locked; recovery after the window (still locked at 30 s, signed in at about 63 s); a success clears the account counter (5 more failures allowed, the 6th refused); IP counter not reduced by successes; other account and other IP unaffected; spoofed `X-Forwarded-For`, `X-Real-IP` and `Forwarded` headers do not change the counted IP; CSRF 419 and not counted.

Authentication regression (same seeded database, corrected branch against `origin/dev`): **0 differences** over 108 route-by-role cells and 22 flow checks (logins and redirects for rep, lab staff and admin, session regeneration, onboarding, logout and cookie replay, deactivated user, generic messages, forgot and reset password). Password reset: token consumed, old password rejected, new password signs in, token not reusable.

## Test results (follow-up pass)

| Tree | Run | Result |
|---|---|---|
| Phase 12 corrected (`feat/frontend-design-12-security`) | gated, disposable MariaDB, all gates | 135 tests, **130 passed, 0 failures, 0 errors**, 5 skipped (photo tests, no GD), 2 risky (inherited from the Phase 11 base) |
| Phase 12 corrected | default (gates off) | 135 tests, 87 passed, 48 skipped (DB-gated by design), 0 failures, 2 risky |
| `origin/dev` (`5c14c79`) + Phase 12 corrected (temporary merge, no conflicts, no overlapping files) | gated | 139 tests, **134 passed, 0 failures, 0 errors**, 5 skipped, **0 risky** |
| dev + Phase 12 | default | 139 tests, 88 passed, 51 skipped, 0 failures, 0 risky |
| dev + Phase 12 | gated, reverse order and random seed 7 | 134 passed each |

23 tests were added to the original 112: 17 in `AuthWorkflowTest` (7 original, 1 long-address, 9 for spellings, keys and counting) and 6 in `tests/Unit/LoginThrottleKeyTest.php`.

## Remaining risks and limits

- **Distributed attacks** across many IPs against one account are not limited (the per-account counter includes the IP). There is no account-wide lockout, CAPTCHA or alerting; the `Lockout` event has no listener, so nothing is logged.
- **Shared IP.** The limiter uses `$request->ip()`; the app configures no trusted proxies (not changed here). Behind a reverse proxy, Docker/WSL gateway or campus NAT, everyone is one address: 20 failures per minute from anyone refuse every login from that address, valid passwords included, and an attacker on the same address can lock one account for that address for up to 60 s. Configure trusted proxies before production.
- **IPv6.** The key uses the full address with no /64 grouping, so a client with a /64 can rotate source addresses to get fresh counters, and an IPv4-mapped address (`::ffff:a.b.c.d`) is a different key from plain IPv4. Not changed (needs a policy decision and interacts with the proxy settings).
- **In-flight counting** (residual of follow-up 3): more than 5 simultaneous correct logins for one account, or 20 from one IP, can be refused.
- **A locked-out owner** stays blocked for the rest of the 60 s window even after a password reset or with the right password.
- **Canonical form for unknown addresses** only approximates the collation (known accounts use the exact database lookup). A made-up address spelled with some exotic collation equivalence could get a separate counter; this has no effect on real accounts.
- **Cache table housekeeping.** Counter rows are hashed (no personal data except the client IP) but expired rows are not purged by anything scheduled; a periodic delete of expired `cache` rows is advisable.
- **Response shape.** A lockout is a 302 back to `/login` with `Retry-After`, not a 429.
- **Test limits.** PHPUnit uses the array cache (the suites require it), so the persistent store, key length and concurrency were verified manually against the disposable database and are not automated; nothing pins the 60 s window length beyond `travel(61)`.
- **Deployment.** `APP_DEBUG=true` is still the default in `.env.example` and compose; the cache store must be shared across instances.
