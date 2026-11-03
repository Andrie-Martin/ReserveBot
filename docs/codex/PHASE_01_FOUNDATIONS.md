# Phase 01 — frontend design system foundations

Implementation for review, 2026-11-03. Branch: `feat/frontend-01-foundations`; base HEAD: `62bf640`. No commit, push, PR, merge, or Phase 02 work performed.

## Approved scope and architecture

The user approved isolated tokens and presentation-only anonymous Blade components, self-hosted Inter, test-only examples, and minimal stylesheet links in the existing layout. The refinement sets controls to **10px** and cards to **16px**. These are provisional design-kit references. Live Figma inspection remains blocked; no design fidelity claim is made.

The existing stylesheet remains byte-for-byte unchanged. Three independently cache-busted foundation stylesheets follow it. Tokens have the `--rb-*` namespace; component styles use `.rb-*` classes and opt-in `.rb-ui` typography. No existing screen opts into those classes. A distinct local font-family alias prevents accidental global typography changes.

Input, select, and textarea compose one internal native-control renderer. Fields expose label/hint/error associations; caller-supplied IDs and attributes are retained. Error summaries compose the existing `manage._errors` partial. Stat cards compose the card primitive. The existing avatar component is reused unchanged. Components have no database access, authorization decisions, business-state transitions, automatic old-input lookup, or session flash lookup. No application JavaScript was added; the new JavaScript file is only a browser test runner.

