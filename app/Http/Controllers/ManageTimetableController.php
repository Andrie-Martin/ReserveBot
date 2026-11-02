<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DepartmentConfig;
use App\Models\TimetableBackup;
use App\Models\TimetableDraft;
use App\Services\Timetables;
use App\Support\Spreadsheet;
use App\Support\SpreadsheetProblem;
use App\Support\TimetableFile;
use App\Support\TimetableRules;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 11: Manage → Timetable (lab staff and admins). A new semester's timetable, or changes during
 * one: upload an Excel or CSV file, or change a copy of the timetable on the site; check it; then it
 * replaces the timetable right away, and the one in use is kept as a backup (App\Services\Timetables).
 */
class ManageTimetableController extends Controller
{
    private const ROW_MESSAGES = [
        'day.*' => 'Pick a day.',
        'start.required' => 'Pick the start time.',
        'end.required' => 'Pick the end time.',
        'room.required' => 'Type the room, e.g. CEA300.',
        'room.max' => 'Keep the room under 30 characters.',
        'type.*' => 'Pick whether it’s a class or time the room isn’t available.',
        'code.required_if' => 'Type the course code, e.g. CMPE 402.',
        'code.max' => 'Keep the course code under 20 characters.',
        'title.max' => 'Keep the course title under 100 characters.',
        'block.max' => 'Keep the block under 30 characters.',
        'instructor.max' => 'Keep the instructor under 60 characters.',
        'note.max' => 'Keep the note under 150 characters.',
    ];

    public function __construct(private Timetables $timetables) {}

    /** The timetable in use, the new one in progress, ways to start one, and the backups */
    public function index(Request $request): View
    {
        $department = $request->user()->department;
        $classes = $this->timetables->classes($department);
        $draft = $this->timetables->draft($department);

        return view('manage.timetable', [
            'department' => $department,
            'term' => $this->timetables->term($department, $classes),
            'counts' => [
                'classes' => $classes->where('entry_type', 'Class')->count(),
                'blocked' => $classes->where('entry_type', 'Blocked')->count(),
                'rooms' => $classes->pluck('room_id')->unique()->count(),
                'blocks' => $classes->pluck('section_id')->filter()->unique()->count(),
            ],
            'draft' => $draft,
            'draftProblems' => $draft ? count(array_filter($draft->rows, fn ($row) => TimetableRules::problems($row))) : 0,
            'backups' => TimetableBackup::with('creator')
                ->select(['id', 'department_id', 'term_name', 'term_start', 'term_end', 'class_count', 'blocked_count', 'reason', 'created_by', 'created_at'])
                ->where('department_id', $department->id)->latest('id')->get(),
            'clashes' => $this->timetables->clashesNow($department, $classes),
            'canXlsx' => Spreadsheet::canXlsx(),
        ]);
    }

    /** The timetable in use, by day, or one room's or block's week (read only) */
    public function view(Request $request): View
    {
        $department = $request->user()->department;
        $classes = $this->timetables->classes($department);

        return view('manage.timetable_rows', $this->rowsPage($request, $department, $this->timetables->rows($classes), []) + [
            'draft' => null,
            'term' => $this->timetables->term($department, $classes),
        ]);
    }

    /** The new timetable on the site: a day's list, or a room's or block's week, with add, change and delete */
    public function draft(Request $request): View|RedirectResponse
    {
        $department = $request->user()->department;
        $draft = $this->timetables->draft($department);
        if (! $draft) {
            return redirect()->route('manage.timetable')->with('status', 'There’s no new timetable in progress. Start one below.');
        }

        $problems = [];
        foreach ($draft->rows as $row) {
            if ($found = TimetableRules::problems($row)) {
                $problems[$row['id']] = $found;
            }
        }

        return view('manage.timetable_rows', $this->rowsPage($request, $department, $draft->rows, $problems) + [
            'draft' => $draft,
            'term' => null,
        ]);
    }

