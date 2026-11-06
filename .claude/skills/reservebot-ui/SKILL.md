---
name: reservebot-ui
description: Implement the approved ReserveBot frontend design without changing existing Laravel functionality.
disable-model-invocation: true
---

# ReserveBot UI Implementation

Read AGENTS.md and the design implementation contract.

For the selected screen:

1. Inspect its actual Laravel routes, Blade views, data and permissions.
2. Examine the matching approved prototype screen.
3. Identify the exact visual differences.
4. Reuse existing components and CSS tokens.
5. Modify presentation only within the approved scope.
6. Preserve native forms, validation and conditional actions.
7. Run focused functional and responsive checks.
8. Review the Git diff and report results briefly.
9. Stop after the approved task.

The Laravel source always wins on functionality.
The prototype wins on visual presentation.

Never invent features, fields, statistics or permissions.
Never replace live data with prototype sample data.
Never touch protected Docker files, migrations or .env.
Never commit, push, merge or change branches automatically.

Verify layouts at 1438x638, 390x844 and 320x640.
Do not claim visual verification without browser evidence.
