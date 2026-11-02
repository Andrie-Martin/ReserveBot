<?php

namespace Tests\Unit;

use App\Support\Spreadsheet;
use App\Support\SpreadsheetProblem;
use App\Support\TimetableFile;
use App\Support\TimetableRules;
use PHPUnit\Framework\TestCase;

class TimetableFileTest extends TestCase
{
    private function sheet(array $rows, string $name = 'Timetable'): array
    {
        return ['name' => $name, 'rows' => $rows];
    }

    public function test_the_template_reads_back_the_same(): void
    {
        $rows = [
            ['id' => 1, 'line' => null, 'day' => 'Mon', 'start' => '10:30', 'end' => '13:30', 'room' => 'CEA300', 'type' => 'Class', 'code' => 'CMPE 402',
                'title' => 'Project Management', 'block' => 'BSCPE 4-1', 'instructor' => 'L. Reyes', 'note' => null, 'notes' => []],
            ['id' => 2, 'line' => null, 'day' => 'Mon', 'start' => '07:30', 'end' => '21:00', 'room' => 'CEA302', 'type' => 'Blocked', 'code' => null,
                'title' => null, 'block' => null, 'instructor' => null, 'note' => 'Used by ECE', 'notes' => []],
        ];

        $sheet = TimetableFile::sheetRows($rows);
        $this->assertSame(TimetableFile::HEADERS, $sheet[0]);
        $this->assertSame(['Mon', ['time' => 630], ['time' => 810], 'CEA300', 'CMPE 402', 'Project Management', 'BSCPE 4-1', 'L. Reyes', null], $sheet[1]);
        $this->assertSame('BLOCKED', $sheet[2][4]);

        // As Excel would hand it back: numbered rows, times as parts of a day
        $cells = [];
        foreach ($sheet as $i => $row) {
            $cells[$i + 1] = array_filter(array_map(fn ($v) => is_array($v) ? $v['time'] / 1440 : $v, $row), fn ($v) => $v !== null);
        }
        $read = TimetableFile::rows([$this->sheet($cells)]);

        $this->assertSame('Timetable', $read['sheet']);
        $this->assertCount(2, $read['rows']);
        foreach (['day', 'start', 'end', 'room', 'type', 'code', 'title', 'block', 'instructor', 'note'] as $field) {
            $this->assertSame($rows[0][$field], $read['rows'][0][$field], $field);
            $this->assertSame($rows[1][$field], $read['rows'][1][$field], $field);
        }
        $this->assertSame(2, $read['rows'][0]['line']);
    }

