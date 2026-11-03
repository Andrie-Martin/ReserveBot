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
