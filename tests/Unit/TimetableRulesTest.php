<?php

namespace Tests\Unit;

use App\Support\TimetableRules;
use PHPUnit\Framework\TestCase;

class TimetableRulesTest extends TestCase
{
    /** A tidy row, with $changes */
    private function row(array $changes = []): array
    {
        return $changes + [
            'id' => 1, 'line' => null, 'day' => 'Mon', 'start' => '10:30', 'end' => '13:30', 'room' => 'CEA300', 'type' => 'Class',
            'code' => 'CMPE 402', 'title' => 'Project Management', 'block' => 'BSCPE 4-1', 'instructor' => 'L. Reyes', 'note' => null, 'notes' => [],
        ];
    }

    public function test_days_are_read_the_ways_people_write_them(): void
    {
        $this->assertSame(['Mon'], TimetableRules::days('Mon'));
        $this->assertSame(['Mon'], TimetableRules::days(' monday '));
        $this->assertSame(['Sat'], TimetableRules::days('Sat.'));
        $this->assertSame(['Sun'], TimetableRules::days('SU'));
        $this->assertSame(['Tue', 'Thu'], TimetableRules::days('TTh'));
        $this->assertSame(['Tue', 'Thu'], TimetableRules::days('T-Th'));
        $this->assertSame(['Tue', 'Thu'], TimetableRules::days('Tue and Thu'));
        $this->assertSame(['Mon', 'Thu'], TimetableRules::days('Thu, Mon'));     // week order
        $this->assertSame(['Mon', 'Wed', 'Fri'], TimetableRules::days('MWF'));
        $this->assertSame(['Mon', 'Tue', 'Wed', 'Thu', 'Fri'], TimetableRules::days('MTWThF'));
        $this->assertSame(['Mon', 'Tue', 'Wed', 'Thu', 'Fri'], TimetableRules::days('Mon - Fri'));
        $this->assertNull(TimetableRules::days('Mnday'));
        $this->assertNull(TimetableRules::days(''));
        $this->assertNull(TimetableRules::days(null));
    }

    public function test_times_are_read_as_24_hour(): void
    {
        $this->assertSame('07:30', TimetableRules::time('7:30 AM'));
        $this->assertSame('07:30', TimetableRules::time('7:30am'));
        $this->assertSame('07:30', TimetableRules::time('7.30 a.m.'));
        $this->assertSame('13:30', TimetableRules::time('1:30 PM'));
        $this->assertSame('13:30', TimetableRules::time('13:30'));
        $this->assertSame('13:30', TimetableRules::time('13:30 PM'));
        $this->assertSame('07:30', TimetableRules::time('0730'));
        $this->assertSame('07:30', TimetableRules::time('730'));
        $this->assertSame('12:00', TimetableRules::time('12NN'));
        $this->assertSame('12:00', TimetableRules::time('12:00 noon'));
        $this->assertSame('00:30', TimetableRules::time('12:30 AM'));
        $this->assertNull(TimetableRules::time('25:00'));
        $this->assertNull(TimetableRules::time('7:75'));
        $this->assertNull(TimetableRules::time('13:30 AM'));
        $this->assertNull(TimetableRules::time('soon'));
        $this->assertNull(TimetableRules::time(''));
    }

    public function test_one_to_six_without_am_or_pm_means_pm(): void
    {
        $this->assertSame('13:30', TimetableRules::time('1:30'));
        $this->assertSame('18:00', TimetableRules::time('6:00'));
        $this->assertSame('07:00', TimetableRules::time('7'));
        $this->assertSame('12:00', TimetableRules::time('12'));
        $this->assertSame('01:30', TimetableRules::time('01:30'));        // a leading 0: 24-hour time
    }

    public function test_excel_times(): void
    {
        $this->assertSame('07:30', TimetableRules::time(0.3125, $guessed));
        $this->assertFalse($guessed);
        $this->assertSame('13:30', TimetableRules::time(0.5625));
        $this->assertSame('07:30', TimetableRules::time(46000.3125));      // a date and a time
        $this->assertSame('07:00', TimetableRules::time(7.0));
        $this->assertSame('13:30', TimetableRules::time(1330.0));

        // Excel turns a typed "1:30" into 1:30 AM: it's read as 1:30 PM, and that's noted
        $this->assertSame('13:30', TimetableRules::time(0.0625, $guessed));
        $this->assertTrue($guessed);
        $this->assertNull(TimetableRules::time(7.5));
    }

