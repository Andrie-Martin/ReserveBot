# ReserveBot Codex frontend starter kit

> **Archived 2026-11-08.** This was the root `START_HERE.md` of the original Codex frontend starter kit. The setup steps are finished and some of the listed files have since been filled in or superseded. Kept for history; current guidance is in [../README.md](../README.md) and [../frontend/QA_HANDOFF.md](../frontend/QA_HANDOFF.md).

**This is a documentation/planning starter kit, not a completed architecture audit.** The author's earlier code snapshot predates new changes pulled on November 3, 2026. The CURRENT repository checkout is authoritative.

## What is inside
- `AGENTS.md`: concise, repository-wide safety and development rules Codex recognizes automatically.
- `docs/codex/PHASE_00_AUDIT.md`: read-only audit checklist before UI edits.
- `docs/architecture/SYSTEM_MAP.md`: architecture map template; initially unverified.
- `docs/frontend/SCREEN_INVENTORY.md`: Figma-to-Laravel view mapping template.
- `docs/frontend/DESIGN_SYSTEM.md`: unified PUP-inspired visual design rules.
- `docs/frontend/PHASE_PLAN.md`: branch/PR workflow and proposed phases.
- `docs/codex/PR_CHECKLIST.md`: review and regression checklist.
- `docs/codex/PROMPTS.md`: copy-paste Codex task prompts.

## Setup without disturbing local Docker edits
1. In the cloned repo, inspect `git status`, `git branch -a` and existing files. **Don't overwrite any AGENTS.md or docs that the latest repository already contains.**
2. Create or switch to `dev` according to `docs/frontend/PHASE_PLAN.md` and obtain remote push permissions.
3. Create `docs/frontend-00-audit` from `dev`.
4. Copy these files into the repository, review diffs, and use Prompt A in `docs/codex/PROMPTS.md`.
5. Codex should investigate the real code before populating documentation; it must not implement the frontend yet.

After the Phase 00 documentation is reviewed, PR it to `dev`, then start Phase 01 from updated `dev`.
