<?php

namespace App\Support;

use App\Models\DepartmentConfig;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * A department's booking rules (the department_configs row) as checks on a day and times.
 * No database here, so the rules are easy to test. Times are minutes after midnight (7:30 AM = 450).
 */
class BookingRules
{
    public function __construct(
        public readonly array $openDays,        // ['Mon', 'Tue', ...]
        public readonly int $opens,             // 450 = 7:30 AM
        public readonly int $closes,            // 1260 = 9:00 PM
        public readonly int $step,              // slot length, e.g. 30 minutes
        public readonly int $minMinutes,        // shortest extra booking
        public readonly int $maxMinutes,        // longest extra booking (class sessions keep their own length)
        public readonly int $maxDaysAhead,
        public readonly int $noticeMinutes,     // how long before the start you must book
        public readonly int $cancelMinutes,     // how long before the start a rep can still cancel
        public readonly bool $needsApproval,
        public readonly CarbonImmutable $now,
    ) {}

    public static function for(DepartmentConfig $config, ?CarbonImmutable $now = null): self
    {
        return new self(
            $config->openDays(), $config->opensAt(), $config->closesAt(), $config->slot_minutes,
            $config->min_booking_minutes, $config->max_booking_minutes, $config->max_days_ahead,
            $config->min_notice_minutes, $config->cancel_cutoff_minutes, $config->requires_approval,
            $now ?? CarbonImmutable::now(),
        );
    }

    public function today(): CarbonImmutable
    {
        return $this->now->startOfDay();
    }

    public function lastDay(): CarbonImmutable
    {
        return $this->today()->addDays($this->maxDaysAhead);
    }

    public function isOpen(CarbonImmutable $day): bool
    {
        return in_array($day->format('D'), $this->openDays, true);    // 'D' is always Mon, Tue, ...
    }

    /** Days you can book: today up to the limit, on the days the department is open */
    public function days(): array
    {
        $days = [];
        for ($day = $this->today(); $day->lte($this->lastDay()); $day = $day->addDay()) {
            if ($this->isOpen($day)) {
                $days[] = $day;
            }
        }

        return $days;
    }

    /** Start times you can pick, e.g. 450, 480, ... 1230 */
    public function starts(): array
    {
        return TimeSlots::slots($this->opens, $this->closes, $this->step);
    }

    /** End times you can pick, e.g. 480, 510, ... 1260 */
    public function ends(): array
    {
        return array_map(fn ($t) => $t + $this->step, $this->starts());
    }

    /** The earliest a booking can start: now plus the notice */
    public function earliestStart(): CarbonImmutable
    {
        return $this->now->addMinutes($this->noticeMinutes);
    }

    /** What's wrong with an extra booking (not a class) on $day from $from to $to, or null if it's fine */
    public function checkExtra(CarbonImmutable $day, int $from, int $to): ?string
    {
        if ($day->lt($this->today()) || $day->gt($this->lastDay())) {
            return $this->windowMessage();
        }
        if (! $this->isOpen($day)) {
            return 'Rooms and equipment aren’t booked on '.$day->format('l').'s.';
        }
        if (! in_array($from, $this->starts(), true) || ! in_array($to, $this->ends(), true)) {
            return 'Pick a start and an end time from the lists.';
        }
        if ($to <= $from) {
            return 'Pick an end time that is after the start time.';
        }
        if ($to - $from < $this->minMinutes || $to - $from > $this->maxMinutes) {
            return 'A booking can be '.self::duration($this->minMinutes).' to '.self::duration($this->maxMinutes).' long.';
        }

        return $this->checkNotice($day->addMinutes($from));
    }

    /** What's wrong with booking a class session that starts at $start, or null if it's fine */
    public function checkSession(CarbonImmutable $start): ?string
    {
        $day = $start->startOfDay();
        if ($day->lt($this->today()) || $day->gt($this->lastDay())) {
            return $this->windowMessage();
        }

        return $this->checkNotice($start);
    }

    /** The last moment a rep can cancel a booking that starts at $start */
    public function cancelDeadline(CarbonInterface $start): CarbonImmutable
    {
        return CarbonImmutable::instance($start)->subMinutes($this->cancelMinutes);
    }

    /** True while a booking that starts at $start can still be cancelled by its rep */
    public function canCancel(CarbonInterface $start): bool
    {
        return $this->now->lte($this->cancelDeadline($start));
    }

    private function checkNotice(CarbonImmutable $start): ?string
    {
        return $start->lt($this->earliestStart())
            ? 'Book at least '.self::duration($this->noticeMinutes).' before the start time.'
            : null;
    }

    private function windowMessage(): string
    {
        return 'You can book from today up to '.$this->lastDay()->format('F j').'.';
    }

    /** 30 -> '30 minutes', 60 -> '1 hour', 90 -> '1 hour 30 minutes', 180 -> '3 hours' */
    public static function duration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;
        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours.' '.($hours === 1 ? 'hour' : 'hours');
        }
        if ($rest > 0 || $hours === 0) {
            $parts[] = $rest.' '.($rest === 1 ? 'minute' : 'minutes');
        }

        return implode(' ', $parts);
    }
}
