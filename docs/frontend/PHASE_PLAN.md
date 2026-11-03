# ReserveBot frontend roadmap — Phase 00 baseline

Baseline: **2026-11-03**, source commit **`e07be8f939ae6b1932150483d3770be3af2c64d9`**. The user approved documenting the audited system and separating timetable management into **Phase 08**, with final QA in **Phase 09**. This is not approval to implement any phase.

Read [SYSTEM_MAP.md](../architecture/SYSTEM_MAP.md), [SCREEN_INVENTORY.md](SCREEN_INVENTORY.md), [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md), the current phase brief, and [PR_CHECKLIST.md](../codex/PR_CHECKLIST.md) before implementation. Source and tests govern behavior; Figma governs approved visuals only. Figma coverage remains **UNVERIFIED** until accessible designs are independently reviewed. Backend comments referring to Phases 4–11 belong to the earlier backend roadmap, not these frontend phase numbers.

## Branch and approval rules

- `main` is team-managed stable release; `dev` is the frontend integration target.
- Phase 00 stays on `docs/frontend-00-audit`. Only the three approved documentation files may change now. Do not stage, commit, push, merge, switch branches, or start Phase 01 during this task.
- **Each implementation phase, including Phase 09, requires its own feature branch created from the latest reviewed `dev` at the time that phase starts.** Branch names are listed below. Updating/fetching/switching/creating branches requires the user's authorization; a roadmap entry is not that authorization.
- Complete and merge a predecessor before starting its successor. Refresh source traces after any backend or `dev` change; do not branch from a stale predecessor branch or rely on this audit indefinitely.
- Reuse an existing `dev` branch; do not create a competing integration branch. User/team owns commits, pushes, merges, and release decisions.
- Protect the user's modified `docker-compose.yml` and untracked `docker-compose.yml.save`; never stage, overwrite, clean, or include them in frontend work.

## Common gates for every implementation phase

The following gates apply in full to **each** Phase 01–09 and are part of its acceptance and merge requirements.

1. **Discovery and approval:** verify Git state, read current docs/phase brief, trace affected routes → middleware/authorization → controller → services/models → Blade → CSS/tests. Propose exact changed files, roles/states, reusable pieces, risks, verification, and unknowns. Obtain explicit phase implementation approval. If behavior is unproven or conflicts with documentation, stop for a decision before changing functionality.
2. **Presentation boundary:** preserve route names/URLs, methods/actions, field names, CSRF, method spoofing, signed URLs, validation/error bags, old input, flash keys, query parameters, conditional permissions, and data visibility. No backend, schema, notification, job, infrastructure, dependency/framework/CDN, or build-tool changes without separate review/approval. No invented analytics, mock live status, pagination, endpoints, or email-delivery guarantees.
3. **Reuse and visuals:** use existing Blade/asset conventions and approved tokens. Inventory reused/new components; avoid duplicate desktop/mobile forms or hidden copies with conflicting IDs. Keep labels, keyboard access, focus, contrast, error announcements, and scrollable narrow-screen tables. Verify the relevant Figma frame/state before claiming visual fidelity; resolve missing designs before visual acceptance.
4. **Regression evidence:** run relevant existing tests in an approved isolated environment, with actual command/results/skips. Exercise every applicable role plus denied access, empty/populated states, validation/old input, success/problem flashes, expired sessions, and phase-specific cases. Never run tests against production or assume SQLite supports the MariaDB migrations. Database fixtures/mutations require an approved isolated test setup; no migration/seed/scheduler/data writes are authorized by this roadmap alone.
5. **Acceptance evidence:** capture before/after desktop/mobile and important state evidence; check keyboard operation and visible focus. Inspect form/route invariants and `git diff --check`. An unexecuted required check is not a pass: resolve it or obtain a specific reviewer-approved exception documented in the PR. Source reading, helper tests, lint, and screenshots prove different things; do not substitute one for another.
6. **Merge gate M:** PR targets **`dev`**, contains problem/result, approved scope, explicit changed files, before/after evidence, component inventory, backend-invariant checklist, test commands/results, unresolved issues/approved exceptions, and completed PR checklist. Reviewer accepts the phase-specific criteria below. User/team authorizes commits/push/merge separately. Exclude unrelated files and local Docker changes. Do not automatically begin the next phase after opening a PR or passing checks.

