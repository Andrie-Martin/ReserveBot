<?php

namespace App\Support;

/**
 * Time-slot math for the free rooms page.
 * Times are minutes after midnight (7:30 AM = 450). No database here, so it's easy to test.
 * A busy item is an array with at least 'start' and 'end' in minutes.
 */
class TimeSlots
{
    /** Start time of every slot, e.g. 450, 480, ... for 7:30 AM to 9:00 PM in 30-minute steps */
    public static function slots(int $opens, int $closes, int $step): array
    {
        $slots = [];
        for ($t = $opens; $t + $step <= $closes; $t += $step) {
            $slots[] = $t;
        }

        return $slots;
    }

    /**
     * The free stretches of one room's day, as [start, end] pairs.
     * With $now (today), slots that have already started are left out.
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function freeRanges(array $items, int $opens, int $closes, int $step, ?int $now = null): array
    {
        $ranges = [];
        foreach (self::slots($opens, $closes, $step) as $t) {
            if (($now !== null && $t < $now) || ! self::isFree($items, $t, $t + $step)) {
                continue;
            }
            $last = count($ranges) - 1;
            if ($last >= 0 && $ranges[$last][1] === $t) {
                $ranges[$last][1] = $t + $step;
            } else {
                $ranges[] = [$t, $t + $step];
            }
        }

        return $ranges;
    }

    /** True when nothing in $items overlaps from..to */
    public static function isFree(array $items, int $from, int $to): bool
    {
        return self::firstClash($items, $from, $to) === null;
    }

    /** The earliest item that overlaps from..to, or null */
    public static function firstClash(array $items, int $from, int $to): ?array
    {
        usort($items, fn ($a, $b) => $a['start'] <=> $b['start']);
        foreach ($items as $item) {
            if (self::overlaps($item, $from, $to)) {
                return $item;
            }
        }

        return null;
    }

    /** Same rule as the database: starts before the other ends, and ends after the other starts */
    public static function overlaps(array $item, int $from, int $to): bool
    {
        return $item['start'] < $to && $item['end'] > $from;
    }

    /** 690 -> '11:30 AM', 780 -> '1:00 PM' */
    public static function clock(int $minutes): string
    {
        $h = intdiv($minutes, 60) % 24;

        return sprintf('%d:%02d %s', ($h % 12) ?: 12, $minutes % 60, $h < 12 ? 'AM' : 'PM');
    }

    /** 690 -> '11:30' (form values and links) */
    public static function hhmm(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** '11:30 AM to 1:30 PM' */
    public static function range(int $from, int $to): string
    {
        return self::clock($from).' to '.self::clock($to);
    }

    /** Short form for chips: '7:30–11:30 AM', or '11:30 AM–1:30 PM' across noon */
    public static function span(int $from, int $to): string
    {
        $a = self::clock($from);
        $b = self::clock($to);
        if (substr($a, -2) === substr($b, -2)) {
            $a = substr($a, 0, -3);
        }

        return $a.'–'.$b;
    }
}