Full token inventory, component props/slots, accessibility expectations, font policy, and migration guidance: [DESIGN_SYSTEM.md](../frontend/DESIGN_SYSTEM.md#phase-01-implementation-contract).

## Source contracts preserved

- `resources/views/layouts/app.blade.php:9`: existing stylesheet/cache busting remains; only three link elements added. Role menus, waiting counts, logout CSRF, onboarding suppression, email notices and hidden-user error behavior are untouched.
- `routes/web.php:75` → `BookingController::create/store` → `Bookings`/`BookingRules`/reservation data → `bookings/create.blade.php`: two-stage GET/POST contract and current choices preserved. `public/css/style.css:291–298` retains choice focus/checked styles and class/other, room/equipment visibility.
- `routes/web.php:167` → admin role gate → `AccountsController`/account helpers/User → `accounts/_fields.blade.php`: field names, old values, rep/staff conditional fields and named bags preserved. CSS at `style.css:661–662` untouched.
- `routes/web.php:130` → staff/admin management gate → `ManageTimetableController`/`Timetables`/`TimetableRules`/draft models → `_timetable_row_form.blade.php`: Class/Blocked inputs and row bag preserved. CSS at `style.css:837` and code/title datalist JS remain unchanged.
- `User::photoUrl()` → `PhotoController::show` → `components/avatar.blade.php`: private photo URLs and initials/size behavior retained.

CSS-hidden controls continue to submit as before. No disabling, clearing, removal, or selector renaming was introduced. All routes, backend logic, migrations, seeders, configurations, and original screen templates were protected by a baseline SHA-256 comparison.

## Verification performed

### Database-free rendering

Executed using the already installed Laravel runtime in the existing app container, with source staged under `/tmp/reservebot-phase01-checks`. The running app checkout and its vendor directory were not modified. The temporary checkout reuses the installed vendor directory; no package installation was performed. No `.env` was copied.

```sh
docker compose exec -T app php /tmp/reservebot-phase01-checks/tests/Support/render-foundations.php --check
```

**PASS: 58 component/render contracts.** The harness removes all database connections and sets array session/cache/mail drivers; compiled views go into a process-specific temporary directory. It renders actual Blade components and the existing login, six error views, booking steps, account fields, and timetable fields.

Coverage includes escaping, forwarded data/native attributes, explicit submit type/name/value/form/formaction, default button type, field IDs and descriptions, selected options, textarea content, named error-bag isolation, empty summaries, password/file non-population, native disabled controls, table semantics, duplicate IDs, and 413/500 hidden-user rendering for all three roles without a database.

`tests/Feature/Frontend/FoundationsTest.php` exposes the same contract checks to PHPUnit when its development dependencies are available. That PHPUnit entrypoint was not executed here; the identical contract function was executed through the standalone runner.

### Browser verification

Rendered `gallery`, `conditional`, `legacy`, `booking-first`, and `booking-second` modes to temporary HTML files using `render-foundations.php`. Ran:

```sh
node tests/Support/foundations-browser.cjs \
  /home/james/projects/ticketing/node_modules/playwright-core \
  /tmp/reservebot-phase01-evidence
```

Used the already installed Playwright/Chromium; no new browser packages. Every page request was intercepted and fulfilled from the local fixtures or repository CSS/fonts. External requests and non-GET submissions are rejected by the runner.

**PASS:**

- Gallery at 320, 375, 768, 1440px, with no page-wide overflow.
- Login before/after computed styles match at every width. Screenshot pairs are also byte-identical at all four widths.
- Nine conditional states: rep/staff account fields, Class/Blocked timetable fields, class/other booking step one, both/room/equipment booking step two. Existing computed styles and FormData are identical with/without foundation styles.
- Simulated unsupported-`:has()` fallback (remove those rules): all conditional panels remain available and FormData is unchanged. This is a simulation, not a test on an old browser engine.
- Existing course-title autofill works and does not overwrite a nonempty title.
- Native FormData preserves submitter intent, method spoofing and readonly values while excluding disabled inputs.
- Keyboard focus is visible; disabled button is skipped in tab order.
- All four local Inter weights load. Computed control/card radii are 10px/16px.
- 200% root text enlargement at 320px causes no page-wide overflow. A 320 CSS-pixel viewport covers the reflow size corresponding to 1280px at 400% zoom; actual browser-UI zoom was not exercised.
- Checked contrast: primary text/white 16.14:1; muted/canvas 6.95:1; white/maroon 11.60:1; success 6.49:1; warning 6.37:1; danger 6.80:1; neutral 6.87:1; control border/white 4.83:1. These checks do not constitute a full WCAG audit.
- No browser page errors or external asset requests.

Inspected the 375px and 1440px gallery screenshots visually. The legacy header and page canvas deliberately retain their existing purple/system presentation around the opted-in example content.

Local evidence (temporary, not committed): `/tmp/reservebot-phase01-evidence/browser-results.json`, `gallery-{320,375,768,1440}.png`, and `legacy-{width}-{before,after}.png`. Recreate these when `/tmp` is cleared. The HTML files are test artifacts, not production routes.

### Static and preservation checks

- `php -l tests/Support/foundations-runtime.php`: pass.
- `php -l tests/Support/render-foundations.php`: pass.
- `php -l tests/Feature/Frontend/FoundationsTest.php`: pass.
- `node --check tests/Support/foundations-browser.cjs`: pass.
- `git diff --check`: pass.
- Official Inter files and license compared against the downloaded release; hashes recorded in font provenance.
- Baseline SHA-256 comparison: only approved existing files (layout and design-system documentation) changed. Legacy CSS, original screen/component/partial files, backend files, and both `docker-compose.yml` and `docker-compose.yml.save` unchanged. Their existing modified/untracked Git status is preserved.

## Verification gaps and deviations

- Figma MCP access was denied during planning. No live design measurements or screenshot comparison; this limitation is explicitly accepted as the documented-foundations basis.
- Existing PHPUnit Unit/Feature suites and the PHPUnit wrapper were not run: local vendor dependencies and container PHPUnit are absent. Installing dependencies was not authorized or necessary for the standalone rendering checks. Helper tests were not represented as browser coverage.
- No authenticated end-to-end workflows, production data changes, live account/booking operations, mail, scheduler, migrations or seeders were run. Staff/admin navigation counts and data-backed pages were not dynamically tested; source was preserved and isolated hidden-user paths were exercised.
- Browser automation covers Chromium only, not Firefox/Safari, real assistive technology, OS high-contrast mode, or actual browser-UI zoom. Full accessibility certification is not claimed.
- The running container is a copied checkout, so checks used a temporary staged copy rather than editing/rebuilding the running service. New assets become available in that service through the team's normal deployment/rebuild process, which was not performed.
- Implementation detail: one internal `_control` component was added to avoid duplicating input/select/textarea attribute and ARIA logic. No scope or business-behavior deviation.

## Reproduction without production routes

In a checkout with installed Laravel dependencies:

```sh
php tests/Support/render-foundations.php --check
php tests/Support/render-foundations.php gallery > /tmp/gallery.html
php tests/Support/render-foundations.php conditional > /tmp/conditional.html
php tests/Support/render-foundations.php legacy > /tmp/legacy.html
php tests/Support/render-foundations.php booking-first > /tmp/booking-first.html
php tests/Support/render-foundations.php booking-second > /tmp/booking-second.html
node tests/Support/foundations-browser.cjs /absolute/path/to/playwright-core /tmp
```

The browser runner resolves CSS/fonts from this repository. Screenshots and results must stay outside public paths. Do not expose the fixture renderer as an HTTP endpoint or add a preview route. It is CLI/test-only. Browser tooling is provided as an explicit external path, not a new project dependency.

## Complete changed-file inventory

| File | Change | Purpose |
|---|---|---|
| `docs/codex/PHASE_01_FOUNDATIONS.md` | Added | Approved scope, evidence, limits, reproduction and file inventory. |
| `docs/frontend/DESIGN_SYSTEM.md` | Modified | Tokens, provisional values, component API, provenance and migration contract. |
| `public/css/foundations/components.css` | Added | Opt-in component/type/layout styles and states. |
| `public/css/foundations/fonts.css` | Added | Four local Inter font-face declarations with swap. |
| `public/css/foundations/tokens.css` | Added | Namespaced primitive and semantic tokens. |
| `public/fonts/inter/Inter-Bold.woff2` | Added | Unmodified official Inter Bold WOFF2 face. |
| `public/fonts/inter/Inter-Medium.woff2` | Added | Unmodified official Inter Medium WOFF2 face. |
| `public/fonts/inter/Inter-Regular.woff2` | Added | Unmodified official Inter Regular WOFF2 face. |
| `public/fonts/inter/Inter-SemiBold.woff2` | Added | Unmodified official Inter SemiBold WOFF2 face. |
| `public/fonts/inter/LICENSE.txt` | Added | Unmodified upstream SIL Open Font License. |
| `public/fonts/inter/PROVENANCE.md` | Added | Official release source, version and SHA-256 checksums. |
| `resources/views/components/ui/_control.blade.php` | Added | Shared native-control attributes, field context and ARIA composition. |
| `resources/views/components/ui/alert.blade.php` | Added | Semantic tone and optional announcement role. |
| `resources/views/components/ui/badge.blade.php` | Added | Escaped status label with caller-selected tone. |
| `resources/views/components/ui/button.blade.php` | Added | Native button variants with safe button-type default. |
| `resources/views/components/ui/card.blade.php` | Added | Shared panel with content/heading/actions slots. |
| `resources/views/components/ui/empty-state.blade.php` | Added | Empty-state heading, explanation and optional actions. |
| `resources/views/components/ui/error-summary.blade.php` | Added | Composition of the existing named-bag error partial. |
| `resources/views/components/ui/field.blade.php` | Added | Single-control label/hint/error wrapper with stable IDs. |
| `resources/views/components/ui/input.blade.php` | Added | Native input wrapper composing the shared control. |
| `resources/views/components/ui/link-button.blade.php` | Added | Native anchor with shared button appearance. |
| `resources/views/components/ui/page-header.blade.php` | Added | Page title, description and actions. |
| `resources/views/components/ui/select.blade.php` | Added | Native select wrapper with caller options slot. |
| `resources/views/components/ui/stat-card.blade.php` | Added | Card composition with caller-supplied label/value. |
| `resources/views/components/ui/table-container.blade.php` | Added | Labelled keyboard-accessible scrolling region. |
| `resources/views/components/ui/textarea.blade.php` | Added | Native textarea wrapper with content slot. |
| `resources/views/layouts/app.blade.php` | Modified | Only three independently cache-busted stylesheet links. |
| `tests/Feature/Frontend/FoundationsTest.php` | Added | PHPUnit entrypoint for the shared database-free contracts. |
| `tests/Fixtures/views/conditional.blade.php` | Added | Existing account/timetable partials with sample data only. |
| `tests/Fixtures/views/gallery.blade.php` | Added | Rendered reusable-component examples with sample data only. |
| `tests/Support/foundations-browser.cjs` | Added | Local-only browser, form, visibility, font and visual checks. |
| `tests/Support/foundations-runtime.php` | Added | Isolated Laravel bootstrap, sample data and 58 assertions. |
| `tests/Support/render-foundations.php` | Added | CLI check/HTML renderer; no production route. |

Total: **33 files** (31 added, two modified), excluding pre-existing Docker changes.

## Review gate

Implementation and available isolated checks are complete. Review the examples, component contracts and explicit verification gaps before accepting Phase 01. No Phase 02 work is authorized by this handoff. User/team retains all commit, push, PR, merge and deployment decisions.
