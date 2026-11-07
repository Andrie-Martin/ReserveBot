# Phase 03 — authentication and onboarding

Implemented for review on `feat/frontend-03-auth`, base `399c507`, 2026-11-04. Scope: S01–S07, own-account/photo/email/password, and shared 403/404/413/419/429/500 presentation. No Phase 04, staging, commit, push, merge or deployment. The Functional Parity Contract is now mandatory in `AGENTS.md` for every frontend phase.

## Architecture and protected behavior

The current Laravel application governs functionality. Routes `web.php:26–68`, login/password/reset/account/email/photo controllers, `AccountChecks`, `RequireConnectedEmail`, `AccountFields`, `AccountRules`, `User`, `Photos`/`Photo`, notification links, provider limits and bootstrap signature handling were inspected. They are unchanged. Full source/field/state inventory remains in `SCREEN_INVENTORY.md` and the matrix below.

The single Phase 02 shell remains in use, including role navigation, POST logout, onboarding suppression, email notice, waiting query and hidden-user guard. `layouts.app` only adds an optional `styles` stack. Each migrated view pushes `auth.css` with its own filemtime cache key. The sheet scopes layout to `rb-auth-*` and `rb-account-*`, uses existing tokens, and opts migrated content into `.rb-ui`. No production JavaScript was added.

Reused components: `ui.card`, `field`, `input`, `select`, `button`, `alert`, `badge`, `error-summary`, plus `avatar`. New `ui.auth-card` composes the existing card, forwards attributes, and accepts heading/default/footer slots. Forms, headings, message selection, old values, error bags and authorization/state conditions remain caller-owned. The account page composes `_photo`, `_email-state` and `_information-form`; photo upload, photo removal, resend and information saving remain independent forms. Account tabs remain native GET navigation.

## Functional Parity Matrix

Evidence codes: **P** = actual before/after browser comparison of every form/control's method, action, encoding, name, value, selected options, native constraints, FormData and every link; **I** = every original main-content paragraph retained; **R** = isolated Blade/state/ARIA assertions; **B** = browser interaction/responsive check; **S** = unchanged protected source. **H pending** means real HTTP/backend behavior has not been executed; it is an outstanding pre-merge review item. P/I/R/B do not prove backend behavior.

