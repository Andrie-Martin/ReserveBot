<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Phase 9: account checks that need no database, so they can be unit-tested.
 */
final class AccountRules
{
    /** A block rep's email must be Gmail or PUP webmail */
    public const REP_EMAIL_DOMAINS = ['gmail.com', 'iskolarngbayan.pup.edu.ph'];

    /** A PUP School ID (student number), e.g. 2021-00123-MN-0 */
    public const SCHOOL_ID_PATTERN = '/^\d{4}-\d{5}-[A-Z]{2}-\d$/';

    /** Emails are saved without spaces and in small letters, so logging in ignores capitals */
    public static function email(?string $email): string
    {
        return Str::lower(trim((string) $email));
    }

    /** Is this a Gmail or PUP webmail address? */
    public static function isRepEmail(string $email): bool
    {
        $email = self::email($email);
        $at = strrpos($email, '@');

        return $at !== false && $at > 0 && in_array(substr($email, $at + 1), self::REP_EMAIL_DOMAINS, true);
    }

    /** A typed School ID tidied up: capitals, no spaces, and dashes added when they were left out */
    public static function schoolId(?string $id): string
    {
        $id = strtoupper((string) preg_replace('/\s+/', '', (string) $id));

        if (preg_match('/^(\d{4})-?(\d{5})-?([A-Z]{2})-?(\d)$/', $id, $m)) {
            return $m[1].'-'.$m[2].'-'.$m[3].'-'.$m[4];
        }

        return $id;
    }

    public static function isSchoolId(string $id): bool
    {
        return preg_match(self::SCHOOL_ID_PATTERN, $id) === 1;
    }

    /** "Bea Prieto" -> "BP": shown in a circle when someone has no photo */
    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (! $words) {
            return '?';
        }

        $first = mb_substr($words[0], 0, 1);
        $last = count($words) > 1 ? mb_substr($words[count($words) - 1], 0, 1) : '';

        return mb_strtoupper($first.$last);
    }
}
