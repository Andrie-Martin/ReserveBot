# ReserveBot — repository guidance for Codex

## Mission and boundaries
ReserveBot is a PHP/Laravel room and equipment reservation application for PUP College of Engineering. Preserve existing behavior and security while incrementally replacing the provisional frontend with the approved PUP-inspired Figma design.

**Never infer how a feature works from a mockup or filename. Read the current code and tests first.** The linked Figma is a visual reference, not an authority on routes, authorization, data, business rules, or functionality.

## Before doing any implementation
1. Read `docs/architecture/SYSTEM_MAP.md`, `docs/frontend/SCREEN_INVENTORY.md`, `docs/frontend/DESIGN_SYSTEM.md`, `docs/frontend/PHASE_PLAN.md`, and the approved phase brief. If an inventory is incomplete, perform the Phase 00 audit instead of guessing.
2. Check `git status`, current branch, and latest relevant changes. Do not switch branches, stash, commit, push, merge, or modify infrastructure without the user's approval.
3. Trace affected routes -> middleware/authorization -> controller -> services/models -> Blade templates -> CSS and tests. Cite file paths and lines in the plan.
4. Summarize proposed files to change, affected roles/states, risks, verification, and unknowns **before writing application code**. Stop for approval if behavior is not proven.

## Hard safeguards
- Frontend tasks are presentation-only unless a backend change is separately reviewed and approved. Do not alter routes, middleware, authorization, policies, controllers, models, services, migrations, validation, notification behavior, seeders, or scheduled jobs as a shortcut to make a UI work.
- Preserve existing route names, form `method`/`action`, field `name`s, CSRF tokens, validation errors, old input values, flash messages, query parameters, pagination, permissions, and conditional role-specific content. Audit each before refactoring a view.
- Confirm user roles and state transitions in the CURRENT checkout. Never guess whether an action is allowed just because a button exists in Figma.
- Never fake live status, availability, bookings, approvals, or email delivery in production UI. Clearly mark sample data only inside prototypes/tests.
- Do not introduce React, Vue, a CSS framework, a bundler, major package, remote CDN, or dependency without an explicit architecture decision and approval. Prefer current Laravel Blade and the project's existing asset approach.
- Avoid duplicate markup and magic values. Extract shared Blade components/partials, and use approved design tokens. Reuse existing components before making new ones.
- Keep semantic HTML, labels, keyboard access, visible focus, contrast, responsive tables/forms, and helpful empty/error/loading states.
- Do not reveal `.env`, credentials, tokens, private data, or seed passwords in outputs. Never run `migrate:fresh`, `db:wipe`, destructive cleanup, or force push.
- Local Docker configuration may differ from the committed compose file. Do not stage or overwrite the developer's `docker-compose.yml` or `docker-compose.yml.save` when doing frontend work.

## Verification and handoff
- Check the targeted views under every applicable role and important state (empty, pending, approved, rejected, validation errors, expired session, permissions).
- Run relevant existing tests plus targeted checks when practical; report actual commands, results, and any tests not run. Do not claim success on unobserved outcomes.
- Keep changes within the approved phase. Do not automatically begin the next phase or merge to `dev`.
- Every pull request should target `dev`, include before/after evidence, changed files, backend-invariant checks, and test results. The user/team owns commits, pushes, merges and release decisions.

## Required source of truth
- Architecture: `docs/architecture/SYSTEM_MAP.md` (must be refreshed from current code).
- UI inventory: `docs/frontend/SCREEN_INVENTORY.md`.
- Visual system: `docs/frontend/DESIGN_SYSTEM.md` and the Figma design (visual only).
- Phase scope: `docs/frontend/PHASE_PLAN.md` and `docs/codex/PHASE_00_AUDIT.md`.
- Review: `docs/codex/PR_CHECKLIST.md`.

If a document disagrees with running code, trust verified code/tests for current behavior, report the discrepancy, and ask before changing functionality.

## Functional Parity Contract — mandatory for every frontend phase

The current Laravel application is the source of truth for functionality; Figma supplies visual direction only. Before redesigning a screen, inventory every interactive control, visible information item, notification, status, shortcut and conditional state. Map each interaction to its route, HTTP method, controller behavior, authorization and state conditions.

Produce a before-and-after Functional Parity Matrix identifying where every existing capability will appear. Preserve all capabilities unless the user explicitly approves removal, including form names/values, CSRF, method spoofing, validation/error bags, old input, redirects, sessions, signed links, permissions and role-specific states.

Missing features in Figma are design coverage gaps, never permission to remove functionality. Do not substitute decorative controls, placeholder links or dummy data for working behavior. Define and execute appropriate regression checks; distinguish isolated rendering from authenticated workflow verification and report every unexecuted check. A redesign that removes working functionality fails acceptance.

## Frontend design completion

For frontend redesign tasks, follow
`docs/frontend/DESIGN_IMPLEMENTATION_CONTRACT.md`.

Visual reference:
`/home/james/reservebot-design/ReserveBot_Verified_Design/`

The Laravel source remains authoritative for functionality.
The prototype governs visual presentation only.
