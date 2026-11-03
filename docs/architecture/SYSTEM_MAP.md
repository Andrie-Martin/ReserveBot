# ReserveBot architecture map — NOT YET VERIFIED AGAINST CURRENT CHECKOUT

> This is a **Phase 00 output template**, not a claim about the latest architecture. Populate from the cloned repository at the audit commit. Every table entry should cite a real file path, symbol, and line range. Write `UNVERIFIED` instead of guessing. Do not modify application code during this audit.

## Audit provenance
- Git commit SHA: `TO VERIFY (git rev-parse HEAD)`
- Branch: `TO VERIFY`
- Audit date and reviewer: `TO VERIFY`
- Stack/build entry points: `TO VERIFY from composer.json, Dockerfile, docker-compose.yml, asset pipeline`
- Baseline checks and results: `TO RUN SAFELY`

## Architectural overview
- HTTP request lifecycle and Laravel middleware: `UNVERIFIED`
- Authentication, session handling, authorization and role names: `UNVERIFIED`
- Domain models and relationships: `UNVERIFIED`
- Booking lifecycle/status transitions and business invariants: `UNVERIFIED`
- Room/equipment availability, scheduling, and class/timetable rules: `UNVERIFIED`
- Notifications, email setup, reminders, scheduler behavior: `UNVERIFIED`
- Photos/uploads, accounts/password resets: `UNVERIFIED`
- Data sources, seeders, migrations and test isolation: `UNVERIFIED`

## Route -> UI -> business logic map
| Route name and method | Role/middleware | Controller method | Services/models | View | Form/state contracts | Tests/evidence |
|---|---|---|---|---|---|---|
| TO INVENTORY | UNVERIFIED | UNVERIFIED | UNVERIFIED | UNVERIFIED | UNVERIFIED | UNVERIFIED |

## View variables and includes
| Blade view / component | Provided variables and types | Includes / extends | Actions, field names, query params | Authorization-sensitive sections |
|---|---|---|---|---|
| TO INVENTORY | UNVERIFIED | UNVERIFIED | UNVERIFIED | UNVERIFIED |

## Frontend/backend invariants to protect
Record verified constraints for: route names; middleware/roles; form methods and fields; validation/error bags; CSRF; date/time/locale; booking statuses; approvals/cancellations; class checks; inventories; pagination; onboarding and email verification; uploads; scheduler.

## Tests and developer commands
- Existing test commands: `TO VERIFY`
- Non-destructive local setup and migration commands: `TO VERIFY`
- Baseline results and limitations: `TO VERIFY`

## Known discrepancies / unresolved questions
Write each issue with evidence, risk, and the next question; never silently invent a solution.
