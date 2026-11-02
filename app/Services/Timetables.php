<?php

namespace App\Services;

use App\Models\ClassSchedule;
use App\Models\Course;
use App\Models\Department;
use App\Models\DepartmentConfig;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Section;
use App\Models\Term;
use App\Models\TimetableBackup;
use App\Models\TimetableDraft;
use App\Models\User;
use App\Support\ClassCheckRules;
use App\Support\TimeSlots;
use App\Support\TimetableRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11: replacing a department's timetable, for a new semester or a change during one.
 *
 * Staff make the new timetable as a draft (timetable_drafts): from an uploaded Excel or CSV file
 * (App\Support\TimetableFile), or on the site, starting from a copy of the timetable in use.
 * check() compares it with the timetable in use and lists what to fix or look at; apply() replaces
 * the timetable right away, after saving the one in use as a backup (timetable_backups) that can be
 * brought back the same way.
 *
 * A class that stays the same keeps its class_schedules row, so its face-to-face answers, reminders
 * and class-session bookings stay with it. A removed class is deleted; its class-session bookings
 * stay as plain bookings.
 */
class Timetables
{
    /** What a backup keeps of each row */
    private const BACKUP_FIELDS = ['day', 'start', 'end', 'room', 'type', 'code', 'title', 'block', 'instructor', 'note'];

    public function __construct(private ClassChecks $checks) {}

    /** The department's timetable in use */
    public function classes(Department $department): Collection
    {
        return ClassSchedule::with(['course', 'room', 'section', 'term'])->where('department_id', $department->id)->get();
    }

    /** The timetable in use as rows (App\Support\TimetableRules), by day, room and time. Each has class_id and its semester's dates. */
    public function rows(Collection $classes): array
    {
        return TimetableRules::sort($classes->map(fn (ClassSchedule $class) => [
            'id' => $class->id,
            'class_id' => $class->id,
            'line' => null,
            'day' => $class->day_of_week,
            'start' => substr($class->start_time, 0, 5),
            'end' => substr($class->end_time, 0, 5),
            'room' => $class->room->name,
            'type' => $class->entry_type,
            'code' => $class->entry_type === 'Class' ? $class->course?->code : null,
            'title' => $class->entry_type === 'Class' ? $class->course?->title : null,
            'block' => $class->section?->name,
            'instructor' => $class->instructor,
            'note' => $class->note,
            'notes' => [],
            'term_id' => $class->term_id,
            'from' => $class->term->start_date->toDateString(),
            'until' => $class->term->end_date->toDateString(),
        ])->all());
    }

    /** The semester of the timetable in use: the newest one its classes use, else today's, else the newest */
    public function term(Department $department, ?Collection $classes = null): ?Term
    {
        $classes ??= $this->classes($department);

        return $classes->pluck('term')->filter()->sortByDesc(fn (Term $term) => $term->start_date)->first()
            ?? Term::current()->first()
            ?? Term::orderByDesc('start_date')->first();
    }

    /** ['name' => ..., 'start' => 'Y-m-d', 'end' => 'Y-m-d'] of the timetable in use */
    public function semesterFor(Department $department): array
    {
        $term = $this->term($department);

        return ['name' => $term?->name, 'start' => $term?->start_date?->toDateString(), 'end' => $term?->end_date?->toDateString()];
    }

    public function draft(Department $department): ?TimetableDraft
    {
        return TimetableDraft::with(['starter', 'updater'])->where('department_id', $department->id)->first();
    }

