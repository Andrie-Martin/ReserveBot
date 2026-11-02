<?php

namespace App\Support;

/**
 * Finds bookings that ask for more of an item than the stock allows, e.g. after someone
 * lowers the stock. No database here, so it's easy to test.
 *
 * Same rule as booking: at a booking's time, all the bookings overlapping it (itself
 * included) together may ask for at most the stock.
 */
class StockCheck
{
    /**
     * @param  list<array{id: int, start: int, end: int, qty: int}>  $bookings  times as Unix timestamps
     * @return list<int> ids of the bookings that ask for too many
     */
    public static function overBooked(array $bookings, int $stock): array
    {
        $over = [];
        foreach ($bookings as $booking) {
            $asked = 0;
            foreach ($bookings as $other) {
                if ($other['start'] < $booking['end'] && $other['end'] > $booking['start']) {
                    $asked += $other['qty'];
                }
            }
            if ($asked > $stock) {
                $over[] = $booking['id'];
            }
        }

        return $over;
    }
}
