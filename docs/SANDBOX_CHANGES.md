# Changes on the `sandbox` branch

`sandbox` starts from `dev` at `897d478` (PR #32). Each issue below was fixed in its own commit and pushed on its own,
so the commit history is the record. This file says, in plain words, what changed and how to check it.
`sandbox` is not merged into `main` or `dev` yet.

The issue numbers are the ones from the project owner's list of 2026-11-10. Issues 3 (keep Delete for rooms and
equipment) and 4 (do not merge into `main` yet) needed no code change.

## Issue 5: Tidy-up

### Code style

- Ran Laravel Pint (`vendor/bin/pint`) on the whole project. It reformatted the 36 files it flagged: 2 in `app/`
  (`AccountsController.php`, `Auth/LoginController.php`) and 34 under `tests/`. Formatting only: no behaviour changed,
  and all 308 tests pass before and after.
- **Check it:** `vendor/bin/pint --test` reports `passed`.
