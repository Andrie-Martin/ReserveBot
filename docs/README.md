# ReserveBot documentation index

Frontend redesign status as of `origin/dev` @ `0a30297` (2026-11-08): design phases 01-13 and correction PRs #26, #27 and #28 are merged.
**Reviewing the frontend? Start with [frontend/QA_HANDOFF.md](frontend/QA_HANDOFF.md).** For running the app, see the [project README](../README.md). The latest UI polish pass is described in [frontend/UI_POLISH_ROOM_CONTROLS.md](frontend/UI_POLISH_ROOM_CONTROLS.md).

How to read this folder: files marked **Current** describe how things are now. Files marked **Historical** are reports written at a point in time; they are kept as evidence
and are *not* edited to look current (several carry a status banner pointing to what superseded them). When a historical report and `QA_HANDOFF.md` disagree, `QA_HANDOFF.md` and Git history win.

## 1. Start here (Current)

| Document | Use it for |
|---|---|
| [frontend/QA_HANDOFF.md](frontend/QA_HANDOFF.md) | What is merged, what was tested and how, open risks, how to review `dev` by hand |
| [frontend/LOGIN_CARD_AND_PAGE_LOAD_FIXES.md](frontend/LOGIN_CARD_AND_PAGE_LOAD_FIXES.md) | PR #27 (login maroon card) and PR #28 (page-load flicker): root causes, fixes, evidence |
| [../AGENTS.md](../AGENTS.md) and [../CLAUDE.md](../CLAUDE.md) | Rules for AI-assisted work in this repository |

## 2. Reference for how the system and design work (Current, with dated baselines)

| Document | Notes |
|---|---|
| [architecture/SYSTEM_MAP.md](architecture/SYSTEM_MAP.md) | Routes, controllers, roles, data and rules. Baseline written 2026-11-03 with dated update sections; confirm against code before relying on a detail. |
| [frontend/SCREEN_INVENTORY.md](frontend/SCREEN_INVENTORY.md) | Screen-to-view/route mapping. Baseline 2026-11-03. |
| [frontend/DESIGN_SYSTEM.md](frontend/DESIGN_SYSTEM.md) | PUP-inspired design rules and tokens (`--rb-*`). Unofficial student concept, not official PUP branding. |
| [frontend/DESIGN_IMPLEMENTATION_CONTRACT.md](frontend/DESIGN_IMPLEMENTATION_CONTRACT.md) | Authority rules: Laravel code governs behaviour, the approved prototype governs appearance. The prototype itself is not in the repository. |
| [frontend/references/figma/](frontend/references/figma/) | 14 exported design reference images (visual only). |
| [codex/PR_CHECKLIST.md](codex/PR_CHECKLIST.md) | Review checklist for frontend pull requests. |

## 3. Frontend delivery history

There are two phase numberings. Do not mix them.

**Early phases (PRs #1-#10, 2026-11-03 to 2026-11-06)** (Historical): audit, foundations, layout, auth, availability, then Stage 1-3 refinements.

| Document | Covers |
|---|---|
| [codex/PHASE_00_AUDIT.md](codex/PHASE_00_AUDIT.md) | Audit instructions (PR #1) |
| [codex/PHASE_01_FOUNDATIONS.md](codex/PHASE_01_FOUNDATIONS.md) | Design tokens and components (PR #3) |
| [codex/PHASE_02_LAYOUT.md](codex/PHASE_02_LAYOUT.md) | Global layout and navigation (PR #4) |
| [codex/PHASE_03_AUTH.md](codex/PHASE_03_AUTH.md) | Authentication and onboarding (PR #5) |
| [codex/PHASE_04_AVAILABILITY.md](codex/PHASE_04_AVAILABILITY.md) | Representative dashboard and room availability (PR #6) |
| [codex/STAGE_01_SHELL_REFINEMENT.md](codex/STAGE_01_SHELL_REFINEMENT.md) | Shell refinement (PR #7); its scrolling approach was superseded by the next file |
| [codex/SHELL_SCROLLING_ARCHITECTURE.md](codex/SHELL_SCROLLING_ARCHITECTURE.md) | Application-shell scrolling correction (PR #8) |
| [codex/STAGE_02_TASK_UX.md](codex/STAGE_02_TASK_UX.md) | Task-focused UX (PR #9) |
| [codex/STAGE_03_VISUAL_REFINEMENT.md](codex/STAGE_03_VISUAL_REFINEMENT.md) | Visual consistency refinement (PR #10) |
| [frontend/PHASE_PLAN.md](frontend/PHASE_PLAN.md) | The Phase 00 plan for that first wave (its phase numbers do **not** match the design phases below) |

**Design phases 01-13 (PRs #11-#25, 2026-11-06 to 2026-11-08)**: every screen redone against the approved prototype. Per-phase commit tables and verification notes:

| Document | Covers |
|---|---|
| [frontend/OVERNIGHT_PROGRESS.md](frontend/OVERNIGHT_PROGRESS.md) (Historical) | Phases 01-10 build log: commits, measurements, verification per phase |
| [frontend/INDEPENDENT_QA_REPORT.md](frontend/INDEPENDENT_QA_REPORT.md) (Historical) | Independent audit of the Phase 10 tree (findings F-01 to F-08) |
| [frontend/QA_CORRECTIONS.md](frontend/QA_CORRECTIONS.md) (Historical) | Phase 11 corrections for F-02, F-03, F-04, F-08 |
| [frontend/SECURITY_F01_LOGIN_THROTTLE.md](frontend/SECURITY_F01_LOGIN_THROTTLE.md) | Phase 12: login throttling, audit follow-ups, remaining limits |
| [frontend/BOOKING_F07_HANDOUT_GUARD.md](frontend/BOOKING_F07_HANDOUT_GUARD.md) | Phase 13: future-day hand-out / no-show guard (PR #25) |

**Correction PRs after phase 13:** [frontend/BOOKING_FB_STATUS_MOVE_RACE.md](frontend/BOOKING_FB_STATUS_MOVE_RACE.md) (#26), [frontend/LOGIN_CARD_AND_PAGE_LOAD_FIXES.md](frontend/LOGIN_CARD_AND_PAGE_LOAD_FIXES.md) (#27, #28).

## 4. Planning documents that were not carried out as written (Historical)

| Document | State |
|---|---|
| [frontend/V2_IMPLEMENTATION_ROADMAP.md](frontend/V2_IMPLEMENTATION_ROADMAP.md) | A "V2" second-pass roadmap drafted 2026-11-06. No implementation phase was approved from it; the branch `feat/frontend-v2-01-shell` has no commits beyond `dev`. The redesign was delivered as design phases 01-13 instead. Kept for its screen-by-screen analysis. |
| [codex/PROMPTS.md](codex/PROMPTS.md) | Prompt templates from the original starter kit |
| [archive/START_HERE.md](archive/START_HERE.md) | The original "Codex frontend starter kit" note (was `START_HERE.md` at the repository root) |

## 5. Known documentation caveats

- Reports link to screenshots and JSON files under `/tmp/...` or a developer's scratch folder. They were never committed, so those links do not resolve; the reports' numbers are the record.
- Reports include developer-machine paths (for example the prototype location and Playwright install paths). No secrets are present; the README documents the seeded test-account password as a development default.
- Evidence is described as of its own date. In particular, test counts differ between reports because the suite grew; [frontend/QA_HANDOFF.md](frontend/QA_HANDOFF.md) section 4 reconciles them.
- Not documented yet: a CI setup (none exists), a deployment guide, and a recorded full gated test run of the merged `dev`.