    public function test_other_column_names_and_orders_work(): void
    {
        $read = TimetableFile::rows([$this->sheet([
            1 => [0 => 'ReserveBot timetable'],                         // a title above the column names
            3 => ['Room No.', 'Days', 'Time', 'Subject Code', 'Descriptive Title', 'Section', 'Faculty', 'Remarks'],
            4 => ['CEA311', 'TTh', '7:30-10:30 AM', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-7', 'R. Mahaguay'],
            5 => [1 => 'Fri', 2 => '1:00-4:00', 3 => 'CMPE 011', 5 => 'BSIE 1-2'],      // no room: the one above
        ])]);

        $rows = $read['rows'];
        $this->assertCount(3, $rows);
        $this->assertSame(['Tue', 'CEA311', '07:30', '10:30', 'CMPE 403', 'Computer Architecture', 'BSCPE 4-7', 'R. Mahaguay', 4],
            [$rows[0]['day'], $rows[0]['room'], $rows[0]['start'], $rows[0]['end'], $rows[0]['code'], $rows[0]['title'], $rows[0]['block'], $rows[0]['instructor'], $rows[0]['line']]);
        $this->assertSame('Thu', $rows[1]['day']);
        $this->assertSame(['Fri', 'CEA311', '13:00', '16:00', 'BSIE 1-2', 5], [$rows[2]['day'], $rows[2]['room'], $rows[2]['start'], $rows[2]['end'], $rows[2]['block'], $rows[2]['line']]);
    }

    public function test_merged_day_cells_and_empty_rows(): void
    {
        $read = TimetableFile::rows([$this->sheet([
            1 => TimetableFile::HEADERS,
            2 => ['Mon', '7:30 AM', '10:30 AM', 'CEA300', 'CMPE 402'],
            3 => [1 => '10:30 AM', 2 => '1:30 PM', 3 => 'CEA300', 4 => 'CMPE 403'],      // Day empty: Monday, like above
            5 => [1 => '1:30 PM', 2 => '4:30 PM', 3 => 'CEA310', 4 => 'CMPE 404'],       // after an empty row: no day
            6 => ['Day', 'Start', 'End', 'Room'],                                           // the column names again
        ])]);

        $this->assertSame(['Mon', 'Mon', null], array_column($read['rows'], 'day'));
        $this->assertSame(['Day is empty: write Mon, Tue, Wed, Thu, Fri, Sat or Sun.'], TimetableRules::problems($read['rows'][2]));
    }

    public function test_code_and_title_in_one_cell(): void
    {
        $read = TimetableFile::rows([$this->sheet([
            1 => ['Day', 'Start', 'End', 'Room', 'Course'],
            2 => ['Wed', '15:00', '18:00', 'CEA300', 'CMPE 402 - Project Management'],
        ])]);

        $this->assertSame(['CMPE 402', 'Project Management'], [$read['rows'][0]['code'], $read['rows'][0]['title']]);
    }

    public function test_the_timetable_sheet_is_used_first(): void
    {
        $read = TimetableFile::rows([
            $this->sheet([1 => ['Day', 'Room', 'Start', 'End', 'Course code'], 2 => ['Mon', 'X', '8:00', '9:00', 'A 1']], 'Old'),
            $this->sheet([1 => ['Day', 'Room', 'Start', 'End', 'Course code'], 2 => ['Tue', 'Y', '8:00', '9:00', 'B 2']], 'timetable'),
        ]);

        $this->assertSame('timetable', $read['sheet']);
        $this->assertSame('Y', $read['rows'][0]['room']);
    }

    public function test_files_that_arent_a_timetable(): void
    {
        $problem = function (array $rows): string {
            try {
                TimetableFile::rows([$this->sheet($rows)]);
            } catch (SpreadsheetProblem $e) {
                return $e->getMessage();
            }

            return '';
        };

        $this->assertStringContainsString('column names (Day, Start, End, Room, Course code, …) aren’t in the first rows', $problem([1 => ['Name', 'Grade']]));
        $this->assertStringContainsString('has no Course code column', $problem([1 => ['Day', 'Start', 'End', 'Room']]));
        $this->assertStringContainsString('has no Start or End column', $problem([1 => ['Day', 'Room', 'Course code']]));
        $this->assertStringContainsString('no classes under them', $problem([1 => TimetableFile::HEADERS]));
    }

    public function test_csv_both_ways(): void
    {
        $csv = Spreadsheet::csv([TimetableFile::HEADERS, ['Mon', ['time' => 630], ['time' => 810], 'CEA300', 'CMPE 402', 'Peñafrancia, the “room”', null, '=HYPERLINK("x")']]);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Mon,"10:30 AM","1:30 PM",CEA300', $csv);
        $this->assertStringContainsString("\"'=HYPERLINK(\"\"x\"\")\"", $csv);    // Excel shows it as text, never runs it

        $rows = Spreadsheet::readCsv($csv);
        $this->assertSame('Peñafrancia, the “room”', $rows[2][5]);
        $this->assertSame('=HYPERLINK("x")', $rows[2][7]);
        $read = TimetableFile::rows([$this->sheet($rows, 'CSV')])['rows'][0];
        $this->assertSame(['Mon', '10:30', '13:30'], [$read['day'], $read['start'], $read['end']]);
    }

    public function test_csv_from_excel_in_other_settings(): void
    {
        // Semicolons, and Excel's older Windows encoding (ñ is one byte)
        $rows = Spreadsheet::readCsv("Day;Start;End;Room;Course code;Instructor\r\nMon;7:30;10:30;CEA300;CMPE 402;Pe\xF1a\r\n");

        $this->assertSame('Peña', $rows[2][5]);
        $this->assertSame('CEA300', $rows[2][3]);
    }

    public function test_xlsx_both_ways(): void
    {
        if (! Spreadsheet::canXlsx()) {
            $this->markTestSkipped('PHP’s zip extension is off');
        }
        $rows = [
            ['id' => 1, 'line' => null, 'day' => 'Thu', 'start' => '18:00', 'end' => '21:00', 'room' => 'CEA313', 'type' => 'Class', 'code' => 'CMPE 304',
                'title' => 'Logic Circuits & Design <Lab>', 'block' => 'BSCPE 3-3', 'instructor' => 'J. Cansiño', 'note' => null, 'notes' => []],
        ];
        $path = Spreadsheet::xlsx(TimetableFile::workbook($rows, ['CEA313'], ['CMPE 304'], ['BSCPE 3-3']));

        try {
            $sheets = Spreadsheet::read($path, 'xlsx');
        } finally {
            unlink($path);
        }

        $this->assertSame(['Timetable', 'How to fill this in', 'Lists'], array_column($sheets, 'name'));
        $this->assertSame(0.75, $sheets[0]['rows'][2][1]);
        $read = TimetableFile::rows($sheets)['rows'][0];
        $this->assertSame(['Thu', '18:00', '21:00', 'CEA313', 'CMPE 304', 'Logic Circuits & Design <Lab>', 'BSCPE 3-3', 'J. Cansiño'],
            [$read['day'], $read['start'], $read['end'], $read['room'], $read['code'], $read['title'], $read['block'], $read['instructor']]);
    }

    public function test_a_file_that_isnt_an_excel_workbook(): void
    {
        if (! Spreadsheet::canXlsx()) {
            $this->markTestSkipped('PHP’s zip extension is off');
        }
        $path = tempnam(sys_get_temp_dir(), 'tt');
        file_put_contents($path, 'not a zip');

        try {
            Spreadsheet::read($path, 'xlsx');
            $this->fail('No problem reported');
        } catch (SpreadsheetProblem $e) {
            $this->assertStringContainsString('couldn’t be opened as an Excel workbook', $e->getMessage());
        } finally {
            unlink($path);
        }
    }

    public function test_column_letters(): void
    {
        $this->assertSame(0, Spreadsheet::columnIndex('A1'));
        $this->assertSame(27, Spreadsheet::columnIndex('AB12'));
        $this->assertSame('A', Spreadsheet::columnName(0));
        $this->assertSame('Z', Spreadsheet::columnName(25));
        $this->assertSame('AA', Spreadsheet::columnName(26));
    }
}
