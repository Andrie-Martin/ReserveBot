# Booking audit F-B: hand-out / no-show versus a concurrent move

## Defect
`TodayController::handOut` and `noShow` checked the calendar-day rule on the route-bound booking, then `StaffDesk::setStatus` updated
`WHERE id AND status = 'Confirmed'`. Nothing re-read `start_time`. A `Bookings::move` that committed in between left a booking on a future day
as `InUse` or `NoShow` (final audit, section 9).

## Fix
`StaffDesk::setStatus(..., ?callable $allowed)` now opens its transaction, locks the booking row (`SELECT ... FOR UPDATE`), re-reads it,
and refuses (returns false, writes nothing, no log) when the status is no longer `$from` or `$allowed($freshRow)` is false.
Hand-out and no-show pass the unchanged `StaffRules::canHandOut / canMarkNoShow` on the fresh row. The refusal message is built from the
fresh row, so it names the new day. Policy is unchanged.

Lock order: `Bookings::move` locks reservation, then room, then equipment. `setStatus` locks only the reservation row, so there is no
inverted order and no deadlock. A move and a status change on one booking now run one after the other.

## Tests
`tests/Feature/StaffStatusRaceTest.php` (gated, `tests/Support/run-db-tests.sh`): a listener on the transaction `setStatus` opens runs the
competing request exactly between the first check and the locked re-read. Three of its ten tests fail on the old code.

## Real concurrent HTTP (disposable MariaDB, 6-worker `php -S`, two staff sessions, hand-out delayed 0-60 ms)
| Tree | Action vs move | Attempts | Ended on a future day |
|---|---|---|---|
| before (`b1c104d`) | hand-out | 264 | 73 InUse |
| before | no-show | 264 | 71 NoShow |
| after | hand-out x2 runs | 528 | 0 |
| after | no-show x2 runs | 528 | 0 |

After: 1007 moves matched 1007 Rescheduled logs; every InUse/NoShow booking has exactly one status log and none has a Rescheduled log;
no orphan logs; no deadlock or SQL error in the server logs.

## Limits
Cancel and move by staff still read `canChange` on the loaded row, but they were already guarded by the status condition and a moved booking
only ends later or stays; they were not changed. Only roomless bookings were raced over HTTP (room and equipment bookings are covered by the
interleaving tests). A single MariaDB node was used.