| Original capability / information | Original contract and conditions | Redesigned location | Evidence / outstanding |
|---|---|---|---|
| Login email/password and submit | GET named `login`, unnamed POST `/login`; guest; email old input only | Login card, explicit field labels/errors | P/R/B; H pending |
| Login class-booking description | Existing introductory information | Login card description beneath h2 | I |
| Login success/status and credential failure | `status`; default `email` error | Success alert and email inline error | P/R; H pending |
| Forgot password shortcut | GET `password.request` | Visible login-card footer | P/B |
| Active-account check, normalization, role redirect | `LoginController::login`; generic wrong/disabled credentials; regenerated session | Same native submission | S; H pending |
| Logout, session/CSRF invalidation | POST `logout`, auth only, including onboarding | Unchanged shared account area | P; Phase 02 B; H pending |
| Recovery email and submit | GET `password.request`, POST `password.email`; `email`, old input | Compact recovery card | P/R; H pending |
| Recovery return/assistance | Back to login link and administrator-help guidance | Recovery footer | P/I |
| Recovery outcomes | Generic `status`, invalid email, sending exception, throttling | Status alert / field error / shared 429 | R/I; H pending |
| Reset token and email | GET `password.reset` token/query; POST `password.update`; hidden token; old email fallback | Compact reset form | P/R/B; H pending |
| Reset new password and confirmation | `password`, `password_confirmation`; 8–64 letters/numbers policy; default errors | Associated fields, hint and summary | P/R; H pending |
| Reset outcome | Invalid/used/expired/wrong-email error; success to login; clears must-change, connects email, rotates remember token | Existing messages/redirect contract | S/R; H pending |
| Mandatory onboarding identity/guidance | Admin-set password must be replaced; authenticated main gate | Focused welcome card and reduced shell | I/P/R |
| Conditional onboarding school ID | `$askSchoolId`; missing rep required, missing lab staff optional, admin absent | Before new-password fields; same old input | P/R; H pending |
| Onboarding save/password policy | PUT `welcome.save`; must differ, confirmation; default bag | Native Save and continue | P/R; H pending |
| Onboarding success/next steps | Account redirect with connected/unconnected status | Existing account status area | S/R; H pending |
| Account Information/Password navigation | GET `account` / `password.edit`; active route | Shared styled `_tabs`, `aria-current` | P/R/B |
| Account status/problem | Existing session keys and status/alert semantics | Below account navigation | I/R |
| Identity/photo | Real name, role, department; private avatar URL or initials | Identity card | I/P/R; private HTTP H pending |
| Photo upload/replace | POST `account.photo`, multipart `photo`, `photo` bag; conditional button wording | Independent labelled upload form | P/R/B; H pending |
| Photo removal | DELETE `account.photo.remove`, CSRF/method spoof; only with photo | Separate adjacent native form | P/R/B; H pending |
| Photo guidance | Types, 5 MB, face/pickup purpose; server errors | Visible input-associated description and errors | I/R; H pending |
| Photo processing/access | Minimum dimensions, pixel limit, GD transformation/fallback, private cache/versioned URL, owner/same-department non-rep | Existing avatar/backend unchanged | S; H pending |
| Connected email | Address, Connected label and date | Email status panel | I/R |
| Unconnected email | Address, instructions, rep-only booking restriction | Warning panel | I/R |
| Account resend and feedback | POST `email.resend`; button retained alongside `connect_note` | Native panel form and status text | P/I/R; H pending |
| Shell resend and feedback | Same route; `connect_note` replaces controls; suppressed on account/onboarding/connected | Unchanged shell partial | P/R; Phase 02 B; H pending |
| Name/email editing | PUT `account.update`; `info` bag; old input, limits and autocomplete | Information form | P/R; H pending |
| Email-change current password | Required server-side only if normalized email changes; HTML remains optional | Same field and explanation | P/I/R; H pending |
| Rep email exception/policy | Existing address allowed; new Gmail/PUP student webmail only | Retained hint, no new client restriction | I/S; H pending |
| School ID editing | Rep required; lab staff optional; admin omitted | Role-specific information field | P/R; H pending |
| Rep program/block | Read-only program/block or No block yet; administrator ownership explanation | Read-only information panel | I/P/R |
| Staff program/section | Optional `section_id`, None option, grouped options, selected old value | Native select | P/R; H pending |
| Information save outcomes | Unchanged email saves; changed email clears connection, sends link or returns problem | Same submit and existing status/problem areas | P/S/R; H pending |
| Own-password change | PUT `password.change`; required current password, different new password, confirmation; default bag | Password tab card | P/R; H pending |
| Password/session guidance | Other-device logout, recovery/admin guidance | Retained card footer | I/S; H pending |
| Email connection success/reuse | Signed GET `email.connect`; actual `ok/message`; no auth identity restriction | Result card with status | I/P/R; H pending |
| Changed-email hash failure | Failure view, ordinary HTTP status from controller | Same result component | S/R; H pending |
| Invalid/expired signature | HTTP 403 via bootstrap handler | Same failure card | S/R; H pending |
| Invalid signed class link | Class-specific message from existing handler; class content remains P06 | Shared email-result card | I/R/S; H pending |
| Result recovery link | Authenticated account link; guest login link | Card footer | P/R |
| 403 / 404 | Existing heading/text, root home link | Shared error card | I/P/R; H pending |
| 413 | Existing photo-specific size guidance; `hideUser=true` | Shared safe error shell/card | I/P/R incl throwing auth; H pending |
| 419 | Existing refresh/retry guidance; no auto resubmit | Shared error card | I/P/R; H pending |
| 429 | Existing wait/retry guidance; distinct from inline resend feedback | Shared error card | I/P/R; H pending |
| 500 | Existing text; debug-off custom page; `hideUser=true` | Shared safe error shell/card | I/P/R incl throwing auth; H pending |
| All shared navigation/shortcuts | Brand/root, role menu, account, disclosure, waiting count, skip link | Existing Phase 02 shell | P; Phase 02 R/B |
| Native fields/security values | CSRF, PUT/DELETE spoofing, reset token, names, values, required/limits, autofocus/autocomplete; passwords/files empty | Existing forms with foundation controls | P/R/B; security H pending |
| Legacy conditional forms | Booking/account-admin/timetable `:has()` relationships and hidden-control submission | Unchanged later-phase screens | Phase 01 B, nine states |