    /**
     * Starts a new timetable (in place of any the department had in progress).
     * $source: 'site' (a copy of the timetable in use), 'upload' ($name: the file) or 'backup' ($name: which one).
     */
    public function startDraft(Department $department, User $by, array $rows, string $source, ?string $name, array $semester): TimetableDraft
    {
        $id = 0;
        $rows = array_map(function (array $row) use (&$id) {
            return ['id' => ++$id] + Arr::except($row, ['id', 'class_id', 'term_id', 'from', 'until']) + ['line' => null, 'notes' => []];
        }, array_values($rows));

        return DB::transaction(function () use ($department, $by, $rows, $id, $source, $name, $semester) {
            $draft = TimetableDraft::where('department_id', $department->id)->lockForUpdate()->first() ?? new TimetableDraft(['department_id' => $department->id, 'version' => 0]);
            $draft->fill([
                'rows' => $rows,
                'next_id' => $id + 1,
                'source' => $source,
                'source_name' => $name !== null ? mb_substr($name, 0, 150) : null,
                'edited' => false,
                'term_name' => $semester['name'] ?? null,
                'term_start' => $semester['start'] ?? null,
                'term_end' => $semester['end'] ?? null,
                'version' => $draft->version + 1,
                'started_by' => $by->id,
                'updated_by' => $by->id,
            ]);
            $draft->created_at = now();     // a new start, even when it takes the place of one in progress
            $draft->save();

            return $draft;
        });
    }

    /**
     * Changes the department's new timetable: $change(rows, next id) returns [rows, next id], or null to
     * change nothing. Returns the draft, or null when there's none.
     */
    public function editDraft(Department $department, User $by, callable $change): ?TimetableDraft
    {
        return DB::transaction(function () use ($department, $by, $change) {
            $draft = TimetableDraft::where('department_id', $department->id)->lockForUpdate()->first();
            if (! $draft) {
                return null;
            }
            $result = $change($draft->rows, $draft->next_id);
            if ($result !== null) {
                [$rows, $nextId] = $result;
                $draft->update(['rows' => array_values($rows), 'next_id' => $nextId, 'edited' => true,
                    'version' => $draft->version + 1, 'updated_by' => $by->id]);
            }

            return $draft;
        });
    }

    /** Sets the semester the new timetable is for */
    public function setSemester(Department $department, User $by, array $semester): ?TimetableDraft
    {
        return DB::transaction(function () use ($department, $by, $semester) {
            $draft = TimetableDraft::where('department_id', $department->id)->lockForUpdate()->first();
            if ($draft && $draft->semester() !== $semester) {
                $draft->update(['term_name' => $semester['name'], 'term_start' => $semester['start'], 'term_end' => $semester['end'],
                    'version' => $draft->version + 1, 'updated_by' => $by->id]);
            }

            return $draft;
        });
    }

    /** A backup's rows, ready to start a new timetable from */
    public static function backupRows(TimetableBackup $backup): array
    {
        return array_map(fn (array $row) => $row + ['line' => null, 'notes' => []], $backup->rows);
    }

