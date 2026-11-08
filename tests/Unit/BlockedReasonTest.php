<?php

namespace Tests\Unit;

use App\Support\BlockedReason;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BlockedReasonTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: string, 2: bool}> */
    public static function notes(): array
    {
        return [
            'spreadsheet colour remark, no label' => ['Not available (yellow on timetable, no label)', 'Reason not specified', false],
            'plain not available' => ['Not available', 'Reason not specified', false],
            'case and spacing' => ["  NOT   available. ", 'Reason not specified', false],
            'null note' => [null, 'Reason not specified', false],
            'empty note' => ['', 'Reason not specified', false],
            'only the colour remark' => ['(yellow on timetable)', 'Reason not specified', false],
            'named user of the room' => ['Used by ECE (yellow on timetable)', 'Assigned to ECE', true],
            'named user without remark' => ['used by Chemistry Dept', 'Assigned to Chemistry Dept', true],
            'free-text reason is kept' => ['Under renovation until June', 'Under renovation until June', true],
            'colour remark is dropped from a real reason' => ['Aircon repair (yellow on timetable)', 'Aircon repair', true],
        ];
    }

    #[DataProvider('notes')]
    public function test_notes_become_readable_reasons(?string $note, string $text, bool $known): void
    {
        $this->assertSame(['text' => $text, 'known' => $known], BlockedReason::describe($note));
        $this->assertSame($text, BlockedReason::text($note));
    }

    public function test_the_word_yellow_never_reaches_the_screen(): void
    {
        foreach (self::notes() as [$note]) {
            $this->assertStringNotContainsStringIgnoringCase('yellow', BlockedReason::text($note));
        }
    }
}