No page-owned filters, search, pagination, asynchronous loading or new authentication controls existed in this scope. None were invented. No existing capability was removed. Route/security outcomes are source-preserved, not runtime-certified by the parity matrix.

## Figma alignment and adaptations

Visually inspected committed login, foundations and reusable-components exports. No Figma MCP or live measurement claim. Desktop login follows the warm two-column composition, left headline and white form card, right cream architectural illustration, maroon primary action, existing typography and palette. The local SVG recreates decorative geometry without embedding the exported screenshot, external assets, a real building claim or an official seal.

Export-supported kit values: Inter; 40/30/22px type scale and 14px body guidance; 8px rhythm; 10px controls/16px cards; documented palette, secondary text and warm borders. Existing tokens/fonts remain intact. Assumptions: 51/49 desktop columns, 64rem content breakpoint, 30rem compact card, 35rem login maximum, 51.25rem account maximum, responsive insets, 32.5rem illustration maximum. All are recorded in DESIGN_SYSTEM.md.

Deliberate differences: Forgot password retained; class-booking description retained; card grows for both and validation; stronger control borders/focus; readable microcopy; empty inputs rather than sample credentials; existing unofficial header wording. The desktop card is taller than the 900px reference; no content is clipped. At mobile widths the decorative illustration hides and all functional content remains in a single column. Recovery/account/error states have no screen exports and use the shared visual vocabulary. No production JS, fake data, remember-me, registration or invented loading state.

Local before/after evidence (temporary and reproducible):

- [Login desktop](/tmp/reservebot-phase03-evidence/login-1440-after.png) / [before](/tmp/reservebot-phase03-evidence/login-1440-before.png).
- [Login mobile](/tmp/reservebot-phase03-evidence/login-390-after.png).
- [Recovery](/tmp/reservebot-phase03-evidence/forgot-390-after.png), [reset](/tmp/reservebot-phase03-evidence/reset-390-after.png), [onboarding](/tmp/reservebot-phase03-evidence/welcome-rep-390-after.png).
- [Account mobile with named errors](/tmp/reservebot-phase03-evidence/account-errors-lab_staff-390-after.png), [account desktop](/tmp/reservebot-phase03-evidence/account-rep-1440-after.png).
- [Password](/tmp/reservebot-phase03-evidence/password-admin-390-after.png), [link failure](/tmp/reservebot-phase03-evidence/link-guest-0-390-after.png), [500](/tmp/reservebot-phase03-evidence/error-500-admin-390-after.png).
- [200% text account](/tmp/reservebot-phase03-evidence/account-long-text200.png), [200% text login error](/tmp/reservebot-phase03-evidence/login-error-text200.png).
- [Browser results](/tmp/reservebot-phase03-evidence/auth-browser-results.json); Phase 01/02 screenshots under `/tmp/reservebot-phase03-regression`.

The photo-filled fixture uses a clearly test-only cream image response; actual private photo URL/cache query remains in the rendered HTML. It is not an application placeholder or evidence that private-photo HTTP authorization passed.

## Executed checks

Only temporary snapshots under container `/tmp/reservebot-phase03-checks` and `/tmp/reservebot-phase03-before` were used, reusing installed `/var/www/html/vendor`. No `.env` copied, package installed, app source deployed, service restarted, database mutation, account action or email sent. The rendering harness uses array session/cache/mail and a no-PDO recording connection limited to the existing shell count. Browser requests are local fixtures/assets; sample POST submissions are intercepted.

