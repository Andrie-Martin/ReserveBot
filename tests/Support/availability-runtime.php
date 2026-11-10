<?php

use App\Models\Department;
use App\Models\Section;
use App\Services\RoomAvailability;
use App\Support\TimeSlots;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

require_once __DIR__.'/layout-runtime.php';

/** Explicit sample data only. Never calls controllers, opens PDO, saves models or sends mail. */
function availabilityCases(): array
{
    $cases = [];
    foreach (['rep', 'lab_staff', 'admin'] as $role) {
        foreach (['normal', 'search', 'invalid-time', 'none-free', 'started', 'closed', 'outside-term', 'invalid-date', 'empty', 'boundary', 'long', 'long-events'] as $state) {
            $cases['rooms-'.$role.'-'.$state] = ['rooms', $role, $state];
        }
    }
    foreach (['normal', 'empty', 'no-section', 'long', 'feedback', 'unconnected', 'ended', 'priority'] as $state) {
        $cases['home-rep-'.$state] = ['home', 'rep', $state];
    }
    foreach (['lab_staff', 'admin'] as $role) {
        $cases['home-'.$role] = ['home', $role, 'normal'];
    }

    return $cases;
}

function availabilityRender(array $case, ?callable $transform = null): string
{
    [$page,$role,$state] = $case;
    $today = CarbonImmutable::parse('2026-11-04 09:05:00', 'Asia/Manila');
    Carbon\Carbon::setTestNow($today);
    CarbonImmutable::setTestNow($today);
    layoutState($role, ['route' => $page === 'rooms' ? 'rooms' : ($role === 'rep' ? 'rep.home' : ($role === 'admin' ? 'admin.home' : 'staff.home')), 'connected' => $state !== 'unconnected']);
    $user = auth()->user();
    $department = new Department(['name' => 'Example Engineering']);
    $section = new Section(['name' => '3-2', 'program' => 'BSCPE']);
    $section->id = 12;
    $user->setRelation('department', $department);
    $user->setRelation('section', $state === 'no-section' ? null : $section);
    if ($state === 'long') {
        $user->name = str_repeat('LongExampleName', 12);
    }
    if ($state === 'feedback') {
        session()->put('status', 'Example class answer saved.');
        session()->put('problem', 'Example room was booked after it was freed.');
    }
    $room = (object) ['id' => 11, 'name' => $state === 'long' ? str_repeat('LongRoomName', 12) : 'CE-305', 'capacity' => 40];
    $course = (object) ['code' => 'CMPE 451', 'title' => 'Example Engineering Laboratory'];
    $class = (object) ['id' => 21, 'course' => $course, 'room' => $room, 'day_of_week' => 'Wed', 'time_range' => '10:00–11:30 AM', 'instructor' => 'Example Instructor'];
    if ($page === 'home') {
        $meetings = [];
        if ($role === 'rep' && ! in_array($state, ['empty', 'no-section'], true)) {
            foreach (['waiting', 'f2f', 'online', 'freed', 'booked'] as $i => $status) {
                $date = $today->startOfDay()->addDays($i);
                $c = clone $class;
                $c->id += $i;
                $meetings[] = ['class' => $c, 'date' => $date, 'start' => $date->addHours($state === 'ended' ? 7 : 10), 'end' => $date->addHours($state === 'ended' ? 8 : 12), 'state' => $status, 'check' => null];
            }
        }
        $classRows = [$class];
        if ($state === 'priority') {
            // Supplied records deliberately unordered: all today's states, future
            // waiting/freed and answered meetings, plus an ended waiting record.
            $meetings = [];
            $classRows = [];
            foreach ([['waiting', 0], ['f2f', 0], ['online', 0], ['freed', 0], ['booked', 0], ['waiting', 1], ['freed', 2], ['f2f', 3], ['online', 4], ['waiting', -1]] as $i => [$status,$offset]) {
                $date = $today->startOfDay()->addDays($offset);
                $c = clone $class;
                $c->id = 100 + $i;
                $c->course = (object) ['code' => 'SAMPLE '.$i, 'title' => 'Example priority fixture '.$i];
                $meetings[] = ['class' => $c, 'date' => $date, 'start' => $date->addHours(10)->addMinutes($i * 10), 'end' => $date->addHours(12)->addMinutes($i * 10), 'state' => $status, 'check' => null];
                $classRows[] = $c;
            }
            $meetings = array_reverse($meetings);
        }

        return view('home', [
            'title' => 'Home', 'user' => $user, 'intro' => $role === 'rep' ? 'Book rooms and equipment for your block, and check which rooms are free.' : 'Example staff introduction.',
            'stat' => null, 'stats' => $role === 'rep' ? null : [['label' => 'Example waiting requests', 'value' => 3, 'note' => 'Example note'], ['label' => 'Example bookings today', 'value' => 2, 'note' => 'Example note'], ['label' => 'Example units in use', 'value' => 0, 'note' => 'Example note']],
            'classes' => $role === 'rep' ? collect($state === 'empty' ? [] : $classRows) : null, 'meetings' => $meetings,
            'ready' => $role === 'rep' ? [
                ['label' => 'See which rooms are free', 'url' => route('rooms')],
                ['label' => 'Book a room or equipment', 'url' => route('book')],
                ['label' => 'See and cancel my bookings', 'url' => route('bookings.mine')],
            ] : [['label' => 'Review requests', 'url' => route('requests')], ['label' => 'Timetable (new semester)', 'url' => route('manage.timetable')]],
            'comingNext' => $role === 'rep' ? [] : ['Reminders by Facebook Messenger, once the site is online'],
        ])->render();
    }
    $date = $state === 'closed' ? $today->next('Sunday')->startOfDay() : $today->startOfDay();
    $last = $today->startOfDay()->addDays(14);
    $isOpen = $state !== 'closed';
    $staff = $role !== 'rep';
    $search = in_array($state, ['search', 'none-free', 'started'], true) ? ['from' => $state === 'started' ? 480 : 600, 'to' => 660, 'free' => $state === 'none-free' ? [] : [$room], 'clashes' => [], 'started' => $state === 'started'] : null;
    $keep = $search ? ['from' => TimeSlots::hhmm($search['from']), 'to' => '11:00'] : [];
    $dayUrl = fn ($d) => route('rooms', ['date' => $d->toDateString()] + $keep).'#day';
    $item = fn ($kind, $status, $i) => [
        'kind' => $kind, 'start' => 600 + $i * 30, 'end' => 660 + $i * 30, 'room' => $i === 3 ? 'Equipment only' : $room->name, 'noRoom' => $i === 3,
        'text' => $staff ? 'Example booker' : 'Example event '.$i, 'title' => $staff ? 'Example booker: example purpose' : 'Example full event '.$i,
        'status' => $status, 'tag' => $status ? 'Room and equipment · '.RoomAvailability::STATUS_WORDS[$status] : null,
        'tip' => 'Example event detail with equipment · projector', 'check' => $i === 2 ? 'Online · room freed' : null, 'freed' => $i === 2,
        'url' => $status ? route('bookings.show', 100 + $i) : null, 'schedule_id' => 21 + $i, 'booked_elsewhere' => false,
    ];
    $items = [$item($staff ? 'booking' : 'class', null, 0), $item($staff ? 'booking' : 'class', 'Confirmed', 1), $item('booking', 'Pending', 2), $item('booking', 'InUse', 3)];
    if ($state === 'long-events') {
        foreach ($items as &$event) {
            $event['tip'] = str_repeat('Example authorized booking description; projector, microphones, extension cables and laboratory equipment. ', 12);
            $event['title'] .= ' — '.str_repeat('Example long purpose ', 10);
        }
        unset($event);
    }
    if (! $staff) {
        $items[0]['bookUrl'] = route('book', ['for' => 'class', 'session' => '21_'.$date->toDateString()]);
        $items[1]['url'] = null;
    }
    if ($state === 'empty') {
        $items = [];
    }
    $weeks = [];
    $start = $date->startOfMonth()->startOfWeek(CarbonInterface::SUNDAY);
    for ($i = 0; $i < 35; $i++) {
        $d = $start->addDays($i);
        $pick = $d->gte($today->startOfDay()) && $d->lte($last) && ! $d->isSunday();
        $weeks[intdiv($i, 7)][] = ['date' => $d, 'inMonth' => $d->month === 11, 'state' => $d->lt($today->startOfDay()) ? 'past' : ($d->gt($last) ? 'later' : ($d->isSunday() ? 'closed' : 'open')), 'isToday' => $d->isSameDay($today), 'isSelected' => $d->isSameDay($date), 'items' => $state === 'empty' ? [] : (($d->day % 3 === 0 || ($state === 'long-events' && $d->day === 5)) ? $items : array_slice($items, 0, 1)), 'url' => $pick ? $dayUrl($d) : null];
    }
    $busy = [['kind' => 'class', 'start' => 660, 'end' => 720, 'label' => $staff ? 'Example class · other section' : 'Your class · CMPE 451', 'status' => 'Confirmed', 'mine' => ! $staff, 'detail' => $staff ? ['Instructor: Example Instructor', 'Room booked by Example booker'] : ['Example Engineering Laboratory']], ['kind' => 'booking', 'start' => 780, 'end' => 840, 'label' => $staff ? 'Booked by Example booker' : 'Booked by other block', 'status' => 'Pending', 'mine' => false, 'detail' => $staff ? ['Purpose: Example laboratory'] : []]];
    $rows = [];
    foreach (['free', 'busy', 'blocked', 'full'] as $i => $type) {
        $r = clone $room;
        $r->id += $i;
        $r->name .= ' '.($i + 1);
        if ($i === 1) {
            $r->capacity = null;
        }
        $free = in_array($type, ['blocked', 'full'], true) ? [] : [[570, 660], [840, 1020]];
        $match = $search ? ($state !== 'none-free' && $i === 0) : null;
        $rows[] = ['room' => $r, 'free' => $free, 'busy' => $type === 'free' ? [] : $busy, 'allDay' => $type === 'blocked' ? 'Example maintenance' : null, 'match' => $match, 'clash' => $match === false ? $busy[0] : null, 'bookUrl' => ! $staff && $free && $match !== false ? route('book', ['for' => 'other', 'date' => $date->toDateString()] + $keep + ['room' => $r->id]) : null];
    }
    $data = [
        'department' => $department, 'staffView' => $staff, 'canBook' => ! $staff, 'date' => $date, 'lastDay' => $last,
        'calendar' => ['title' => 'November 2026', 'weeks' => $weeks, 'prevUrl' => null, 'nextUrl' => $state === 'boundary' ? null : $dayUrl($last)],
        'todayUrl' => route('rooms', $keep).'#day', 'prevUrl' => $state === 'boundary' ? null : $dayUrl($date->subDay()), 'nextUrl' => $state === 'boundary' ? null : $dayUrl($date->addDay()),
        'dateError' => $state === 'invalid-date' ? 'That date doesn’t look right. Showing today instead.' : null, 'isOpen' => $isOpen, 'inTerm' => $state !== 'outside-term',
        'dayItems' => $isOpen ? $items : [], 'slots' => TimeSlots::slots(450, 1260, 30), 'step' => 30, 'now' => $date->isToday() ? 545 : null,
        'search' => $search, 'searchError' => $state === 'invalid-time' ? 'Pick an end time that is after the start time.' : null, 'roomList' => collect($state === 'empty' ? [] : $rows),
    ];

    return view('rooms.index', $transform ? $transform($data) : $data)->render();
}

