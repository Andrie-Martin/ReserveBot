<?php

use App\Models\Department;
use App\Models\Reservation;
use App\Models\Section;
use App\Models\User;
use App\Support\BookingRules;
use App\Support\StaffRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

require_once __DIR__.'/layout-runtime.php';

/** Lab operations fixtures (Today, Requests, staff actions on booking detail). Sample data only; no database, no mail. */
function opsCases(): array
{
    $cases = [];
    foreach (['lab_staff', 'admin'] as $role) {
        foreach (['normal', 'empty', 'late', 'long', 'other-day'] as $s) {
            $cases['today-'.$role.'-'.$s] = ['today', $role, $s];
        }
        foreach (['normal', 'empty', 'errors', 'long'] as $s) {
            $cases['requests-'.$role.'-'.$s] = ['requests', $role, $s];
        }
        foreach (['reject-errors', 'move-errors', 'cancel-errors', 'return-errors', 'late'] as $s) {
            $cases['show-'.$role.'-'.$s] = ['show', $role, $s];
        }
    }

    return $cases;
}

function opsReservation(int $id, string $status, int $addDays, array $o = []): Reservation
{
    $today = CarbonImmutable::now();
    $user = new User(['name' => $o['name'] ?? 'Example Booker', 'role' => 'rep', 'email' => 'booker@example.invalid']);
    $user->id = 70 + ($id % 7);
    $section = new Section(['name' => '3-2', 'program' => 'BSCPE']);
    $user->setRelation('section', $section);
    $room = array_key_exists('noroom', $o) ? null : (object) ['id' => 11, 'name' => $o['room'] ?? 'CE-305', 'capacity' => 40];
    $r = new Reservation(['purpose' => $o['purpose'] ?? 'Example lecture with slides', 'status' => $status, 'status_reason' => $o['reason'] ?? null,
        'other_items' => $o['other'] ?? null, 'room_id' => $room ? 11 : null]);
    $r->id = $id;
    $r->user_id = $user->id;
    $d = $today->startOfDay()->addDays($addDays);
    $r->start_time = $d->addHours($o['hour'] ?? 10);
    $r->end_time = $d->addHours(($o['hour'] ?? 10) + 2);
    $r->created_at = $today->subDays(1);
    $r->setRelation('user', $user);
    $r->setRelation('room', $room);
    $r->setRelation('classSchedule', $o['class'] ?? null);
    $r->setRelation('equipment', collect($o['equipment'] ?? [])->map(fn ($e) => (object) ['name' => $e[0], 'pivot' => (object) ['qty_requested' => $e[1]]]));
    $r->setRelation('reviewer', isset($o['reviewer']) ? (object) ['name' => 'Example Lab Staff'] : null);

    return $r;
}