Known issues K1–K6 and unknowns U1–U4 are tracked in the system map. A frontend phase must preserve and accurately communicate verified behavior while flagging these issues; it must not silently convert a known issue into an intended requirement or “fix” backend behavior through UI restrictions.

## Phase overview

| Phase | Dedicated branch | Scope | Exit gate |
|---|---|---|---|
| 00 | `docs/frontend-00-audit` | Source-grounded architecture, screen/action inventory, roadmap | Documentation reviewed and merged into `dev`; no app edits |
| 01 | `feat/frontend-01-foundations` | Tokens and reusable Blade/CSS foundations | Approved component inventory and accessible examples; gate M |
| 02 | `feat/frontend-02-layout` | Shared responsive role-aware shell | All menu/account/error conditions preserved; gate M |
| 03 | `feat/frontend-03-auth` | Authentication, onboarding, own account, email/password, errors | Existing account flows and error behavior verified; gate M |
| 04 | `feat/frontend-04-availability` | Rep dashboard and room availability | Role-specific calendar/filter data and booking links preserved; gate M |
| 05 | `feat/frontend-05-bookings` | Two-stage creation, own history, shared detail | Fields, conflict handling, statuses and rep cancellation preserved; gate M |
| 06 | `feat/frontend-06-staff` | Staff/admin dashboards, approval, Today, class-check surfaces | Staff state transitions and signed class workflows verified; gate M |
| 07 | `feat/frontend-07-admin` | S/A resources/categories/rules/semesters; admin account management | Shared-data warnings, permissions and account lifecycle preserved; gate M |
| 08 | `feat/frontend-08-timetable` | Timetable upload/edit/check/replace/download/backup | Draft/version/warning/backup contracts verified; gate M |
| 09 | `feat/frontend-09-qa` | Cross-role responsive/a11y/regression consistency | Consolidated evidence, resolved blockers, reviewer sign-off; gate M |

Branch labels are organizational: `07-admin` does not make resource management admin-only. S/A permissions are explicit in the inventory. Phases 01–09 are planned work, not completed work.

## Phase 00 — architecture and screen audit

- **Scope:** populate `docs/architecture/SYSTEM_MAP.md`, `docs/frontend/SCREEN_INVENTORY.md`, and this roadmap, with audit date/SHA, source references, VERIFIED/UNVERIFIED/KNOWN ISSUE distinctions, and safe verification limits.
- **Preserve:** all application and local infrastructure files; no application data writes, packages, migrations, seeders, jobs, mail sends, or implementation.
- **Acceptance:** every product screen and significant route/action is mapped or explicitly operational/out of scope; role/status/form constraints are sourced; missing Figma evidence and test gaps remain visible; Phase 08/09 scope is consistent across all three files.
- **Verification:** compare claims with source, check referenced paths/symbols, route/view coverage, cross-document phase ownership, unchanged non-doc files, and `git diff --check`.
- **Merge requirements:** first report documentation diff and gaps for review. A later separately authorized documentation PR targets `dev`, changes only approved docs, and excludes Docker files. **Do not start Phase 01 until Phase 00 is reviewed and merged into `dev`.**

## Phase 01 — foundations

