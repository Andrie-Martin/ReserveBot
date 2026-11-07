# ReserveBot — Frontend Design Implementation Contract

**Purpose:** Finish the frontend of the **existing** Laravel/Blade ReserveBot application across Representative, Lab Staff, Admin and shared screens. This supplements `AGENTS.md` and its **Functional Parity Contract**; it does not replace them. No new application or backend redesign.

## Authority and conflict resolution

1. **Current Laravel code and tests govern behavior:** routes, HTTP methods, roles, middleware, forms, validation, conditions, state transitions, privacy, actual data and algorithms.
2. **Our approved source-aligned mockup governs appearance:** `/home/james/reservebot-design/ReserveBot_Verified_Design/index.html` and `style.css`. Match its composition, layout hierarchy, design language, navigation, typography, spacing, responsive patterns, cards, tables and forms. Historical Figma exports are secondary references.
3. **Mockup handoff aids discovery, not proof:** same directory's `IMPLEMENTATION_HANDOFF.md` and `SOURCE_CONTRACTS.json` map 29 app screens and six error pages from an **older source snapshot**. Recheck the current checkout. Demo people, dates, bookings, counts, statuses and interactions are illustrative.

If mockup and Laravel disagree, **preserve Laravel functionality and adapt the visual design**. Never copy prototype simulation JavaScript, fake records, unsupported analytics or unauthorized controls into production. A missing mockup control never justifies deleting a working Laravel capability. Report genuine conflicts rather than guessing.

## Non-negotiable constraints

- Preserve all existing native forms and semantics: action/method, route helpers, `@csrf`, `@method`, field `name`/`value`, named submitters and `formaction`, hidden fields, validation/error bags, old input, flash feedback, disabled states, GET query parameters, redirects and signed links.
- Preserve real role/ownership/connected-email restrictions; statuses, conditional actions, empty/error states; booking, resource, timetable and availability calculations. Do not change controllers, routes, services, models, migrations, permissions or database in presentation phases.
- Watch especially for **When → Need** booking stages; rep cancel vs staff/admin move/review operations; `answer=f2f` / `answer=online` as **two submit buttons**; timetable **draft → check → replace**; timeline free bands computed from server ranges (neutral does not prove occupied); real controller-provided dashboard statistics only.
- Reuse existing Blade components, `.rb-*` classes, `--rb-*` tokens, self-hosted Inter, page-scoped CSS and stationary desktop shell/main scroll. Keep responsive and keyboard/no-JS behavior. Do not replace whole stylesheets or add new frameworks/CDNs by default.
- Protect `docker-compose.yml`, `docker-compose.yml.save`, `.env`, local database and secrets. Never modify, stage, reset or remove them during frontend work. Do not independently switch branches, commit, push, merge, rebase, stash or run destructive commands.

## One-task implementation protocol

**Inspect → Implement → Verify → Stop.** Only one user-approved screen or tightly related component group per task.

1. Confirm `pwd`, current branch and Git status. Read `AGENTS.md`, this contract and **only the relevant roadmap section**. Trace affected route → middleware/permission → controller/service → Blade → scoped CSS → tests. Consult only corresponding `SOURCE_CONTRACTS.json` record and mockup screen.
2. Before editing, provide a **short parity matrix** of existing actions/forms/roles/states, a concrete current-versus-mockup **visual delta**, and an exact allowed-file list. Do not claim a source was inspected unless it was. Stop only for significant functional/scope conflicts; make ordinary styling decisions from the approved mockup.
3. Implement the smallest coherent changes while retaining every source-backed control and conditional state. Use actual collections, current routes and data, not prototype examples. Do not silently broaden scope.
4. Verify `git diff --check`, the allowed paths, targeted existing tests and relevant rendered states. Compare real Laravel screenshots to the mockup at **1438×638** and **390×844**; inspect **320×640** for overflow/focus. If browser or authenticated tests weren't run, mark **NOT RUN**, never **PASS**. Do not weaken tests to accommodate the redesign.
5. Update the roadmap with actual changed files, results, screenshot evidence, limitations and the next small task. **Stop for review**, without committing or proceeding to another task.

**Acceptance:** Visual match to the approved prototype's composition and design language **plus** functional parity evidenced by relevant tests and source review. Passing static fixtures alone is not production or end-to-end verification.

## Continuity and token economy

- Integration branch: `dev`. Start new phase branches from the **post-Stage-3 merged** `dev` baseline, with the user's approval. Keep all original `docs/codex/` phase reports, `docs/frontend/PHASE_PLAN.md`, and historical Figma references.
- `docs/frontend/V2_IMPLEMENTATION_ROADMAP.md` is the existing **Frontend Design Completion** roadmap; “V2” is merely an organizational label. Its pre-merge Git/status notes are superseded by the verified post-Stage-3 checkpoint and must be refreshed before implementation.
- Keep per-task prompts short: name the screen(s), approved visual target and allowed file scope. Read **relevant files and contract sections**, not the whole repo or all 29 screen records. Run focused tests during implementation and broader regressions at phase completion.
- Final response in six compact fields: **Changed | Functional parity | Tests | Visual comparison | Gaps | Next task**. No enormous code dumps unless requested.

**If the agent cannot access the prototype on disk, it must say so and stop rather than inventing a visual comparison.**