function opsRender(array $case): string
{
    [$page, $role, $state] = $case;
    $now = CarbonImmutable::parse('2026-11-04 11:30:00', 'Asia/Manila');
    Carbon\Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);
    $long = str_contains($state, 'long');
    $routes = ['today' => 'today', 'requests' => 'requests', 'show' => 'bookings.show'];
    layoutState($role, ['route' => $routes[$page], 'waiting' => 3, 'name' => $long ? str_repeat('LongExampleName', 6) : null]);
    $user = auth()->user();
    $user->setRelation('department', new Department(['name' => 'Example Engineering']));
    $errors = new ViewErrorBag;
    $old = [];
    $staff = new StaffRules(30, $now);
    $name = $long ? str_repeat('LongBookerName', 5) : null;
    $room = $long ? str_repeat('LongRoomName', 7) : 'CE-305';
    $course = (object) ['code' => 'CMPE 451', 'title' => 'Example Engineering Laboratory'];
    $section = new Section(['name' => '3-2', 'program' => 'BSCPE']);
    $classRoom = (object) ['id' => 11, 'name' => $room, 'capacity' => 40];
    $class = (object) ['id' => 21, 'course' => $course, 'room' => $classRoom, 'section' => $section];
    $reviewer = ['reviewer' => true];
    $o = fn (array $extra = []) => $extra + ['room' => $room] + ($name ? ['name' => $name, 'purpose' => str_repeat('Long reason ', 12)] : []);

    if ($page === 'today') {
        $date = $now->startOfDay()->addDays($state === 'other-day' ? 2 : 0);
        $empty = $state === 'empty';
        $inUse = $empty ? collect() : collect([opsReservation(201, 'InUse', 0, $o(['hour' => 9, 'equipment' => [['Projector', 1]]] + $reviewer))]);
        $toHandOut = $empty ? collect() : collect([
            opsReservation(202, 'Confirmed', 0, $o(['hour' => $state === 'late' ? 8 : 13, 'equipment' => [['HDMI cable', 2]], 'other' => 'Bluetooth speaker x1'] + $reviewer)),
            opsReservation(203, 'Confirmed', 0, $o(['hour' => 15, 'class' => $class] + $reviewer)),
        ]);
        $waiting = $empty ? collect() : collect([opsReservation(204, 'Pending', 0, $o(['hour' => 16]))]);
        $done = $empty ? collect() : collect([
            opsReservation(205, 'Completed', 0, $o(['hour' => 7, 'reason' => 'All items returned'] + $reviewer)),
            opsReservation(206, 'NoShow', 0, $o(['hour' => 7] + $reviewer)),
            opsReservation(207, 'Rejected', 0, $o(['hour' => 14, 'reason' => 'The lab is closed for maintenance that day'] + $reviewer)),
        ]);
        $classes = $empty ? [] : array_map(fn ($i) => ['class' => $class, 'date' => $date, 'start' => $date->addHours(8 + $i * 2), 'end' => $date->addHours(10 + $i * 2), 'state' => ['waiting', 'f2f', 'online', 'freed'][$i], 'check' => $i === 2 ? (object) ['answeredBy' => (object) ['name' => 'Example Lab Staff']] : null], [0, 1, 2, 3]);
        session()->put('_old_input', []);
        app('view')->share('errors', $errors);

        return view('today.index', [
            'department' => $user->department, 'date' => $date,
            'prevUrl' => route('today', ['date' => $date->subDay()->toDateString()]), 'nextUrl' => route('today', ['date' => $date->addDay()->toDateString()]),
            'staff' => $staff, 'inUse' => $inUse, 'toHandOut' => $toHandOut, 'waiting' => $waiting, 'done' => $done, 'classes' => $classes,
        ])->render();
    }

    if ($page === 'requests') {
        $waiting = $state === 'empty' ? collect() : collect([
            opsReservation(301, 'Pending', 0, $o(['hour' => 9, 'equipment' => [['Projector', 1], ['HDMI cable', 1]], 'other' => 'Bluetooth speaker x1'])),
            opsReservation(302, 'Pending', 1, $o(['hour' => 13, 'class' => $class])),
            opsReservation(303, 'Pending', 3, $o(['hour' => 15, 'noroom' => true, 'equipment' => [['Portable speaker', 1]]])),
        ]);
        $decided = collect([
            opsReservation(310, 'Confirmed', 2, $o(['hour' => 10] + $reviewer)),
            opsReservation(311, 'Rejected', 2, $o(['hour' => 10, 'reason' => 'The lab is closed for maintenance that day'] + $reviewer)),
        ]);
        if ($state === 'errors') {
            $errors->put('reject301', new MessageBag(['reason' => ['Write a reason the rep can read.']]));
        }
        session()->put('_old_input', []);
        app('view')->share('errors', $errors);

        return view('requests.index', ['waiting' => $waiting, 'decided' => $decided])->render();
    }

    // show: staff actions on the booking detail page
    $rules = new BookingRules(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], 450, 1260, 30, 30, 240, 14, 60, 120, true, $now);
    $map = ['reject-errors' => ['Pending', 1], 'move-errors' => ['Confirmed', 2], 'cancel-errors' => ['Confirmed', 2], 'return-errors' => ['InUse', 0], 'late' => ['Confirmed', 0]];
    [$status, $add] = $map[$state];
    $r = opsReservation(55, $status, $add, ['hour' => $state === 'late' ? 8 : 13, 'class' => $class, 'equipment' => [['Projector', 1]]] + ($status !== 'Pending' ? $reviewer : []));
    $bagKey = ['reject-errors' => ['reject55', 'reason'], 'move-errors' => ['move', 'reason'], 'cancel-errors' => ['cancel', 'reason'], 'return-errors' => ['return55', 'note']][$state] ?? null;
    if ($bagKey) {
        $errors->put($bagKey[0], new MessageBag([$bagKey[1] => ['Example validation message for this form.']]));
        $old = ['reason' => 'Old example reason', 'note' => 'Old example note'];
    }
    session()->put('_old_input', $old);
    app('view')->share('errors', $errors);

    return view('bookings.show', [
        'reservation' => $r, 'history' => collect(), 'isStaff' => true, 'staff' => $staff,
        'canChange' => $staff->canChange($status, $r->end_time), 'moveDays' => $rules->days(), 'starts' => $rules->starts(), 'ends' => $rules->ends(),
        'rooms' => collect([$classRoom, (object) ['id' => 12, 'name' => 'CE-306', 'capacity' => 30]]),
        'canReview' => $status === 'Pending', 'canCancel' => false, 'tooLateToCancel' => false,
        'cancelDeadline' => $rules->cancelDeadline($r->start_time), 'cancelHours' => BookingRules::duration($rules->cancelMinutes),
    ])->render();
}

function opsContracts(): int
{
    $count = 0;
    $check = function ($ok, $message) use (&$count) {
        foundationsCheck((bool) $ok, $message);
        $count++;
    };
    foreach (opsCases() as $name => $case) {
        $html = opsRender($case);
        $x = foundationsDocument($html);
        $check($x->query('//main//h1')->length === 1, "$name one main heading");
        $ids = [];
        foreach ($x->query('//*[@id]') as $node) {
            $id = $node->getAttribute('id');
            $check(! isset($ids[$id]), "$name unique ID $id");
            $ids[$id] = true;
        }
        foreach ($x->query('//main//input[not(@type="hidden")]|//main//select|//main//textarea') as $node) {
            $id = $node->getAttribute('id');
            $check($id && $x->query('//label[@for="'.$id.'"]')->length === 1, "$name labelled $id");
        }
        foreach ($x->query('//main//form[@method="post"]') as $form) {
            $check($x->query('.//input[@name="_token" and @value="isolated-layout-csrf"]', $form)->length === 1, "$name csrf");
        }
        $check($x->query('//link[contains(@href,"css/booking.css?v=")]')->length === 1, "$name booking stylesheet");
        $check($x->query('//link[contains(@href,"css/operations.css?v=")]')->length === (int) ($case[0] !== 'show'), "$name operations stylesheet");
        foreach ($x->query('//form[contains(@action,"/no-show")]') as $form) {
            $check(str_contains($form->getAttribute('onsubmit'), 'confirm('), "$name no-show keeps its confirmation");
        }
    }

    return $count;
}
