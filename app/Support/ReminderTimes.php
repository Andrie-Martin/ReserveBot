<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Phase 10: when reminder emails go out (no database, so it's easy to test).
 * Each booking or class gets up to three: 1 day, 5 hours and 1 hour before it starts.
 * One due from 10 PM to 6 AM goes out at 9 PM the night before instead.
 */
final class ReminderTimes
{
    /** Minutes before the start */
    public const MARKS = ['day' => 1440, '5h' => 300, '1h' => 60];

    public const NIGHT_STARTS = 22 * 60;   // 10:00 PM

    public const NIGHT_ENDS = 6 * 60;      // 6:00 AM

    public const NIGHT_SEND = 21 * 60;     // 9:00 PM

    public static function sendTime(CarbonImmutable $start, string $mark): CarbonImmutable
    {
        $at = $start->subMinutes(self::MARKS[$mark]);
        $minute = $at->hour * 60 + $at->minute;

        if ($minute >= self::NIGHT_STARTS) {
            return $at->startOfDay()->addMinutes(self::NIGHT_SEND);              // e.g. 11 PM -> 9 PM that day
        }
        if ($minute < self::NIGHT_ENDS) {
            return $at->subDay()->startOfDay()->addMinutes(self::NIGHT_SEND);    // e.g. 2:30 AM -> 9 PM the day before
        }

        return $at;
    }

    /**
     * The newest email whose time has come, or null (none yet, or it has started). If the site was
     * off at an earlier one's time, only this newest one goes out.
     */
    public static function due(CarbonImmutable $start, CarbonImmutable $now): ?string
    {
        if ($now->gte($start)) {
            return null;
        }

        $due = null;
        foreach (array_keys(self::MARKS) as $mark) {
            if (self::sendTime($start, $mark)->lte($now)) {
                $due = $mark;
            }
        }

        return $due;
    }

    /** 'today at 1:30 PM', 'tomorrow at 7:30 AM' or 'Thursday, November 5 at 1:30 PM', seen from $now */
    public static function when(CarbonImmutable $start, CarbonImmutable $now): string
    {
        $day = match (true) {
            $start->isSameDay($now) => 'today',
            $start->isSameDay($now->addDay()) => 'tomorrow',
            default => $start->format('l, F j'),
        };

        return $day.' at '.$start->format('g:i A');
    }
}
