<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * What lab staff can do with a booking, and the warnings to show. No database here, so it's easy to test.
 *
 *   Approved (Confirmed) --hand out--> In use (InUse) --returned--> Completed
 *   Approved --no-show--> No-show
 *   Approved --cancel or move (with a reason)--> Cancelled, or still Approved at a new room/time
 *
 * Hand out and no-show open on the booking's own day (app timezone) and stay open afterwards, so staff can
 * handle early, late and missed pick-ups; they are refused for a booking on a future calendar day (QA F-07).
 * Returned works whenever a booking is in use. The time otherwise only decides the warnings: Late (not
 * picked up after the grace period) and Overdue.
 */
class StaffRules
{
    public function __construct(
        public readonly int $graceMinutes,     // the department's no-show grace period, e.g. 15
        public readonly CarbonImmutable $now,
    ) {}

    /** True when the booking starts on a calendar day after today, in the app's timezone */
    public function isFutureDay(CarbonInterface $start): bool
    {
        return CarbonImmutable::instance($start)->setTimezone($this->now->getTimezone())->startOfDay()->gt($this->now->startOfDay());
    }

    /** An approved booking on today's date or earlier (also early the same day, or late) */
    public function canHandOut(string $status, CarbonInterface $start): bool
    {
        return $status === 'Confirmed' && ! $this->isFutureDay($start);
    }

    /** Same rule as hand-out: a booking that hasn't reached its day can't be a no-show yet */
    public function canMarkNoShow(string $status, CarbonInterface $start): bool
    {
        return $status === 'Confirmed' && ! $this->isFutureDay($start);
    }

    /** Approved but not picked up yet, and the grace period after the start is over */
    public function isLate(string $status, CarbonInterface $start): bool
    {
        return $status === 'Confirmed' && $this->now->gte(CarbonImmutable::instance($start)->addMinutes($this->graceMinutes));
    }

    public function canMarkReturned(string $status): bool
    {
        return $status === 'InUse';
    }

    /** In use after its end time: the key and items should be back by now */
    public function isOverdue(string $status, CarbonInterface $end): bool
    {
        return $status === 'InUse' && $this->now->gt($end);
    }

    /** Lab staff can cancel or move an approved booking until it ends */
    public function canChange(string $status, CarbonInterface $end): bool
    {
        return $status === 'Confirmed' && $this->now->lt($end);
    }
}
