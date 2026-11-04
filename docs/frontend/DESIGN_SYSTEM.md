# ReserveBot frontend design contract (visual reference)

Design reference: https://www.figma.com/design/uQBdTVNhuXGV3q7sYlz1Br/ReserveBot---PUP-CEA-System-Design

This is an **unofficial PUP-inspired concept**, not verified official PUP branding. Use the approved Figma kit as visual guidance; use current Laravel code as the authority for functionality. Do not port its mock data into real Blade views.

## One palette
| Token | Hex | Purpose |
|---|---|---|
| brand / maroon | `#6B1932` | Primary actions, important emphasis |
| brand / deep | `#481021` | Header/strong brand surfaces |
| brand / accent | `#A2384E` | Restrained secondary brand accents |
| accent / gold | `#D6A451` | Small institutional highlights only |
| surface / canvas | `#F8F5EF` | Warm page background |
| surface / cream | `#F2EADF` | Muted panels |
| surface / white | `#FFFFFF` | Cards, forms, dialogs |
| text / primary | `#232022` | Main reading text |

Status colors must carry meaning (success, warning, danger, neutral) and never rely on color alone. Map them to ONE existing set of local styles or tokens after auditing current CSS and Figma; do not proliferate duplicates.

## One typography system
- Family: `Inter` with Regular, Medium, Semi Bold, Bold only.
- Heading hierarchy: display 40px, H1 30px, H2 22px, H3 18px; body 14–16px; labels 13–14px; captions 12px minimum for user-facing content when possible.
- Avoid applying tiny Figma 'micro' text (10px) as standard content. Prioritize readability and contrast.

## Layout, spacing, interaction
- Start with an 8px spacing rhythm (4px only for tight icon/label gaps); consistent max-width, radii and border weights.
- Shared header + responsive navigation + authenticated role-based sidebar; reuse common shell across screens.
- Reuse Blade components/partials for: brand/logo, primary/secondary button, form field and error, status badge, stat card, table patterns, empty state, page header, navigation and alerts.
- Prefer local assets or existing project asset convention. Do not download/use an unlicensed PUP logo or substitute a fabricated 'official' emblem. Current Figma uses a designed `PU` motif.
- Preserve semantic forms, focus indicators, mobile tables and error/empty/success states.
- Page frame numbers (`08 •`, `09 •`, etc.) belong in Figma labels, not on-page headings.

## Implementation rule
Map existing component paths and design tokens before creating anything; incrementally replace temporary CSS and Blade markup. Do NOT change backend contracts to match visual mockups. Maintain a reusable inventory in the phase PR description.

## Phase 01 implementation contract

Approved scope: isolated foundations on `feat/frontend-01-foundations`. Existing pages retain their legacy appearance until their assigned frontend phases. Phase 01 adds stylesheet links only to the shared layout; it does not migrate screen markup or legacy CSS.

**Figma access remains UNVERIFIED.** The planning MCP session and `get_design_context` requests for `uQBdTVNhuXGV3q7sYlz1Br`, node `0:1`, were denied for missing edit access. No live measurements, screenshot, component mapping, or `PU` asset were inspected. Values in the original sections above are repository design guidance. The 10px control radius and 16px card radius are user-approved **provisional design-kit references**, not independently measured Figma values. The defaults below are explicitly implementation choices.

### Assets and stylesheet order

`layouts.app` loads the existing `css/style.css`, followed by `css/foundations/fonts.css`, `tokens.css`, and `components.css`. Each URL has its own `filemtime` cache key. No bundler, CSS imports, framework, CDN, preload, or JavaScript is introduced.

Inter 4.1 static WOFF2 faces (400, 500, 600, 700) are hosted in `public/fonts/inter/`. See [provenance and checksums](../../public/fonts/inter/PROVENANCE.md) and [original license](../../public/fonts/inter/LICENSE.txt). `font-display: swap` and a system fallback stack apply. The CSS-only family alias `ReserveBot Inter` avoids affecting any existing font declarations; the binary font files are unmodified.

### Token inventory

All tokens live in `public/css/foundations/tokens.css`. Numeric spacing/type/radius values use rem, based on a normal 16px browser root; do not fix the root font size.

