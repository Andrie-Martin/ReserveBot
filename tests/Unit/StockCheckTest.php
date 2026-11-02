<?php

namespace Tests\Unit;

use App\Support\StockCheck;
use PHPUnit\Framework\TestCase;

class StockCheckTest extends TestCase
{
    /** A booking from hour $from to hour $to (same day) asking for $qty */
    private function booking(int $id, int $from, int $to, int $qty): array
    {
        return ['id' => $id, 'start' => $from * 3600, 'end' => $to * 3600, 'qty' => $qty];
    }

    public function test_nothing_is_over_when_the_stock_is_enough(): void
    {
        $bookings = [$this->booking(1, 8, 10, 4), $this->booking(2, 9, 11, 4)];

        $this->assertSame([], StockCheck::overBooked($bookings, 10));
    }

    public function test_overlapping_bookings_that_ask_for_too_many_are_listed(): void
    {
        $bookings = [
            $this->booking(1, 8, 10, 4),
            $this->booking(2, 9, 11, 4),     // overlaps 1
            $this->booking(3, 13, 15, 2),    // on its own
        ];

        $this->assertSame([1, 2], StockCheck::overBooked($bookings, 6));    // 4 + 4 > 6
        $this->assertSame([], StockCheck::overBooked($bookings, 8));
    }

    public function test_one_booking_can_be_over_on_its_own(): void
    {
        $this->assertSame([1], StockCheck::overBooked([$this->booking(1, 8, 10, 4)], 2));    // 10 projectors lowered to 2
    }

    public function test_bookings_that_only_touch_do_not_add_up(): void
    {
        $bookings = [$this->booking(1, 8, 10, 2), $this->booking(2, 10, 12, 2)];    // one ends when the other starts

        $this->assertSame([], StockCheck::overBooked($bookings, 2));
    }
}
