# ReserveBot V2 — Source-aware frontend implementation roadmap

**Status:** V2-00 planning draft for human review. **No implementation phase is approved.**

**Purpose:** A *second-pass visual redesign* of an existing Laravel/Blade reservation system. This roadmap does not replace `docs/frontend/PHASE_PLAN.md`, historical `docs/codex/` reports, existing designs, tests, or backend functionality.

## 1. Evidence and truth labels

- **USER-OBSERVED (2026-11-06):** Current local branch is `feat/frontend-visual-refinement`. `git status --short` shows six modified frontend CSS files, a modified `docker-compose.yml`, and an untracked `docker-compose.yml.save`. The new roadmap is untracked. `git diff --check` produced no diagnostics.
- **USER-OBSERVED:** The user backed up the six-file uncommitted CSS diff to `~/reservebot-design/backups/stage3-uncommitted-css.patch`. This is an additional copy, **not** a Git checkpoint or proof the patch applies elsewhere.
- **SOURCE-SNAPSHOT:** The supplied November 2026 `ReserveBot-dev.zip` contains `docs/frontend/SCREEN_INVENTORY.md`, `docs/frontend/PHASE_PLAN.md`, the existing frontend phase reports, and the Laravel files named below. `~/reservebot-design/ReserveBot_Verified_Design/SOURCE_CONTRACTS.json` maps 29 application screens and six error screens from that snapshot. These paths and labels are **not yet independently checked against the user's current worktree**.
- **HISTORY/USER-REPORTED:** Original frontend foundations/layout/auth/representative availability, shell refinement, Stage 2 UX and timeline were implemented; Stage 2 merged into `dev`. Stage 3 visual CSS refinement was interrupted before final verification. Do not claim current `dev` HEAD or test status without new commands.
- **UNVERIFIED:** The current Git commit SHA; whether the current source has changed relative to the design snapshot; full route/action parity across all 35 screens; end-to-end/database/browser compatibility; design fidelity and deployment readiness.

**Source priority:** (1) current Laravel routes/controllers/services/models/Blade and tests for behavior; (2) `AGENTS.md` Functional Parity Contract and current architecture/inventory; (3) source-aligned design handoff and HTML/CSS **for presentation only**; (4) historical Figma frames and reports. Discrepancies must be recorded, never guessed away.

### Explicit corrections to the previous local-model roadmap

The previous roadmap mistakenly asserted `main`, clean Git state, Stage 3 changes in `resources/css/app.css`, and a three-phase Rep/Lab/Admin plan. **All four claims are rejected.** No comprehensive source comparison or functional-parity audit has yet been demonstrated by the local model.

## 2. Protected working tree and continuity decision

**Current branch (user-observed):** `feat/frontend-visual-refinement`.

**Pre-existing modified files:**

- `public/css/auth.css`
- `public/css/availability.css`
- `public/css/dashboard.css`
- `public/css/foundations/components.css`
- `public/css/foundations/tokens.css`
- `public/css/layout.css`
- `docker-compose.yml` (**protected, unrelated local change**)

**Pre-existing untracked files:** `docker-compose.yml.save` (**protected**) and this V2 roadmap.

**Additional protected content:** `.env`, backend services/routes/controllers/models/database/migrations, existing design references and historical documentation; do not touch without explicit separate approval.

**Stage 3 integration decision — NOT YET MADE:** The six CSS edits may be reusable V2-01 groundwork; they are not assumed approved or fully tested. Before creating V2-01 branches, the human reviewer must choose whether to complete/commit/merge Stage 3 as a limited change or preserve it on its current branch while starting V2 from reviewed `dev`. No automatic branch switch, stash, reset, cherry-pick, rebase, commit or merge.

**Safe read-only checks on the user's machine (run before V2-01):** `git branch --show-current`, `git status --short`, `git log -5 --oneline`, `git diff --stat`, `git diff --check`, and compare relevant current source files against the uploaded snapshot. No database or Docker operations.

## 3. Existing work worth reusing

Reuse, subject to CURRENT SOURCE confirmation:

