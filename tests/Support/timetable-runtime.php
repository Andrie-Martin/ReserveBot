<?php

require_once __DIR__.'/layout-runtime.php';

/** Timetable management fixtures (overview, in use, draft, row edit, check). Sample data only; no database, no mail, no files. */
function ttCases(): array
{
    $cases = [];
    foreach (['empty', 'inuse', 'draft', 'draft-problems', 'clashes', 'backups', 'long'] as $s) {
        $cases['tt-overview-admin-'.$s] = ['overview', 'admin', $s];
    }
    $cases['tt-overview-lab_staff-draft'] = ['overview', 'lab_staff', 'draft'];
    foreach (['day', 'week', 'block', 'quiet'] as $s) {
        $cases['tt-use-admin-'.$s] = ['use', 'admin', $s];
    }
    foreach (['day', 'week', 'fix', 'warnings', 'undo', 'errors', 'long'] as $s) {
        $cases['tt-draft-admin-'.$s] = ['draft', 'admin', $s];
    }
    $cases['tt-draft-lab_staff-day'] = ['draft', 'lab_staff', 'day'];
    foreach (['class', 'blocked', 'problems', 'errors', 'long'] as $s) {
        $cases['tt-row-admin-'.$s] = ['row', 'admin', $s];
    }
    foreach (['ok', 'problems', 'mostly', 'empty', 'same', 'bookings', 'errors', 'long'] as $s) {
        $cases['tt-check-admin-'.$s] = ['check', 'admin', $s];
    }
    $cases['tt-check-lab_staff-ok'] = ['check', 'lab_staff', 'ok'];

    return $cases;
}

function ttRow(int $id, string $day, ?string $start, ?string $end, ?string $room, array $o = []): array
{
    $blocked = ($o['type'] ?? 'Class') === 'Blocked';

    return ['id' => $id, 'line' => $o['line'] ?? null, 'day' => $day, 'start' => $start, 'end' => $end, 'room' => $room, 'type' => $blocked ? 'Blocked' : 'Class',
        'code' => $blocked ? null : ($o['code'] ?? 'CMPE 402'), 'title' => $blocked ? null : ($o['title'] ?? 'Project Management'), 'block' => $blocked ? null : ($o['block'] ?? 'BSCPE 4-1'),
        'instructor' => $blocked ? null : ($o['instructor'] ?? 'J. Tena'), 'note' => $o['note'] ?? null, 'notes' => $o['notes'] ?? []];
}

function ttRows(bool $long = false, bool $withProblems = false): array
{
    $c = fn ($s) => $long ? str_repeat($s, 6) : $s;
    $rows = [
        ttRow(1, 'Mon', '07:30', '09:00', 'CEA300', ['code' => 'CMPE 402', 'title' => $c('Project Management'), 'instructor' => $c('J. Tena'), 'line' => 2]),
        ttRow(2, 'Mon', '09:00', '10:30', 'CEA300', ['code' => 'CMPE 410', 'title' => 'Embedded Systems', 'block' => 'BSCPE 4-2', 'instructor' => 'L. Reyes']),
        ttRow(3, 'Mon', '08:00', '10:00', 'CEA301', ['code' => 'ECE 301', 'title' => 'Signals', 'block' => 'BSECE 3-1', 'note' => 'Bring laptops']),
        ttRow(4, 'Mon', '13:00', '15:00', 'CEA300', ['type' => 'Blocked', 'note' => 'Used by ECE']),
        ttRow(5, 'Tue', '10:00', '12:00', 'CEA300', ['code' => 'CMPE 402', 'block' => 'BSCPE 4-1']),
        ttRow(6, 'Wed', '13:00', '16:00', 'CEA301', ['code' => $c('CMPE 499'), 'title' => 'Capstone', 'block' => $c('BSCPE 4-3'), 'instructor' => 'M. Cruz']),
        ttRow(7, 'Thu', '07:30', '09:00', 'CEA300', ['code' => 'CMPE 402', 'block' => 'BSCPE 4-1']),
    ];
    if ($withProblems) {
        $rows[] = ttRow(8, 'Mon', '11:00', '10:00', 'CEA300', ['code' => 'CMPE 420', 'line' => 9]);
        $rows[] = ttRow(9, 'Funday', '11:00', '12:00', null, ['code' => null, 'line' => 10]);
    }

    return $rows;
}

