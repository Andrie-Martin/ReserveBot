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

/** Explicit sample data only. Never calls controllers, opens PDO, saves models or sends mail. */
function bookingCases(): array
{
    $cases = [];
    foreach (['class', 'other', 'error', 'no-sessions', 'no-class'] as $s) {
        $cases['book-step1-'.$s] = ['book', 'rep', 'step1-'.$s];
    }
    foreach (['class', 'class-taken', 'other', 'other-nofree', 'errors', 'approval', 'long'] as $s) {
        $cases['book-step2-'.$s] = ['book', 'rep', 'step2-'.$s];
    }
    foreach (['empty', 'normal', 'long'] as $s) {
        $cases['mine-'.$s] = ['mine', 'rep', $s];
    }
    foreach (['pending', 'confirmed', 'late', 'rejected', 'cancelled', 'errors', 'history', 'equipment', 'eqonly', 'long'] as $s) {
        $cases['show-rep-'.$s] = ['show', 'rep', $s];
    }
    // Staff views share the same markup; they are checked for parity here and designed in Phase 05.
    foreach (['lab_staff', 'admin'] as $role) {
        foreach (['pending', 'confirmed', 'inuse'] as $s) {
            $cases['show-'.$role.'-'.$s] = ['show', $role, $s];
        }
    }

    return $cases;
}