    /**
     * Everything to know before $rows replace the timetable in use:
     *  ok        true when nothing must be fixed first
     *  problems  [['row' => row, 'text' => ...]]: must be fixed first
     *  warnings  [['text' => ..., 'ids' => [row ids]]]: worth a look, but it can be saved
     *  rows      what will be saved (rows without problems, each once)
     *  compare   ['same', 'changed', 'added', 'removed'] (App\Support\TimetableRules::compare())
     *  rooms     ['new' => [names], 'shared' => [names]]: added to the department's rooms
     *  blocks    blocks that aren't in the system yet; courses: the same for courses
     *  orphans   [block => [rep names]]: reps whose block has no class in it
     *  clashes   [['booking' => Reservation, 'row' => row]]: upcoming bookings at the same time as a class in the same room
     *  sessions  upcoming class-session bookings of classes it removes (they stay, as plain bookings)
     *  counts    ['classes' => n, 'blocked' => n, 'rooms' => n, 'blocks' => n]
     */
    public function check(Department $department, array $rows, array $semester): array
    {
        $problems = [];
        $valid = [];
        foreach ($rows as $row) {
            $found = TimetableRules::problems($row);
            foreach ($found as $text) {
                $problems[] = ['row' => $row, 'text' => $text];
            }
            if (! $found) {
                $valid[] = $row;
            }
        }

        [$once, $repeats] = TimetableRules::dedupe($valid);
        $warnings = [];
        foreach ($repeats as [$row, $first]) {
            $warnings[] = ['text' => TimetableRules::describe($row).' is there twice'.(! empty($first['line']) ? ' (also row '.$first['line'].')' : '').', so it’s saved once.', 'ids' => [$row['id'] ?? null]];
        }
        $warnings = array_merge($warnings, TimetableRules::overlaps($once));
        $config = DepartmentConfig::forDepartment($department);
        foreach ($once as $row) {
            if ($text = TimetableRules::outsideHours($row, $config->opensAt(), $config->closesAt())) {
                $warnings[] = ['text' => $text, 'ids' => [$row['id'] ?? null]];
            }
            foreach ($row['notes'] ?? [] as $note) {
                $warnings[] = ['text' => TimetableRules::describe($row).': '.$note, 'ids' => [$row['id'] ?? null]];
            }
        }

        $compare = TimetableRules::compare($this->rows($this->classes($department)), $once);
        $classRows = array_values(array_filter($once, fn ($row) => $row['type'] === 'Class'));

        // Rooms, blocks and courses that will be added
        $rooms = Room::all()->keyBy(fn (Room $room) => TimetableRules::roomKey($room->name));
        $mine = $department->rooms()->pluck('rooms.id')->all();
        $newRooms = [];
        $sharedRooms = [];
        foreach (collect($once)->pluck('room')->unique(fn ($name) => TimetableRules::roomKey($name)) as $name) {
            $room = $rooms->get(TimetableRules::roomKey($name));
            if (! $room) {
                $newRooms[] = $name;
            } elseif (! in_array($room->id, $mine, true)) {
                $sharedRooms[] = $room->name;
            }
        }
        $warnings = array_merge($warnings, $this->otherDepartments($department, $once, $rooms, $semester));
        $knownBlocks = Section::pluck('name')->map(fn ($name) => mb_strtoupper($name))->all();
        $blocksAfter = collect($classRows)->pluck('block')->filter()->unique(fn ($block) => mb_strtoupper($block));
        $knownCourses = Course::get(['code', 'title'])->map(fn (Course $course) => self::courseKey($course->code, $course->title))->all();

        // Reps whose block has no class in the new timetable: they'd get no class reminders
        $blockKeys = $blocksAfter->map(fn ($block) => mb_strtoupper($block))->all();
        $orphans = User::with('section')
            ->where('department_id', $department->id)->where('role', 'rep')->where('is_active', true)->whereNotNull('section_id')
            ->orderBy('name')->get()
            ->reject(fn (User $rep) => in_array(mb_strtoupper($rep->section->name), $blockKeys, true))
            ->groupBy(fn (User $rep) => $rep->section->name)->map(fn ($reps) => $reps->pluck('name')->all())
            ->sortKeys()->all();

        $removedIds = array_column($compare['removed'], 'class_id');

        return [
            'ok' => ! $problems && $once,
            'problems' => $problems,
            'warnings' => $warnings,
            'rows' => $once,
            'compare' => $compare,
            'rooms' => ['new' => $newRooms, 'shared' => $sharedRooms],
            'blocks' => $blocksAfter->reject(fn ($block) => in_array(mb_strtoupper($block), $knownBlocks, true))->sort()->values()->all(),
            'courses' => collect($classRows)->unique(fn ($row) => self::courseKey($row['code'], $row['title']))
                ->reject(fn ($row) => in_array(self::courseKey($row['code'], $row['title']), $knownCourses, true))
                ->map(fn ($row) => trim($row['code'].' '.$row['title']))->sort()->values()->all(),
            'orphans' => $orphans,
            'clashes' => $this->clashes($compare, $semester),
            'sessions' => $removedIds ? Reservation::with(['user.section', 'room', 'classSchedule.course'])->blocking()
                ->whereIn('class_schedule_id', $removedIds)->where('end_time', '>', now())->orderBy('start_time')->get() : collect(),
            'counts' => [
                'classes' => count($classRows),
                'blocked' => count($once) - count($classRows),
                'rooms' => collect($once)->map(fn ($row) => TimetableRules::roomKey($row['room']))->unique()->count(),
                'blocks' => $blocksAfter->count(),
            ],
        ];
    }