function ttPage(array $rows, array $problems, string $day, string $week = ''): array
{
    $config = new App\Models\DepartmentConfig(App\Models\DepartmentConfig::DEFAULTS + ['department_id' => 73]);
    $R = App\Support\TimetableRules::class;
    $valid = array_filter($rows, fn ($row) => ! isset($problems[$row['id']]));
    $warned = [];
    $warnings = $R::overlaps(array_values($valid));
    foreach ($warnings as $w) {
        foreach (array_filter($w['ids']) as $id) {
            $warned[$id][] = $w['text'];
        }
    }
    $counts = array_fill_keys($R::DAYS, 0);
    foreach ($rows as $row) {
        if (isset($counts[$row['day']])) {
            $counts[$row['day']]++;
        }
    }
    $rooms = collect($rows)->pluck('room')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
    $blocks = collect($rows)->where('type', 'Class')->pluck('block')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
    $grid = null;
    if (preg_match('/^(room|block):(.+)$/', $week, $m)) {
        $match = $m[1] === 'room' ? fn ($r) => $r['room'] === $m[2] : fn ($r) => $r['type'] === 'Class' && $r['block'] === $m[2];
        $method = new ReflectionMethod(App\Http\Controllers\ManageTimetableController::class, 'week');
        $controller = (new ReflectionClass(App\Http\Controllers\ManageTimetableController::class))->newInstanceWithoutConstructor();
        $grid = $method->invoke($controller, array_values(array_filter($valid, $match)), $config) + ['kind' => $m[1], 'name' => $m[2]];
    }
    $list = $day === 'fix' ? array_filter($rows, fn ($r) => isset($problems[$r['id']])) : array_filter($rows, fn ($r) => $r['day'] === $day);
    $classes = array_filter($rows, fn ($r) => $r['type'] === 'Class' && $r['code']);
    $titles = [];
    foreach ($classes as $r) {
        if ($r['title']) { $titles[mb_strtoupper($r['code'])] ??= $r['title']; }
    }

    return ['rows' => $rows, 'list' => $R::sort($list), 'problems' => $problems, 'warned' => $warned, 'warningCount' => count($warnings), 'counts' => $counts, 'openDays' => $config->openDays(),
        'day' => $day, 'grid' => $grid, 'week' => $week, 'rooms' => $rooms, 'blocks' => $blocks, 'undo' => null,
        'roomNames' => $rooms->all(), 'codes' => collect($classes)->pluck('code')->unique()->values()->all(), 'titles' => $titles, 'blockNames' => $blocks->all()];
}

function ttDraft(string $source = 'copy', bool $long = false, array $rows = []): App\Models\TimetableDraft
{
    $d = new App\Models\TimetableDraft(['rows' => $rows, 'source' => $source, 'source_name' => $long ? str_repeat('very-long-file-name-', 5).'.xlsx' : '2nd sem.xlsx', 'edited' => true,
        'term_name' => '2nd Semester AY 2026-2027', 'term_start' => '2027-01-11', 'term_end' => '2027-05-22', 'version' => 3]);
    $d->created_at = Carbon\CarbonImmutable::now()->subDays(2);
    $d->updated_at = Carbon\CarbonImmutable::now()->subHours(3);
    $d->setRelation('starter', new App\Models\User(['name' => 'Example Lab Staff']));
    $d->setRelation('updater', new App\Models\User(['name' => 'Example Admin']));

    return $d;
}

