<?php

namespace App\Support;

/**
 * Phase 11: the timetable as a spreadsheet: the Excel template staff download, and the files they upload.
 *
 * One row per class meeting: Day, Start, End, Room, Course code, Course title, Block, Instructor, Note.
 * Uploads can have the columns in any order and under other common names ("Section" for Block, or
 * "Time" holding "7:30-10:30 AM"), and a Day like "TTh" gives a row for each day.
 */
final class TimetableFile
{
    public const HEADERS = ['Day', 'Start', 'End', 'Room', 'Course code', 'Course title', 'Block', 'Instructor', 'Note'];

    /** Names a column can have (lower case, letters and digits only) */
    private const NAMES = [
        'day' => ['day', 'days'],
        'start' => ['start', 'starttime', 'timestart', 'from', 'timein', 'begin', 'starts'],
        'end' => ['end', 'endtime', 'timeend', 'to', 'timeout', 'until', 'ends'],
        'time' => ['time', 'times', 'schedule', 'timeslot', 'hours'],
        'room' => ['room', 'rooms', 'roomno', 'roomnumber', 'rm', 'venue'],
        'code' => ['coursecode', 'code', 'subjectcode', 'subjcode', 'course', 'subject'],
        'title' => ['coursetitle', 'title', 'description', 'subjecttitle', 'coursedescription', 'descriptivetitle', 'subjectdescription', 'coursename', 'subjectname'],
        'block' => ['block', 'section', 'blocksection', 'sectionblock', 'yearandsection', 'yearsection', 'yrsec', 'sections'],
        'instructor' => ['instructor', 'faculty', 'professor', 'teacher', 'prof', 'facultyname', 'instructorname'],
        'note' => ['note', 'notes', 'remarks', 'remark', 'comment', 'comments'],
        'type' => ['type', 'entrytype'],
    ];

    /** The template's "How to fill this in" sheet */
    private const HELP = [
        'How to fill in the timetable',
        '',
        'One row per class meeting. A class that meets on two days needs two rows, or both days in one cell: “Mon, Thu” or “TTh”.',
        'Day: Mon, Tue, Wed, Thu, Fri, Sat or Sun. Monday, M, T, W, Th, F and S work too.',
        'Start and End: like 7:30 AM and 10:30 AM, or 13:30. Without AM or PM, 1:00 to 6:59 means PM (classes don’t run at night).',
        'Room: like CEA300. A room that isn’t on your list yet is added to it.',
        'Course code and Course title: like CMPE 402 and Project Management.',
        'Time a room isn’t available (another department’s day, repairs): write BLOCKED as the course code and the reason in Note.',
        'Block: the section, like BSCPE 4-1. Its block account gets the reminder emails for the class. Leave it empty for a class with no block (like GEED).',
        'Instructor and Note: optional.',
        'An empty Day or Room cell means the same as the row above (handy with merged cells).',
        'Keep row 1 (the column names). The order of the rows doesn’t matter.',
        '',
        'When you’re done: save it as an Excel Workbook (.xlsx) or as CSV, and upload it on ReserveBot (Manage → Timetable).',
        'Nothing changes until you check the new timetable there and press “Replace the timetable”. The timetable in use is kept as a backup you can bring back.',
    ];

    /**
     * The timetable rows in an uploaded file: from the first sheet with the column names (a sheet
     * named Timetable first). Empty rows are skipped.
     *
     * @return array{rows: list<array>, sheet: string}
     *
     * @throws SpreadsheetProblem
     */
    public static function rows(array $sheets): array
    {
        usort($sheets, fn ($a, $b) => (strcasecmp($b['name'], 'Timetable') === 0) <=> (strcasecmp($a['name'], 'Timetable') === 0));
        $found = null;
        foreach ($sheets as $sheet) {
            ksort($sheet['rows']);
            if ($header = self::header($sheet['rows'])) {
                $found = [$sheet, ...$header];
                break;
            }
        }
        if (! $found) {
            throw new SpreadsheetProblem('The column names (Day, Start, End, Room, Course code, …) aren’t in the first rows of the file. Start from the Excel template: it has them in row 1.');
        }
        [$sheet, $headerRow, $columns] = $found;

        $missing = array_filter([
            'Start' => ! isset($columns['start']) && ! isset($columns['time']),
            'End' => ! isset($columns['end']) && ! isset($columns['time']),
            'Course code' => ! isset($columns['code']),
        ]);
        if ($missing) {
            throw new SpreadsheetProblem('The file has no '.implode(' or ', array_keys($missing)).' column. Its columns should be: '.implode(', ', self::HEADERS).'.');
        }

        $rows = [];
        $day = null;
        $room = null;
        $previous = $headerRow;
        foreach ($sheet['rows'] as $number => $cells) {
            if ($number <= $headerRow) {
                continue;
            }
            if ($number > $previous + 1) {
                $day = $room = null;    // an empty row: "same as above" starts again
            }
            $previous = $number;

            $cell = fn (string $field) => isset($columns[$field]) ? ($cells[$columns[$field]] ?? null) : null;
            $blank = fn ($value) => TimetableRules::clean($value) === null;
            $used = array_filter(array_keys($columns), fn ($field) => ! $blank($cell($field)));
            if (! $used) {
                continue;
            }
            if (mb_strtolower((string) TimetableRules::clean($cell('day'))) === 'day' && mb_strtolower((string) TimetableRules::clean($cell('room'))) === 'room') {
                continue;               // the column names again
            }

            // An empty Day or Room means the same as the row above (merged cells)
            $dayCell = $blank($cell('day')) ? $day : $cell('day');
            $roomCell = $blank($cell('room')) ? $room : $cell('room');
            [$day, $room] = [$dayCell, $roomCell];

            // Both times in one cell: '7:30-10:30 AM'
            $start = $cell('start');
            $end = $cell('end');
            if ($blank($start) && $blank($end) && ($range = TimetableRules::range($cell('time')))) {
                [$start, $end] = $range;
            } elseif ($blank($end) && ($range = TimetableRules::range($start))) {
                [$start, $end] = $range;
            }

            // Code and title in one cell: 'CMPE 402 Project Management'
            $code = $cell('code');
            $title = $cell('title');
            if (! isset($columns['title']) && is_string($code) && preg_match('/^\s*([A-Za-z]{2,8} ?\d{2,5}[A-Za-z]?)\s+(\S.*)$/u', $code, $m)) {
                [$code, $title] = [$m[1], preg_replace('/^[\s:\-–—]+/u', '', $m[2])];
            }

            $in = ['line' => $number, 'start' => $start, 'end' => $end, 'room' => $roomCell, 'code' => $code, 'title' => $title,
                'block' => $cell('block'), 'instructor' => $cell('instructor'), 'note' => $cell('note'), 'type' => $cell('type')];
            $days = TimetableRules::days($dayCell);
            foreach ($days && count($days) > 1 ? $days : [$dayCell] as $oneDay) {
                $rows[] = TimetableRules::row(['day' => $oneDay] + $in);
            }
            if (count($rows) > Spreadsheet::MAX_ROWS) {
                throw new SpreadsheetProblem('The file has more than '.number_format(Spreadsheet::MAX_ROWS).' classes. Is it the right file?');
            }
        }

        if (! $rows) {
            throw new SpreadsheetProblem('The file has the column names, but no classes under them.');
        }

        return ['rows' => $rows, 'sheet' => $sheet['name']];
    }

