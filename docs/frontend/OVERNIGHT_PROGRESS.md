# Overnight autonomous frontend progress log

Local commits only. Nothing is pushed, merged, rebased or force-pushed. Protected and never staged:
`docker-compose.yml`, `docker-compose.yml.save`, `.env`.

Verification tooling (outside the repo, in the session scratchpad):
`/tmp/claude-1000/-home-james-projects-ReserveBot/137867d6-5226-48dc-811f-1568c85a4d24/scratchpad/`.
Repo browser suites are run from patched copies that differ ONLY in the browser executable path and the asset
path. Fixtures are rendered in a throwaway container from the working tree with a read-only mount
(`--network none --entrypoint sh`); the running Docker app is never used as evidence. Baselines are exported from
`git archive <commit>` so before/after comparisons are genuine.

## Branch map and dependencies

| Branch | Based on | Status |
|---|---|---|
| `feat/frontend-design-03-rep-availability` | `dev` @ `7db0f1c` (includes merged Phase 02, PR #12) | complete, local, NOT merged |
| `feat/frontend-design-04-rep-booking` | `feat/frontend-design-03-rep-availability` @ `4d108c0` (Phase 03 is not in `dev`, so this branch is stacked; it contains all Phase 03 commits) | in progress, local |

## Phase 03 — Representative dashboard and Free Rooms

| Part | Commit | Summary |
|---|---|---|
| 03A | `f6039ba` | Representative dashboard hierarchy (kicker, banner, icon tiles, meeting rows) |
| 03B | `291eda0` | Free Rooms hierarchy |
| 03D | `4eb5902` | Free Rooms visual fidelity (grouped finder, type scale, header, calendar tiles, agenda) and continuous timeline border |
| 03D-2 | `bfc4900` | Expanded "What's on" disclosure alignment and density |
| 03D-3 | `4d108c0` | `/rep` and `/rooms` share header-block and card-heading rhythm |

03D-3 measured result (1438 wide): kicker top 32px, title 29px at 52.5px, sub-line 14px at 96.8px, first card at
133.8px on BOTH pages; card gaps 16px; card headings 16px.

Final Phase 03 verification (final tree), unmodified suite assertions:
- Against the previous commit and against the original pre-Phase-03 code (`7db0f1c`): availability-browser,
  task-ux, timeline, rooms-correction PASS; foundations, layout, auth PASS; shell-navigation PASS (3 runs).
- 4980 keyboard stops on Free Rooms and 696 on `/rep`: 0 focus problems; no 200% text overflow; sticky labels OK.
- Structural parity of every control, label, option and link vs the previous commit: 0 mismatches.

Deliberate test exceptions (recorded, not hidden):
- `visual-refinement-browser` asserts byte-identical HTML and exact board heights for CSS-only changes. Phases 03A,
  03B and 03D intentionally changed markup/typography, so its unmodified run fails at HTML parity. An adapted
  scratchpad copy skips HTML parity ONLY for the documents with intentional markup change; it keeps computed-style
  parity, x/width of every timeline element and exact track/free-band geometry, and passes.

Known issues / open decisions:
- At 1438×638 the first meeting-answer buttons on `/rep` start at about 694px (baseline 518px), below the 638px fold;
  they are fully visible at 1438×900. This follows the approved prototype order (banner, shortcuts, meetings).
  Options: accept, compact the banner, or move the meetings card above "Ready to use". Needs a human decision.
- Live Docker app serves a copy of the code baked into its image. Refresh needs the reviewed `docker cp` procedure;
  restarting or recreating the container runs `php artisan migrate --force` (entrypoint) and must be avoided.
- NOT RUN: PHPUnit, authenticated live-app checks.

Screenshot evidence: `fr-d3after-*`, `dash-after-*`, `busy-after-*`, `edge-before.png`, `edge-after.png`,
`mock-*` (prototype) in the scratchpad directory above.

## Phase 04 — Representative booking workflows (S12 form, S13 My bookings, S14 booking detail)

Branch `feat/frontend-design-04-rep-booking`, stacked on `feat/frontend-design-03-rep-availability` @ `4d108c0`.

| Part | Commit | Summary |
|---|---|---|
| 04A | `f4f58f5` | `public/css/booking.css` (new, page-scoped on `.rb-booking`) + wrappers/classes in `bookings/{create,mine,show}.blade.php`: maroon system replaces the legacy violet; stepper, choice cards, time-summary strip, equipment disclosures, compact primary actions; My bookings as two divided panels with pill badges; detail page as header + facts + action panels + history. No form, field name, link or label changed. |
| 04B | see `git log` | Verification tooling: `tests/Support/booking-runtime.php`, `render-booking.php`, `booking-browser.cjs` (31 database-free states incl. validation errors, long names, staff views) |

Verification (final tree):
- Isolated: layout 279, foundations 63, auth 1335, availability 2137, booking 536 PASS.
- Ordered structural parity vs the pre-change views (controls, labels, options, links, headings, paragraph text; only the added kicker line ignored): 31 documents, 0 mismatches. Browser contract parity (forms, fields, FormData, links, paragraphs): 31 documents PASS.
- booking-browser: 248 layout states at 100% and 200% text over 1438x638, 1438x900, 390x844, 320x640 (no page overflow, no clipped controls); 2408 keyboard stops with a visible focus indicator; the legacy CSS-only need/for show-hide still works; intercepted native submissions carry the exact field names (GET step 1: for/date/from/to; POST step 2: _token, need, room, qty[], other_items, purpose, for/date/from/to; POST cancel: reason).
- Neighbouring suites unchanged and passing against the original and previous baselines: availability, task-ux, timeline, rooms-correction, foundations, layout, auth, shell-navigation (3 runs), adapted visual-refinement.

Real defects found by the new test and fixed in CSS only (no assertion weakened): equipment "picked" badge overflowed at 200% text (summary now wraps); long unbroken room names overflowed room options (now wrap); equipment quantity select and status badge overflowed at 320px with 200% text (item row stacks on narrow phones; badge wraps).

Deliberate exceptions / notes:
- The prototype's "Booking rules" side card, "Check free rooms" header button and source-contract strip were NOT added (prototype-only or duplicate of an existing hint link).
- Staff/admin sections of the shared detail page get the same panel styling here; their states are fixture-covered for parity (`show-lab_staff-*`, `show-admin-*`) and are designed/verified in Phase 05.
- NOT RUN: PHPUnit, authenticated live app, real booking submission.
