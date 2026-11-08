# Security correction F-01: login throttling

**Contents.** The original Phase 12 implementation (`c74c9b4`) is described first, as shipped. Four follow-up corrections made after the independent security audit (`/tmp/reservebot-phase12-review.md`) come after it, each in its own commit: hashed keys (`305d0e2`, a cherry-pick of Phase 13's `553e0b3`), one counter for every spelling of an account (`404ffdd`), atomic counting (`47fc419`) and, last, turns and in-flight admission so that valid logins neither use up nor wait on the failure allowance (`f72709f`). The sections "Current behaviour", "Follow-up fixes" and "Remaining risks" describe the code as it is now; follow-up 3 describes an intermediate design that follow-up 4 replaced.

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

**Residual (found in the final concurrency review, fixed by follow-up 4).** Because every attempt was counted *before* the password check, attempts in flight, including correct logins, used the failure allowance until they finished: 8 simultaneous correct logins for one account had up to 3 refused, 12 had up to 7 refused, and with 19 failures on an IP 2 of 3 simultaneous correct logins were refused.

**Tests** (gated): successes do not use the IP allowance (19 failures + 3 logins, the 20th failure is still allowed, the 21st refused); a refusal for the IP is not charged to the account or the IP counter; hammering a locked account does not raise the IP counter. True concurrency cannot be reproduced inside one PHPUnit process, so it was verified with the multi-worker bursts above (manual, not automated).

### 4. Valid logins do not use up or wait on the allowance (`f72709f`)

**Question.** Does counting attempts before authentication make legitimate logins consume the failure quota or block one another? **Yes**, while they are in flight. Measured on a 12-worker `php -S` server with the database cache (3 repeats each unless noted), design of follow-up 3:

| Scenario (all simultaneous) | Policy | Follow-up 3 | Follow-up 4 |
|---|---|---|---|
| 8 correct logins, one account, no failures | all sign in | 5 / 8 / 7 signed in | **8, 8, 8** |
| 12 correct logins, one account (2 repeats) | all sign in | 9 and 5 signed in | **12, 12** |
| 5 wrong + 5 correct, one account | correct ones sign in, at most 5 wrong checked | 3-4 correct signed in, others refused | **5 correct signed in, 5 wrong checked** |
| 6 wrong + 6 correct, one account | correct ones sign in | 3-4 correct signed in | **6 correct signed in** |
| 40 wrong, one account | exactly 5 checked | 5 | **5** (3 repeats) |
| 60 wrong across 60 emails, one IP | exactly 20 checked | 20 | **20** (2 repeats) |
| 30 correct over 5 accounts, one IP | all sign in | 30 | **30** |
| 10 earlier IP failures + 15 correct logins | all sign in | 15 | **15** |
| 19 earlier IP failures + 3 correct logins | all sign in | 1 of 3 (both runs) | **3 of 3** |
| 19 earlier IP failures + 10 wrong | exactly 1 checked | 1 | **1** |
| 15 wrong (distinct emails) + 15 correct, one IP | all correct sign in | | **15 and 15** |

**Design.**
- *Account counter.* Attempts for one account (from one IP) take turns under a cache lock (`Cache::lock`, 10 s, polled every 50 ms, up to 5 s of waiting). Inside the turn the counter holds exactly the failures so far: it is checked, the password is verified, and one is added only if the check fails. Simultaneous wrong guesses cannot exceed 5 (the 6th waits its turn, sees 5 and is refused at once); a correct login never uses allowance and so cannot refuse another. Different accounts never wait for each other. A turn that cannot be had in 5 s is refused (fail closed). The turn is released on every path.
- *IP counter.* Shared by many accounts, so it is not serialized (everyone behind one address would wait for each other). Two numbers are kept: recorded failures (`login-ip|<ip>`, failures only, exactly as in the original) and attempts in flight (`login-ip|<ip>:inflight`, short-lived). An attempt is admitted when failures + in-flight (itself included) is at most 20, which holds even if all of them fail; an attempt that does not fit yet waits up to 3 s for the in-flight ones to settle (each either succeeds, leaving no trace, or fails and is recorded) instead of being refused, and is refused immediately once 20 failures are recorded. The in-flight number is read after it is incremented and failures are recorded before it is decremented, so no attempt can be missed. A successful login never uses an IP slot, so successes are not refunded either.
- Behaviour kept: thresholds 5 and 20, 60 s window from the first failure, wording, `Retry-After`, `Lockout` event, hashed account-identity keys, CSRF (419, not counted), session regeneration, onboarding, reset and authorization (re-checked live: 7 wrong gives 5 + refusals with `Retry-After`, correct password refused while locked, the zero-width and upper-case spellings refused while locked, success clears the account counter and 5 more failures are allowed, reset-password works, 0 cache rows or keys contain typed text, longest key 100 characters, no lock or in-flight counter left behind).

**Cost.** Single login, median of 20: wrong password 236 ms, correct 210 ms (previous design 230 / 203 ms). Waiting is only for simultaneous attempts on the *same* account: 2 simultaneous correct logins (a double submit) finish in a median of 393 ms (slowest 518 ms); 12 simultaneous correct logins on one account take turns, median 1.8 s and slowest 3.3 s; 40 simultaneous wrong guesses on one account are all answered within 1.8 s (the first 5 are checked, the rest refused at once); 12 correct logins on 5 different accounts: median 435 ms.

**Tests** (gated): the turn and the IP slot are released after a failure, a success, an account refusal and empty input; 12 successful logins in a row leave the whole allowance of 5 and only 5 failures on the IP counter; correct logins succeed with 19 failures on the IP and the 21st failure is still refused; an attempt that cannot get its account turn is refused, not let through; attempts in flight count against the IP limit (15 failures + 6 in flight: refused; + 4: admitted). They fail against the previous design and against mutants that never release the lock or the slot. True concurrency cannot be reproduced inside one PHPUnit process, so the table above is a manual measurement.

**Trade-offs.** (1) Same-account simultaneous logins are serialized, so the 12th of 12 waits about 3 s; a normal double submit waits about 0.4 s. (2) A request waiting for a turn or an IP slot holds a PHP worker for up to 5 s (turn) or 3 s (IP admission); the waits end as soon as the attempts ahead finish, and under attack the refusals are immediate once the limit is recorded. (3) The lock needs a cache store with locks (the `database` store and its `cache_locks` table, Redis, array; not suitable for a per-process store such as `file` without locking). (4) The IP limit remains exact; the only legitimate logins that can still be delayed are those that arrive while failures + in-flight attempts from the same IP would exceed 20 (they wait up to 3 s, and are refused only if the others do not settle).

## Current behaviour

| Counter | Key | Limit |
|---|---|---|
| Per account and IP | `login\|sha256(account:<id> or typed:<canonical address>)\|<ip>` | 5 failures per 60 s |
| Per IP | `login-ip\|<ip>` | 20 failures per 60 s |

Flow now: take the account turn; refuse if 5 failures are recorded; admit by IP; verify the password; on failure record one failure on the account and the IP; on success clear the account counter; release. Unchanged from the original: thresholds, the fixed 60 s window from the first failure, wording, `Retry-After`, `Lockout` event, CSRF, session regeneration, `is_active` handling and the role redirects. Verified again after the fixes on the corrected branch (real HTTP): the 6th failure for an account and the 21st for an IP are refused; a correct password is refused while locked; recovery after the window (still locked at 30 s, signed in at about 63 s); a success clears the account counter (5 more failures allowed, the 6th refused); IP counter not reduced by successes; other account and other IP unaffected; spoofed `X-Forwarded-For`, `X-Real-IP` and `Forwarded` headers do not change the counted IP; CSRF 419 and not counted.

Authentication regression (same seeded database, corrected branch against `origin/dev`): **0 differences** over 108 route-by-role cells and 22 flow checks (logins and redirects for rep, lab staff and admin, session regeneration, onboarding, logout and cookie replay, deactivated user, generic messages, forgot and reset password). Password reset: token consumed, old password rejected, new password signs in, token not reusable.

## Test results (follow-up passes, final state)

| Tree | Run | Result |
|---|---|---|
| Phase 12 (`feat/frontend-design-12-security`, `f72709f`) | gated, disposable MariaDB, all gates | 140 tests, **135 passed, 0 failures, 0 errors**, 5 skipped (photo tests, no GD), 2 risky (inherited from the Phase 11 base) |
| Phase 12 | default (gates off) | 140 tests, 87 passed, 53 skipped (DB-gated by design), 0 failures, 2 risky |
| `origin/dev` (`5c14c79`) + Phase 12 (temporary merge, parents verified, no conflicts, no overlapping files; only the 4 Phase 12 files differ from dev) | gated | 144 tests, **139 passed, 0 failures, 0 errors**, 5 skipped, **0 risky** |
| dev + Phase 12 | default | 144 tests, 88 passed, 56 skipped, 0 failures, 0 risky |
| dev + Phase 12 | gated, reverse order and random seed 7 | 139 passed each |

28 tests were added to the original 112: 22 in `AuthWorkflowTest` (7 original, 1 long-address, 9 for spellings, keys and counting, 5 for turns and in-flight admission) and 6 in `tests/Unit/LoginThrottleKeyTest.php`. The 5 skips are the photo tests.

## Remaining risks and limits

- **Distributed attacks** across many IPs against one account are not limited (the per-account counter includes the IP). There is no account-wide lockout, CAPTCHA or alerting; the `Lockout` event has no listener, so nothing is logged.
- **Shared IP.** The limiter uses `$request->ip()`; the app configures no trusted proxies (not changed here). Behind a reverse proxy, Docker/WSL gateway or campus NAT, everyone is one address: 20 failures per minute from anyone refuse every login from that address, valid passwords included, and an attacker on the same address can lock one account for that address for up to 60 s. Configure trusted proxies before production.
- **IPv6.** The key uses the full address with no /64 grouping, so a client with a /64 can rotate source addresses to get fresh counters, and an IPv4-mapped address (`::ffff:a.b.c.d`) is a different key from plain IPv4. Not changed (needs a policy decision and interacts with the proxy settings).
- **Waiting under simultaneity** (follow-up 4): simultaneous logins on the *same account* take turns (a double submit waits about 0.4 s; 12 at once, up to about 3 s); an attempt that cannot get a turn within 5 s, or an IP slot within 3 s, is refused (fail closed). Requests that wait hold a PHP worker meanwhile. The attempts that can still be delayed are those arriving while recorded failures plus in-flight attempts from one IP would exceed 20.
- **A locked-out owner** stays blocked for the rest of the 60 s window even after a password reset or with the right password.
- **Canonical form for unknown addresses** only approximates the collation (known accounts use the exact database lookup). A made-up address spelled with some exotic collation equivalence could get a separate counter; this has no effect on real accounts.
- **Cache table housekeeping.** Counter rows are hashed (no personal data except the client IP) but expired rows are not purged by anything scheduled; a periodic delete of expired `cache` rows is advisable.
- **Response shape.** A lockout is a 302 back to `/login` with `Retry-After`, not a 429.
- **Test limits.** PHPUnit uses the array cache (the suites require it), so the persistent store, key length and concurrency were verified manually against the disposable database and are not automated; nothing pins the 60 s window length beyond `travel(61)`.
- **Deployment.** `APP_DEBUG=true` is still the default in `.env.example` and compose; the cache store must be shared across instances.