- **Branch:** `feat/frontend-01-foundations`, newly created from latest reviewed `dev` after Phase 00 merge and implementation approval.
- **Scope:** establish the documented palette/type/spacing system in the existing asset approach; inventory current CSS and extract reusable presentation primitives. Resolve local/licensed font/brand assets through the approved design decision; no automatic remote font/CDN installation.
- **Backend contracts:** no route/data/form changes. CSS currently controls booking/account/timetable field visibility through `:has()`; do not break those selectors during token work. Keep protected avatar URLs, status value-to-label distinction, error bag arguments, and escaped output.
- **Reusable components:** audit `components.avatar`, `manage._errors`, existing buttons/notices/status classes/table wrappers before introducing form-field, badge, empty-state, heading, or button primitives. Preserve component props and accessible IDs.
- **Acceptance:** one approved token set; documented component inventory; accessible default/focus/disabled/error/empty examples; no global CSS regression on untouched screens; no new runtime dependency. Isolated examples are clearly examples, not production live data.
- **Regression checks:** exercise representative booking radios, role fields, blocked timetable fields, focus states, avatars and error summaries; inspect stylesheet/cache-busting link and HTML form output. Run relevant existing tests where executable; helper tests alone do not validate CSS.
- **Merge requirements:** gate M plus reviewer approval of token/component examples, asset provenance, and explicit evidence that untouched conditional forms still work. Stop before P02.

## Phase 02 — shared layout

- **Branch:** `feat/frontend-02-layout`, from latest reviewed `dev` after P01 merge/approval.
- **Scope:** shared header/navigation/sidebar and responsive content layout in `layouts.app`; all role homes and guest/error contexts share the shell. Do not redesign each screen's business content here.
- **Backend contracts:** `User::homeUrl`, role menus, staff waiting count, POST logout/CSRF, onboarding navigation suppression, email-connect prompt/resend, active route matching, and `hideUser` for server/upload error pages. Do not query user/booking data in the hidden-user error path.
- **Reusable components:** P01 primitives, shared brand/nav item/account menu/alert, existing avatar and page content slots.
- **Acceptance:** R/S/A receive only their current menu actions; guests and onboarding users get the appropriate reduced shell; keyboard and narrow-screen navigation remain usable; long names and nonzero request counts fit; page titles/main-class/content yield contracts preserved.
- **Regression checks:** all roles, guest, mandatory-password, connected/unconnected email, zero/nonzero pending counts, 413/500 hidden-user contexts, POST logout, and route-active states across manage/account/booking screens. Guest feature tests plus isolated role/browser checks.
- **Merge requirements:** gate M plus role-by-role desktop/mobile shell evidence, error-path evidence, and menu/action comparison. Stop before P03.

## Phase 03 — authentication, own account, and errors

- **Branch:** `feat/frontend-03-auth`, from latest reviewed `dev` after P02 merge/approval.
- **Scope:** inventory S01–S07 and E403/E404/E413/E419/E429/E500; own information/photo/password/email tabs and prompts. Preserve notification links; custom email-template redesign is not automatically included. Signed class-check content stays P06.
- **Backend contracts:** guest/main gates; password fields/confirmation/current-password and school-ID rules; legacy email exception; role-dependent section fields; mandatory onboarding; signed email user/hash/query; recovery token/email; default/info/photo bags; `status/problem/connect_note`; separate multipart upload/remove/resend forms; private photo access/cache version.
- **Reusable components:** form field/error summary/buttons, `account._tabs`, avatar, alerts, auth-card pattern, `errors.message`. Error presentation remains independent of database queries where `hideUser` applies.
- **Acceptance:** every current auth/account action works with unchanged payloads; passwords are not repopulated; connection failures and throttles remain distinguishable; email-change/reset effects are accurately worded; no registration feature added; errors provide keyboard-accessible recovery paths.
- **Regression checks:** guest redirect/login; disabled/wrong credentials; first password different/confirmation/missing school ID; unchanged vs changed email and password requirement; unconnected-rep booking redirect; successful/invalid/expired connection/reset links; resend rate limit; photo valid/invalid/oversize/remove/no-photo/denied access; session invalidation. Use isolated mail and accounts; no real emails or live-account mutations without approval.
- **Merge requirements:** gate M plus each auth form's field/bag/action matrix, signed-link and photo evidence, and error page evidence with debug-off behavior tested in isolation. Stop before P04.

