# Frontend phase plan — proposed, validate after Phase 00

## Branch model
- `main`: stable release branch (managed by team).
- `dev`: frontend integration branch. Pull latest `main` changes into `dev` through reviewed integration PRs as needed.
- One branch per phase created from updated `dev`; PR each into `dev`. No direct pushes to `main` from Codex.
- If `origin/dev` exists, use it rather than creating a duplicate. If GitHub write access is missing, obtain owner collaboration or use a fork + PR.

## Phase gates
Each phase requires (1) read-only discovery, (2) file-level implementation plan, (3) explicit approval, (4) scoped changes, (5) verification, (6) reviewed PR to `dev`. No automatic next-phase execution.

| Phase | Suggested branch | Goal | Exit gate |
|---|---|---|---|
| 00 | `docs/frontend-00-audit` | Verify current architecture/route-to-view map; inventory Figma gaps | Reviewed `SYSTEM_MAP` and `SCREEN_INVENTORY`; no app edits |
| 01 | `feat/frontend-01-foundations` | Single design token set; reusable Blade/CSS components | Component inventory and isolated examples; no altered business rules |
| 02 | `feat/frontend-02-layout` | Shared header, sidebar, responsive role-aware shell | Existing menus/routes/role gates preserved |
| 03 | `feat/frontend-03-auth` | Login, onboarding, account/email/password screens | All existing auth workflows and errors still work |
| 04 | `feat/frontend-04-availability` | Rep dashboard, free rooms/calendar | Dates, availability, filters and route/query contracts verified |
| 05 | `feat/frontend-05-bookings` | Booking form, history, details | Validation, conflicts, statuses, cancellation tested |
| 06 | `feat/frontend-06-staff` | Staff dashboards, approvals, today's handovers/class check | Permissions and state transitions unchanged |
| 07 | `feat/frontend-07-admin` | Rooms, equipment, timetables, accounts and rules | Existing management actions and upload rules unchanged |
| 08 | `feat/frontend-08-qa` | Full responsive/a11y/regression review and consistency | Role-based manual QA + automated test evidence + no UX blockers |

If Phase 00 shows a workflow is much larger, split its phase BEFORE implementation and document the change. Reconcile the final scope with teammates.

## Branch commands (example)
Create integration branch only when no `dev` exists:
```bash
git switch main
git fetch origin
git pull --ff-only origin main
git switch -c dev
git push -u origin dev
```
To begin Phase 00 after `dev` is on remote:
```bash
git switch dev
git pull --ff-only origin dev
git switch -c docs/frontend-00-audit
```
After Phase 00 approval, commit ONLY approved docs (explicit paths), push the branch, and open a PR with base `dev`. Then start next phase from fresh `dev`.

**Caution:** Local uncommitted `docker-compose.yml` and `docker-compose.yml.save` may be present. Preserve them and never include them in frontend phase commits.
