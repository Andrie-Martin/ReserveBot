<?php

namespace App\Support;

/**
 * Presentation only. A room's capacity is NULL until someone records it (see the rooms migration), which is different
 * from zero: unknown is shown as nothing at all (no placeholder), never as 0 seats.
 */
final class RoomCapacity
{
    public static function label(int|string|null $capacity): string
    {
        if (! self::known($capacity)) {
            return '';
        }
        $n = (int) $capacity;

        return $n.' '.($n === 1 ? 'seat' : 'seats');
    }

    public static function known(int|string|null $capacity): bool
    {
        return $capacity !== null && $capacity !== '' && is_numeric($capacity);
    }
}
