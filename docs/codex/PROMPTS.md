# Ready-to-copy Codex prompts

## A. Phase 00 — architecture audit (start here)
Read `AGENTS.md`, `docs/codex/PHASE_00_AUDIT.md`, `docs/architecture/SYSTEM_MAP.md`, `docs/frontend/SCREEN_INVENTORY.md` and `docs/frontend/PHASE_PLAN.md`. You are on the `docs/frontend-00-audit` branch of the current ReserveBot checkout. Perform a read-only, evidence-based system audit before any UI implementation. Map routes, middleware, roles, controller/service/model behavior, Blade views and form contracts; pay special attention to the newly added email/account/class-check/timetable functionality. Use safe commands, cite paths and line ranges, and mark all uncertainties as UNVERIFIED. First report the architecture, potential breaking points, and proposed documentation edits. After I approve, update ONLY the two architecture/inventory Markdown files and run `git diff --check`. Do not modify application code, run migrations, commit, push or merge. Stop after Phase 00.

## B. Preflight for any frontend phase
Read `AGENTS.md`, the completed architecture and screen inventory, the Figma-based `DESIGN_SYSTEM.md`, and the specific phase plan. Analyze the actual current routes/views and contracts before coding. Show the smallest file-level plan, reuse opportunities, affected roles and state flows, tests, and unknowns. Do not edit until approved.

## C. Implement an approved phase
Implement ONLY Phase XX on its dedicated branch from `dev`, following approved plan and existing code contracts. Preserve backend semantics. Extract shared Blade/CSS patterns instead of duplication. Use authentic dynamic data and existing routes. Run safe relevant checks, report results and changed files. Do not commit, push, merge, install packages, or start the next phase without separate permission.

## D. Review before PR
Audit the current phase diff against `docs/codex/PR_CHECKLIST.md`. Find backend contract changes, role/security regressions, missing states, duplicate assets, responsive/accessibility issues and unverified claims. Propose or apply only approved phase-scope fixes. Provide tested outcomes and a PR description targeting `dev`.