    /**
     * Upcoming bookings in a room at the same time as a class (or blocked time) of the timetable in use.
     * Normally none: booking checks the timetable. They come from replacing the timetable.
     */
    public function clashesNow(Department $department, ?Collection $classes = null): array
    {
        return $this->bookingsAgainst($this->rows($classes ?? $this->classes($department)), null, null);
    }

    /**
     * Replaces the department's timetable with its new one, right away, after saving the one in use as
     * a backup. $semester: the semester to save it for (it's saved on the new timetable too).
     *
     * @return array{0: bool, 1: string} [ok, message]
     */
    public function apply(Department $department, User $by, int $version, array $semester): array
    {
        return DB::transaction(function () use ($department, $by, $version, $semester) {
            $draft = TimetableDraft::where('department_id', $department->id)->lockForUpdate()->first();
            if (! $draft) {
                return [false, 'There’s no new timetable to save: it was saved or discarded already.'];
            }
            if ($draft->version !== $version) {
                return [false, 'The new timetable changed after this page was opened (maybe by someone else). Check it again below, then press Replace.'];
            }
            if ($draft->semester() !== $semester) {
                // Kept on the new timetable too, in case it can't be saved yet
                $draft->update(['term_name' => $semester['name'], 'term_start' => $semester['start'], 'term_end' => $semester['end'], 'updated_by' => $by->id]);
            }
            $check = $this->check($department, $draft->rows, $semester);
            if (! $check['ok']) {
                return [false, 'Fix the problems listed below first.'];
            }

            $classes = $this->classes($department);
            $backup = $this->backup($department, $by, $classes, $draft->replacedBy());

            // The semester: shared by every department, so one with the same name is used (with these dates)
            $term = Term::firstOrNew(['name' => $semester['name']]);
            $term->fill(['start_date' => $semester['start'], 'end_date' => $semester['end']])->save();

            $ids = $this->ids($department, $check['rows']);
            $compare = $check['compare'];
            foreach ($compare['same'] as [$was]) {
                if ((int) $was['term_id'] !== $term->id) {
                    ClassSchedule::whereKey($was['class_id'])->update(['term_id' => $term->id]);
                }
            }
            foreach ($compare['changed'] as [$was, $row]) {
                ClassSchedule::whereKey($was['class_id'])->update(Arr::only($this->columns($department, $term, $row, $ids),
                    ['term_id', 'course_id', 'instructor', 'note']));
            }

            // Removed classes: their class-session bookings stay, as plain bookings
            $removed = array_column($compare['removed'], 'class_id');
            foreach (array_chunk($removed, 500) as $chunk) {
                Reservation::whereIn('class_schedule_id', $chunk)->update(['class_schedule_id' => null, 'purpose' => DB::raw("COALESCE(purpose, 'Class session')")]);
                ClassSchedule::whereKey($chunk)->delete();
            }

            foreach ($compare['added'] as $row) {
                ClassSchedule::create($this->columns($department, $term, $row, $ids));
            }

            $draft->delete();

            $counts = $check['counts'];
            $message = 'Done: the new timetable is in use ('.$counts['classes'].' '.($counts['classes'] === 1 ? 'class' : 'classes')
                .($counts['blocked'] ? ' and '.$counts['blocked'].' blocked '.($counts['blocked'] === 1 ? 'time' : 'times') : '').', '.$term->name.').';
            $message .= $backup
                ? ' The one it replaced is saved under Backups below.'
                : '';
            if ($term->start_date->isFuture()) {
                $message .= ' Its classes count from '.$term->start_date->format('F j, Y').'.';
            }

            return [true, $message];
        }, 3);
    }

    /** 'CMPE 402|project management': the same course, whatever the capital letters */
    public static function courseKey(?string $code, ?string $title): string
    {
        return mb_strtoupper((string) $code).'|'.mb_strtolower((string) $title);
    }

