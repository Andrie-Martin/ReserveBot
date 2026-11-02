<?php

namespace Tests\Unit;

use App\Support\StaffRules;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class StaffRulesTest extends TestCase
{
    /** A booking from 11:30 AM to 1:30 PM, and the time it is now */
    private function at(string $time): StaffRules
    {
        return new StaffRules(graceMinutes: 15, now: CarbonImmutable::parse('2026-11-03 '.$time));
    }

    private CarbonImmutable $start;

    private CarbonImmutable $end;

    protected function setUp(): void
    {
        $this->start = CarbonImmutable::parse('2026-11-03 11:30');
        $this->end = CarbonImmutable::parse('2026-11-03 13:30');
    }

    public function test_hand_out_and_no_show_work_at_any_time_for_approved_bookings(): void
    {
        foreach (['06:00', '11:30', '13:30', '20:00'] as $time) {          // before, during and after the booking
            $this->assertTrue($this->at($time)->canHandOut('Confirmed'));
            $this->assertTrue($this->at($time)->canMarkNoShow('Confirmed'));
        }
        $this->assertFalse($this->at('11:00')->canHandOut('Pending'));      // not approved yet
        $this->assertFalse($this->at('12:00')->canMarkNoShow('InUse'));     // they came
    }

    public function test_late_shows_after_the_grace_period(): void
    {
        $this->assertFalse($this->at('11:44')->isLate('Confirmed', $this->start));
        $this->assertTrue($this->at('11:45')->isLate('Confirmed', $this->start));
        $this->assertFalse($this->at('12:00')->isLate('InUse', $this->start));             // picked up
    }

    public function test_only_handed_out_bookings_can_be_returned_and_be_overdue(): void
    {
        $this->assertTrue($this->at('12:00')->canMarkReturned('InUse'));
        $this->assertFalse($this->at('12:00')->canMarkReturned('Confirmed'));
        $this->assertFalse($this->at('13:30')->isOverdue('InUse', $this->end));
        $this->assertTrue($this->at('13:31')->isOverdue('InUse', $this->end));
    }

    public function test_approved_bookings_can_be_changed_until_they_end(): void
    {
        $this->assertTrue($this->at('13:00')->canChange('Confirmed', $this->end));
        $this->assertFalse($this->at('13:30')->canChange('Confirmed', $this->end));
        $this->assertFalse($this->at('09:00')->canChange('InUse', $this->end));
        $this->assertFalse($this->at('09:00')->canChange('Pending', $this->end));               // approve or reject instead
    }
}
