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
 * Hand out, no-show and returned work at any time, on any day, so lab staff and admins can step in by hand
 * whenever something goes wrong (an early pick-up, a booking made for the wrong day, a missed one).
 * The time only decides the warnings: Late (not picked up after the grace period) and Overdue.
 */
class StaffRules
{
    public function __construct(
        public readonly int $graceMinutes,     // the department's no-show grace period, e.g. 15
        public readonly CarbonImmutable $now,
    ) {}

    /** Any approved booking, at any time */
    public function canHandOut(string $status): bool
    {
        return $status === 'Confirmed';
    }

    /** Any approved booking, at any time */
    public function canMarkNoShow(string $status): bool
    {
        return $status === 'Confirmed';
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
