<?php

namespace App\Support;

/**
 * Presentation only: turns the free-text note on a "Blocked" timetable entry into wording a person can read.
 *
 * The notes come from the timetable import (the seeded data copies the source spreadsheet's own remarks, such as
 * "Used by ECE (yellow on timetable)" or "Not available (yellow on timetable, no label)"). The parenthesised remark
 * describes a cell colour in that spreadsheet, not anything on screen, so it is dropped. A note that only says the room
 * is not available has no reason in it, so it reads "Reason not specified"; a named user of the room reads
 * "Assigned to X". Nothing is stored or changed: the note itself, the block and its times are untouched.
 */
final class BlockedReason
{
    public const UNKNOWN = 'Reason not specified';

    /** Notes that carry no reason, compared case-insensitively after clean-up. */
    private const NO_REASON = ['', 'not available', 'unavailable', 'blocked', 'no label', 'n/a', 'na', 'none'];

    /** @return array{text: string, known: bool} */
    public static function describe(?string $note): array
    {
        $clean = (string) preg_replace('/\s*\([^()]*\byellow on timetable\b[^()]*\)/i', '', (string) $note);
        $clean = trim((string) preg_replace('/\s+/', ' ', $clean), " \t.:;-—");

        if (in_array(mb_strtolower($clean), self::NO_REASON, true)) {
            return ['text' => self::UNKNOWN, 'known' => false];
        }
        if (preg_match('/^used by\s+(.+)$/i', $clean, $m)) {
            return ['text' => 'Assigned to '.trim($m[1]), 'known' => true];
        }

        return ['text' => $clean, 'known' => true];
    }

    public static function text(?string $note): string
    {
        return self::describe($note)['text'];
    }
}
