<?php

namespace Tests\Unit;

use App\Support\RoomCapacity;
use PHPUnit\Framework\TestCase;

class RoomCapacityTest extends TestCase
{
    public function test_a_recorded_capacity_is_shown_as_seats(): void
    {
        $this->assertSame('40 seats', RoomCapacity::label(40));
        $this->assertSame('40 seats', RoomCapacity::label('40'));
        $this->assertSame('1 seat', RoomCapacity::label(1));
        $this->assertTrue(RoomCapacity::known(40));
    }

    public function test_a_missing_capacity_shows_nothing_at_all(): void
    {
        foreach ([null, '', 'n/a'] as $missing) {
            $this->assertSame('', RoomCapacity::label($missing), 'no placeholder wording, no zero, no dash');
            $this->assertFalse(RoomCapacity::known($missing));
        }
    }

    public function test_a_recorded_zero_is_a_real_value_and_is_kept(): void
    {
        $this->assertTrue(RoomCapacity::known(0));
        $this->assertSame('0 seats', RoomCapacity::label(0));
    }
}