    public function test_both_times_in_one_cell(): void
    {
        $this->assertSame(['07:30', '10:30'], TimetableRules::range('7:30-10:30 AM'));
        $this->assertSame(['07:30', '10:30'], TimetableRules::range('7:30 AM – 10:30 AM'));
        $this->assertSame(['10:30', '13:30'], TimetableRules::range('10:30-1:30 PM'));
        $this->assertSame(['19:30', '21:00'], TimetableRules::range('7:30-9:00 PM'));
        $this->assertSame(['18:00', '21:00'], TimetableRules::range('6:00-9:00'));
        $this->assertSame(['16:30', '19:30'], TimetableRules::range('4:30-7:30'));
        $this->assertSame(['07:30', '09:00'], TimetableRules::range('7:30-9:00'));
        $this->assertSame(['13:00', '16:00'], TimetableRules::range('1:00 to 4:00'));
        $this->assertNull(TimetableRules::range('7:30 AM'));
        $this->assertNull(TimetableRules::range('later-sooner'));
        $this->assertNull(TimetableRules::range(0.3125));
    }

    public function test_a_typed_row_is_tidied(): void
    {
        $row = TimetableRules::row(['line' => 4, 'day' => 'monday', 'start' => '10:30', 'end' => '1:30', 'room' => '  CEA  300 ',
            'code' => 'cmpe402', 'title' => ' Project  Management ', 'block' => 'bscpe4-1', 'instructor' => 'L. Reyes', 'note' => '']);

        $this->assertSame(4, $row['line']);
        $this->assertSame('Mon', $row['day']);
        $this->assertSame(['10:30', '13:30'], [$row['start'], $row['end']]);
        $this->assertSame('CEA 300', $row['room']);
        $this->assertSame('Class', $row['type']);
        $this->assertSame('CMPE 402', $row['code']);
        $this->assertSame('Project Management', $row['title']);
        $this->assertSame('BSCPE 4-1', $row['block']);
        $this->assertNull($row['note']);
        $this->assertSame([], TimetableRules::problems($row + ['id' => 1]));
    }

    public function test_blocked_as_the_course_code_is_time_the_room_isnt_available(): void
    {
        $row = TimetableRules::row(['day' => 'Mon', 'start' => '7:30 AM', 'end' => '9:00 PM', 'room' => 'CEA302',
            'code' => 'blocked', 'block' => 'BSCPE 1-1', 'instructor' => 'X', 'note' => 'Used by ECE']);

        $this->assertSame('Blocked', $row['type']);
        $this->assertNull($row['code']);
        $this->assertNull($row['block']);
        $this->assertNull($row['instructor']);
        $this->assertSame('Used by ECE', $row['note']);
        $this->assertSame([], TimetableRules::problems($row));
        $this->assertSame('Blocked', TimetableRules::row(['type' => 'blocked', 'code' => null])['type']);
    }

    public function test_what_cant_be_read_stays_as_typed_and_is_a_problem(): void
    {
        $row = TimetableRules::row(['day' => 'Mnday', 'start' => 'morning', 'end' => '', 'room' => '', 'code' => '']);

        $this->assertSame('Mnday', $row['day']);
        $this->assertSame('morning', $row['start']);
        $problems = TimetableRules::problems($row);
        $this->assertCount(5, $problems);
        $this->assertStringContainsString('“Mnday” isn’t a day', $problems[0]);
        $this->assertStringContainsString('“morning” isn’t a time', $problems[1]);
        $this->assertSame('End time is empty.', $problems[2]);
        $this->assertSame('Room is empty.', $problems[3]);
        $this->assertStringContainsString('Course code is empty', $problems[4]);
    }

    public function test_an_excel_time_read_as_pm_is_noted(): void
    {
        $row = TimetableRules::row(['day' => 'Tue', 'start' => 0.0625, 'end' => 0.1875, 'room' => 'CEA300', 'code' => 'CMPE 402']);

        $this->assertSame(['13:30', '16:30'], [$row['start'], $row['end']]);
        $this->assertSame('Start time: the file says 1:30 AM; it was read as 1:30 PM.', $row['notes'][0]);
        $this->assertCount(2, $row['notes']);
    }