function bookingRender(array $case): string
{
    [$page, $role, $state] = $case;
    $today = CarbonImmutable::parse('2026-11-04 09:05:00', 'Asia/Manila');
    Carbon\Carbon::setTestNow($today);
    CarbonImmutable::setTestNow($today);
    $long = str_contains($state, 'long');
    $routes = ['book' => 'book', 'mine' => 'bookings.mine', 'show' => 'bookings.show'];
    layoutState($role, ['route' => $routes[$page], 'name' => $long ? str_repeat('LongExampleName', 6) : null]);
    $user = auth()->user();
    $section = new Section(['name' => '3-2', 'program' => 'BSCPE']);
    $section->id = 12;
    $user->setRelation('department', new Department(['name' => 'Example Engineering']));
    $user->setRelation('section', $role === 'rep' ? $section : null);

    $errors = new ViewErrorBag;
    $old = [];
    // Approval notice shows on some step-2 states; everything else uses direct confirmation.
    $needsApproval = in_array($state, ['step2-approval', 'step2-other', 'step2-errors'], true);
    $rules = new BookingRules(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], 450, 1260, 30, 30, 240, 14, 60, 120, $needsApproval, $today);

    $room = fn (int $id, string $name, ?int $cap = 40) => (object) ['id' => $id, 'name' => $name, 'capacity' => $cap];
    $course = (object) ['code' => 'CMPE 451', 'title' => 'Example Engineering Laboratory'];
    $classRoom = $room(11, $long ? str_repeat('LongRoomName', 8) : 'CE-305');
    $class = (object) ['id' => 21, 'course' => $course, 'room' => $classRoom, 'section' => $section];

    if ($page === 'book') {
        $view = ['user' => $user, 'rules' => $rules, 'canBookClass' => $state !== 'step1-no-class', 'picked' => [], 'whenError' => null];
        $day = $today->startOfDay()->addDays(2);
        if (str_starts_with($state, 'step1-')) {
            $sessions = [];
            if (! in_array($state, ['step1-no-sessions', 'step1-no-class'], true)) {
                foreach ([2, 3, 7] as $i => $add) {
                    $d = $today->startOfDay()->addDays($add);
                    $sessions[] = ['key' => (30 + $i).'_'.$d->toDateString(), 'day' => $d, 'class' => $class, 'from' => 600, 'to' => 720];
                }
            }
            $view += ['for' => in_array($state, ['step1-other', 'step1-no-sessions', 'step1-no-class'], true) ? 'other' : 'class',
                'when' => null, 'sessions' => $sessions, 'days' => $rules->days()];
            if ($state === 'step1-error') {
                $view['whenError'] = 'That time is too soon: bookings must start at least 1 hour from now.';
                $view['picked'] = ['date' => $day->toDateString(), 'from' => '09:30', 'to' => '10:00'];
            }
        } else {
            $isClass = in_array($state, ['step2-class', 'step2-class-taken'], true);
            $session = $isClass ? ['class' => $class, 'key' => '30_'.$day->toDateString()] : null;
            $params = $isClass ? ['for' => 'class', 'session' => '30_'.$day->toDateString()]
                : ['for' => 'other', 'date' => $day->toDateString(), 'from' => '13:30', 'to' => '15:00'];
            $item = fn (int $id, string $name, int $free, int $max) => (object) ['id' => $id, 'name' => $name, 'free' => $free, 'max_per_request' => $max];
            $cat = fn (string $name, array $items) => (object) ['name' => $name, 'equipment' => collect($items)];
            $categories = collect([
                $cat('Projectors and display', [$item(1, 'Projector', 2, 2), $item(2, 'HDMI cable', 4, 2), $item(3, 'Extension cord', 0, 2)]),
                $cat('Audio', [$item(4, 'Portable speaker', 1, 1), $item(5, 'Wireless microphone', 3, 2)]),
            ]);
            $view += ['for' => $params['for'], 'when' => ['day' => $day, 'from' => $isClass ? 600 : 810, 'to' => $isClass ? 720 : 900, 'session' => $session, 'params' => $params],
                'needDefault' => 'both', 'changeUrl' => route('book', $params + ['change' => 1]), 'categories' => $categories];
            if ($isClass) {
                $view += ['classRoom' => $classRoom, 'classRoomTaken' => $state === 'step2-class-taken'];
            } else {
                $busy = fn () => ['label' => 'Example class · CMPE 452', 'start' => 810, 'end' => 900];
                $rooms = $state === 'step2-other-nofree'
                    ? [['room' => $room(12, 'CE-306', 30), 'clash' => $busy()], ['room' => $room(13, 'CE-307', null), 'clash' => $busy()]]
                    : [['room' => $room(12, 'CE-306', 30), 'clash' => null], ['room' => $room(13, 'CE-307', null), 'clash' => null],
                        ['room' => $room(14, $long ? str_repeat('LongRoomName', 8) : 'CE-308', 50), 'clash' => null], ['room' => $room(15, 'CE-309', 24), 'clash' => $busy()]];
                $view += ['rooms' => $rooms, 'preRoom' => 0];
            }
            if ($state === 'step2-errors') {
                $errors->put('default', new MessageBag(['purpose' => ['Write the reason for your booking.'], 'qty' => ['Only 2 Projector are free then.']]));
                $old = ['need' => 'both', 'room' => '13', 'purpose' => 'Old example reason', 'other_items' => 'Old example item', 'qty' => [1 => '2', 4 => '1']];
            }
        }
        session()->put('_old_input', $old);
        app('view')->share('errors', $errors);

        return view('bookings.create', $view)->render();
    }

    // Reservations
    $booker = new User(['name' => 'Example Booker', 'role' => 'rep', 'email' => 'booker@example.invalid']);
    $booker->id = 77;
    $booker->setRelation('section', $section);
    $mk = function (int $id, string $status, int $addDays, array $extra = []) use ($today, $booker, $classRoom, $long) {
        $r = new Reservation(['purpose' => ($long ? str_repeat('Long reason ', 12) : 'Example lecture with slides'), 'status' => $status, 'status_reason' => null,
            'other_items' => $extra['other'] ?? null, 'room_id' => array_key_exists('noroom', $extra) ? null : 11]);
        $r->id = $id;
        $r->user_id = $booker->id;
        $d = $today->startOfDay()->addDays($addDays);
        $r->start_time = $d->addHours(13);
        $r->end_time = $d->addHours(15);
        $r->created_at = $today->subDays(1);
        $r->setRelation('user', $booker);
        $r->setRelation('room', array_key_exists('noroom', $extra) ? null : $classRoom);
        $r->setRelation('classSchedule', $extra['class'] ?? null);
        $eq = collect($extra['equipment'] ?? [])->map(fn ($e) => (object) ['name' => $e[0], 'pivot' => (object) ['qty_requested' => $e[1]]]);
        $r->setRelation('equipment', $eq);
        $r->setRelation('reviewer', $extra['reviewer'] ?? null);
        foreach ($extra['attrs'] ?? [] as $k => $v) {
            $r->$k = $v;
        }

        return $r;
    };
    $reviewer = (object) ['name' => 'Example Lab Staff'];

    if ($page === 'mine') {
        $upcoming = collect();
        $past = collect();
        if ($state !== 'empty') {
            $upcoming = collect([
                $mk(101, 'Pending', 1, ['equipment' => [['Projector', 1], ['HDMI cable', 1]], 'other' => 'Bluetooth speaker x1']),
                $mk(102, 'Confirmed', 2, ['class' => (object) ['course' => $course]]),
                $mk(103, 'InUse', 0, ['noroom' => true, 'equipment' => [['Portable speaker', 1]]]),
            ]);
            $past = collect([
                $mk(90, 'Completed', -2, ['attrs' => ['status_reason' => 'All items returned']]),
                $mk(91, 'Rejected', -3, ['attrs' => ['status_reason' => 'The lab is closed for maintenance that day']]),
                $mk(92, 'Cancelled', -4, ['attrs' => ['status_reason' => 'Our class was moved online']]),
                $mk(93, 'NoShow', -5),
            ]);
        }
        session()->put('_old_input', []);
        app('view')->share('errors', $errors);

        return view('bookings.mine', ['upcoming' => $upcoming, 'past' => $past, 'rules' => $rules])->render();
    }

    // show
    $map = ['pending' => ['Pending', 1], 'confirmed' => ['Confirmed', 3], 'late' => ['Confirmed', 0], 'rejected' => ['Rejected', 2], 'cancelled' => ['Cancelled', 2],
        'errors' => ['Pending', 1], 'history' => ['Confirmed', 3], 'equipment' => ['Pending', 1], 'eqonly' => ['Confirmed', 1], 'long' => ['Pending', 1], 'inuse' => ['InUse', 0]];
    [$status, $add] = $map[$state];
    $extra = [];
    if (in_array($state, ['equipment', 'long'], true)) {
        $extra = ['equipment' => [['Projector', 2], ['HDMI cable', 1]], 'other' => 'Bluetooth speaker x1'];
    }
    if ($state === 'eqonly') {
        $extra = ['noroom' => true, 'equipment' => [['Portable speaker', 1]]];
    }
    if (in_array($state, ['confirmed', 'history', 'rejected', 'inuse'], true)) {
        $extra['reviewer'] = $reviewer;
    }
    if (in_array($state, ['confirmed', 'history'], true)) {
        $extra['class'] = $class;
    }
    $r = $mk(55, $status, $add, $extra);
    if ($state === 'rejected') {
        $r->status_reason = 'The lab is closed for maintenance that day';
    }
    if ($state === 'cancelled') {
        $r->status_reason = 'Our class was moved online';
    }

    $isStaff = $role !== 'rep';
    $staffRules = new StaffRules(30, $today);
    $cancellable = ! $isStaff && in_array($status, Reservation::CANCELLABLE_STATUSES, true);
    $log = fn (string $when, string $text, ?string $why) => new class($today->subHours(5), $text, $why)
    {
        public function __construct(public $created_at, private string $text, public ?string $reason)
        {
            $this->changedBy = (object) ['name' => 'Example Lab Staff'];
        }

        public $changedBy;

        public function describe(): string
        {
            return $this->text;
        }

        public function hasOwnReason(): bool
        {
            return filled($this->reason);
        }
    };
    $history = $state === 'history' ? collect([$log('', 'moved the booking to CE-306', 'The projector in CE-305 is broken'), $log('', 'approved it', null)]) : collect();
    if ($state === 'errors') {
        $errors->put('default', new MessageBag(['reason' => ['Write why you are cancelling.']]));
    }
    session()->put('_old_input', []);
    if ($state === 'late') {
        session()->forget('problem');
    }
    app('view')->share('errors', $errors);

    return view('bookings.show', [
        'reservation' => $r, 'history' => $history, 'isStaff' => $isStaff, 'staff' => $isStaff ? $staffRules : null,
        'canChange' => $isStaff && $staffRules->canChange($status, $r->end_time),
        'moveDays' => $isStaff ? $rules->days() : [], 'starts' => $rules->starts(), 'ends' => $rules->ends(),
        'rooms' => $isStaff ? collect([$classRoom, (object) ['id' => 12, 'name' => 'CE-306', 'capacity' => 30]]) : collect(),
        'canReview' => $isStaff && $status === 'Pending',
        'canCancel' => $cancellable && $state !== 'late', 'tooLateToCancel' => $cancellable && $state === 'late',
        'cancelDeadline' => $rules->cancelDeadline($r->start_time), 'cancelHours' => BookingRules::duration($rules->cancelMinutes),
    ])->render();
}