## Phase 04 — representative dashboard and availability

- **Branch:** `feat/frontend-04-availability`, from latest reviewed `dev` after P03 merge/approval.
- **Scope:** S08 and S11. Update representative home content, month calendar, selected-day agenda, free-room ranges/search. Preserve existing class-answer controls until their shared presentation is addressed in P06; do not duplicate them.
- **Backend contracts:** `RepController`, `AvailabilityController`, `RoomAvailability` datasets; `date/from/to`, `#day`, day/month URLs; configured horizon/open days; role-specific disclosure; own/block vs department agendas; class-session identifiers and book links; `waiting/f2f/online/freed/booked` labels. Availability is a snapshot, not submission approval.
- **Reusable components:** shared page heading, calendar/day cell/legend, status badges, room cards, empty/error states, responsive tables, existing `classes._answer` unchanged in behavior.
- **Acceptance:** correct calendar/month/day/filter continuity, closed/invalid/outside-term states, role-specific text and links, and mobile agenda visibility. No fake live updates or additional booking capabilities for staff.
- **Regression checks:** `TimeSlotsTest`; every role's calendar; invalid/past/future/closed dates; filter persistence/clear/no matches/started interval; shared-room conflicts; pending/confirmed/in-use holds; online/freed classes; empty rooms/classes/agenda; representative class session links and unchanged answer submissions. Review K1/K3 implications without changing backend behavior.
- **Merge requirements:** gate M plus representative and staff availability screenshots and query/link contract comparison. Stop before P05.

## Phase 05 — booking creation, history, details

- **Branch:** `feat/frontend-05-bookings`, from latest reviewed `dev` after P04 merge/approval.
- **Scope:** S12–S14: both creation stages, own upcoming/past lists, detail/status/history and rep cancellation. Retain staff-only sections and shared partial contracts for later P06 refinement.
- **Backend contracts:** GET time selection vs POST creation; class/other and room/equipment/both choices; session key, hidden parameters, `change`, `qty[id]`, purpose/other-items fields; default and staff-specific bags; connected-email gate; ownership/department checks; stored status labels; cancellation cutoff; room/stock revalidation; 30-record past limit, no invented pagination.
- **Reusable components:** field/choice controls, date/time summary, equipment category disclosure, booking row, status badge, details/history/empty states; preserve `bookings._staff_actions` rather than fork it.
- **Acceptance:** class room, extra room, equipment-only, and combined paths remain possible; old input and error recovery survive both stages; no free resource is promised before successful submission; rep sees correct cancellation availability/reason/deadline and history; staff detail access remains intact.
- **Regression checks:** `BookingRulesTest`, `TimeSlotsTest`, `StockCheckTest`; isolated create/conflict scenarios; simultaneous submission checks if approved test setup exists; stale session return to stage one; unavailable stock/room; reason/quantity validation; need switching; cutoff boundary; closed statuses; owner vs other rep/department; missing class link after timetable change. Tests must cover service/controller paths, not only helpers.
- **Merge requirements:** gate M plus stage-by-stage payload comparison, representative cancellation evidence and staff-detail preservation evidence. Stop before P06.

## Phase 06 — staff operations and class checks