- `public/css/foundations/{tokens,components,fonts}.css`, self-hosted Inter, reusable Blade UI components.
- `public/css/layout.css`, `public/js/layout.js`, role-aware shell, stationary desktop header/sidebar, independently scrolling main content and mobile natural-scroll fallback.
- `public/css/auth.css`, authentication/account templates.
- `public/css/dashboard.css`, representative task-first dashboard, class-answer forms and timetable disclosure.
- `public/css/availability.css`, existing search-first Free Rooms, contextual calendar details, proportional availability board and exact free-time labels.
- Historical verification evidence in `docs/codex/PHASE_00_AUDIT.md`, `PHASE_01_FOUNDATIONS.md`, `PHASE_02_LAYOUT.md`, `PHASE_03_AUTH.md`, `PHASE_04_AVAILABILITY.md`, `STAGE_01_SHELL_REFINEMENT.md`, `STAGE_02_TASK_UX.md`, and `SHELL_SCROLLING_ARCHITECTURE.md`.

These are **candidates for reuse**, not blanket authorization to retain every current design detail or claim tests passed in V2.

## 4. Screen-by-screen coverage checklist (source snapshot)

Each screen starts as `REVIEW CURRENT SOURCE`; do not mark `DONE` merely because the HTML prototype has a screen. Screens may share layout/components, but distinct roles/states must remain covered.

| ID | Screen | Roles | Main Blade view in uploaded source | V2 phase | Status |
|---|---|---|---|---|---|
| S01 | Login | Guest | `resources/views/auth/login.blade.php` | 02 | REVIEW CURRENT SOURCE |
| S02 | Forgot password | Guest | `resources/views/auth/forgot.blade.php` | 02 | REVIEW CURRENT SOURCE |
| S03 | Reset password | Guest | `resources/views/auth/reset.blade.php` | 02 | REVIEW CURRENT SOURCE |
| S04 | Mandatory onboarding | Rep/Lab/Admin | `resources/views/account/welcome.blade.php` | 02 | REVIEW CURRENT SOURCE |
| S05 | Own account | Rep/Lab/Admin | `resources/views/account/info.blade.php` | 02 | REVIEW CURRENT SOURCE |
| S06 | Own password | Rep/Lab/Admin | `resources/views/account/password.blade.php` | 02 | REVIEW CURRENT SOURCE |
| S07 | Email connection result | Signed-link visitor | `resources/views/auth/email_link.blade.php` | 02 | REVIEW CURRENT SOURCE |
| S08 | Representative dashboard | Rep | `resources/views/home/_representative.blade.php` | 03 | REVIEW CURRENT SOURCE |
| S09 | Lab Staff dashboard | Lab | `resources/views/home.blade.php` | 05 | REVIEW CURRENT SOURCE |
| S10 | Admin dashboard | Admin | `resources/views/home.blade.php` | 06 | REVIEW CURRENT SOURCE |
| S11 | Free Rooms | Rep/Lab/Admin | `resources/views/rooms/index.blade.php` | 03 | REVIEW CURRENT SOURCE |
| S12 | Create reservation | Rep | `resources/views/bookings/create.blade.php` | 04 | REVIEW CURRENT SOURCE |
| S13 | My bookings | Rep | `resources/views/bookings/mine.blade.php` | 04 | REVIEW CURRENT SOURCE |
| S14 | Booking detail (role-dependent) | Rep/Lab/Admin | `resources/views/bookings/show.blade.php` | 04,05 | REVIEW CURRENT SOURCE |
| S15 | Approval requests | Lab/Admin | `resources/views/requests/index.blade.php` | 05 | REVIEW CURRENT SOURCE |
| S16 | Today's schedule | Lab/Admin | `resources/views/today/index.blade.php` | 05 | REVIEW CURRENT SOURCE |
| S17 | Signed class answer | Signed-link visitor | `resources/views/classes/check.blade.php` | 09 | REVIEW CURRENT SOURCE |
| S18 | Manage rooms | Lab/Admin | `resources/views/manage/rooms.blade.php` | 07 | REVIEW CURRENT SOURCE |
| S19 | Edit room | Lab/Admin | `resources/views/manage/room_edit.blade.php` | 07 | REVIEW CURRENT SOURCE |
| S20 | Equipment inventory | Lab/Admin | `resources/views/manage/equipment.blade.php` | 07 | REVIEW CURRENT SOURCE |
| S21 | Edit equipment | Lab/Admin | `resources/views/manage/equipment_edit.blade.php` | 07 | REVIEW CURRENT SOURCE |
| S22 | Rules and terms | Lab/Admin | `resources/views/manage/rules.blade.php` | 07 | REVIEW CURRENT SOURCE |
| S23 | Manage accounts | Admin | `resources/views/accounts/index.blade.php` | 06 | REVIEW CURRENT SOURCE |
| S24 | Edit managed account | Admin | `resources/views/accounts/edit.blade.php` | 06 | REVIEW CURRENT SOURCE |
| S25 | Timetable overview | Lab/Admin | `resources/views/manage/timetable.blade.php` | 08 | REVIEW CURRENT SOURCE |
| S26 | Timetable in use | Lab/Admin | `resources/views/manage/timetable_rows.blade.php` | 08 | REVIEW CURRENT SOURCE |
| S27 | Draft timetable | Lab/Admin | `resources/views/manage/timetable_rows.blade.php` | 08 | REVIEW CURRENT SOURCE |
| S28 | Edit draft row | Lab/Admin | `resources/views/manage/timetable_row.blade.php` | 08 | REVIEW CURRENT SOURCE |
| S29 | Check draft / apply | Lab/Admin | `resources/views/manage/timetable_check.blade.php` | 08 | REVIEW CURRENT SOURCE |
| E403/E404/E413/E419/E429/E500 | Six error surfaces | Conditional | `resources/views/errors/{403,404,413,419,429,500}.blade.php` | 09 | REVIEW CURRENT SOURCE |