function bookingContracts(): int
{
    $count = 0;
    $check = function ($ok, $message) use (&$count) {
        foundationsCheck((bool) $ok, $message);
        $count++;
    };
    foreach (bookingCases() as $name => $case) {
        $html = bookingRender($case);
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
            if ($node->getAttribute('type') === 'radio') {
                $check($x->query('ancestor::label', $node)->length === 1, "$name radio wrapped by label");

                continue;
            }
            $check($id && $x->query('//label[@for="'.$id.'"]')->length === 1, "$name labelled $id");
        }
        foreach ($x->query('//main//form[@method="post"]') as $form) {
            $check($x->query('.//input[@name="_token" and @value="isolated-layout-csrf"]', $form)->length === 1, "$name csrf");
        }
        $check($x->query('//link[contains(@href,"css/booking.css?v=")]')->length === 1, "$name booking stylesheet");
        if ($case[0] === 'book') {
            $step2 = str_contains($name, 'step2');
            $check($x->query('//form[@action="'.route('book').'" and @method="get"]')->length === (int) ! $step2, "$name step 1 GET form");
            $check($x->query('//form[@action="'.route('book.store').'" and @method="post"]')->length === (int) $step2, "$name step 2 POST form");
            if ($step2) {
                $check($x->query('//input[@name="need"]')->length === 3, "$name need choices");
                $check($x->query('//textarea[@name="purpose" and @required and @maxlength="150"]')->length === 1, "$name purpose field");
                $check($x->query('//a[contains(@href,"change=1")]')->length >= 1, "$name change link");
                // Missing capacities leave no text at all; a recorded one is still shown.
                $check(! str_contains($html, 'Capacity not specified'), "$name no capacity placeholder");
                foreach ($x->query('//*[contains(@class,"room-opt-name")]') as $nameNode) {
                    $text = trim(preg_replace('/\s+/', ' ', $nameNode->textContent));
                    $check(preg_match('/^\S+( \d+ seats?)?$/', $text) === 1, "$name room name is the name alone or name plus seats: $text");
                    if ($text === 'CE-307') {
                        $check($x->query('.//*[contains(@class,"room-opt-cap")]', $nameNode)->length === 0, "$name empty capacity renders no element");
                    }
                    if ($text === 'CE-306 30 seats') {
                        $check($x->query('.//*[contains(@class,"room-opt-cap")]', $nameNode)->length === 1, "$name known capacity kept");
                    }
                }
                // The unavailable rooms are listed in the page itself: no disclosure, no controls, one row per room.
                $check($x->query('//details[contains(@class,"busy-rooms")]')->length === 0 && ! str_contains($html, 'busy-rooms'), "$name no busy-rooms disclosure");
                $panel = $x->query('//section[contains(@class,"unavail")]');
                if ($panel->length) {
                    $rows = $x->query('.//li[contains(@class,"unavail__row")]', $panel->item(0));
                    $check($rows->length >= 1, "$name unavailable rooms listed");
                    $check($x->query('.//input|.//select|.//button|.//textarea|.//a', $panel->item(0))->length === 0, "$name unavailable rooms have no controls");
                    $check($x->query('.//*[contains(@class,"count")]', $panel->item(0))->item(0)->textContent == $rows->length, "$name unavailable count matches the rows");
                    foreach ($rows as $row) {
                        foreach (['unavail__room', 'unavail__reason', 'unavail__time'] as $part) {
                            $check($x->query('.//*[contains(@class,"'.$part.'")]', $row)->length === 1, "$name row has $part");
                        }
                    }
                }
            } else {
                foreach (['date', 'from', 'to'] as $f) {
                    $check($x->query('//select[@name="'.$f.'"]')->length === 1, "$name $f field");
                }
            }
        }
        if ($case[0] === 'show' && $case[1] === 'rep') {
            $check($x->query('//form[contains(@action,"/staff-cancel") or contains(@action,"/approve") or contains(@action,"/move")]')->length === 0, "$name no staff-only controls for representatives");
        }
    }

    return $count;
}
