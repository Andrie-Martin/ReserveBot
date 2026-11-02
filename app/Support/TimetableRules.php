<?php

namespace App\Support;

/**
 * Phase 11: timetable rows, as typed in the Excel file or on the site, and the checks before a new
 * timetable replaces the one in use. No database here, so it's easy to test.
 *
 * A row is an array: day ('Mon' … 'Sun'), start and end ('07:30', 24-hour), room, type ('Class',
 * or 'Blocked' for time the room isn't available), code, title, block, instructor, note, line (its
 * row number in the uploaded file, or null) and notes (how something was read). A value that
 * couldn't be read stays as typed, and problems() says what's wrong with it.
 */
final class TimetableRules
{
    public const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    public const DAY_NAMES = ['Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday', 'Thu' => 'Thursday',
        'Fri' => 'Friday', 'Sat' => 'Saturday', 'Sun' => 'Sunday'];

    /** Written as the course code for time the room isn't available */
    public const BLOCKED = 'BLOCKED';

    /** The longest each value can be (the database's column sizes) */
    public const MAX = ['room' => 30, 'code' => 20, 'title' => 100, 'block' => 30, 'instructor' => 60, 'note' => 150];

    public const LABELS = ['day' => 'Day', 'start' => 'Start', 'end' => 'End', 'room' => 'Room', 'code' => 'Course code',
        'title' => 'Course title', 'block' => 'Block', 'instructor' => 'Instructor', 'note' => 'Note'];

    /** How people write days (lower case) */
    private const DAY_WORDS = [
        'mon' => 'Mon', 'monday' => 'Mon', 'm' => 'Mon',
        'tue' => 'Tue', 'tues' => 'Tue', 'tuesday' => 'Tue', 'tu' => 'Tue', 't' => 'Tue',
        'wed' => 'Wed', 'wednesday' => 'Wed', 'w' => 'Wed',
        'thu' => 'Thu', 'thur' => 'Thu', 'thurs' => 'Thu', 'thursday' => 'Thu', 'th' => 'Thu', 'h' => 'Thu', 'r' => 'Thu',
        'fri' => 'Fri', 'friday' => 'Fri', 'f' => 'Fri',
        'sat' => 'Sat', 'saturday' => 'Sat', 'sa' => 'Sat', 's' => 'Sat',
        'sun' => 'Sun', 'sunday' => 'Sun', 'su' => 'Sun',
    ];

    // ---- Reading what was typed ----