    public function test_an_end_before_the_start_without_am_or_pm_is_in_the_evening(): void
    {
        $typed = TimetableRules::row(['day' => 'Mon', 'start' => '6:00 PM', 'end' => '9:00', 'room' => 'CEA300', 'code' => 'CMPE 402']);
        $this->assertSame(['18:00', '21:00'], [$typed['start'], $typed['end']]);
        $this->assertSame(['End time: “9:00” was read as 9:00 PM, after the start.'], $typed['notes']);

        // Excel: "6:00" and "9:00" typed without PM are 6:00 AM and 9:00 AM
        $excel = TimetableRules::row(['day' => 'Mon', 'start' => 0.25, 'end' => 0.375, 'room' => 'CEA300', 'code' => 'CMPE 402']);
        $this->assertSame(['18:00', '21:00'], [$excel['start'], $excel['end']]);
        $this->assertSame('End time: the file says 9:00 AM; it was read as 9:00 PM, after the start.', $excel['notes'][1]);

        // Written with AM: kept, and it's a problem
        $am = TimetableRules::row(['day' => 'Mon', 'start' => '6:00 PM', 'end' => '9:00 AM', 'room' => 'CEA300', 'code' => 'CMPE 402']);
        $this->assertSame('09:00', $am['end']);
        $this->assertSame(['It ends (9:00 AM) before it starts (6:00 PM).'], TimetableRules::problems($am));
    }

    public function test_problems(): void
    {
        $this->assertSame([], TimetableRules::problems($this->row()));
        $this->assertSame(['It ends (10:30 AM) before it starts (1:30 PM).'], TimetableRules::problems($this->row(['start' => '13:30', 'end' => '10:30'])));
        $this->assertSame(['It starts and ends at 1:30 PM.'], TimetableRules::problems($this->row(['start' => '13:30'])));
        $this->assertSame(['Note is too long: keep it to 150 characters.'], TimetableRules::problems($this->row(['note' => str_repeat('x', 151)])));
        $this->assertSame([], TimetableRules::problems($this->row(['block' => null, 'title' => null, 'instructor' => null])));
    }

    public function test_the_same_room_is_found_however_its_written(): void
    {
        $this->assertSame('CEA300', TimetableRules::roomKey('CEA 300'));
        $this->assertSame('CEA300', TimetableRules::roomKey('cea-300'));
        $this->assertSame('BSCPE', TimetableRules::program('BSCPE 4-1'));
        $this->assertSame('GEED', TimetableRules::code('geed'));
    }

    public function test_comparing_with_the_timetable_in_use(): void
    {
        $old = [
            $this->row(['class_id' => 10]),
            $this->row(['class_id' => 11, 'day' => 'Tue']),
            $this->row(['class_id' => 12, 'day' => 'Wed']),
            $this->row(['class_id' => 13, 'day' => 'Wed']),        // the same class twice
        ];
        $new = [
            $this->row(['id' => 1]),                                                 // same
            $this->row(['id' => 2, 'day' => 'Tue', 'instructor' => 'J. Tena']),     // changed
            $this->row(['id' => 3, 'day' => 'Wed', 'title' => 'PROJECT MANAGEMENT']),  // same (title capitals only)
            $this->row(['id' => 4, 'day' => 'Thu']),                                 // added
        ];

        $compare = TimetableRules::compare($old, $new);

        $this->assertSame([[10, 1], [12, 3]], array_map(fn ($pair) => [$pair[0]['class_id'], $pair[1]['id']], $compare['same']));
        $this->assertSame([[11, 2]], array_map(fn ($pair) => [$pair[0]['class_id'], $pair[1]['id']], $compare['changed']));
        $this->assertSame([4], array_column($compare['added'], 'id'));
        $this->assertSame([13], array_column($compare['removed'], 'class_id'));
        $this->assertSame(['Instructor: L. Reyes → J. Tena'], TimetableRules::changes($old[1], $new[1]));

        // Another room or time is another class
        $moved = TimetableRules::compare([$this->row(['class_id' => 10])], [$this->row(['room' => 'CEA310'])]);
        $this->assertCount(1, $moved['added']);
        $this->assertCount(1, $moved['removed']);
        $this->assertSame([], TimetableRules::compare([], [])['removed']);
    }

    public function test_rows_typed_twice_are_saved_once(): void
    {
        [$once, $repeats] = TimetableRules::dedupe([$this->row(['id' => 1]), $this->row(['id' => 2]), $this->row(['id' => 3, 'instructor' => 'J. Tena'])]);

        $this->assertSame([1, 3], array_column($once, 'id'));
        $this->assertSame(2, $repeats[0][0]['id']);
        $this->assertSame(1, $repeats[0][1]['id']);
    }