    /** Start changing the timetable on the site, from a copy of the one in use */
    public function start(Request $request): RedirectResponse
    {
        $department = $request->user()->department;
        if ($this->timetables->draft($department)) {
            return redirect()->route('manage.timetable.draft')->with('status', 'Here’s the new timetable in progress. To start again from the timetable in use, discard this one first.');
        }

        $rows = $this->timetables->rows($this->timetables->classes($department));
        $this->timetables->startDraft($department, $request->user(), $rows, 'site', null, $this->timetables->semesterFor($department));

        return redirect()->route('manage.timetable.draft')->with('status', 'This is a copy of the timetable in use. Change it here: nobody sees the changes until you check it and press Replace.');
    }

    /** An uploaded Excel or CSV file becomes the new timetable in progress */
    public function upload(Request $request): RedirectResponse
    {
        $request->validateWithBag('upload', ['file' => ['required', 'file', 'max:5120']], [
            'file.required' => 'Choose the file first.',
            'file.file' => 'The file didn’t arrive. Try again.',
            'file.uploaded' => 'The file didn’t arrive. Is it bigger than 5 MB?',
            'file.max' => 'That file is bigger than 5 MB. A timetable is much smaller: is it the right file?',
        ]);

        $file = $request->file('file');
        $name = mb_substr(basename($file->getClientOriginalName()), 0, 150);
        $extension = strtolower($file->getClientOriginalExtension());
        $problem = match (true) {
            $extension === 'xls' => 'That’s an old Excel file (.xls). In Excel, choose File → Save As → Excel Workbook (.xlsx), then upload that.',
            in_array($extension, ['ods', 'numbers', 'xlsm', 'xlsb'], true) => 'Save it as an Excel Workbook (.xlsx) or as CSV first, then upload that.',
            ! in_array($extension, ['xlsx', 'csv', 'txt'], true) => 'Upload an Excel file (.xlsx) or a CSV file (.csv).',
            default => null,
        };
        $parsed = null;
        if (! $problem) {
            try {
                $parsed = TimetableFile::rows(Spreadsheet::read($file->getRealPath(), $extension));
            } catch (SpreadsheetProblem $e) {
                $problem = $e->getMessage();
            }
        }
        if ($problem) {
            return back()->withErrors(['file' => $problem], 'upload');
        }

        $department = $request->user()->department;
        $semester = $this->timetables->draft($department)?->semester() ?? $this->timetables->semesterFor($department);
        $this->timetables->startDraft($department, $request->user(), $parsed['rows'], 'upload', $name, $semester);

        $count = count($parsed['rows']);
        $sheet = in_array($parsed['sheet'], ['Timetable', 'CSV'], true) ? '' : ' (sheet “'.$parsed['sheet'].'”)';

        return redirect()->route('manage.timetable.check')
            ->with('status', 'Read '.$count.' '.($count === 1 ? 'row' : 'rows').' from “'.$name.'”'.$sheet.'. Nothing has changed yet: check it below.');
    }

    /** Download the timetable in use (the Excel template) */
    public function download(Request $request): Response
    {
        $department = $request->user()->department;
        $classes = $this->timetables->classes($department);
        $term = $this->timetables->term($department, $classes);

        return $this->file($request, $department, $this->timetables->rows($classes), 'timetable '.($term?->name ?? $department->slug));
    }

    /** Download the new timetable in progress (to carry on in Excel) */
    public function downloadDraft(Request $request): Response|RedirectResponse
    {
        $department = $request->user()->department;
        $draft = $this->timetables->draft($department);
        if (! $draft) {
            return redirect()->route('manage.timetable');
        }

        return $this->file($request, $department, $draft->rows, 'new timetable '.($draft->term_name ?? $department->slug));
    }

    /** Download a backup */
    public function downloadBackup(Request $request, TimetableBackup $backup): Response
    {
        $department = $request->user()->department;
        abort_unless($backup->department_id === $department->id, 404);

        return $this->file($request, $department, Timetables::backupRows($backup), 'timetable backup '.$backup->created_at->format('Y-m-d Hi'));
    }