    /**
     * The days in a Day cell, in week order: 'Mon' -> ['Mon']; 'TTh', 'T-Th' or 'Tue, Thu' -> ['Tue', 'Thu'];
     * 'MWF' -> ['Mon', 'Wed', 'Fri']; 'Mon-Fri' -> Monday to Friday. Null if it isn't days.
     */
    public static function days(mixed $text): ?array
    {
        $text = mb_strtolower(trim((string) $text));
        $text = preg_replace('/\s*[-–]\s*/u', '-', $text) ?? '';
        if ($text === '') {
            return null;
        }

        $days = [];
        foreach (preg_split('/\s*(?:,|\/|&|\+|;|\band\b)\s*|\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $found = self::dayWord(rtrim($word, '.'));
            if ($found === null) {
                return null;
            }
            array_push($days, ...$found);
        }

        $days = array_values(array_intersect(self::DAYS, $days));     // week order, each once

        return $days ?: null;
    }

    /**
     * A time as 24-hour 'HH:MM': '7:30 AM', '7:30am', '7.30', '13:30', '0730', 730, '12NN' (noon), or
     * an Excel time (0.3125 = 7:30 AM). Classes don't run at night, so 1:00 to 6:59 without AM or PM
     * means PM: '1:30' -> '13:30' (but '01:30' is 24-hour time). An Excel time from 1:00 to 6:59 AM is
     * read as PM too, and $guessed says so (Excel turns a typed 1:30 into 1:30 AM). Null if it isn't a time.
     */
    public static function time(mixed $value, ?bool &$guessed = null): ?string
    {
        $guessed = false;
        if (is_int($value) || is_float($value)) {
            return self::numberTime($value, $guessed);
        }

        $text = str_replace(['.', ' '], '', mb_strtolower(trim((string) $value)));
        if (! preg_match('/^(\d{1,2})(?::?(\d{2}))?(?::\d{2})?(am|pm|a|p|nn|noon)?$/', $text, $m)) {
            return null;
        }
        $hour = (int) $m[1];
        $minute = ($m[2] ?? '') !== '' ? (int) $m[2] : 0;
        $half = $m[3] ?? '';
        if ($minute > 59) {
            return null;
        }

        if ($half === '') {
            if (strlen($m[1]) === 1 && $hour >= 1 && $hour <= 6) {
                $hour += 12;                        // '1:30' -> 1:30 PM ('01:30' is 24-hour: 1:30 AM)
            }
        } elseif ($half === 'nn' || $half === 'noon') {
            if ($hour !== 12) {
                return null;
            }
        } elseif ($hour > 12) {
            if ($half[0] !== 'p') {
                return null;                        // '13:30 AM'
            }
        } elseif ($hour >= 1) {
            $hour = $hour % 12 + ($half[0] === 'p' ? 12 : 0);
        } else {
            return null;                            // '0:30 PM'
        }

        return $hour < 24 ? sprintf('%02d:%02d', $hour, $minute) : null;
    }

    /**
     * Start and end from one cell: '7:30-10:30 AM', '7:30 AM – 10:30 AM', '10:30 to 1:30' ->
     * ['07:30', '10:30']. When only the end has AM or PM, the start gets the same, unless that would
     * put it after the end ('10:30-1:30 PM' -> 10:30 AM to 1:30 PM). Null if it isn't two times.
     */
    public static function range(mixed $text): ?array
    {
        if (! is_string($text)) {
            return null;
        }
        $parts = preg_split('/\s*(?:-|–|—|\bto\b)\s*/iu', trim($text));
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }
        [$from, $to] = $parts;

        $end = self::time($to);
        if ($end === null) {
            return null;
        }
        if (! self::saysHalf($from) && preg_match('/(a|p)\.?\s*m\.?$/i', $to, $m)) {
            $same = strtolower($m[1]).'m';
            $start = self::time($from.$same);
            if ($start === null || $start >= $end) {
                $start = self::time($from.($same === 'pm' ? 'am' : 'pm'));
            }
        } else {
            $start = self::time($from);
            if ($start !== null && ! self::saysHalf($from) && ! self::saysHalf($to)) {
                $end = self::evening($start, $end) ?? $end;      // '6:00-9:00' is 6 to 9 PM
            }
        }

        return $start === null ? null : [$start, $end];
    }

    /**
     * A row from what was typed (a row of the file, or the site's form). Tidies each value and keeps
     * what can't be read as typed, so problems() can say what's wrong. $in: day (one day), start,
     * end, room, code, title, block, instructor, note, and optionally type and line. BLOCKED as the
     * course code (or type 'Blocked') makes it time the room isn't available.
     */
    public static function row(array $in): array
    {
        $code = self::code($in['code'] ?? null);
        $blocked = $code === self::BLOCKED || strcasecmp(trim((string) ($in['type'] ?? '')), 'Blocked') === 0;
        $days = self::days($in['day'] ?? null);

        $times = [];
        $notes = [];
        foreach (['start' => 'Start', 'end' => 'End'] as $field => $label) {
            $value = $in[$field] ?? null;
            $time = self::time($value, $guessed);
            if ($guessed) {
                $notes[] = $label.' time: the file says '.TimeSlots::clock(self::minutes($time) - 720).'; it was read as '.self::clock($time).'.';
            }
            $times[$field] = $time ?? self::clean($value);
        }

        // An end before the start, with no AM or PM written: '6:00' to '9:00' is 6 to 9 PM
        $end = $in['end'] ?? null;
        if (self::isTime($times['start']) && self::isTime($times['end']) && ! self::saysHalf($end) && ($later = self::evening($times['start'], $times['end']))) {
            $notes[] = 'End time: '.(is_string($end) ? '“'.trim($end).'” was read' : 'the file says '.self::clock($times['end']).'; it was read')
                .' as '.self::clock($later).', after the start.';
            $times['end'] = $later;
        }

        return [
            'line' => $in['line'] ?? null,
            'day' => $days && count($days) === 1 ? $days[0] : self::clean($in['day'] ?? null),
            'start' => $times['start'],
            'end' => $times['end'],
            'room' => self::clean($in['room'] ?? null),
            'type' => $blocked ? 'Blocked' : 'Class',
            'code' => $blocked ? null : $code,
            'title' => $blocked ? null : self::clean($in['title'] ?? null),
            'block' => $blocked ? null : self::block($in['block'] ?? null),
            'instructor' => $blocked ? null : self::clean($in['instructor'] ?? null),
            'note' => self::clean($in['note'] ?? null),
            'notes' => $notes,
        ];
    }

