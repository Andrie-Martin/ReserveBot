# Phase 00 — Codex architecture onboarding (no application edits)

## Objective
Fully understand the **current cloned checkout**, not an older ZIP and not the Figma prototype. Deliver source-grounded architecture and screen documentation, with explicit unknowns and tests. **No UI implementation in this phase.**

## Initial read-only investigation
1. Read root `README.md`, `AGENTS.md`, `composer.json`, `composer.lock`, `Dockerfile`, `docker-compose.yml`, `docker/entrypoint.sh`, `.env.example`, `bootstrap/app.php`, `routes/web.php`, `routes/console.php` and tests. Never print actual `.env` secrets.
2. Run `git status --short`, `git rev-parse --short HEAD`, `git branch --show-current`, and `git log -5 --oneline`; inspect pending changes, especially local Docker settings. Do not alter them.
3. Use safe commands: `docker compose ps`, `docker compose exec -T app php artisan route:list`, `docker compose exec -T app php artisan about` (if running). Avoid migrations, writes, scheduled jobs, emails, seeders or destructive commands as discovery.
4. Trace every major workflow through route, middleware, controller method, service, model/data, and actual Blade view. Inspect `resources/views/layouts`, components, forms, and `public/css/style.css` / any asset tooling.
5. Map exact role names, permissions, availability rules, reservation transitions, approval/cancellation/handover logic, email verification/reminders, password/account lifecycle, timetable import/editing, and class checks.
6. Inspect existing test coverage and identify the safest test commands/environment. Do not run tests against a production database. Note tests that are absent or risky.
7. Identify visual Figma screens already represented in Blade and missing screens introduced by the latest backend changes (account, email, class checking, timetable, etc.).

## Deliverables (docs only, after proposed findings are reviewed)
- Populate `docs/architecture/SYSTEM_MAP.md` with actual evidence and a date/SHA.
- Populate `docs/frontend/SCREEN_INVENTORY.md` with route names, actual views, roles, form contracts and states.
- Update `docs/frontend/PHASE_PLAN.md` only if verified architecture requires changing phase boundaries; report proposals first.
- Provide architecture diagram in Markdown/Mermaid if it improves clarity, but no invented interfaces.

## Evidence standard
- For every architectural claim, record a path, class/function/route and ideally line range.
- Distinguish observed behavior, inferred behavior, missing evidence and proposed changes.
- Flag conflicts between documentation and code, fragile contracts and UI-risk areas.

## Forbidden in Phase 00
- No edits to `app/`, `routes/`, `database/`, `config/`, `resources/`, `public/`, tests, Docker files, dependency manifests, or CI.
- No package installation, database mutations, seed runs, mail sends, migrations, scheduler runs, git commits/pushes/merges, or deployment.
- No 'quick frontend fixes' while auditing; report them as later work.

## Completion criteria
- Every current significant route is covered or explicitly documented as out of scope.
- Key user roles and reservation/status transitions have verified source evidence.
- Backend-protected behaviors and form contracts are listed.
- Current Figma gaps are visible in the screen inventory.
- A phase-by-phase frontend plan has clear validation gates.
- Only approved documentation files are changed; `git diff --check` passes.
- Stop and ask for review before Phase 01.
