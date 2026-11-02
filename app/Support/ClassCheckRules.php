<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Phase 10: the face-to-face check of one class meeting (no database, so it's easy to test).
 *
 * States: 'f2f' (confirmed face-to-face), 'online' (online or no class: room freed),
 * 'freed' (nobody confirmed in time: room freed), 'waiting' (not confirmed yet: room still held).
 */
final class ClassCheckRules
{
    public const LABELS = [
        'f2f' => 'Face-to-face',
        'online' => 'Online · room freed',
        'freed' => 'Not confirmed · room freed',
        'waiting' => 'Not confirmed yet',
    ];

    /**
     * The question must go out at least this long before the 5-hour email for a missing answer to
     * free the room. On time it always does (the day-before email is 10+ hours earlier); this only
     * protects reps when the site was off and the question went out late, e.g. at 3 AM.
     */
    public const MIN_HOURS_TO_ANSWER = 6;

    /**
     * An unanswered class loses its room when the 5-hour email goes out, but only if the rep was
     * asked in time (the day-before email), so a rep who wasn't asked in time keeps the room.
     */
    public static function state(?string $answer, ?CarbonImmutable $askedAt, CarbonImmutable $start, CarbonImmutable $now): string
    {
        if ($answer === 'f2f' || $answer === 'online') {
            return $answer;
        }

        return $askedAt !== null && self::freesIfUnanswered($askedAt, $start) && $now->gte(self::deadline($start)) ? 'freed' : 'waiting';
    }

    /** Does a question sent at $askedAt free the room if nobody answers? */
    public static function freesIfUnanswered(CarbonImmutable $askedAt, CarbonImmutable $start): bool
    {
        return $askedAt->lte(self::deadline($start)->subHours(self::MIN_HOURS_TO_ANSWER));
    }

    /** When an unanswered class's room is freed: when the 5-hour email goes out */
    public static function deadline(CarbonImmutable $start): CarbonImmutable
    {
        return ReminderTimes::sendTime($start, '5h');
    }

    public static function roomFree(string $state): bool
    {
        return $state === 'online' || $state === 'freed';
    }

    /**
     * Which email the block's reps get at this mark:
     * 'ask' (is it face-to-face?), 'remind' (face-to-face reminder), 'freed' (nobody confirmed, room freed) or null.
     * Face-to-face classes get reminders; online ones get nothing more; a freed one is told once (at 5 hours).
     */
    public static function email(string $mark, string $state): ?string
    {
        return match ($state) {
            'f2f' => 'remind',
            'online' => null,
            'freed' => $mark === '5h' ? 'freed' : null,
            default => 'ask',
        };
    }
}