**Additional role variants** (e.g., Staff/Admin on Free Rooms, detailed booking operations, and shared account flows) must be reviewed even where the view path is shared. Reconcile screen coverage with `docs/frontend/SCREEN_INVENTORY.md` and the current `routes/web.php` before implementation.

## 5. V2 implementation phases and exit gates

| Phase | Scope and intended visual goal | Must preserve | Exit evidence |
|---|---|---|---|
| **V2-00** | **Continuity and design alignment**, no application edits. This roadmap and review of unfinished Stage 3 changes. | Every existing file, local Docker/`.env`, current branch, historical records. | Accurate Git state; coverage checklist; explicit unverified claims; human approval to choose branch strategy. |
| **V2-01** | **Shared foundations + shell.** Refine tokens, surfaces, navigation, header/sidebar, buttons, cards, form primitives to approved visual language. Reuse Stage 3 CSS where useful. | Stationary shell, role navigation, focus, responsive fallback, native menus/forms. | Shared component parity; representative desktop/mobile shell screenshots; CSS/layout regressions. |
| **V2-02** | **Auth + own account** S01–S07. | Login/recovery CSRF, field names, onboarding conditions, photo/email/password separate forms, signed-link outcomes. | Guest + each role/validation fixtures; login controls at 1438×638; targeted auth checks. |
| **V2-03** | **Rep dashboard + Free Rooms** S08,S11. | Class `answer=f2f/online`, native actions, actual room free ranges, search GET keys, calendar context, shell scrolling, role privacy. | Rep meeting parity, 3-role Free Rooms parity, timeline geometry, keyboard/fragment tests, screenshots. |
| **V2-04** | **Rep booking workflows** S12–S14 (Rep-specific detail). | WHEN-first then RESOURCE choice, GET/POST fields, clash/status/validation/cancellation, owner visibility. | Two-stage forms and special cases, native payload parity, permitted links/actions. |
| **V2-05** | **Lab operations** S09,S15,S16 and Staff/Admin detail actions on S14. | Real counts/queues, approval/rejection reasons, handout/return/no-show/move rules and conditional state. | Lab/Admin role tests, invalid/late transitions, state-dependent action checks. |
| **V2-06** | **Admin + managed accounts** S10,S23,S24. | Real pending counts only, account role gates, active state, separate password, create/edit/remove rules. | Admin screens, disabled/invalid states, no invented metrics. |
| **V2-07** | **Rooms, equipment, department rules** S18–S22. | Original field names/limits, active flags, warnings about shared resources, stock and term rules. | Lab/Admin management parity, validation and empty states, mobile tables/forms. |
| **V2-08** | **Timetables** S25–S29. | Upload/draft/live/check/apply workflow, validation issues, backup/undo, action-specific submitter/`formaction` and version checks. | Draft/live/invalid states, exact field/action parity and role checks; no live replacement on dev DB. |
| **V2-09** | **Signed class check + six error templates**, shared exceptional/empty states. | Signed URL semantics, no action on GET, role-sensitive links, proper 403/404/413/419/429/500 guidance. | Signed/expired/denied/empty fixtures, error view snapshots, accessible navigation. |
| **V2-10** | **Integration visual consistency and QA.** No new feature scope. | All prior contracts. | Cross-role smoke, accessibility/responsive review, comprehensive relevant regressions, documented limitations and human QA handoff. |