| Token family (`--rb-` prefix) | Values and intended use |
|---|---|
| `color-maroon`, `color-deep`, `color-accent`, `color-gold` | `#6B1932`, `#481021`, `#A2384E`, `#D6A451`; documented brand palette. Gold is restrained decorative emphasis. |
| `color-canvas`, `color-cream`, `color-white` | `#F8F5EF`, `#F2EADF`, `#FFFFFF`; documented surfaces. |
| `surface-canvas`, `surface-panel`, `surface-muted` | Semantic aliases to canvas, white, cream. |
| `text-primary`, `text-muted` | `#232022` documented primary; `#4B5563` proposed readable muted text, reused from existing local styles. |
| `action-primary-bg`, `action-primary-hover`, `action-primary-text` | Maroon, deep, white aliases. |
| `border-control`, `border-subtle` | `#6B7280`, `#E5E7EB`; proposed control vs decorative separator distinction. |
| `focus-color`, `focus-width`, `focus-offset` | Maroon, 2px, 2px; proposed visible keyboard focus. |
| `success-text/bg` | `#166534` / `#DCFCE7`, existing local status pair. |
| `warning-text/bg` | `#92400E` / `#FEF3C7`, existing local status pair. |
| `danger-text/bg` | `#991B1B` / `#FEE2E2`, existing local status pair. |
| `neutral-text/bg` | `#4B5563` / `#F3F4F6`, existing local status pair. |
| `font-family` | Local Inter, system-ui, Apple system, Segoe UI, sans-serif. |
| `weight-regular/medium/semibold/bold` | 400 / 500 / 600 / 700. |
| `text-caption/small/body/h3/h2/h1/display` | 12 / 14 / 16 / 18 / 22 / 30 / 40px equivalents. |
| `leading-heading/body` | 1.25 / 1.5; proposed defaults. |
| `space-0/1/2/4/6/8/10/12/16` | 0 / 4 / 8 / 16 / 24 / 32 / 40 / 48 / 64px equivalents. Four pixels is for tight gaps. |
| `radius-control/card/pill` | 10px / 16px / 999px; first two provisional kit references, pill an implementation default. |
| `border-width`, `control-height` | 1px / 44px equivalents; proposed defaults. |
| `grid-min` | 16rem preferred minimum for wrapping grid/header content, clamped to available width. |
| `tone-bg/text` | Private component aliases assigned by `.rb-tone-*`; not a second status palette. |

Cards use flat bordered surfaces. Layout remains fluid; Phase 01 does not redefine existing shell widths or breakpoints. New cards reduce padding at 40rem. No Figma-specific shadow, elevation, sidebar width, icon size, or motion system is asserted.

### Scoping and migration boundaries

- `.rb-ui` opts a subtree into the new text color and font. Components also declare their own font; adding stylesheet links alone changes no existing UI.
- `.rb-*` component selectors and `__element` / `--modifier` names avoid collisions with legacy `.card`, `.error`, `.small`, `.active`, and generic element rules.
- `.rb-stack`, `.rb-grid`, `.rb-actions`, `.rb-heading` with size modifiers, and `.rb-description` are the small shared layout/type vocabulary. Use these instead of duplicating inline styles.
- `--rb-*` variables are not aliases for legacy `--brand`, `--bg`, `--text`, etc. Future page phases migrate consumers deliberately: brand → action-primary, bg → surface-canvas, text → text-primary, muted → text-muted, line → border-subtle/control according to purpose, error → danger-text.
- `.rb-ui .avatar` opts the existing avatar into the new surface/color only; no duplicate avatar component or photo authorization exists.
- Existing global CSS is still present. Component defaults explicitly neutralize inherited button width, control margins, and heading/table presentation. A later adoption inside a highly specific legacy form must be checked in that phase; isolated examples do not prove every future CSS combination.
- Preserve legacy `:has()` selectors and their ancestor/descendant/name/value relationships. Hidden controls continue to submit; no JS, disabled attributes, or clearing behavior is added.

### Blade component API

Public components are under `resources/views/components/ui/`. All accept an attribute bag, merge classes, escape scalar props, and render Blade slots normally. Callers remain responsible for escaping dynamic values in slots; do not use raw HTML for untrusted content. These components do not query records, read session flashes, infer old input, authorize actions, or translate business statuses.

