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