    public function test_overlaps_are_worth_a_look(): void
    {
        $rows = [
            $this->row(['id' => 1]),
            $this->row(['id' => 2, 'start' => '12:00', 'end' => '15:00', 'code' => 'CMPE 310', 'block' => 'BSCPE 1-2P']),  // same room, at the same time
            $this->row(['id' => 3, 'start' => '13:30', 'end' => '16:30', 'code' => 'CMPE 403']),                            // right after 1: fine
            $this->row(['id' => 4, 'room' => 'CEA311', 'start' => '08:00', 'end' => '11:00', 'code' => 'CMPE 451']),        // BSCPE 4-1 in two rooms
        ];
        $warnings = TimetableRules::overlaps($rows);

        $this->assertCount(3, $warnings);
        $this->assertSame([1, 2], $warnings[0]['ids']);
        $this->assertStringContainsString('CEA300 on Monday: CMPE 402 · BSCPE 4-1 (10:30 AM–1:30 PM) and CMPE 310 · BSCPE 1-2P (12:00–3:00 PM)', $warnings[0]['text']);
        $this->assertSame([2, 3], $warnings[1]['ids']);
        $this->assertSame([4, 1], $warnings[2]['ids']);
        $this->assertStringContainsString('BSCPE 4-1 on Monday has two classes at once', $warnings[2]['text']);

        // Blocked time with a class in it; two blocked times don't matter
        $blocked = [
            $this->row(['id' => 5, 'type' => 'Blocked', 'code' => null, 'block' => null, 'start' => '07:30', 'end' => '21:00', 'note' => 'Used by ECE', 'line' => 9]),
            $this->row(['id' => 6, 'type' => 'Blocked', 'code' => null, 'block' => null, 'start' => '07:30', 'end' => '12:00']),
            $this->row(['id' => 7]),
        ];
        $warnings = TimetableRules::overlaps($blocked);
        $this->assertCount(2, $warnings);
        $this->assertSame('CEA300 on Monday: it’s not available 7:30 AM–12:00 PM, but CMPE 402 · BSCPE 4-1 is there 10:30 AM–1:30 PM.', $warnings[0]['text']);
        $this->assertSame('CEA300 on Monday: it’s not available 7:30 AM–9:00 PM (Used by ECE, row 9), but CMPE 402 · BSCPE 4-1 is there 10:30 AM–1:30 PM.', $warnings[1]['text']);
    }

    public function test_opening_hours(): void
    {
        $this->assertNull(TimetableRules::outsideHours($this->row(), 450, 1260));
        $this->assertStringContainsString('outside the opening hours (7:30 AM–9:00 PM)', (string) TimetableRules::outsideHours($this->row(['end' => '21:30']), 450, 1260));
        $this->assertNull(TimetableRules::outsideHours($this->row(['type' => 'Blocked', 'start' => '06:00']), 450, 1260));
    }

    public function test_things_at_the_same_time_share_the_width(): void
    {
        $lanes = TimetableRules::lanes([
            ['start' => 450, 'end' => 630],     // A
            ['start' => 600, 'end' => 720],     // B overlaps A
            ['start' => 630, 'end' => 700],     // C after A, during B: A's lane
            ['start' => 720, 'end' => 900],     // D alone
        ]);

        $this->assertSame([[0, 2], [1, 2], [0, 2], [0, 1]], $lanes);
        $this->assertSame([], TimetableRules::lanes([]));
    }

    public function test_sorted_by_day_room_and_time(): void
    {
        $sorted = TimetableRules::sort([
            $this->row(['id' => 1, 'day' => 'Tue']),
            $this->row(['id' => 2, 'day' => 'Mnday']),
            $this->row(['id' => 3, 'room' => 'CEA310', 'start' => '07:30']),
            $this->row(['id' => 4, 'start' => '07:30', 'end' => '10:30']),
        ]);

        $this->assertSame([4, 3, 1, 2], array_column($sorted, 'id'));
    }

    public function test_words_for_people(): void
    {
        $this->assertSame('10:30 AM–1:30 PM', TimetableRules::span($this->row()));
        $this->assertSame('CMPE 402 · BSCPE 4-1, Mon in CEA300 (row 7)', TimetableRules::describe($this->row(['line' => 7])));
        $this->assertSame('Not available', TimetableRules::what($this->row(['type' => 'Blocked'])));
        $this->assertSame('1:30 PM', TimetableRules::clock('13:30'));
        $this->assertSame('later', TimetableRules::clock('later'));
    }
}