| Component | Props / slots / semantics |
|---|---|
| `x-ui.button` | `variant=primary` (primary/secondary/quiet/danger), `size=regular` (regular/compact), `type=button`; content slot. Explicit `type=submit` required for submission. Native disabled, name/value, form, formaction, formmethod and ARIA/data attributes pass through. Compact reduces horizontal padding, not target height. |
| `x-ui.link-button` | Required `href`; same variants/sizes and content slot. Always an anchor; no invented disabled-link behavior. |
| `x-ui.field` | Required `field-id`, `label`; optional `hint`, `error`; native control in default slot. Generates label and `<field-id>-hint/error` IDs. Provide globally unique IDs, including across multiple forms. For radio/checkbox groups, use native fieldset/legend and labels rather than this single-control wrapper. |
| `x-ui.input` | Native input; `type=text`, optional explicit `id`, `invalid=false`; other input attributes passed through. Inherits field ID/hint/error context. No implicit old input or value. |
| `x-ui.select` | Same ID/invalid/description contract; options/optgroups in slot. Caller renders selected/disabled state. |
| `x-ui.textarea` | Same ID/invalid/description contract; escaped caller value in slot, not a value attribute. |
| `x-ui.error-summary` | Required explicit `bag` (MessageBag); composes `manage._errors` without duplicating its loop. Empty bag emits nothing; exactly one existing role=alert is retained. |
| `x-ui.alert` | `tone=info` (info/success/warning/danger); optional `role`, content slot. Static messages have no live role by default; choose status or alert only when warranted. |
| `x-ui.badge` | Required escaped `label`, `tone=neutral` (neutral/success/warning/danger). Caller supplies the current model's label and appropriate presentation tone. |
| `x-ui.card` | Default content, optional `heading` and `actions` slots. Neutral div; heading slot provides semantic heading level. |
| `x-ui.page-header` | Required title, optional description/actions slot. Renders h1; use once for the page heading. |
| `x-ui.stat-card` | Required label/value; composes card with a definition list. Values are caller supplied, never calculated. |
| `x-ui.empty-state` | Required title/description, `level=2` (2–6), optional actions slot. No fabricated illustration or live metric. |
| `x-ui.table-container` | Required label; defaults to labelled region with tabindex=0 for keyboard scrolling. Native table/caption/th scope supplied in slot; no duplicated mobile table. |

`ui/_control.blade.php` is an internal shared renderer used by input/select/textarea. It combines caller `aria-describedby` with field hint/error IDs and marks actual field errors `aria-invalid=true`. An explicitly provided control ID wins; when nesting in a field, keep it equal to `field-id` so the label points to the control. Standalone controls need their own external label. Invalid variants fall back to documented defaults.

Example (caller owns old input, name and bag selection):

```blade
<x-ui.field field-id="edit-email" label="Email"
    hint="Use your account email." :error="$errors->getBag('details')->first('email')">
    <x-ui.input type="email" name="email" :value="old('email', $account->email)" required />
</x-ui.field>
```

Keep native forms, CSRF/method fields, signed action URLs, submitter attributes, multipart encoding, and permission conditionals at the calling view. Password/file controls receive no automatically restored values. Existing shared partials and backend validation remain the source of truth.

### Accessibility and examples

Components use native controls, explicit labels, visible focus, wrapped text/actions, 44px default targets, readable caption/body sizes, and text alongside status color. Default error summaries announce once; inline errors supply descriptions without additional alert roles. No custom interaction JS or animation is required. Horizontal scrolling is confined to labelled table containers where necessary.

See [Phase 01 evidence and reproduction](../codex/PHASE_01_FOUNDATIONS.md) for isolated fixtures, commands, verification limits, and complete file inventory. Examples are test-only, explicitly labelled sample data, and have no production route.

## Phase 02 — exported-reference shell

Implemented on `feat/frontend-02-layout`, from `472a54d`, after approval of the revised exported-image plan. **No live Figma inspection is claimed.** Earlier access/Starter-plan limits remain unresolved. All 14 user-supplied PNGs under `references/figma/` were visually reviewed; exports prove only their pictured states. They are preserved unchanged.

The foundations export explicitly labels Inter, the existing eight palette colors, secondary text `#736C6D`, borders `#E9E2DA`, 8px spacing, 24–40px margins, 10px controls and 16px cards. These are export-supported kit references, not measurements of live Figma nodes. Desktop active-row pixels support `#F8EDF0`. New additive tokens are `--rb-text-secondary`, `--rb-border-warm`, `--rb-surface-active`, `--rb-sidebar-width` (15.75rem) and `--rb-shell-gutter` (3rem). Existing Phase 01 muted/control/status tokens remain unchanged.

The 1440px desktop raster shows a 30px institutional strip, white header through y106, a sidebar boundary at x252, and content starting around x298. These inform minimum strip/header heights, a 252px sidebar and 48px content gutter. The mobile reference is 390×844, with a compact strip/header and four text-only bottom links. Typography uses local Inter only within shell subtrees. The unofficial PU monogram is text plus a gold CSS underline, not an official seal or downloaded artwork.

### Shared shell and component contracts