    /** Saves the timetable in use as a backup (nothing when it's empty) */
    private function backup(Department $department, User $by, Collection $classes, string $reason): ?TimetableBackup
    {
        if ($classes->isEmpty()) {
            return null;
        }
        $term = $this->term($department, $classes);
        $rows = array_map(fn (array $row) => Arr::only($row, self::BACKUP_FIELDS), $this->rows($classes));
        $classCount = count(array_filter($rows, fn ($row) => $row['type'] === 'Class'));

        return TimetableBackup::create([
            'department_id' => $department->id,
            'term_name' => $term?->name,
            'term_start' => $term?->start_date,
            'term_end' => $term?->end_date,
            'rows' => $rows,
            'class_count' => $classCount,
            'blocked_count' => count($rows) - $classCount,
            'reason' => mb_substr($reason, 0, 200),
            'created_by' => $by->id,
        ]);
    }

    /**
     * The IDs of every room, block and course in $rows, adding the ones that are new. Rooms are added
     * to the department's list (a room another department has is shared, not copied).
     */
    private function ids(Department $department, array $rows): array
    {
        $rooms = Room::all()->keyBy(fn (Room $room) => TimetableRules::roomKey($room->name));
        $roomIds = [];
        foreach ($rows as $row) {
            $key = TimetableRules::roomKey($row['room']);
            $roomIds[$key] ??= ($rooms->get($key) ?? Room::create(['name' => $row['room'], 'is_active' => true]))->id;
        }
        $department->rooms()->syncWithoutDetaching(array_values($roomIds));

        $blocks = Section::all()->keyBy(fn (Section $section) => mb_strtoupper($section->name));
        $blockIds = [];
        $courses = Course::all()->keyBy(fn (Course $course) => self::courseKey($course->code, $course->title));
        $courseIds = [];
        foreach ($rows as $row) {
            if ($row['type'] !== 'Class') {
                continue;
            }
            if ($row['block'] !== null) {
                $key = mb_strtoupper($row['block']);
                $blockIds[$key] ??= ($blocks->get($key) ?? Section::create(['name' => $row['block'], 'program' => TimetableRules::program($row['block'])]))->id;
            }
            $key = self::courseKey($row['code'], $row['title']);
            $courseIds[$key] ??= ($courses->get($key) ?? Course::create(['code' => $row['code'], 'title' => $row['title']]))->id;
        }

        return ['rooms' => $roomIds, 'blocks' => $blockIds, 'courses' => $courseIds];
    }

    /** A row -> the columns of its class_schedules row */
    private function columns(Department $department, Term $term, array $row, array $ids): array
    {
        $class = $row['type'] === 'Class';

        return [
            'term_id' => $term->id,
            'department_id' => $department->id,
            'room_id' => $ids['rooms'][TimetableRules::roomKey($row['room'])],
            'day_of_week' => $row['day'],
            'start_time' => $row['start'].':00',
            'end_time' => $row['end'].':00',
            'entry_type' => $row['type'],
            'course_id' => $class ? $ids['courses'][self::courseKey($row['code'], $row['title'])] : null,
            'section_id' => $class && $row['block'] !== null ? $ids['blocks'][mb_strtoupper($row['block'])] : null,
            'instructor' => $class ? $row['instructor'] : null,
            'note' => $row['note'],
        ];
    }

    /**
     * Rooms are shared: another department's timetable using the same room at the same time
     * (during the semester), as warnings.
     */
    private function otherDepartments(Department $department, array $rows, Collection $rooms, array $semester): array
    {
        $byRoom = [];
        foreach ($rows as $row) {
            if ($room = $rooms->get(TimetableRules::roomKey($row['room']))) {
                $byRoom[$room->id][] = $row;
            }
        }
        if (! $byRoom) {
            return [];
        }

        $warnings = [];
        $others = ClassSchedule::with(['course', 'section', 'department', 'term'])
            ->where('department_id', '!=', $department->id)
            ->whereIn('room_id', array_keys($byRoom))
            ->get();
        foreach ($others as $other) {
            if (! empty($semester['start']) && ! empty($semester['end'])
                && ($other->term->end_date->toDateString() < $semester['start'] || $other->term->start_date->toDateString() > $semester['end'])) {
                continue;       // their semester doesn't meet this one
            }
            $from = DepartmentConfig::minutes($other->start_time);
            $to = DepartmentConfig::minutes($other->end_time);
            foreach ($byRoom[$other->room_id] as $row) {
                if ($row['day'] !== $other->day_of_week || TimetableRules::minutes($row['start']) >= $to || TimetableRules::minutes($row['end']) <= $from) {
                    continue;
                }
                $theirs = $other->entry_type === 'Blocked'
                    ? 'blocked'
                    : 'has '.($other->course?->code ?? 'a class').($other->section ? ' · '.$other->section->name : '').' there';
                $warnings[] = [
                    'text' => $row['room'].' on '.TimetableRules::DAY_NAMES[$row['day']].': '.$other->department->name.'’s timetable '.$theirs.' '
                        .TimeSlots::span($from, $to).', at the same time as '.TimetableRules::describe($row).'. The room is shared, so check with them.',
                    'ids' => [$row['id'] ?? null],
                ];
            }
        }

        return $warnings;
    }