| Command / check | Actual result |
|---|---|
| `docker compose exec -T app php /tmp/reservebot-phase03-checks/tests/Support/render-auth.php --check` | PASS: 1,318 isolated presentation assertions |
| Same path `render-foundations.php --check` | PASS: 59 component/render assertions |
| Same path `render-layout.php --check` | PASS: 241 isolated shell assertions |
| `node tests/Support/auth-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/reservebot-phase03-evidence /tmp/reservebot-phase03-before-evidence` | PASS: 60 states, 420 width/state combinations at 320/375/390/768/1024/1280/1440; complete native form/link parity and all original paragraphs; three intercepted native submissions; bag isolation; multipart separation; no-JS; keyboard focus; 200% text; 390×300 bottom clearance; 1023/1024 illustration breakpoint; empty reset native validation |
| `node tests/Support/foundations-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/reservebot-phase03-regression` | PASS: nine conditional states, no-`:has()` fallback, native FormData, focus/disabled, datalist, four local font weights, four widths, 200% text, contrast |
| `node tests/Support/layout-browser.cjs /home/james/projects/ticketing/node_modules/playwright-core /tmp/reservebot-phase03-regression` | PASS: 72 shell combinations, menu/Escape, breakpoint focus, no-JS, native form values, content widths, bottom-space and contrast checks |
| PHP / JS syntax | PASS for new PHP support/feature files and changed/new browser scripts |
| `git diff --check` | PASS |

The Phase 01 browser test's original “login unchanged when foundations removed” assertion now targets the existing conditional-template fixture because login intentionally adopts foundations. Its nine conditional-state checks are retained. Phase 03 compares login's actual forms, links and visible information against the pre-change snapshot; no functional expectation was deleted to obtain a pass.

During verification the baseline comparison initially treated the fixture-directory portion of the skip link as application behavior. It now compares the original href attribute (`#rb-main`) rather than the artificial fixture URL. No application behavior was changed in response to that test issue.

### Reproduction of isolated evidence

Use a temporary copy with existing Laravel dependencies, no `.env`, a writable temporary compiled-view directory and the provided no-PDO harness. Export current fixtures with `php tests/Support/render-auth.php --export /tmp/reservebot-phase03-evidence`. Export baseline fixtures from a separate copy of base `399c507` with only the new `auth-runtime.php` and `render-auth.php` copied into its test support directory. This executes the same sample states against the original views; do not replace views in the working repository or live service. The actual baseline archive is `/tmp/reservebot-phase03-before.tar`, and its exported fixtures are `/tmp/reservebot-phase03-before-evidence`.

Export regression fixtures using `php tests/Support/render-layout.php --export /tmp/reservebot-phase03-regression`; run the three browser commands listed above with an existing Playwright installation. All fixture HTML/screenshots remain outside `public`. Chromium and Docker access required sandbox escalation; neither dependency installation nor infrastructure modification was used. The before/after snapshots omit `.env` and credentials.

## HTTP security checks — outstanding before merge

`tests/Feature/AuthWorkflowTest.php` is implemented and syntax-checked, **not executed**. Local `vendor/autoload.php`/PHPUnit are absent; container Laravel is present but `vendor/bin/phpunit` is absent. No safe provisioned/authorized HTTP test database was established. No dependency installation or database setup was attempted.

The suite requires explicit `RB_AUTH_HTTP_TESTS=1`, `RB_AUTH_TEST_DATABASE` matching the effective MariaDB database ending `_test`, testing environment, no DB URL override, and array sessions/cache/mail. It verifies the actual selected database before starting a rollback transaction. It uses fake notifications/storage and unique sample users, and never runs migrations, seeders, cleanup SQL or RefreshDatabase. These guards do not replace reviewer authorization or independent database provisioning.

Unexecuted suite coverage: guest/main/role redirects; login normalization, wrong/disabled credentials and session regeneration; logout/token/session invalidation; disabled-session and mandatory-password gates; onboarding policies/school-ID conditions; info named bag, legacy-email exception, changed-email password requirement and reconnection; section ownership; current/different/confirmed password and stale-session invalidation; generic recovery/disabled user/mail failure; reset token success/reuse/expiry/wrong email/inactive account and reset side effects; signed connection success/reuse/hash mismatch/tampering/expiry; resend feedback/no-op/throttle/mail failure; 5/10/20 request limits; photo invalid/missing/size/dimension/upload/replace/private access/remove/no-photo; 403/404/419 and debug-off 413/500. Test-process-only error routes exercise exception output; no production routes are added.

A future approved run should use a copied checkout with existing development dependencies and a preprovisioned disposable MariaDB schema, then execute `php vendor/bin/phpunit --do-not-cache-result tests/Feature/AuthWorkflowTest.php`. Set/verify isolation explicitly; do not blindly reuse the repository's SQLite test configuration or live application environment. The suite may expose existing backend issues; investigate rather than changing security rules in this frontend phase.