`layouts.app` owns the **single** role-aware navigation definition and the one existing staff waiting-count call. Shared partials render institutional masthead, header, account controls, navigation, advisory card and email notice. A single navigation DOM adapts to sidebar, representative bottom navigation or staff/admin disclosure. Existing `x-avatar`, `x-ui.button`, `x-ui.card` and `x-ui.alert` are reused.

- `x-ui.brand`: required `href`, forwarded attributes; text ReserveBot and institutional subtitle; decorative monogram hidden from assistive technology.
- `x-ui.nav-item`: `href`, escaped `label`, `tile`, optional `mobile`, `active=false`, `count=0`; forwarded attributes, native anchor, `aria-current=page` for active state, labelled nonzero waiting count. It does not authorize or query anything.
- Staff/admin Equipment links use the existing `manage.equipment` route. Equipment/categories activate Equipment; other `manage.*` routes activate Manage rooms. Existing in-content Rules/Timetable tabs stay intact. Accounts remains admin-only even though absent from the export.
- Account and logout are rendered once. The existing POST/CSRF logout occupies the action area where the concept shows unsupported Help. Real account name/role/photo replaces sample initials. Onboarding retains name/logout only. `hideUser` is evaluated before authentication. Email notice suppression, copy, POST resend and `connect_note` replacement behavior are preserved.

`css/layout.css` loads after foundations; `js/layout.js` is a deferred local progressive enhancement. Both use independent `filemtime` keys. Only shell elements opt into `.rb-ui`; main content is deliberately outside that scope. No legacy variables or stylesheet rules are modified.

### Responsive and accessibility decisions

The following are implementation assumptions approved with the revised plan, **not inferred live Figma interactions**: an 80rem breakpoint; fixed representative bottom navigation; an in-flow account/menu disclosure; staff/admin mobile navigation; Escape closing and returning focus; retaining an open menu if resizing would hide its focused control. The toggle transfers focus to the account link when it disappears at desktop width. There is no modal, focus trap, account dropdown, animation, polling or second navigation tree.

Without JavaScript, account/logout and staff links are visible; the nonfunctional toggle stays hidden. Bottom safe space has a rem fallback plus safe-area inset; JavaScript measures actual bottom-bar height using ResizeObserver. Final content and focus targets reserve that space. Minimum 44px actions, visible focus, a skip link, text labels, wrapping long names and 12px minimum shell text take precedence over tiny export microcopy. No official-services claim is made. Sidebar wording is “Reservations follow your department’s rules and current availability,” avoiding a claim that all reservations await approval.

Authenticated main content uses the available shell column instead of the old generic 960px cap. `main-class` is preserved, including availability's `wide` class. Page-owned `.narrow` (400px), `.book-card`/`.booking-card` (880px), `.today-card`/`.manage-card` (980px), `.account-card` (820px), `.welcome-card` (440px) and `.tt-card` (1180px) maxima remain in legacy CSS. Guest/onboarding main retains its prior sizing. Changes in available column width can reflow existing content; they do not migrate its typography, controls, tables or conditional selectors.

See [Phase 02 implementation evidence](../codex/PHASE_02_LAYOUT.md) for screenshots, tests, reference differences and verification limits.

## Phase 03 — authentication and own account

Phase 03 uses the committed `01 • Login — Desktop.png`, `00 • Foundations — Brand, color, type and reusable UI.png`, and `Reusable UI components.png`. No live Figma nodes were inspected. Export-supported details are the two-column desktop login, warm/cream surfaces, maroon primary action, architectural motif, Inter scale, 8px spacing rhythm and 10px/16px control/card radii. All existing tokens and font assets remain unchanged.

`css/auth.css` loads only through the affected views' `styles` push; `layouts.app` adds the stack after layout CSS. It uses `rb-auth-*` / `rb-account-*` selectors and existing `--rb-*` tokens. Only migrated content opts into `.rb-ui`. The Phase 02 shell, navigation, email notice, JavaScript and legacy stylesheet remain unchanged. Guest login uses the existing `main-class` extension point for a full-width composition.

New `x-ui.auth-card` composes `x-ui.card`, forwards attributes, and supports heading, default and footer slots. Callers supply semantic headings, descriptive paragraphs, forms, routes, messages and conditions. It contains no authentication/session/database logic. Account photo, email-state and information-form partials preserve three separate concerns and independent native forms. Existing `account._tabs` remains shared navigation between two server pages, with `aria-current`, not a client-side tab widget.