    /**
     * The timetable as spreadsheet rows: the column names, then one row per class by day, room and
     * time. Times are Excel times (['time' => minutes]); a value that isn't a time stays as text.
     */
    public static function sheetRows(array $rows): array
    {
        $sheet = [self::HEADERS];
        foreach (TimetableRules::sort($rows) as $row) {
            $sheet[] = [
                $row['day'],
                TimetableRules::isTime($row['start']) ? ['time' => TimetableRules::minutes($row['start'])] : $row['start'],
                TimetableRules::isTime($row['end']) ? ['time' => TimetableRules::minutes($row['end'])] : $row['end'],
                $row['room'],
                $row['type'] === 'Blocked' ? TimetableRules::BLOCKED : $row['code'],
                $row['title'],
                $row['block'],
                $row['instructor'],
                $row['note'],
            ];
        }

        return $sheet;
    }

    /**
     * The Excel template's sheets (for Spreadsheet::xlsx()): Timetable with the rows, How to fill this in,
     * and Lists (the rooms, course codes and blocks the dropdowns offer; other values can still be typed).
     */
    public static function workbook(array $rows, array $rooms, array $codes, array $blocks): array
    {
        $lists = [['Rooms', 'Course codes', 'Blocks']];
        $longest = max(count($rooms), count($codes), count($blocks));
        for ($i = 0; $i < $longest; $i++) {
            $lists[] = [$rooms[$i] ?? null, $codes[$i] ?? null, $blocks[$i] ?? null];
        }

        $dropdowns = [0 => '"'.implode(',', TimetableRules::DAYS).'"'];
        foreach ([3 => [$rooms, 'A'], 4 => [$codes, 'B'], 6 => [$blocks, 'C']] as $column => [$values, $letter]) {
            if ($values) {
                $dropdowns[$column] = 'Lists!$'.$letter.'$2:$'.$letter.'$'.(count($values) + 1);
            }
        }

        return [
            [
                'name' => 'Timetable',
                'rows' => self::sheetRows($rows),
                'header' => true,
                'widths' => [0 => 8, 1 => 11, 2 => 11, 3 => 11, 4 => 14, 5 => 36, 6 => 13, 7 => 18, 8 => 34],
                'styles' => [1 => 'time', 2 => 'time', 3 => 'text', 4 => 'text', 6 => 'text'],
                'lists' => $dropdowns,
            ],
            [
                'name' => 'How to fill this in',
                'rows' => array_map(fn ($line) => [$line], self::HELP),
                'title' => true,
                'widths' => [0 => 110],
                'styles' => [0 => 'wrap'],
            ],
            [
                'name' => 'Lists',
                'rows' => $lists,
                'header' => true,
                'widths' => [0 => 14, 1 => 16, 2 => 16],
            ],
        ];
    }

    /** The row with the column names (in the first 10 rows): [row number, [field => column]], or null */
    private static function header(array $rows): ?array
    {
        $checked = 0;
        foreach ($rows as $number => $cells) {
            if (++$checked > 10) {
                break;
            }
            $columns = [];
            foreach ($cells as $column => $value) {
                $name = preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $value));
                foreach (self::NAMES as $field => $names) {
                    if (! isset($columns[$field]) && in_array($name, $names, true)) {
                        $columns[$field] = $column;
                        break;
                    }
                }
            }
            if (isset($columns['day'], $columns['room'])) {
                return [$number, $columns];
            }
        }

        return null;
    }
}
