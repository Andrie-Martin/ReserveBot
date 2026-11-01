<?php

namespace Tests\Unit;

use App\Support\TimeSlots;
use PHPUnit\Framework\TestCase;

class TimeSlotsTest extends TestCase
{
    public function test_slots_cover_opening_hours(): void
    {
        $slots = TimeSlots::slots(450, 1260, 30);   // 7:30 AM to 9:00 PM

        $this->assertCount(27, $slots);
        $this->assertSame(450, $slots[0]);
        $this->assertSame(1230, end($slots));      // the last slot is 8:30 to 9:00 PM
    }

    public function test_free_times_are_the_gaps_between_busy_items(): void
    {
        $busy = [
            ['start' => 540, 'end' => 630],   // 9:00 to 10:30
            ['start' => 780, 'end' => 900],   // 1:00 to 3:00 PM
        ];

        $free = TimeSlots::freeRanges($busy, 450, 1260, 30);

        $this->assertSame([[450, 540], [630, 780], [900, 1260]], $free);
    }

    public function test_a_busy_item_off_the_slot_grid_blocks_every_slot_it_touches(): void
    {
        $free = TimeSlots::freeRanges([['start' => 500, 'end' => 610]], 450, 720, 30);   // 8:20 to 10:10

        $this->assertSame([[450, 480], [630, 720]], $free);
    }

    public function test_slots_that_have_started_are_not_free_today(): void
    {
        $free = TimeSlots::freeRanges([], 450, 720, 30, now: 545);   // it's 9:05

        $this->assertSame([[570, 720]], $free);
    }

    public function test_a_room_busy_all_day_has_no_free_time(): void
    {
        $this->assertSame([], TimeSlots::freeRanges([['start' => 0, 'end' => 1440]], 450, 1260, 30));
    }

    public function test_touching_times_do_not_clash(): void
    {
        $class = ['start' => 540, 'end' => 630, 'label' => 'CMPE 451'];   // 9:00 to 10:30

        $this->assertTrue(TimeSlots::isFree([$class], 630, 720));   // starts when the class ends
        $this->assertTrue(TimeSlots::isFree([$class], 450, 540));   // ends when the class starts
        $this->assertFalse(TimeSlots::isFree([$class], 600, 660));  // overlaps the last half hour
        $this->assertFalse(TimeSlots::isFree([$class], 480, 720));  // covers the whole class
    }

    public function test_first_clash_is_the_earliest_overlap(): void
    {
        $late = ['start' => 660, 'end' => 720, 'label' => 'Late'];
        $early = ['start' => 540, 'end' => 600, 'label' => 'Early'];

        $this->assertSame('Early', TimeSlots::firstClash([$late, $early], 450, 1260)['label']);
        $this->assertNull(TimeSlots::firstClash([$late, $early], 600, 660));
    }

    public function test_times_are_written_the_way_people_read_them(): void
    {
        $this->assertSame('7:30 AM', TimeSlots::clock(450));
        $this->assertSame('12:00 PM', TimeSlots::clock(720));
        $this->assertSame('9:00 PM', TimeSlots::clock(1260));
        $this->assertSame('12:00 AM', TimeSlots::clock(0));
        $this->assertSame('07:30', TimeSlots::hhmm(450));
        $this->assertSame('7:30 AM to 1:30 PM', TimeSlots::range(450, 810));
    }

    public function test_short_spans_drop_a_repeated_am_or_pm(): void
    {
        $this->assertSame('7:30–11:30 AM', TimeSlots::span(450, 690));
        $this->assertSame('11:30 AM–1:30 PM', TimeSlots::span(690, 810));
        $this->assertSame('4:00–9:00 PM', TimeSlots::span(960, 1260));
    }
}