    /** Bookings that clash with the new timetable: its classes keep their ids where they stay the same */
    private function clashes(array $compare, array $semester): array
    {
        if (empty($semester['start']) || empty($semester['end'])) {
            return [];
        }
        $rows = [];
        foreach (array_merge($compare['same'], $compare['changed']) as [$was, $row]) {
            $rows[] = ['class_id' => $was['class_id']] + $row;
        }
        foreach ($compare['added'] as $row) {
            $rows[] = ['class_id' => null] + $row;
        }

        return $this->bookingsAgainst($rows, $semester['start'], $semester['end']);
    }

    /**
     * Upcoming bookings in the same room at the same time as one of $rows, between $from and $until
     * (null: each row's own semester). Not counted: a class's own class-session booking, and a
     * meeting that's online or was freed (anyone may book its room then).
     *
     * @return list<array{booking: Reservation, row: array}>
     */
    private function bookingsAgainst(array $rows, ?string $from, ?string $until): array
    {
        $rooms = Room::all()->keyBy(fn (Room $room) => TimetableRules::roomKey($room->name));
        $byRoom = [];
        foreach ($rows as $row) {
            if ($room = $rooms->get(TimetableRules::roomKey($row['room']))) {
                $byRoom[$room->id][] = $row;
            }
        }
        if (! $byRoom) {
            return [];
        }

        $bookings = Reservation::with(['user.section', 'room'])
            ->blocking()
            ->whereIn('room_id', array_keys($byRoom))
            ->where('end_time', '>', now())
            ->orderBy('start_time')
            ->get();
        if ($bookings->isEmpty()) {
            return [];
        }

        $classIds = array_filter(array_column($rows, 'class_id'));
        $classes = $classIds ? ClassSchedule::whereKey($classIds)->get() : collect();
        $states = [];       // date => [class id => state]
        $clashes = [];
        foreach ($bookings as $booking) {
            $date = $booking->start_time->toDateString();
            $start = $booking->start_time->hour * 60 + $booking->start_time->minute;
            $end = $booking->end_time->isSameDay($booking->start_time) ? $booking->end_time->hour * 60 + $booking->end_time->minute : 1440;
            foreach ($byRoom[$booking->room_id] as $row) {
                if ($row['day'] !== $booking->start_time->format('D')
                    || TimetableRules::minutes($row['start']) >= $end || TimetableRules::minutes($row['end']) <= $start
                    || $date < ($from ?? $row['from']) || $date > ($until ?? $row['until'])) {
                    continue;
                }
                $classId = $row['class_id'] ?? null;
                if ($classId && (int) $booking->class_schedule_id === (int) $classId) {
                    continue;       // the class's own session
                }
                if ($classId && $row['type'] === 'Class') {
                    $states[$date] ??= $this->checks->states($classes, CarbonImmutable::parse($date));
                    if (ClassCheckRules::roomFree($states[$date][$classId] ?? 'waiting')) {
                        continue;   // online or freed that day: the room was free to book
                    }
                }
                $clashes[] = ['booking' => $booking, 'row' => $row];
                break;
            }
        }

        return $clashes;
    }
}