function ttBookings(bool $long): array
{
    $now = Carbon\CarbonImmutable::now();
    $out = [];
    foreach ([[501, 'Pending', 1, 'Mon'], [502, 'Confirmed', 3, 'Tue']] as [$id, $status, $add, $day]) {
        $u = new App\Models\User(['name' => $long ? str_repeat('LongBookerName', 5) : 'Ana Cruz', 'role' => 'rep']);
        $u->setRelation('section', new App\Models\Section(['name' => '3-2', 'program' => 'BSCPE']));
        $r = new App\Models\Reservation(['purpose' => 'Example lecture with slides', 'status' => $status]);
        $r->id = $id;
        $r->start_time = $now->startOfDay()->addDays($add)->addHours(13);
        $r->end_time = $now->startOfDay()->addDays($add)->addHours(15);
        $r->setRelation('user', $u);
        $r->setRelation('room', (object) ['id' => 11, 'name' => 'CEA300']);
        $r->setRelation('classSchedule', (object) ['course' => (object) ['code' => 'CMPE 402']]);
        $out[] = ['booking' => $r, 'row' => ttRow(5, 'Tue', '10:00', '12:00', 'CEA300')];
    }

    return $out;
}

function ttRender(array $case): string
{
    [$page, $role, $state] = $case;
    $now = Carbon\CarbonImmutable::parse('2026-11-04 11:30:00', 'Asia/Manila');
    Carbon\Carbon::setTestNow($now); Carbon\CarbonImmutable::setTestNow($now);
    $long = $state === 'long';
    $route = ['overview' => 'manage.timetable', 'use' => 'manage.timetable.view', 'draft' => 'manage.timetable.draft', 'row' => 'manage.timetable.rows.edit', 'check' => 'manage.timetable.check'][$page];
    layoutState($role, ['route' => $route, 'waiting' => 3]);
    $user = auth()->user();
    $user->setRelation('department', $dept = new App\Models\Department(['name' => $long ? str_repeat('LongDepartmentName', 5) : 'Example Engineering']));
    $errors = new Illuminate\Support\ViewErrorBag;
    $bag = fn (array $m) => new Illuminate\Support\MessageBag($m);
    $old = [];
    $term = tap(new App\Models\Term(['name' => '1st Semester AY 2026-2027', 'start_date' => '2026-08-10', 'end_date' => '2026-12-19']), fn ($t) => $t->id = 1);
    $withProblems = in_array($state, ['problems', 'fix'], true) || ($page === 'overview' && $state === 'draft-problems');
    $rows = ttRows($long, $withProblems);
    $problemsOf = fn (array $rs) => collect($rs)->mapWithKeys(fn ($r) => [$r['id'] => App\Support\TimetableRules::problems($r)])->filter()->all();

    if ($page === 'overview') {
        $inUse = $state !== 'empty';
        $draft = in_array($state, ['draft', 'draft-problems', 'long'], true) ? ttDraft('upload', $long, ttRows($long, $state === 'draft-problems')) : null;
        $backups = $state === 'empty' ? collect() : collect([['2026-09-01 10:00:00', 'Replaced by the file “2nd sem.xlsx”', 52, 4], ['2026-05-30 09:00:00', 'Replaced by changes made on the site', 48, 0]])->map(fn ($b, $i) => tap(new App\Models\TimetableBackup(['term_name' => $i ? null : ($long ? str_repeat('LongSemesterName', 4) : '2nd Semester AY 2025-2026'), 'class_count' => $b[2], 'blocked_count' => $b[3], 'reason' => $b[1]]), fn ($m) => [$m->id = $i + 1, $m->created_at = Carbon\CarbonImmutable::parse($b[0]), $m->setRelation('creator', new App\Models\User(['name' => 'Example Admin']))]));
        if ($state === 'backups') {
            $draft = null;
        }
        $errs = $state === 'draft-problems' ? ['upload' => $bag(['file' => ['The file isn’t an Excel or CSV file.']])] : [];
        foreach ($errs as $k => $v) { $errors->put($k, $v); }
        session()->put('_old_input', $old);
        app('view')->share('errors', $errors);

        return view('manage.timetable', ['department' => $dept, 'term' => $inUse ? $term : null,
            'counts' => $inUse ? ['classes' => 52, 'blocked' => 4, 'rooms' => 9, 'blocks' => 14] : ['classes' => 0, 'blocked' => 0, 'rooms' => 0, 'blocks' => 0],
            'draft' => $draft, 'draftProblems' => $state === 'draft-problems' ? 2 : 0, 'backups' => $backups,
            'clashes' => $state === 'clashes' ? ttBookings($long) : [], 'canXlsx' => $state !== 'long'])->render();
    }

    if ($page === 'use') {
        $p = ttPage($rows, [], 'Mon', $state === 'week' ? 'room:CEA300' : ($state === 'block' ? 'block:BSCPE 4-1' : ''));
        if ($state === 'quiet') { $p = ttPage($rows, [], 'Fri'); }
        session()->put('_old_input', $old);
        app('view')->share('errors', $errors);

        return view('manage.timetable_rows', $p + ['draft' => null, 'term' => $term])->render();
    }

    if ($page === 'draft') {
        $problems = $state === 'fix' ? $problemsOf($rows) : [];
        $p = ttPage($rows, $problems, $state === 'fix' ? 'fix' : 'Mon', $state === 'week' ? 'room:CEA300' : '');
        if ($state === 'warnings') { $p = ttPage(array_merge($rows, [ttRow(10, 'Mon', '08:30', '09:30', 'CEA300', ['code' => 'CMPE 430'])]), [], 'Mon'); }
        if ($state === 'undo') { $p['undo'] = 'encrypted-undo-token'; }
        if ($state === 'errors') {
            $errors->put('row', $bag(['room' => ['Room is empty.'], 'end' => ['It ends before it starts.']]));
            $old = ['day' => 'Mon', 'start' => '11:00', 'end' => '10:00', 'room' => '', 'type' => 'Class', 'code' => 'CMPE 420'];
        }
        session()->put('_old_input', $old);
        app('view')->share('errors', $errors);

        return view('manage.timetable_rows', $p + ['draft' => ttDraft('copy', $long, $rows), 'term' => null])->render();
    }

    if ($page === 'row') {
        $rowData = match ($state) {
            'blocked' => ttRow(4, 'Mon', '13:00', '15:00', 'CEA300', ['type' => 'Blocked', 'note' => 'Used by ECE']),
            'problems' => ttRow(8, 'Mon', '11:00', '10:00', 'CEA300', ['code' => 'CMPE 420', 'line' => 9, 'instructor' => 'J. Tena']),
            'long' => ttRow(6, 'Wed', '13:00', '16:00', 'CEA301', ['code' => str_repeat('CMPE499', 3), 'title' => str_repeat('Capstone ', 8), 'block' => str_repeat('BSCPE4', 4), 'instructor' => str_repeat('LongInstructor', 4)]),
            default => ttRow(1, 'Mon', '07:30', '09:00', 'CEA300', ['line' => 2]),
        };
        if ($state === 'errors') {
            $errors->put('row', $bag(['room' => ['Room is empty.']]));
            $old = ['_token' => 'x', 'day' => 'Tue', 'start' => '08:00', 'end' => '09:00', 'room' => '', 'type' => 'Class', 'code' => 'OLD 101'];
        }
        session()->put('_old_input', $old);
        app('view')->share('errors', $errors);
        $p = ttPage($rows, [], 'Mon');

        return view('manage.timetable_row', ['draft' => ttDraft('copy', false, $rows), 'row' => $rowData, 'problems' => App\Support\TimetableRules::problems($rowData), 'back' => route('manage.timetable.draft', ['day' => 'Mon']),
            'roomNames' => $p['roomNames'], 'codes' => $p['codes'], 'titles' => $p['titles'], 'blockNames' => $p['blockNames']])->render();
    }

    // check
    $R = App\Support\TimetableRules::class;
    $rowsC = ttRows($long, $state === 'problems');
    $old2 = array_slice(ttRows(false, false), 0, $state === 'mostly' ? 7 : 6);
    $once = $state === 'empty' ? [] : array_values(array_filter($rowsC, fn ($r) => ! $R::problems($r)));
    if ($state === 'mostly') { $once = [ttRow(20, 'Fri', '07:30', '09:00', 'CEA400', ['code' => 'NEW 101'])]; $old2 = array_map(fn ($i) => ttRow(30 + $i, 'Mon', sprintf('%02d:00', 7 + $i % 12), sprintf('%02d:30', 7 + $i % 12), 'CEA30'.($i % 5), ['code' => 'OLD '.$i]), range(0, 11)); }
    if ($state === 'same') { $once = $old2; }
    $compare = $R::compare($old2, $once);
    $problems = [];
    foreach ($rowsC as $r) { foreach ($R::problems($r) as $t) { $problems[] = ['row' => $r, 'text' => $t]; } }
    $check = ['ok' => ! $problems && $once, 'problems' => $problems, 'warnings' => $state === 'ok' || $state === 'long' ? [['text' => 'Monday 8:00 AM to 10:00 AM in CEA301 overlaps another class.', 'ids' => [3]], ['text' => 'CEA300 Mon 7:30 AM: the file says 7:30; it was read as 7:30 AM.', 'ids' => [1]]] : [],
        'rows' => $once, 'compare' => $compare, 'rooms' => ['new' => $state === 'ok' ? ['CEA400'] : [], 'shared' => $state === 'ok' ? ['CEA301'] : []],
        'blocks' => $state === 'ok' ? ['BSCPE 4-3'] : [], 'courses' => $state === 'ok' ? ['CMPE 499 Capstone'] : [], 'orphans' => $state === 'ok' ? ['3-2' => ['Ana Cruz', 'Ben Reyes']] : [],
        'clashes' => $state === 'bookings' ? ttBookings($long) : [], 'sessions' => $state === 'bookings' ? collect(array_map(fn ($c) => $c['booking'], ttBookings($long))) : collect(),
        'counts' => ['classes' => count(array_filter($once, fn ($r) => $r['type'] === 'Class')), 'blocked' => count(array_filter($once, fn ($r) => $r['type'] === 'Blocked')), 'rooms' => 3, 'blocks' => 4]];
    if ($state === 'errors') {
        $errors->put('semester', $bag(['term_end' => ['The last day has to be after the first day.']]));
        $old = ['_token' => 'x', 'term_name' => 'Old semester name', 'term_start' => '2027-05-01', 'term_end' => '2027-01-01'];
    }
    session()->put('_old_input', $old);
    app('view')->share('errors', $errors);

    return view('manage.timetable_check', ['draft' => ttDraft('upload', $long, $rowsC), 'check' => $check, 'term' => $term])->render();
}

