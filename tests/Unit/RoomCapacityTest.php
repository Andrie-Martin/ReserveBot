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

    public function test_a_missing_capacity_is_never_shown_as_zero_or_as_the_old_wording(): void
    {
        foreach ([null, '', 'n/a'] as $missing) {
            $label = RoomCapacity::label($missing);
            $this->assertSame('Capacity not specified', $label);
            $this->assertStringNotContainsString('0', $label);
            $this->assertStringNotContainsString('not recorded', $label);
            $this->assertFalse(RoomCapacity::known($missing));
        }
    }
}