    /** Throw away the new timetable in progress */
    public function discard(Request $request): RedirectResponse
    {
        TimetableDraft::where('department_id', $request->user()->department_id)->delete();

        return redirect()->route('manage.timetable')->with('status', 'Discarded the new timetable. The timetable in use didn’t change.');
    }

    /** A backup becomes the new timetable in progress, to check and bring back */
    public function restore(Request $request, TimetableBackup $backup): RedirectResponse
    {
        $department = $request->user()->department;
        abort_unless($backup->department_id === $department->id, 404);

        $semester = $backup->term_name ? $backup->semester() : $this->timetables->semesterFor($department);
        $this->timetables->startDraft($department, $request->user(), Timetables::backupRows($backup), 'backup', $backup->label(), $semester);

        return redirect()->route('manage.timetable.check')
            ->with('status', 'This is the backup of '.$backup->label().'. Nothing has changed yet: check it, then press Replace to bring it back.');
    }

    /** Everything to know before the new timetable replaces the one in use */
    public function check(Request $request): View|RedirectResponse
    {
        $department = $request->user()->department;
        $draft = $this->timetables->draft($department);
        if (! $draft) {
            return redirect()->route('manage.timetable')->with('status', 'There’s no new timetable to check. Start one below.');
        }

        return view('manage.timetable_check', [
            'draft' => $draft,
            'check' => $this->timetables->check($department, $draft->rows, $draft->semester()),
            'term' => $this->timetables->term($department),
        ]);
    }

    /** Save the semester's name and dates, and check again */
    public function semester(Request $request): RedirectResponse
    {
        $semester = $this->semesterInput($request);
        $this->timetables->setSemester($request->user()->department, $request->user(), $semester);

        return redirect()->route('manage.timetable.check')->with('status', 'Saved: the new timetable is for '.$semester['name'].'.');
    }

    /** Replace the timetable in use with the new one, right away */
    public function apply(Request $request): RedirectResponse
    {
        $semester = $this->semesterInput($request);
        $department = $request->user()->department;
        [$ok, $message] = $this->timetables->apply($department, $request->user(), (int) $request->input('version'), $semester);

        if ($ok || ! $this->timetables->draft($department)) {
            return redirect()->route('manage.timetable')->with($ok ? 'status' : 'problem', $message);
        }

        return redirect()->route('manage.timetable.check')->withInput()->with('problem', $message);
    }

    /** Add a class (or blocked time) to the new timetable */
    public function addRow(Request $request): RedirectResponse
    {
        $row = $this->rowInput($request);
        $draft = $this->timetables->editDraft($request->user()->department, $request->user(), function (array $rows, int $next) use ($row) {
            $rows[] = ['id' => $next] + $row;

            return [$rows, $next + 1];
        });
        if (! $draft) {
            return redirect()->route('manage.timetable');
        }

        return redirect()->route('manage.timetable.draft', ['day' => $row['day']])
            ->with('status', 'Added '.TimetableRules::what($row).', '.$row['day'].' '.TimetableRules::span($row).' in '.$row['room'].'.');
    }

    /** One row's form */
    public function editRow(Request $request, int $row): View|RedirectResponse
    {
        $department = $request->user()->department;
        $draft = $this->timetables->draft($department);
        $found = $draft?->row($row);
        if (! $found) {
            return redirect()->route($draft ? 'manage.timetable.draft' : 'manage.timetable')->with('status', 'That row isn’t in the new timetable anymore.');
        }

        return view('manage.timetable_row', [
            'draft' => $draft,
            'row' => $found,
            'problems' => TimetableRules::problems($found),
            'back' => route('manage.timetable.draft', $request->query('back') === 'fix' ? ['day' => 'fix'] : ['day' => in_array($found['day'], TimetableRules::DAYS, true) ? $found['day'] : 'fix']),
        ] + $this->suggestions($department, $draft->rows));
    }

