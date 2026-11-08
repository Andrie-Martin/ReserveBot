# Login maroon card and page-load flicker fixes

Two frontend-only correction PRs merged into `dev` on 2026-11-08, after design phase 13. Status and context: [QA_HANDOFF.md](QA_HANDOFF.md).

| PR | Merge commit | Fix commits | Topic |
|---|---|---|---|
| #27 | `1fc9d99` | `6019040` | Login page: maroon promotional card disappeared at 100% browser zoom |
| #28 | `0a30297` | `55656f1`, `9e35749` | Layout shift and flicker on navigation and refresh |

Both are CSS, Blade and test changes only. No backend, authentication, database, Docker or deployment file was changed.

## 1. Maroon card hidden at 100% zoom (PR #27)

**Symptom.** On `/login`, the maroon card under "Make room for your next big idea" ("Space to learn. To build. To create.") was visible at Chrome 90% zoom and missing at 100%.

**Root cause.** `public/css/auth.css` had `@media (max-height: 49.999rem)` containing `.rb-auth-login__art { display: none; }`.
`49.999rem` is about 800 CSS pixels of viewport height. On a 900px-tall screen, Chrome at 100% leaves roughly 730px of viewport, so the rule applied; at 90% zoom the CSS viewport is about 811px, so it did not.
Confirmed in an isolated Chromium: at 1438x730 the element computed to `display: none` (height 0); at 1598x811 it computed to `block` (152px).

**Fix.** The height rule no longer hides the card. It keeps the card and tightens its padding and the gap above it, and the page scrolls when the content is taller than the viewport.
The width rules are unchanged: below 64rem the card is hidden, and below 40rem the whole left-hand introduction is hidden, as designed for task-focused mobile.

**Files.** `public/css/auth.css`; test `tests/Feature/Frontend/AuthPresentationTest.php` (`test_login_promotional_card_is_not_hidden_by_viewport_height`, fails on the old CSS).

**Evidence** (isolated Chromium on a static render of the real login view, no database):

| Viewport | Card before | Card after |
|---|---|---|
| 1438x730 (about 100% zoom on a 900px screen) | hidden | visible, 114px tall, 16px below the intro text |
| 1598x811 (about 90% zoom) and 1438x900 | visible | visible, unchanged |
| 1024x600 | hidden | visible |
| 1438x730 with 200% text | hidden | visible, page scrolls |
| 390x844, 320x640 (also with 200% text) | hidden | hidden (by design) |

No horizontal overflow at any size; email and password fields accept input and the Log in button stays reachable at 1438x730, 390x844 and 320x640. The static page has no backend, so submission was not exercised in that run.

## 2. Page-load flicker (PR #28)

**Symptom.** The layout visibly jumped on sidebar clicks, page loads and refreshes, mainly on phone-width windows.

**Root cause.** The page painted in one layout and then changed when the deferred `public/js/layout.js` ran:

1. On phones the account/navigation menu was **expanded** in the first paint, and the menu toggle button carried a `hidden` attribute. After the script ran it collapsed the menu and revealed the button, so everything below moved.
2. The Inter font files were not preloaded and used `font-display: swap`, with a fallback font of different size. When the (unsubset, about 110KB each) files arrived late, text already on screen re-wrapped.

Not the cause: CSS load order (all stylesheets are in the document head), animations or transitions (there are none in the shared layout).

**Fix.**

- `resources/views/layouts/partials/header.blade.php`: the menu toggle is in the first paint (no `hidden` attribute, `aria-expanded="false"`).
- `public/css/layout.css`: under `@media (scripting: enabled)` the mobile menu is collapsed from the first paint (`.rb-shell:not([data-menu-open])`); under `@media (scripting: none)` the toggle is hidden and the menu stays open, so the no-script experience is unchanged.
- `resources/views/layouts/app.blade.php`: preloads the Inter Regular, SemiBold and Bold files.
- `public/css/foundations/fonts.css` and `tokens.css`: a metric-matched local fallback face (`ReserveBot Inter Fallback`) and `font-display: optional` instead of `swap`.
- Test `tests/Feature/Frontend/PageLoadStabilityTest.php` (3 tests; fail on the old code).

**Evidence.** Cumulative layout shift (CLS) measured in Chromium on the **real Laravel app** from a worktree, against a throwaway MariaDB with the repo's seeded test accounts, logging in through the real form as each role,
5 sidebar clicks plus 5 reloads per role and viewport, network throttled to 1.5 Mbps with 3x CPU slowdown. Worst value per page; "before" is `origin/dev` @ `1fc9d99`.

| Viewport | Role | Before | After |
|---|---|---|---|
| 1438x900 | Representative | 0.0023 | 0.0000 |
| 1438x900 | Lab staff | 0.0263 | 0.0000 |
| 1438x900 | Admin | 0.0264 | 0.0000 |
| 390x844 | Representative | 0.0777 | 0.0000 |
| 390x844 | Lab staff | 0.6651 | 0.0000 |
| 390x844 | Admin | 0.4750 | 0.0000 |
| 320x640 | Representative | 0.1461 | 0.0000 |
| 320x640 | Lab staff | 0.5942 | 0.0000 |
| 320x640 | Admin | 0.7377 | 0.0000 |

(A shift above 0.1 is generally considered poor.) No horizontal overflow in any run. Also checked with JavaScript on and off: the toggle opens and closes the menu, Escape closes it, and without JavaScript the menu stays open.
First-paint screenshots before and after were compared during the work but were **not committed**.

**Remaining limitations.**

- `font-display: optional` means a first, uncached visit on a slow connection can show the fallback font until the next page; the font files are still large (subsetting is not done).
- If `layout.js` fails to load, phone users with scripting enabled get the menu collapsed with no working toggle.
- Measurements came from a local development server, not a production host; caching headers, a reverse proxy and HTTPS were not part of the test.
- A static render of the layout was also used for early runs; the table above is from the real app.
- The backend-dependent PHPUnit suites could not run on SQLite (the migrations use MySQL-only column types), so only the frontend test files were run for this fix.