**Phase discipline:** One approved design/implementation pass and one milestone verification pass. Small CSS adjustments get focused checks; full relevant regressions happen at meaningful checkpoints. Do not repeatedly redesign or weaken parity assertions to match mockup HTML.

## 6. High-risk contract examples to verify per affected phase

- **Booking (S12):** Source-snapshot GET parameters include `for`, `session`, `date`, `from`, `to`, `room`, `change`; POST includes `need`, `room`, `qty[id]`, `other_items`, `purpose` and stage-1 hidden fields. Recheck current views; mockup labels cannot replace submitted fields.
- **Class confirmations (S08/S17):** Two named submitters (`answer=f2f` / `answer=online`) and signed-link constraints; do not convert to a single generic save action.
- **Reservation details (S14):** Representatives can cancel when allowed, but staff/admin review, return, handout, no-show and move actions depend on role and status. Do not show staff-only move action to representatives.
- **Free Rooms (S11):** Exact `date`, `from`, `to`, role-filtered information, free-time timeline semantics: green = currently reported free; neutral does NOT automatically mean occupied/blocked; separate agenda/calendar datasets.
- **Managed accounts (S23/S24):** Admin account operations, field validation, activation and separately submitted password form; don't invent public registration.
- **Timetable (S25–S29):** Draft/check/replace, version, warnings, special alternate submit action, invalid rows, and upload/download restrictions. Avoid accidental live data mutations.
- **Dashboards (S09/S10):** Do not invent metrics not supplied by controllers. Dummy names/counts from prototype are EXAMPLE_ONLY.

These examples reflect the uploaded source snapshot; every mutation/form requirement must still be validated against the current checkout.

## 7. Approved design reference versus real behavior

- **Presentation target:** `~/reservebot-design/ReserveBot_Verified_Design/index.html`, `style.css`, `IMPLEMENTATION_HANDOFF.md`.
- **Screen contract index:** `~/reservebot-design/ReserveBot_Verified_Design/SOURCE_CONTRACTS.json` (design snapshot, not runtime truth).
- **Illustrative:** persons, rooms, counts, dates, reservations, status totals and simulated front-end interactions in the HTML prototype.
- **Do not copy:** Mockup JavaScript simulation, example data, invented metrics/actions, or placeholder API behavior into Laravel.
- **Current-source discrepancies:** NONE VERIFIED YET. This means *unexamined*, not that there are none. Add rows here as source comparisons are actually performed.

## 8. Branch strategy — requires review

- Integration target is **`dev`**, not `main`.
- Keep the old historical phase branches/reports intact; V2 is a distinct second-pass initiative.
- First decide what to do with existing uncommitted Stage 3 CSS on `feat/frontend-visual-refinement`. Never switch branches, reset, clean or cherry-pick automatically. User reviews any checkpoint/commit/merge strategy.
- Once a baseline is approved, create a **separate V2 feature branch from updated `dev` for each phase** (candidate names: `feat/v2-01-foundations-shell` through `feat/v2-09-shared`; `feat/v2-10-qa`). Use the branch names only after user approval. Do not assume the mockup snapshot is current.
- Each phase stops before `git add`, commit, push or PR. Human reviews changed-file allowlist and verification evidence, then performs Git operations.
- Protected: `docker-compose.yml`, `docker-compose.yml.save`, `.env`, current database and project secrets.