    public function updateRow(Request $request, int $row): RedirectResponse
    {
        $new = $this->rowInput($request);
        $found = false;
        $this->timetables->editDraft($request->user()->department, $request->user(), function (array $rows, int $next) use ($row, $new, &$found) {
            foreach ($rows as $i => $old) {
                if ($old['id'] === $row) {
                    $rows[$i] = ['id' => $row] + $new;
                    $found = true;
                }
            }

            return $found ? [$rows, $next] : null;
        });

        return redirect()->route('manage.timetable.draft', ['day' => $new['day']])
            ->with('status', $found ? 'Saved '.TimetableRules::what($new).', '.$new['day'].' '.TimetableRules::span($new).' in '.$new['room'].'.' : 'That row isn’t in the new timetable anymore.');
    }

    public function deleteRow(Request $request, int $row): RedirectResponse
    {
        $deleted = null;
        $this->timetables->editDraft($request->user()->department, $request->user(), function (array $rows, int $next) use ($row, &$deleted) {
            foreach ($rows as $i => $old) {
                if ($old['id'] === $row) {
                    $deleted = $old;
                    unset($rows[$i]);

                    return [$rows, $next];
                }
            }

            return null;
        });

        $back = redirect()->to($this->backTo($request));
        if (! $deleted) {
            return $back->with('status', 'That row was deleted already.');
        }

        return $back->with('status', 'Deleted '.TimetableRules::what($deleted).', '.$deleted['day'].' '.TimetableRules::span($deleted).' in '.$deleted['room'].'.')
            ->with('undo', Crypt::encryptString(json_encode($deleted)));
    }

    /** Put back the row just deleted */
    public function undo(Request $request): RedirectResponse
    {
        try {
            $row = json_decode(Crypt::decryptString((string) $request->input('row')), true);
        } catch (DecryptException) {
            $row = null;
        }
        abort_unless(is_array($row) && isset($row['day'], $row['type']), 404);

        $this->timetables->editDraft($request->user()->department, $request->user(), function (array $rows, int $next) use ($row) {
            $taken = in_array($row['id'] ?? null, array_column($rows, 'id'), true);
            $rows[] = ['id' => $taken || ! is_int($row['id'] ?? null) ? $next : $row['id']] + $row;

            return [$rows, max($next, max(array_column($rows, 'id')) + 1)];
        });

        return redirect()->to($this->backTo($request))->with('status', 'Put back '.TimetableRules::what($row).', '.$row['day'].' '.TimetableRules::span($row).'.');
    }

    // ---- Helpers ----

    /** Where to go after deleting or putting back a row: the list it was on ('back'), if it's one of ours */
    private function backTo(Request $request): string
    {
        $back = (string) $request->input('back');
        $draftPage = route('manage.timetable.draft');

        return str_starts_with($back, $draftPage) && ! str_contains($back, '/rows/') ? $back : $draftPage;
    }

