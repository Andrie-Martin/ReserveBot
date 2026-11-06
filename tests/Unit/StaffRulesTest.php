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

    public function test_hand_out_and_no_show_work_on_the_bookings_own_day_and_afterwards(): void
    {
        foreach (['00:00', '06:00', '11:30', '13:30', '23:59'] as $time) {  // early, during, late: all the same day
            $this->assertTrue($this->at($time)->canHandOut('Confirmed', $this->start), $time);
            $this->assertTrue($this->at($time)->canMarkNoShow('Confirmed', $this->start), $time);
        }
        // A booking from an earlier day can still be handled (late pick-up, or recording a missed one).
        $nextDay = new StaffRules(graceMinutes: 15, now: CarbonImmutable::parse('2026-11-05 08:00'));
        $this->assertTrue($nextDay->canHandOut('Confirmed', $this->start));
        $this->assertTrue($nextDay->canMarkNoShow('Confirmed', $this->start));
    }

    public function test_hand_out_and_no_show_are_refused_for_a_booking_on_a_future_day(): void
    {
        $tomorrow = CarbonImmutable::parse('2026-11-04 08:00');
        foreach (['2026-11-04 00:00', '2026-11-04 08:00', '2026-11-10 11:30'] as $start) {
            $this->assertTrue($this->at('23:59')->isFutureDay(CarbonImmutable::parse($start)), $start);
        }
        $this->assertFalse($this->at('23:59')->canHandOut('Confirmed', $tomorrow));
        $this->assertFalse($this->at('00:00')->canMarkNoShow('Confirmed', $tomorrow));
        // Midnight boundary: 23:59 today is still today, 00:00 tomorrow is not.
        $this->assertFalse($this->at('23:59')->isFutureDay(CarbonImmutable::parse('2026-11-03 23:59')));
        $this->assertTrue($this->at('23:59')->isFutureDay(CarbonImmutable::parse('2026-11-04 00:00')));
    }

    public function test_day_is_decided_in_the_clock_timezone_not_the_servers(): void
    {
        // 2026-11-03 20:00 UTC is already 2026-11-04 04:00 in Manila: a 2026-11-04 booking is "today" there.
        $manila = new StaffRules(graceMinutes: 15, now: CarbonImmutable::parse('2026-11-03 20:00', 'UTC')->setTimezone('Asia/Manila'));
        $start = CarbonImmutable::parse('2026-11-04 09:00', 'Asia/Manila');
        $this->assertFalse($manila->isFutureDay($start));
        $this->assertTrue($manila->canHandOut('Confirmed', $start));
        $this->assertTrue($manila->isFutureDay(CarbonImmutable::parse('2026-11-05 00:30', 'Asia/Manila')));
    }

    public function test_hand_out_and_no_show_still_need_an_approved_booking(): void
    {
        $this->assertFalse($this->at('11:00')->canHandOut('Pending', $this->start));      // not approved yet
        $this->assertFalse($this->at('11:00')->canHandOut('InUse', $this->start));
        $this->assertFalse($this->at('12:00')->canMarkNoShow('InUse', $this->start));     // they came
        foreach (['Completed', 'NoShow', 'Cancelled', 'Rejected'] as $status) {
            $this->assertFalse($this->at('12:00')->canHandOut($status, $this->start), $status);
            $this->assertFalse($this->at('12:00')->canMarkNoShow($status, $this->start), $status);
        }
    }

    public function test_returned_has_no_date_restriction(): void
    {
        foreach (['06:00', '12:00', '23:59'] as $time) {
            $this->assertTrue($this->at($time)->canMarkReturned('InUse'));
        }
        $this->assertTrue((new StaffRules(15, CarbonImmutable::parse('2026-11-09 09:00')))->canMarkReturned('InUse'));   // days late
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