function availabilityContracts(): int
{
    $n = 0;
    $check = function ($ok, $message) use (&$n) {
        foundationsCheck((bool) $ok, $message);
        $n++;
    };
    foreach (availabilityCases() as $name => $case) {
        $html = availabilityRender($case);
        $x = foundationsDocument($html);
        [$page,$role,$state] = $case;
        $check($x->query('//main//h1')->length === 1, "$name one page heading");
        $ids = [];
        foreach ($x->query('//*[@id]') as $node) {
            $id = $node->getAttribute('id');
            $check(! isset($ids[$id]), "$name duplicate $id");
            $ids[$id] = true;
        }
        foreach ($x->query('//*[@aria-describedby]') as $node) {
            foreach (explode(' ', $node->getAttribute('aria-describedby')) as $id) {
                $check(isset($ids[$id]), "$name description target");
            }
        }
        $check(count(layoutConnection()->queries) === ($role === 'rep' ? 0 : 1), "$name no additional queries");
        foreach ($x->query('//form[@method="post"]') as $form) {
            $check($x->query('.//input[@name="_token"]', $form)->length === 1, "$name CSRF");
        }
        if ($page === 'rooms') {
            $check($x->query('//*[@id="day"]')->length === 1, "$name day fragment");
            $check($x->query('//main//form[@method="get"]')->length === (int) ($state !== 'closed'), "$name finder visibility");
            if ($role !== 'rep') {
                $check($x->query('//main//a[contains(@href,"/book?")]')->length === 0, "$name no staff booking links");
            }
            if ($state !== 'closed') {
                $check($x->query('//select[@id="from" and @name="from" and @required]')->length === 1, "$name start control");
                $check($x->query('//select[@id="to" and @name="to" and @required]')->length === 1, "$name end control");
            }
        } elseif ($role === 'rep') {
            foreach (['rooms', 'book', 'bookings.mine'] as $route) {
                $check($x->query('//main//a[@href="'.route($route).'"]')->length === 1, "$name shortcut $route");
            }
            $forms = $x->query('//main//form[contains(@action,"/classes/")]');
            $expected = in_array($state, ['empty', 'no-section'], true) ? 0 : ($state === 'priority' ? 8 : ($state === 'ended' ? 3 : 4));
            $check($forms->length === $expected, "$name meeting suppression");
            foreach ($forms as $form) {
                $check($x->query('.//button[@name="answer" and @value="f2f"]', $form)->length === 1, "$name f2f");
                $check($x->query('.//button[@name="answer" and @value="online"]',$form)->length === 1,"$name online");
            }
        }
    }
    Carbon\Carbon::setTestNow();
    CarbonImmutable::setTestNow();

    return $n;
}
