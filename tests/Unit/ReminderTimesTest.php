<?php

namespace Tests\Unit;

use App\Support\ReminderTimes;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ReminderTimesTest extends TestCase
{
    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time, 'Asia/Manila');
    }

    public function test_emails_go_out_a_day_five_hours_and_an_hour_before(): void
    {
        $start = $this->at('2026-11-05 13:30');

        $this->assertSame('2026-11-04 13:30', ReminderTimes::sendTime($start, 'day')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-05 08:30', ReminderTimes::sendTime($start, '5h')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-05 12:30', ReminderTimes::sendTime($start, '1h')->format('Y-m-d H:i'));
    }

    public function test_night_emails_go_out_at_9_pm_the_night_before(): void
    {
        $early = $this->at('2026-11-05 07:30');      // 5 hours before is 2:30 AM

        $this->assertSame('2026-11-04 21:00', ReminderTimes::sendTime($early, '5h')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-05 06:30', ReminderTimes::sendTime($early, '1h')->format('Y-m-d H:i'));    // 6:30 AM is fine
        $this->assertSame('2026-11-04 07:30', ReminderTimes::sendTime($early, 'day')->format('Y-m-d H:i'));

        $this->assertSame('2026-11-04 21:00', ReminderTimes::sendTime($this->at('2026-11-05 03:00'), '5h')->format('Y-m-d H:i'));   // 10 PM
        $this->assertSame('2026-11-05 06:00', ReminderTimes::sendTime($this->at('2026-11-05 11:00'), '5h')->format('Y-m-d H:i'));   // 6 AM is not night
    }

    public function test_only_the_newest_due_email_counts(): void
    {
        $start = $this->at('2026-11-05 13:30');

        $this->assertNull(ReminderTimes::due($start, $this->at('2026-11-04 13:29')));
        $this->assertSame('day', ReminderTimes::due($start, $this->at('2026-11-04 13:30')));
        $this->assertSame('day', ReminderTimes::due($start, $this->at('2026-11-05 08:29')));
        $this->assertSame('5h', ReminderTimes::due($start, $this->at('2026-11-05 08:30')));
        $this->assertSame('1h', ReminderTimes::due($start, $this->at('2026-11-05 13:00')));   // the site was off at 5h: only 1h goes out
        $this->assertNull(ReminderTimes::due($start, $this->at('2026-11-05 13:30')));        // it has started
    }

    public function test_when_is_written_from_the_readers_side(): void
    {
        $start = $this->at('2026-11-05 07:30');

        $this->assertSame('tomorrow at 7:30 AM', ReminderTimes::when($start, $this->at('2026-11-04 21:00')));
        $this->assertSame('today at 7:30 AM', ReminderTimes::when($start, $this->at('2026-11-05 06:30')));
        $this->assertSame('Thursday, November 5 at 7:30 AM', ReminderTimes::when($start, $this->at('2026-11-02 09:00')));
    }
}