## 9. Test/verification protocol

**Before modifying each screen:** identify route → middleware/permissions → controller/service data → Blade form/action/condition → relevant tests. List exact state/role variants. Use a short, **source-verified** parity matrix; unknown means unknown.

**During development:** make small scoped edits; check CSS/parser syntax, `git diff --check`, and only affected unit/render/browser fixtures. Preserve native forms (CSRF/method spoofing/field names, disabled/selected and validation bags), links, role conditions, visibility and keyboard semantics.

**At phase checkpoint:** run the existing *relevant* rendering and browser regression suites, targeted 1438×638 + mobile screenshots, focus/no-JS checks and visible-action reachability. Test role and permission variants. If PHPUnit is unavailable, report as **NOT RUN**; don't call it passed. Authenticated HTTP/DB checks require a separately approved isolated MariaDB test environment. Do not run migrations/seeders against user development data.

**Acceptance categories:** (a) functional parity evidenced, (b) visual target evidenced, (c) test coverage/gaps explicitly recorded. Passing static tests alone never means deployment ready.

## 10. Resumable checkpoint

**Last user-observed Git state:** `feat/frontend-visual-refinement`; 6 edited CSS files, `docker-compose.yml` modified, `docker-compose.yml.save` and this roadmap untracked. The Stage 3 CSS patch has been copied outside repo. **Do not assume this remains true on the next session: recheck.**

**Current phase:** V2-00 — draft complete, awaiting human review; no app code changes authorized.

**Next actions (human approval required):**
1. Verify current status and source against this roadmap, especially screen paths and Stage 3 CSS.
2. Decide treatment of existing Stage 3 branch before V2-01 branch creation.
3. Approve V2-01 scope/file allowlist and then permit narrow implementation, not all V2 phases.

**Next agent starting instructions:** read `AGENTS.md`, this roadmap and only the relevant source contracts; verify Git state; report discrepancies; work **only** on the exact phase the user approved. If context is insufficient or commands/tool output are contradictory, STOP instead of inventing a report.

## Phase 01A checkpoint — desktop shell (awaiting visual review)
- Changed: `public/css/layout.css`; `layouts/partials/{header,navigation,masthead}.blade.php`.
- Brand now fills the sidebar column of the header row; topbar shows a "Workspace › page title" crumb plus account chip and the real logout form. Role card (role label only), refined nav/count badge, scoped header avatar, gold masthead separator. Mobile architecture and short-viewport fallback unchanged.
- Verified: isolated layout (279), foundations (63), auth (1318), availability (2137) checks PASS in a throwaway container with the working tree mounted read-only; fixture screenshots at 1438×638, 390×844, 320×640 (no horizontal overflow). NOT RUN: authenticated pages on the live app, PHPUnit (`artisan test` is absent in the image).
- Open: department line in role card (needs a loaded relation; the layout harness forbids extra queries), masthead wording ("and Architecture"?), tablet rail intentionally excluded.

## Phase 03A checkpoint — Representative dashboard (awaiting visual review)
- Changed: `resources/views/home/_representative.blade.php`, `public/css/dashboard.css`. `home/_meeting.blade.php`, `classes/_answer.blade.php`, controllers and routes unchanged.
- Added a role kicker, a banner carrying the existing Laravel `$intro` text, icon action tiles that keep the exact link text, card-header dividers, and grid-based meeting rows (date over class; status and the shared answer form on the right).
- Verified on fixture exports from the working tree: isolated checks (279/63/1335/2137), availability-browser (parity vs pre-change baseline), task-ux, visual-refinement (before=after export; its HTML-parity stage is not applicable to an intentional markup change), shell-navigation, layout, auth browser suites; 696-stop keyboard sweep and 200% text sweep over 8 home states x 4 viewports. NOT RUN: live app, PHPUnit, real class-answer submissions.
- Next: Phase 03B (Free Rooms) only after review.