function ttContracts(): int
{
    $count = 0;
    $check = function ($ok, $message) use (&$count) { foundationsCheck((bool) $ok, $message); $count++; };
    foreach (ttCases() as $name => $case) {
        $html = ttRender($case);
        $x = foundationsDocument($html);
        $check($x->query('//main//h1')->length === 1, "$name one main heading");
        $ids = [];
        foreach ($x->query('//*[@id]') as $node) { $id = $node->getAttribute('id'); $check(! isset($ids[$id]), "$name unique ID $id"); $ids[$id] = true; }
        foreach ($x->query('//main//input[not(@type="hidden")]|//main//select|//main//textarea') as $node) {
            $id = $node->getAttribute('id');
            if (in_array($node->getAttribute('type'), ['radio', 'checkbox'], true)) { $check($x->query('ancestor::label', $node)->length === 1, "$name choice wrapped by label"); continue; }
            $check($id && $x->query('//label[@for="'.$id.'"]')->length === 1, "$name labelled $id");
        }
        foreach ($x->query('//main//form[@method="post"]') as $form) {
            $check($x->query('.//input[@name="_token" and @value="isolated-layout-csrf"]', $form)->length === 1, "$name csrf");
        }
        $check($x->query('//link[contains(@href,"css/manage.css?v=")]')->length === 1, "$name manage stylesheet");
        $check($x->query('//nav[@aria-label="Manage"]//a[contains(@class,"on")]')->length === 1, "$name one current tab");
    }

    return $count;
}
