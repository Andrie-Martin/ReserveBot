<?php

namespace Tests\Unit;

use App\Support\BookingRules;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class BookingRulesTest extends TestCase
{
    /** The default department rules, as if it's Monday, November 2, 2026 at 9:00 AM */
    private function rules(string $now = '2026-11-02 09:00'): BookingRules
    {
        return new BookingRules(
            openDays: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            opens: 450,              // 7:30 AM
            closes: 1260,            // 9:00 PM
            step: 30,
            minMinutes: 30,
            maxMinutes: 180,
            maxDaysAhead: 14,
            noticeMinutes: 60,
            cancelMinutes: 180,
            needsApproval: true,
            now: CarbonImmutable::parse($now),
        );
    }

    private function day(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date)->startOfDay();
    }

    public function test_a_normal_booking_is_fine(): void
    {
        $this->assertNull($this->rules()->checkExtra($this->day('2026-11-03'), 480, 600));   // 8:00 to 10:00 AM
    }

    public function test_you_can_book_from_today_up_to_the_limit(): void
    {
        $rules = $this->rules();

        $this->assertNull($rules->checkExtra($this->day('2026-11-16'), 480, 540));          // the 14th day ahead
        $this->assertSame('You can book from today up to November 16.', $rules->checkExtra($this->day('2026-11-17'), 480, 540));
        $this->assertSame('You can book from today up to November 16.', $rules->checkExtra($this->day('2026-11-01'), 480, 540));
    }

    public function test_closed_days_are_refused(): void
    {
        $this->assertSame(
            'Rooms and equipment aren’t booked on Sundays.',
            $this->rules()->checkExtra($this->day('2026-11-08'), 480, 540),
        );
    }

    public function test_times_must_come_from_the_lists(): void
    {
        $rules = $this->rules();
        $pick = 'Pick a start and an end time from the lists.';

        $this->assertSame($pick, $rules->checkExtra($this->day('2026-11-03'), 485, 540));   // 8:05 isn't a slot
        $this->assertSame($pick, $rules->checkExtra($this->day('2026-11-03'), 420, 480));   // 7:00 is before opening
        $this->assertSame($pick, $rules->checkExtra($this->day('2026-11-03'), 1200, 1290)); // 9:30 PM is after closing
    }

    public function test_the_end_must_be_after_the_start(): void
    {
        $this->assertSame(
            'Pick an end time that is after the start time.',
            $this->rules()->checkExtra($this->day('2026-11-03'), 600, 600),
        );
    }

    public function test_bookings_must_be_30_minutes_to_3_hours(): void
    {
        $rules = $this->rules();

        $this->assertNull($rules->checkExtra($this->day('2026-11-03'), 450, 630));          // exactly 3 hours
        $this->assertSame(
            'A booking can be 30 minutes to 3 hours long.',
            $rules->checkExtra($this->day('2026-11-03'), 450, 690),                           // 4 hours
        );
    }

    public function test_bookings_need_an_hours_notice(): void
    {
        $rules = $this->rules();                                                                 // now: 9:00 AM

        $this->assertSame('Book at least 1 hour before the start time.', $rules->checkExtra($this->day('2026-11-02'), 570, 630));
        $this->assertNull($rules->checkExtra($this->day('2026-11-02'), 600, 660));            // starts at 10:00, OK
    }

    public function test_class_sessions_check_the_window_and_notice_only(): void
    {
        $rules = $this->rules();

        $this->assertNull($rules->checkSession(CarbonImmutable::parse('2026-11-03 18:00')));    // a class can run past closing
        $this->assertSame('Book at least 1 hour before the start time.', $rules->checkSession(CarbonImmutable::parse('2026-11-02 09:30')));
        $this->assertSame('You can book from today up to November 16.', $rules->checkSession(CarbonImmutable::parse('2026-11-20 07:30')));
    }

    public function test_the_days_list_skips_closed_days(): void
    {
        $days = $this->rules()->days();

        $this->assertCount(13, $days);                                   // November 2 to 16, without the two Sundays
        $this->assertSame('2026-11-02', $days[0]->toDateString());
        $this->assertSame('2026-11-16', end($days)->toDateString());
    }

    public function test_reps_can_cancel_until_3_hours_before_the_start(): void
    {
        $rules = $this->rules('2026-11-03 09:00');

        $this->assertTrue($rules->canCancel(CarbonImmutable::parse('2026-11-03 12:00')));     // exactly 3 hours left
        $this->assertFalse($rules->canCancel(CarbonImmutable::parse('2026-11-03 11:30')));    // only 2½ hours left
        $this->assertSame('2026-11-04 05:00', $rules->cancelDeadline(CarbonImmutable::parse('2026-11-04 08:00'))->format('Y-m-d H:i'));
    }

    public function test_lengths_are_written_in_words(): void
    {
        $this->assertSame('30 minutes', BookingRules::duration(30));
        $this->assertSame('1 hour', BookingRules::duration(60));
        $this->assertSame('1 hour 30 minutes', BookingRules::duration(90));
        $this->assertSame('3 hours', BookingRules::duration(180));
    }
}