    /** The data of the day / week page, for the timetable in use or the new one */
    private function rowsPage(Request $request, Department $department, array $rows, array $problems): array
    {
        $config = DepartmentConfig::forDepartment($department);
        $valid = array_filter($rows, fn ($row) => ! isset($problems[$row['id']]));
        $warnings = TimetableRules::overlaps(array_values($valid));
        $warned = [];
        foreach ($warnings as $warning) {
            foreach (array_filter($warning['ids']) as $id) {
                $warned[$id][] = $warning['text'];
            }
        }

        $counts = array_fill_keys(TimetableRules::DAYS, 0);
        foreach ($rows as $row) {
            if (isset($counts[$row['day']])) {
                $counts[$row['day']]++;
            }
        }

        // What to show: ?day=Mon (a day's list), ?day=fix (rows to fix), or ?week=room:CEA300 / block:BSCPE 4-1
        $week = (string) $request->query('week', '');
        $day = (string) $request->query('day', '');
        if (! in_array($day, TimetableRules::DAYS, true) && ! ($day === 'fix' && $problems)) {
            $day = $problems && $week === '' ? 'fix' : (array_key_first(array_filter($counts)) ?? 'Mon');
        }

        $rooms = collect($rows)->pluck('room')->filter()->unique(fn ($name) => TimetableRules::roomKey($name))->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
        $blocks = collect($rows)->where('type', 'Class')->pluck('block')->filter()->unique(fn ($name) => mb_strtoupper($name))->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
        $grid = null;
        if (preg_match('/^(room|block):(.+)$/', $week, $m)) {
            $match = $m[1] === 'room'
                ? fn ($row) => TimetableRules::roomKey($row['room']) === TimetableRules::roomKey($m[2])
                : fn ($row) => $row['type'] === 'Class' && mb_strtoupper((string) $row['block']) === mb_strtoupper($m[2]);
            $grid = $this->week(array_values(array_filter($valid, $match)), $config) + ['kind' => $m[1], 'name' => $m[2]];
        }

        $list = $day === 'fix'
            ? array_filter($rows, fn ($row) => isset($problems[$row['id']]))
            : array_filter($rows, fn ($row) => $row['day'] === $day);

        return [
            'rows' => $rows,
            'list' => TimetableRules::sort($list),
            'problems' => $problems,
            'warned' => $warned,
            'warningCount' => count($warnings),
            'counts' => $counts,
            'openDays' => $config->openDays(),
            'day' => $day,
            'grid' => $grid,
            'week' => $week,
            'rooms' => $rooms,
            'blocks' => $blocks,
            'undo' => session('undo'),
        ] + $this->suggestions($department, $rows);
    }

    /**
     * One room's or block's week as a grid: the days, the hours, and each class's place
     * (top and height: % of the day's height; left and width: % of the day's width).
     */
    private function week(array $rows, DepartmentConfig $config): array
    {
        $from = $config->opensAt();
        $to = $config->closesAt();
        foreach ($rows as $row) {
            $from = min($from, TimetableRules::minutes($row['start']));
            $to = max($to, TimetableRules::minutes($row['end']));
        }
        $from = intdiv($from, 60) * 60;
        $to = (int) ceil($to / 60) * 60;
        $length = max(60, $to - $from);

        $days = [];
        foreach (TimetableRules::DAYS as $day) {
            $items = array_values(array_filter($rows, fn ($row) => $row['day'] === $day));
            if (! $items && ! in_array($day, $config->openDays(), true)) {
                continue;
            }
            usort($items, fn ($a, $b) => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);
            $places = TimetableRules::lanes(array_map(fn ($row) => ['start' => TimetableRules::minutes($row['start']), 'end' => TimetableRules::minutes($row['end'])], $items));
            $days[$day] = array_map(fn ($row, $i) => [
                'row' => $row,
                'top' => round((TimetableRules::minutes($row['start']) - $from) / $length * 100, 3),
                'height' => round((TimetableRules::minutes($row['end']) - TimetableRules::minutes($row['start'])) / $length * 100, 3),
                'left' => round($places[$i][0] / $places[$i][1] * 100, 3),
                'width' => round(100 / $places[$i][1], 3),
                'overlap' => $places[$i][1] > 1,
            ], $items, array_keys($items));
        }

        return ['days' => $days, 'from' => $from, 'hours' => range($from, $to - 60, 60), 'length' => $length, 'count' => count($rows)];
    }

    /** Values the forms suggest as you type: rooms, course codes (with titles) and blocks */
    private function suggestions(Department $department, array $rows): array
    {
        $rooms = $department->rooms()->pluck('name')->merge(array_column($rows, 'room'))->filter()
            ->unique(fn ($name) => TimetableRules::roomKey($name))->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
        $classes = array_filter($rows, fn ($row) => $row['type'] === 'Class' && $row['code']);
        $titles = [];
        foreach ($classes as $row) {
            if ($row['title'] && ! isset($titles[mb_strtoupper($row['code'])])) {
                $titles[mb_strtoupper($row['code'])] = $row['title'];
            }
        }
        ksort($titles, SORT_NATURAL);

        return [
            'roomNames' => $rooms->all(),
            'codes' => collect($classes)->pluck('code')->unique()->sort(SORT_NATURAL)->values()->all(),
            'titles' => $titles,
            'blockNames' => collect($classes)->pluck('block')->filter()->unique()->sort(SORT_NATURAL)->values()->all(),
        ];
    }