Implementation assumptions (not live Figma measurements): desktop login columns 51%/49%; left inset up to 96px, top inset 104px; illustration maximum 520px; 1024px two-column breakpoint; compact cards maximum 480px; login card maximum 560px; account content maximum 820px and password form maximum 480px. Mobile padding is 16px. These are rem/fluid dimensions; no fixed page/card height. The illustration is decorative SVG geometry local to its Blade partial and hidden from assistive technology; below 64rem it is omitted visually. The 12px/13px SVG captions are also decorative.

Deliberate adaptations: retain Forgot password and the original class-booking description; preserve the unofficial-design wording; retain high-contrast control borders and visible autofocus; allow a taller login card for existing information and recovery navigation. Empty inputs remain empty rather than containing the prototype's example email/password. The original login title is retained in the document title; the visible composition has one h1 plus the login card h2. No registration, remember-me, password-reveal, spinner, fake status, remote image or new JavaScript is introduced.

Unpictured recovery, reset, onboarding, account, email-result and error states use the same card/field/button/alert vocabulary. Default/info/photo bags remain explicit; summaries announce errors, while inline errors provide control descriptions without additional live regions. Fields retain existing names, old values, constraints, IDs where present, autocomplete, autofocus and native submit behavior. Password/file values are never restored. Photo guidance is associated with the native file input. Tabs and recovery links have visible focus and minimum 44px targets; long text wraps and content can grow.

See [Phase 03 report](../codex/PHASE_03_AUTH.md) for the complete parity matrix, actual tests, screenshots and outstanding HTTP/security review items.

## Phase 04 — representative dashboard and room availability

S08 representative content and S11 now compose existing card, table-container, link-button, select and button components using page-owned `dashboard.css` / `availability.css`. Only migrated wrappers opt into `rb-ui`; page classes use `rb-dashboard*` and `rb-availability*`. The staff/admin home, shared shell, foundations and class-answer partial remain unchanged. No new JavaScript or tokens are needed.

Visual direction comes from committed desktop dashboard, availability, mobile dashboard and foundation/component PNGs, not live Figma measurements. Provisional composition defaults: dashboard 2:1 columns, stacked at 70rem; availability 1.45:1 with 22rem minimum day column, stacked at 85rem; compact padding/headings at 40rem. Existing 10px control/16px card radii and Inter/palette tokens remain authoritative. Calendar minimum width is 28rem, reduced to 24.5rem on mobile inside a labelled keyboard-scrollable region; full accessible preview text and complete selected-day agenda remain. Semantic schedule tables scroll locally. Native details and forms work without JavaScript.

Export-only statistics, room classifications and sample bookings are omitted; all real shortcuts, guidance, schedule information, calendar states, search results and disclosures are retained. See [Phase 04 parity and evidence](../codex/PHASE_04_AVAILABILITY.md) for exact checks and outstanding HTTP requirements.

## Stage 1 — shared shell refinement

Approved Stage 1 refines only the shared shell, before the separate task-UX, visual-consistency and theme stages. No live Figma measurement is claimed.

- Desktop navigation remains at the existing 80rem breakpoint. The sidebar is sticky, independently aligned, viewport-limited and vertically scrollable only when its contents overflow. Its advisory uses available space below navigation. Main content keeps document scrolling.
- `layout.js` measures the remaining visible normal-flow header height (including wrapped names), updates `--rb-sidebar-height` on resize/scroll via animation-frame scheduling, and observes header/masthead resizing. The CSS fallback remains sticky with a viewport height when JavaScript is absent. At extreme text enlargement/short heights, natural document scrolling reveals navigation beneath a tall header; content is not clipped to force an above-fold fit.
- Header flex items may wrap. Mobile representative bottom navigation, staff disclosure, Escape handling, focus transfer and safe-area clearance remain unchanged.
- `x-ui.nav-item` now accepts `icon` instead of `tile`. `x-ui.icon` is a local decorative outline SVG with a fixed allowlist (home, rooms, book, bookings, today, requests, manage, equipment, accounts), `aria-hidden` and `focusable=false`. Adjacent labels supply the accessible name. No icon package or external requests.
- Hover uses the existing muted surface; current-page navigation uses the existing active surface, semibold text and a maroon inset edge; keyboard focus retains its separate outline. Mobile current-page emphasis uses a top edge.
- Prototype labels are removed from the masthead/header. The header describes the reservation system; the existing login unofficial-site disclaimer remains. No official endorsement or new university mark is introduced.

See [Stage 1 implementation and verification](../codex/STAGE_01_SHELL_REFINEMENT.md). Page content, tokens, legacy CSS, fonts and Phase 05 remain outside this change.