- **Branch:** `feat/frontend-06-staff`, from latest reviewed `dev` after P05 merge/approval.
- **Scope:** S09/S10/S15/S16/S17; S14 staff controls; shared class-answer controls on S08; class reminder presentation/link contracts M01/M02 as reviewed. No new mail triggers or active Messenger integration.
- **Backend contracts:** S/A department access; pending-only decisions; required reasons and per-record error bags; Confirmed→InUse→Completed and Confirmed→NoShow/Cancelled; movement remains Confirmed; late/overdue are warnings, handout/no-show have no time gate; Today date range; class `answer` enum and signed full URL; GET displays/POST answers; expired/ended/booked/reclaim-refused outcomes; 10 recent decisions limit.
- **Reusable components:** shared `home`, request rows, Today card, `bookings._staff_actions`, `classes._answer`, badges, reason/note forms, signed-link page, warnings/empty states. Reuse across detail/Today/rep home.
- **Acceptance:** role/department/state protections remain server-owned; stale decisions and validation appear on the correct record; no duplicate form IDs; keyboard operations work; signed links survive rendering/posting; reminder language distinguishes scheduling/transport from inbox delivery (K1).
- **Regression checks:** `StaffRulesTest`, `ClassCheckRulesTest`, `ReminderTimesTest`; isolated all status transitions/invalid transitions; past/future handout/no-show; optional return note; move vs cancel timing and conflicts; other-department denials; class online/f2f/booked/late/unasked/ended states; email link tamper/expiry/wrong rep; GET has no answer mutation; post preserves signature and CSRF. No real scheduler/mail invocation without separately approved isolation.
- **Merge requirements:** gate M plus transition matrix, per-record error evidence, signed-link evidence, and reviewers' acceptance of unresolved mail semantics. Stop before P07.

## Phase 07 — resource and account administration

- **Branch:** `feat/frontend-07-admin`, from latest reviewed `dev` after P06 merge/approval.
- **Scope:** S18–S24: room lists/editing, equipment/categories, department rules, shared semester dates, admin account lists/create/edit/password/disable/delete. Keep timetable navigation functional; timetable content is exclusively P08.
- **Backend contracts:** resource management allows S/A; accounts only A with same-department R/S targets. Existing room names attach shared rooms; room edits affect all departments; inactive resources and lower stock preserve bookings. Config/term fields and validation bags stay exact. Admin starting passwords force onboarding; delete-with-history disables; account disable does not cancel bookings; legacy email and rep block/section rules preserved.
- **Reusable components:** `manage._tabs`, `manage._errors`, `accounts._fields`, avatar, list/table patterns, active-state controls, warning panels, grouped forms and confirmation controls.
- **Acceptance:** each add/edit/rename form restores its own input/errors; shared-data/history warnings remain visible; admin records are read-only through account management; no guessed deletion/booking cancellation cascade; no timetable functionality moved into this phase. K2/K3 remain flagged for separate review.
- **Regression checks:** helper account/stock tests plus isolated request flows; S/A/R permission matrix; cross-department resource/account access; duplicate/shared room; active toggle with upcoming bookings; stock reduction shortages; invalid limits/hours/step/dates; account create/change role/email/password; rep blocks and optional staff sections; delete with and without tracked history; simultaneous success/mail-failure messages. Verify rules changes' actual cancellation effects without claiming booking-time snapshots.
- **Merge requirements:** gate M plus all management form/bag contracts, shared-record warning evidence, account lifecycle outcomes, and unchanged timetable navigation. Stop before P08.

## Phase 08 — timetable management