Also unexecuted: existing PHPUnit guest/AccountRules/Photo tests and PHPUnit wrappers; real multi-browser/session end-to-end flows; actual inbox delivery; server/proxy oversize upload handling; 50-million-pixel limit/GD fallback integration; physical mobile keyboard/notch, actual browser-UI 400% zoom, Safari/Firefox and screen-reader runs. The 320 CSS-pixel reflow equivalent and 200% root text were tested, not actual browser zoom. No full WCAG or security certification is claimed.

## Complete changed-file inventory

Modified existing files (15):

| File | Purpose |
|---|---|
| `AGENTS.md` | Permanent Functional Parity Contract |
| `resources/views/layouts/app.blade.php` | Optional page-style stack only |
| `resources/views/auth/login.blade.php` | Login composition, preserved form/status/recovery |
| `resources/views/auth/forgot.blade.php` | Recovery card, field errors and assistance |
| `resources/views/auth/reset.blade.php` | Reset fields/token, hints and default errors |
| `resources/views/auth/email_link.blade.php` | Result card and current conditional recovery link |
| `resources/views/account/welcome.blade.php` | Onboarding card and role-dependent school ID |
| `resources/views/account/info.blade.php` | Compose own-account sections |
| `resources/views/account/password.blade.php` | Password form and guidance |
| `resources/views/account/_tabs.blade.php` | Native account navigation/active state and flashes |
| `resources/views/errors/message.blade.php` | Shared error presentation preserving supplied text and home link |
| `tests/Support/foundations-browser.cjs` | Migrate legacy-isolation fixture target to untouched conditional content |
| `docs/frontend/DESIGN_SYSTEM.md` | Component/CSS/visual assumption contract |
| `docs/frontend/SCREEN_INVENTORY.md` | Phase 03 parity and state coverage update |
| `docs/architecture/SYSTEM_MAP.md` | Source refresh and current presentation architecture |

Added files (12):

| File | Purpose |
|---|---|
| `public/css/auth.css` | Scoped responsive auth/account styling |
| `resources/views/components/ui/auth-card.blade.php` | Reusable card composition and slots |
| `resources/views/auth/_illustration.blade.php` | Self-contained decorative SVG |
| `resources/views/account/_photo.blade.php` | Identity/upload/remove, named photo errors |
| `resources/views/account/_email-state.blade.php` | Connection information and resend |
| `resources/views/account/_information-form.blade.php` | Own information, role fields and info bag |
| `tests/Support/auth-runtime.php` | 60 isolated states and rendering assertions |
| `tests/Support/render-auth.php` | CLI checks/fixture exporter, no production route |
| `tests/Support/auth-browser.cjs` | Before/after parity, responsive/keyboard screenshots |
| `tests/Feature/Frontend/AuthPresentationTest.php` | PHPUnit presentation wrapper, not executed |
| `tests/Feature/AuthWorkflowTest.php` | Guarded real HTTP regression suite, not executed |
| `docs/codex/PHASE_03_AUTH.md` | This matrix, inventory, evidence and review items |

## Integrity and review gate

**PASS:** compared all 274 files in the pre-edit SHA-256 manifest (tracked files plus the user's Docker backup). Exactly the 15 allowed existing files changed and exactly 12 allowed files were added. Backend/controllers/models/middleware/services/support/routes/bootstrap/config/migrations/seeders/notifications, legacy CSS, Phase 01 tokens/components/fonts/licenses, Phase 02 navigation/assets, all other views and all 14 Figma PNGs are byte-identical. Both local Docker files match their pre-edit bytes. Existing modified/untracked Docker status is excluded from this phase's inventory. [Integrity result](/tmp/reservebot-phase03-evidence/integrity.json).

Outstanding review: real HTTP/security checks above, visual acceptance of the accessible/taller login card and unpictured states, and manual assistive-technology/cross-browser checks. Existing 413 photo-specific copy also applies to timetable uploads; existing 500 text mentions the server log. Both supplied messages remain unchanged pending a separate copy decision. No explicit login throttle exists in the inspected routes/controller; this phase neither adds one nor claims it exists.

No functional scope deviation. The decorative SVG, mobile assumptions, retained recovery link and expanded card height are approved adaptations. Stop for review; no staging, commit, push, merge, deployment or Phase 04 is authorized by this report.