    /** An Excel file of $rows (or CSV when asked, or when this computer's PHP can't make Excel files) */
    private function file(Request $request, Department $department, array $rows, string $name): Response
    {
        $name = Str::slug($name);
        if ($request->query('format') === 'csv' || ! Spreadsheet::canXlsx()) {
            $csv = Spreadsheet::csv(TimetableFile::sheetRows($rows));

            return response()->streamDownload(function () use ($csv) {
                echo $csv;
            }, $name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $lists = $this->suggestions($department, $rows);
        $path = Spreadsheet::xlsx(TimetableFile::workbook($rows, $lists['roomNames'], $lists['codes'], $lists['blockNames']));

        return response()->download($path, $name.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->deleteFileAfterSend();
    }

    /** The add / change form -> a row (App\Support\TimetableRules) */
    private function rowInput(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'day' => ['required', Rule::in(TimetableRules::DAYS)],
            'start' => ['required', 'string', 'max:20'],
            'end' => ['required', 'string', 'max:20'],
            'room' => ['required', 'string', 'max:30'],
            'type' => ['required', Rule::in(['Class', 'Blocked'])],
            'code' => ['nullable', 'string', 'max:20', 'required_if:type,Class'],
            'title' => ['nullable', 'string', 'max:100'],
            'block' => ['nullable', 'string', 'max:30'],
            'instructor' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:150'],
        ], self::ROW_MESSAGES);

        $times = [];
        $validator->after(function ($validator) use ($request, &$times) {
            foreach (['start' => 'start', 'end' => 'end'] as $field) {
                if ($validator->errors()->has($field)) {
                    continue;
                }
                $value = trim((string) $request->input($field));
                // The time picker sends 24-hour 'HH:MM'; typed times ('1:30 PM') are read like the Excel file's
                $times[$field] = preg_match('/^\d{2}:\d{2}$/', $value) ? (TimetableRules::isTime($value) ? $value : null) : TimetableRules::time($value);
                if ($times[$field] === null) {
                    $validator->errors()->add($field, 'Write the '.$field.' time like 7:30 AM or 13:30.');
                }
            }
            if (($times['start'] ?? null) && ($times['end'] ?? null) && $times['end'] <= $times['start']) {
                $validator->errors()->add('end', 'The end time must be after the start time.');
            }
        });
        $validator->validateWithBag('row');

        $row = TimetableRules::row($request->only(['day', 'room', 'type', 'code', 'title', 'block', 'instructor', 'note']) + ['line' => null]);

        return ['start' => $times['start'], 'end' => $times['end']] + $row;
    }

    /** The semester fields -> ['name' => ..., 'start' => ..., 'end' => ...] */
    private function semesterInput(Request $request): array
    {
        $data = $request->validateWithBag('semester', [
            'term_name' => ['required', 'string', 'max:60'],
            'term_start' => ['required', 'date_format:Y-m-d'],
            'term_end' => ['required', 'date_format:Y-m-d', 'after:term_start'],
        ], [
            'term_name.required' => 'Name the semester, e.g. 2nd Semester AY 2026-2027.',
            'term_name.max' => 'Keep the semester’s name under 60 characters.',
            'term_start.*' => 'Pick the semester’s first day.',
            'term_end.required' => 'Pick the semester’s last day.',
            'term_end.date_format' => 'Pick the semester’s last day.',
            'term_end.after' => 'The last day must be after the first day.',
        ]);

        return ['name' => TimetableRules::clean($data['term_name']), 'start' => $data['term_start'], 'end' => $data['term_end']];
    }
}