- **Branch:** `feat/frontend-08-timetable`, from latest reviewed `dev` after P07 merge/approval.
- **Scope:** S25–S29: overview, live day/week, shared draft/fix/add/edit/delete/undo, upload and download, semester check, replacement and backup restoration. Include the timetable use of shared E413 error wording; no backend parser or replacement changes.
- **Backend contracts:** one department draft; upload/restore may replace it; starting reuses it. Multipart `file`, XLSX/CSV acceptance and server TXT support, ZIP fallback, row field names/IDs and parsing; `day/week/format/back`; encrypted undo `row`; `version`; `term_name/start/end`; semester button alternate `formaction`. Distinguish blocking problems from warnings. Replacement acts immediately, updates shared terms, retains/detaches bookings as implemented, and backs up only timetable data. Restore is draft→check→replace, not full history rollback.
- **Reusable components:** existing `_timetable_*` partials; shared tabs/errors/fields; comparison lists, warning panels, day/week grid and overlap lanes; source/actor/version/status presentation. Preserve `.tt-form` code/title auto-fill or deliberately adapt its selector without altering the data contract.
- **Acceptance:** live vs draft clearly distinguished; no mutation on view; invalid/empty/stale drafts cannot be presented as successfully applied; all warning categories and preserved bookings visible; overwrite/discard/restore effects clear; downloaded format matches actual response; grid remains keyboard/narrow-screen usable. No claim that backup restores deleted class-check/reminder history.
- **Regression checks:** `TimetableRulesTest`, `TimetableFileTest` (report ZIP skips), plus approved isolated database/controller/browser tests: valid/invalid/oversized/unsupported uploads, missing ZIP, normalization/duplicates, row CRUD and undo, missing row/draft, day/fix/week filters, overlapping grids, shared-resource and orphan-rep warnings, retained-booking clashes, stale version rejection, semester save vs replace, backup download/restore and cross-department 404. Exercise unchanged vs moved class identity and booking detachment to establish preservation.
- **Merge requirements:** gate M plus upload/download and full draft/check/replace/backup evidence, separate blocker/warning screenshots, version-conflict evidence, and reviewer confirmation of shared-data/backup wording. Do not accept parser helper tests alone as proof of replacement correctness. Stop before P09.

## Phase 09 — final frontend QA

- **Branch:** `feat/frontend-09-qa`, from latest reviewed `dev` after P08 merge/approval.
- **Scope:** audit the combined frontend across S01–S29, E403–E500, shared components and notification link surfaces; fix approved presentation inconsistencies within existing architecture. No new features or backend fixes folded into QA.
- **Backend contracts:** all screen/action/role/field/error/query/status contracts in the inventory; maintain the seven reservation statuses, class-check semantics, private data visibility, shared-resource effects, email limitations and timestep/timezone behavior. Refresh traces if backend changed since Phase 00.
- **Reusable components:** consolidate verified duplicates into established components where approved; inspect tokens, typography, nav, fields, badges, alerts, tables/calendars and dialogs/disclosures. Avoid a second design system or parallel mobile markup.
- **Acceptance:** role/state matrix completed with explicit evidence; desktop/mobile and keyboard/focus/contrast review; consistent error/empty/success behavior; no unresolved frontend blocker; no unsupported live-data/delivery claims; reviewed design comparison with missing Figma states explicitly resolved or approved as exceptions; no unexpected backend diff.
- **Regression checks:** all relevant available automated tests in verified isolation plus end-to-end login/onboard/connect/book/review/handout/return/cancel/move/class-check/manage/timetable paths; invalid inputs, unauthenticated/wrong-role/other-department requests, expired session/link, resource/stock conflicts, timetable concurrency; uploads/downloads; mail links tested with non-delivering transport. Confirm actual test counts/skips and distinguish automation from manual evidence. Inspect all source changes and run `git diff --check`.
- **Merge requirements:** gate M plus consolidated QA evidence, known-issue disposition, approved exceptions and final reviewer sign-off. Merge to `dev` is not authorization for release or merging to `main`; those decisions remain with the team.

## Outstanding prerequisites and handoff

- Obtain accessible/reviewed visual references; current Figma coverage is UNVERIFIED in the inventory.
- Establish an approved isolated test environment: audit found local dependencies absent and container PHPUnit absent. Package installation or test-environment changes require their own authorization. SQLite test configuration does not prove MariaDB migration coverage.
- Determine intended behavior for the known discrepancies separately; preserve source behavior in a presentation-only task until a reviewed backend change establishes a new contract.
- Refresh runtime, role, and state evidence at the start of each affected phase; the read-only audit did not render every role or perform data mutations.
- Current task ends after the three documentation files are verified and presented for review. No branch creation, commit, push, PR publication, merge, or Phase 01 implementation is authorized now.
