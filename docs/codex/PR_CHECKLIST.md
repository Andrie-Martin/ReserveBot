# Frontend PR checklist (base branch: `dev`)

## Scope and evidence
- [ ] Phase and scope match `docs/frontend/PHASE_PLAN.md`; no unapproved backend edits.
- [ ] `git diff --name-only` reviewed; no `.env`, credentials, generated secrets or local Docker files.
- [ ] Each modified view's real routes, variables, role checks, forms, validation and old-input behavior verified against current code.
- [ ] Existing patterns and Blade components reused rather than duplicated.
- [ ] Design tokens and typography from `docs/frontend/DESIGN_SYSTEM.md` used consistently.
- [ ] No fake live data, mock statuses or broken placeholder buttons.

## User experience
- [ ] Desktop + mobile responsive layout; no clipping, overflow, inaccessible menus/tables.
- [ ] Keyboard navigation/focus/labels/errors work; contrast appropriate.
- [ ] Empty, loading, validation, unauthorized and success states considered.
- [ ] New features from current backend (email/account/timetable/class checks) accounted for when touched.

## Verification
- [ ] Relevant Laravel tests: command and result included (or why not safely runnable).
- [ ] Manual end-to-end role/workflow checks recorded with honest pass/fail notes.
- [ ] `git diff --check` passes.
- [ ] Before/after screenshots or Figma comparisons included when available.
- [ ] Remaining risks and follow-ups written down.
- [ ] PR targets `dev`, reviewed before merge. No force pushes, no direct `main` edits.