    /** Trimmed, with single spaces; empty becomes null. 300.0 (an Excel number) -> '300' */
    public static function clean(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            $value = floor($value) == $value && abs($value) < 1e15 ? (string) (int) $value : rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
        }
        $text = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        return $text === '' ? null : $text;
    }

    /** 'cmpe402' -> 'CMPE 402' */
    public static function code(mixed $value): ?string
    {
        $text = self::clean($value);

        return $text === null ? null : preg_replace('/^(\p{Lu}+)(\d)/u', '$1 $2', mb_strtoupper($text));
    }

    /** 'bscpe4-1' -> 'BSCPE 4-1' */
    public static function block(mixed $value): ?string
    {
        return self::code($value);
    }

    /** 'CEA 300', 'cea300' and 'CEA-300' are the same room */
    public static function roomKey(?string $name): string
    {
        return (string) preg_replace('/[\s\-_.]+/u', '', mb_strtoupper((string) $name));
    }

    /** The program of a new block: 'BSCPE 4-1' -> 'BSCPE' */
    public static function program(string $block): string
    {
        return mb_substr(explode(' ', trim($block))[0], 0, 10);
    }

    // ---- Checking ----

    /** What must be fixed before the row can be saved (empty when it's fine) */
    public static function problems(array $row): array
    {
        $problems = [];
        $day = $row['day'] ?? null;
        if ($day === null) {
            $problems[] = 'Day is empty: write Mon, Tue, Wed, Thu, Fri, Sat or Sun.';
        } elseif (! in_array($day, self::DAYS, true)) {
            $problems[] = '“'.$day.'” isn’t a day: write Mon, Tue, Wed, Thu, Fri, Sat or Sun.';
        }

        foreach (['start' => 'Start', 'end' => 'End'] as $field => $label) {
            $time = $row[$field] ?? null;
            if ($time === null) {
                $problems[] = $label.' time is empty.';
            } elseif (! self::isTime($time)) {
                $problems[] = $label.' time “'.$time.'” isn’t a time: write it like 7:30 AM or 13:30.';
            }
        }
        if (self::isTime($row['start'] ?? null) && self::isTime($row['end'] ?? null) && $row['end'] <= $row['start']) {
            $problems[] = $row['end'] === $row['start']
                ? 'It starts and ends at '.self::clock($row['start']).'.'
                : 'It ends ('.self::clock($row['end']).') before it starts ('.self::clock($row['start']).').';
        }

        if (($row['room'] ?? null) === null) {
            $problems[] = 'Room is empty.';
        }
        if (($row['type'] ?? 'Class') === 'Class' && ($row['code'] ?? null) === null) {
            $problems[] = 'Course code is empty. For time the room isn’t available, write BLOCKED.';
        }
        foreach (self::MAX as $field => $max) {
            if (mb_strlen((string) ($row[$field] ?? '')) > $max) {
                $problems[] = self::LABELS[$field].' is too long: keep it to '.$max.' characters.';
            }
        }

        return $problems;
    }

    /**
     * Rows typed twice (every value the same): [each row once, [[the extra row, the row it repeats], ...]]
     */
    public static function dedupe(array $rows): array
    {
        $seen = [];
        $once = [];
        $repeats = [];
        foreach ($rows as $row) {
            $key = self::key($row).'|'.implode('|', self::details($row));
            if (isset($seen[$key])) {
                $repeats[] = [$row, $seen[$key]];
            } else {
                $seen[$key] = $row;
                $once[] = $row;
            }
        }

        return [$once, $repeats];
    }

    /**
     * Times that can't all be right: two things in one room at once, or a block in two rooms at once.
     * Each: ['text' => ..., 'ids' => [the two rows' ids]]. For rows without problems.
     */
    public static function overlaps(array $rows): array
    {
        $warnings = [];

        $byRoom = [];
        foreach ($rows as $row) {
            $byRoom[$row['day'].'|'.self::roomKey($row['room'])][] = $row;
        }
        foreach ($byRoom as $list) {
            foreach (self::pairs($list) as [$a, $b]) {
                if ($a['type'] === 'Blocked' && $b['type'] === 'Blocked') {
                    continue;
                }
                $on = $a['room'].' on '.self::DAY_NAMES[$a['day']].': ';
                if ($a['type'] === 'Blocked' || $b['type'] === 'Blocked') {
                    [$blocked, $class] = $a['type'] === 'Blocked' ? [$a, $b] : [$b, $a];
                    $why = array_filter([$blocked['note'] ?? null, ! empty($blocked['line']) ? 'row '.$blocked['line'] : null]);
                    $text = $on.'it’s not available '.self::span($blocked).($why ? ' ('.implode(', ', $why).')' : '')
                        .', but '.self::what($class).' is there '.self::span($class).self::at($class, ' (', ')').'.';
                } else {
                    $text = $on.self::what($a).' ('.self::span($a).self::at($a, ', ').') and '.self::what($b).' ('.self::span($b).self::at($b, ', ')
                        .') are at the same time. That’s only right if they’re one combined class.';
                }
                $warnings[] = ['text' => $text, 'ids' => [$a['id'] ?? null, $b['id'] ?? null]];
            }
        }

        $byBlock = [];
        foreach ($rows as $row) {
            if ($row['type'] === 'Class' && $row['block'] !== null) {
                $byBlock[$row['day'].'|'.mb_strtoupper($row['block'])][] = $row;
            }
        }
        foreach ($byBlock as $list) {
            foreach (self::pairs($list) as [$a, $b]) {
                if (self::roomKey($a['room']) === self::roomKey($b['room'])) {
                    continue;       // said above
                }
                $warnings[] = [
                    'text' => $a['block'].' on '.self::DAY_NAMES[$a['day']].' has two classes at once: '
                        .$a['code'].' in '.$a['room'].' ('.self::span($a).self::at($a, ', ').') and '
                        .$b['code'].' in '.$b['room'].' ('.self::span($b).self::at($b, ', ').').',
                    'ids' => [$a['id'] ?? null, $b['id'] ?? null],
                ];
            }
        }

        return $warnings;
    }

    /** A class outside the opening hours ($opens and $closes: minutes after midnight): a warning, or null */
    public static function outsideHours(array $row, int $opens, int $closes): ?string
    {
        if ($row['type'] === 'Blocked' || (self::minutes($row['start']) >= $opens && self::minutes($row['end']) <= $closes)) {
            return null;
        }

        return self::describe($row).': '.self::span($row).' is outside the opening hours ('.TimeSlots::span($opens, $closes)
            .'). Check the times. If they’re right, nobody can book the room then anyway.';
    }

    // ---- Comparing with the timetable in use ----

    /** Rows with the same key are the same class (or blocked time): same day, room, times, course and block */
    public static function key(array $row): string
    {
        return implode('|', [$row['day'], self::roomKey($row['room']), $row['start'], $row['end'], $row['type'],
            mb_strtoupper((string) $row['code']), mb_strtoupper((string) $row['block'])]);
    }

    /**
     * What changes from the timetable in use ($old) to a new one ($new). A class with the same key
     * stays (or only its title, instructor or note changes); the rest are added or removed.
     *
     * @return array{same: list<array{0: array, 1: array}>, changed: list<array{0: array, 1: array}>, added: list<array>, removed: list<array>}
     */
    public static function compare(array $old, array $new): array
    {
        $waiting = [];
        foreach ($old as $row) {
            $waiting[self::key($row)][] = $row;
        }

        $same = [];
        $changed = [];
        $added = [];
        foreach ($new as $row) {
            $key = self::key($row);
            if (empty($waiting[$key])) {
                $added[] = $row;

                continue;
            }
            $was = array_shift($waiting[$key]);
            if (self::changes($was, $row)) {
                $changed[] = [$was, $row];
            } else {
                $same[] = [$was, $row];
            }
        }

        return ['same' => $same, 'changed' => $changed, 'added' => $added, 'removed' => array_merge(...array_values($waiting))];
    }

    /** What's different between two versions of the same class: ['Instructor: J. Tena → L. Reyes', ...] */
    public static function changes(array $was, array $now): array
    {
        $list = [];
        foreach (['title' => 'Title', 'instructor' => 'Instructor', 'note' => 'Note'] as $field => $label) {
            $a = (string) ($was[$field] ?? '');
            $b = (string) ($now[$field] ?? '');
            if ($field === 'title' ? mb_strtolower($a) !== mb_strtolower($b) : $a !== $b) {
                $list[] = $label.': '.($a !== '' ? $a : '(none)').' → '.($b !== '' ? $b : '(none)');
            }
        }

        return $list;
    }

    // ---- Showing rows ----

    /** By day, then room, then time; rows with a day that isn't a day come last */
    public static function sort(array $rows): array
    {
        usort($rows, function ($a, $b) {
            $dayA = array_search($a['day'], self::DAYS, true);
            $dayB = array_search($b['day'], self::DAYS, true);

            return [$dayA === false ? 9 : $dayA, self::roomKey($a['room']), (string) $a['start'], (string) $a['end']]
                <=> [$dayB === false ? 9 : $dayB, self::roomKey($b['room']), (string) $b['start'], (string) $b['end']];
        });

        return $rows;
    }

    /**
     * Places side by side for things that overlap on one day of the week view: [index => [lane, lanes]]
     * (lane counts from 0; lanes = how many share the width). $items: [['start' => minutes, 'end' =>
     * minutes], ...] sorted by start.
     */
    public static function lanes(array $items): array
    {
        $places = [];
        $group = [];
        $laneEnds = [];
        $groupEnd = -1;
        foreach ($items as $i => $item) {
            if ($group && $item['start'] >= $groupEnd) {
                foreach ($group as $j) {
                    $places[$j][1] = count($laneEnds);
                }
                $group = [];
                $laneEnds = [];
            }
            $lane = count($laneEnds);
            foreach ($laneEnds as $l => $end) {
                if ($end <= $item['start']) {
                    $lane = $l;
                    break;
                }
            }
            $laneEnds[$lane] = $item['end'];
            $places[$i] = [$lane, 1];
            $group[] = $i;
            $groupEnd = max($groupEnd, $item['end']);
        }
        foreach ($group as $j) {
            $places[$j][1] = count($laneEnds);
        }

        return $places;
    }

    public static function isTime(mixed $time): bool
    {
        return is_string($time) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
    }

    /** '13:30' -> 810 */
    public static function minutes(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }

    /** '13:30' -> '1:30 PM' (anything else as it is) */
    public static function clock(?string $time): string
    {
        return self::isTime($time) ? TimeSlots::clock(self::minutes($time)) : (string) $time;
    }

    /** '10:30 AM–1:30 PM' */
    public static function span(array $row): string
    {
        return self::isTime($row['start'] ?? null) && self::isTime($row['end'] ?? null)
            ? TimeSlots::span(self::minutes($row['start']), self::minutes($row['end']))
            : ($row['start'] ?? '?').'–'.($row['end'] ?? '?');
    }

    /** 'CMPE 402 · BSCPE 4-1', or 'Not available' for blocked time */
    public static function what(array $row): string
    {
        if (($row['type'] ?? 'Class') === 'Blocked') {
            return 'Not available';
        }

        return implode(' · ', array_filter([$row['code'] ?? 'Class', $row['block'] ?? null]));
    }

    /** 'CMPE 402 · BSCPE 4-1, Mon in CEA300 (row 12)' */
    public static function describe(array $row): string
    {
        return self::what($row).', '.($row['day'] ?? '?').' in '.($row['room'] ?? '?').self::at($row, ' (', ')');
    }

    /** Where it is in the uploaded file: ', row 12' (or nothing for a row added on the site) */
    private static function at(array $row, string $before, string $after = ''): string
    {
        return ! empty($row['line']) ? $before.'row '.$row['line'].$after : '';
    }

    /** The parts that can change without making it another class */
    private static function details(array $row): array
    {
        return [mb_strtolower((string) ($row['title'] ?? '')), (string) ($row['instructor'] ?? ''), (string) ($row['note'] ?? '')];
    }

    /** Pairs of rows in $list whose times overlap */
    private static function pairs(array $list): array
    {
        usort($list, fn ($a, $b) => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);
        $pairs = [];
        $count = count($list);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count && $list[$j]['start'] < $list[$i]['end']; $j++) {
                $pairs[] = [$list[$i], $list[$j]];
            }
        }

        return $pairs;
    }

    private static function dayWord(string $word): ?array
    {
        if (isset(self::DAY_WORDS[$word])) {
            return [self::DAY_WORDS[$word]];
        }

        // Day names from one to another: 'mon-fri', 'monday-friday'
        if (preg_match('/^([a-z]{3,})-([a-z]{3,})$/', $word, $m) && isset(self::DAY_WORDS[$m[1]], self::DAY_WORDS[$m[2]])) {
            $from = array_search(self::DAY_WORDS[$m[1]], self::DAYS, true);
            $to = array_search(self::DAY_WORDS[$m[2]], self::DAYS, true);

            return $from <= $to ? array_slice(self::DAYS, $from, $to - $from + 1) : null;
        }

        // Letters run together: 'tth', 't-th', 'mwf', 'mth'
        $letters = str_replace('-', '', $word);
        if (preg_match('/^(?:th|su|sa|m|t|w|h|f|s|r)+$/', $letters)) {
            preg_match_all('/th|su|sa|m|t|w|h|f|s|r/', $letters, $m);

            return array_map(fn ($letter) => self::DAY_WORDS[$letter], $m[0]);
        }

        return null;
    }

    /** Text with AM, PM or noon written in it */
    private static function saysHalf(mixed $value): bool
    {
        return is_string($value) && preg_match('/(a|p)\.?\s*m\.?$|nn$|noon$/i', trim($value)) === 1;
    }

    /** An end that's before the start in the morning, 12 hours later ('18:00', '09:00' -> '21:00'), or null */
    private static function evening(string $start, string $end): ?string
    {
        if ($end > $start || $end >= '12:00') {
            return null;
        }
        $later = sprintf('%02d:%s', (int) substr($end, 0, 2) + 12, substr($end, 3, 2));

        return $later > $start ? $later : null;
    }

    /** 0.3125 -> '07:30'; 730 -> '07:30'; 7 -> '07:00' */
    private static function numberTime(int|float $n, bool &$guessed): ?string
    {
        if ($n < 0) {
            return null;
        }
        if ($n < 1 || (floor($n) != $n && $n >= 60)) {
            // Excel: part of a day (or a date with a time: just the time)
            $minutes = (int) round(fmod((float) $n, 1) * 1440) % 1440;
            if ($minutes >= 60 && $minutes < 420) {
                $minutes += 720;            // 1:30 AM -> 1:30 PM
                $guessed = true;
            }

            return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        // A whole number: 7 (o'clock), or 730 and 1330 (written without the colon)
        return floor($n) == $n ? self::time((string) (int) $n) : null;
    }
}
