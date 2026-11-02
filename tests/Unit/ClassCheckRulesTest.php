<?php

namespace Tests\Unit;

use App\Support\ClassCheckRules;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ClassCheckRulesTest extends TestCase
{
    private CarbonImmutable $start;

    protected function setUp(): void
    {
        $this->start = CarbonImmutable::parse('2026-11-05 13:30', 'Asia/Manila');    // 5-hour email: 8:30 AM
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time, 'Asia/Manila');
    }

    public function test_answers_decide_the_room(): void
    {
        $now = $this->at('2026-11-05 10:00');

        $this->assertSame('f2f', ClassCheckRules::state('f2f', null, $this->start, $now));
        $this->assertSame('online', ClassCheckRules::state('online', null, $this->start, $now));
        $this->assertTrue(ClassCheckRules::roomFree('online'));
        $this->assertFalse(ClassCheckRules::roomFree('f2f'));
    }

    public function test_no_answer_frees_the_room_when_the_5_hour_email_goes_out(): void
    {
        $asked = $this->at('2026-11-04 13:30');    // the day-before email

        $this->assertSame('waiting', ClassCheckRules::state(null, $asked, $this->start, $this->at('2026-11-05 08:29')));
        $this->assertSame('freed', ClassCheckRules::state(null, $asked, $this->start, $this->at('2026-11-05 08:30')));
        $this->assertTrue(ClassCheckRules::roomFree('freed'));
        $this->assertSame('f2f', ClassCheckRules::state('f2f', $asked, $this->start, $this->at('2026-11-05 09:00')));    // taken back
    }

    public function test_a_rep_who_was_never_asked_in_time_keeps_the_room(): void
    {
        $this->assertSame('waiting', ClassCheckRules::state(null, null, $this->start, $this->at('2026-11-05 12:00')));      // never asked
        $this->assertSame('waiting', ClassCheckRules::state(null, $this->at('2026-11-05 08:30'), $this->start, $this->at('2026-11-05 12:00')));    // first asked at 5 hours
        $this->assertFalse(ClassCheckRules::roomFree('waiting'));

        // The site was off: the day-before question went out late, at 3 AM, less than 6 hours before 8:30 AM
        $this->assertSame('waiting', ClassCheckRules::state(null, $this->at('2026-11-05 03:00'), $this->start, $this->at('2026-11-05 12:00')));
        $this->assertTrue(ClassCheckRules::freesIfUnanswered($this->at('2026-11-05 02:30'), $this->start));     // exactly 6 hours: counts
        $this->assertFalse(ClassCheckRules::freesIfUnanswered($this->at('2026-11-05 02:31'), $this->start));
    }

    public function test_which_email_goes_out(): void
    {
        $this->assertSame('ask', ClassCheckRules::email('day', 'waiting'));
        $this->assertSame('ask', ClassCheckRules::email('1h', 'waiting'));       // never asked in time: asked again
        $this->assertSame('remind', ClassCheckRules::email('5h', 'f2f'));
        $this->assertSame('remind', ClassCheckRules::email('1h', 'f2f'));
        $this->assertNull(ClassCheckRules::email('5h', 'online'));               // online: no more emails
        $this->assertSame('freed', ClassCheckRules::email('5h', 'freed'));       // told once that the room was freed
        $this->assertNull(ClassCheckRules::email('1h', 'freed'));
    }
}
