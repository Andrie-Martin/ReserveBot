<?php

require_once __DIR__.'/layout-runtime.php';

/** Rooms, equipment and booking-rules management fixtures. Sample data only; no database, no mail. */
function manageCases(): array
{
    $cases = [];
    foreach ([['rooms', ['normal', 'empty', 'errors', 'long']], ['room-edit', ['normal', 'shared', 'off', 'errors', 'none', 'long']],
        ['equipment', ['normal', 'empty', 'errors', 'long']], ['equipment-edit', ['normal', 'over', 'off', 'errors', 'none', 'long']],
        ['rules', ['normal', 'errors', 'term-error', 'long']]] as [$page, $states]) {
        foreach (['lab_staff', 'admin'] as $role) {
            foreach ($states as $s) {
                $cases['manage-'.$page.'-'.$role.'-'.$s] = [$page, $role, $s];
            }
        }
    }

    return $cases;
}

function manageBookings(string $kind, bool $long, bool $none, array $over = []): Illuminate\Support\Collection
{
    if ($none) {
        return collect();
    }
    $now = Carbon\CarbonImmutable::now();
    $who = $long ? str_repeat('LongBookerName', 5) : 'Ana Cruz';

    return collect([[401, 'Pending', 1], [402, 'Confirmed', 3], [403, 'Confirmed', 5]])->map(function ($row) use ($now, $who, $kind) {
        [$id, $status, $add] = $row;
        $u = new App\Models\User(['name' => $who, 'role' => 'rep']);
        $u->setRelation('section', new App\Models\Section(['name' => '3-2', 'program' => 'BSCPE']));
        $r = new App\Models\Reservation(['purpose' => 'Example lecture with slides', 'status' => $status]);
        $r->id = $id;
        $r->start_time = $now->startOfDay()->addDays($add)->addHours(13);
        $r->end_time = $now->startOfDay()->addDays($add)->addHours(15);
        $r->setRelation('user', $u);
        $r->setRelation('room', (object) ['id' => 11, 'name' => 'CE-305']);
        $r->asked = 2;

        return $r;
    });
}

function manageRender(array $case): string
{
    [$page, $role, $state] = $case;
    $now = Carbon\CarbonImmutable::parse('2026-11-04 11:30:00', 'Asia/Manila');
    Carbon\Carbon::setTestNow($now); Carbon\CarbonImmutable::setTestNow($now);
    $long = $state === 'long';
    $routes = ['rooms' => 'manage.rooms', 'room-edit' => 'manage.rooms.edit', 'equipment' => 'manage.equipment', 'equipment-edit' => 'manage.equipment.edit', 'rules' => 'manage.rules'];
    layoutState($role, ['route' => $routes[$page], 'waiting' => 3]);
    $user = auth()->user();
    $user->setRelation('department', new App\Models\Department(['name' => $long ? str_repeat('LongDepartmentName', 5) : 'Example Engineering']));
    $errors = new Illuminate\Support\ViewErrorBag;
    $old = [];
    $bag = fn (array $m) => new Illuminate\Support\MessageBag($m);
    $msg = ['Example validation message for this form.'];
    $name = fn (string $n) => $long ? str_repeat('LongExampleName', 5) : $n;
    $errs = $state === 'errors';

    if ($page === 'rooms') {
        $rooms = $state === 'empty' ? collect() : collect([
            tap(new App\Models\Room(['name' => $name('CE-305'), 'building' => 'CEA Building', 'capacity' => 40, 'fixed_equipment_note' => $long ? str_repeat('Long note words ', 8) : 'Has a TV and aircon']), fn ($r) => [$r->id = 11, $r->is_active = true, $r->departments_count = 2]),
            tap(new App\Models\Room(['name' => 'CE-306', 'building' => null, 'capacity' => null, 'fixed_equipment_note' => null]), fn ($r) => [$r->id = 12, $r->is_active = true, $r->departments_count = 1]),
            tap(new App\Models\Room(['name' => 'CE-307', 'building' => 'NDC', 'capacity' => 30, 'fixed_equipment_note' => 'Projector is broken']), fn ($r) => [$r->id = 13, $r->is_active = false, $r->departments_count = 1]),
        ]);
        if ($errs) {
            $errors->put('add', $bag(['name' => ['Another room already has that name.'], 'capacity' => ['Seats must be a whole number from 0 to 1000.']]));
            $old = ['name' => 'Old example room', 'building' => 'Old building', 'capacity' => '2000', 'note' => 'Old note'];
        }
        session()->put('_old_input', $old);
        app('view')->share('errors', $errors);

        return view('manage.rooms', ['department' => $user->department, 'rooms' => $rooms])->render();
    }

    if ($page === 'room-edit') {
        $room = tap(new App\Models\Room(['name' => $name('CE-305'), 'building' => 'CEA Building', 'capacity' => 40, 'fixed_equipment_note' => 'Has a TV and aircon']), fn ($r) => [$r->id = 11, $r->is_active = $state !== 'off']);
        if ($errs) {
            $errors->put('default', $bag(['name' => ['Another room already has that name.'], 'capacity' => ['Seats must be a whole number from 0 to 1000.']]));
            $old = ['_token' => 'x', 'name' => 'Old example room', 'building' => 'Old building', 'capacity' => '2000', 'note' => 'Old note'];
        }
        session()->put('_old_input', $old);
        app('view')->share('errors', $errors);

        return view('manage.room_edit', ['room' => $room, 'sharedWith' => in_array($state, ['shared', 'long'], true) ? collect(['Electronics Engineering', 'Computer Engineering']) : collect(),
            'upcoming' => manageBookings('room', $long, $state === 'none')])->render();
    }

    $cat = function (int $id, string $n, array $items) {
        $c = new App\Models\EquipmentCategory(['name' => $n]);
        $c->id = $id;
        $c->setRelation('equipment', collect($items)->map(fn ($i) => tap(new App\Models\Equipment(['name' => $i[0], 'total_qty' => $i[1], 'max_per_request' => $i[2], 'category_id' => $id]), fn ($e) => [$e->id = $i[4], $e->is_active = $i[3]])));

        return $c;
    };
    $categories = $state === 'empty' ? collect() : collect([
        $cat(1, $name('Audio visual'), [[$name('Projector'), 4, 1, true, 21], ['HDMI cable', 10, 4, true, 22], ['Old VGA cable', 0, 1, false, 23]]),
        $cat(2, 'Presentation', [['Portable speaker', 3, 2, true, 24]]),
        $cat(3, 'Empty category', []),
    ]);

    if ($page === 'equipment') {
        if ($errs) {
            $errors->put('item', $bag(['name' => ['You already have an item with that name.'], 'total_qty' => ['Stock must be a whole number from 0 to 9999.']]));
            $errors->put('category', $bag(['name' => ['You already have a category with that name.']]));
            $errors->put('category1', $bag(['name' => ['Keep the name under 60 characters.']]));
            $old = ['name' => 'Old example name', 'category_id' => '2', 'total_qty' => '99999', 'max_per_request' => '4'];
        }
        session()->put('_old_input', $old);
        app('view')->share('errors', $errors);

        return view('manage.equipment', ['categories' => $categories])->render();
    }

    if ($page === 'equipment-edit') {
        $item = tap(new App\Models\Equipment(['name' => $name('Projector'), 'total_qty' => 4, 'max_per_request' => 1, 'category_id' => 1]), fn ($e) => [$e->id = 21, $e->is_active = $state !== 'off']);
        if ($errs) {
            $errors->put('default', $bag(['total_qty' => ['Stock must be a whole number from 0 to 9999.']]));
            $old = ['_token' => 'x', 'name' => 'Old example name', 'category_id' => '2', 'total_qty' => '99999', 'max_per_request' => '4'];
        }
        session()->put('_old_input', $old);
        app('view')->share('errors', $errors);
        $upcoming = manageBookings('item', $long, $state === 'none');

        return view('manage.equipment_edit', ['item' => $item, 'categories' => $categories ?: collect(), 'upcoming' => $upcoming, 'over' => $state === 'over' ? [402, 403] : []])->render();
    }

    // rules
    $config = new App\Models\DepartmentConfig(App\Models\DepartmentConfig::DEFAULTS + ['department_id' => 73]);
    $terms = collect([['1st Semester', '2026-08-10', '2026-12-19'], ['2nd Semester', '2027-01-11', '2027-05-22']])->map(fn ($t, $i) => tap(new App\Models\Term(['name' => $t[0], 'start_date' => $t[1], 'end_date' => $t[2]]), fn ($m) => $m->id = $i + 1));
    if ($state === 'term-error') {
        // Only the Semester-dates form failed: its old input (and CSRF token) is flashed, the rules form's fields are not
        $errors->put('term1', $bag(['end_date' => ['The last day has to be after the first day.']]));
        $old = ['_token' => 'x', 'start_date' => '2026-12-01', 'end_date' => '2026-11-01'];
    }
    if ($errs) {
        $errors->put('rules', $bag(['open_days' => ['Pick at least one open day.'], 'max_booking_minutes' => ['The longest booking can’t be shorter than the shortest.']]));
        $errors->put('term1', $bag(['end_date' => ['The last day has to be after the first day.']]));
        $old = ['_token' => 'x', 'open_days' => ['Mon', 'Fri'], 'business_hours_start' => '08:00', 'business_hours_end' => '17:00', 'slot_minutes' => '30', 'min_booking_minutes' => '120', 'max_booking_minutes' => '60', 'max_days_ahead' => '14', 'min_notice_minutes' => '60', 'cancel_cutoff_minutes' => '120', 'no_show_grace_minutes' => '30', 'start_date' => '2026-12-01', 'end_date' => '2026-11-01'];
    }
    session()->put('_old_input', $old);
    app('view')->share('errors', $errors);

    return view('manage.rules', ['config' => $config, 'terms' => $terms])->render();
}

function manageContracts(): int
{
    $count = 0;
    $check = function ($ok, $message) use (&$count) { foundationsCheck((bool) $ok, $message); $count++; };
    foreach (manageCases() as $name => $case) {
        $html = manageRender($case);
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
        $check($x->query('//nav[@aria-label="Manage"]//a')->length === 4, "$name four manage tabs");
        $check($x->query('//nav[@aria-label="Manage"]//a[contains(@class,"on")]')->length === 1, "$name one current tab");
        if (str_starts_with($name, 'manage-rules-')) {
            // The approval checkbox and the open days belong to the rules form: only a failure of THAT form may change them.
            $approval = $x->query('//input[@name="requires_approval"]')->item(0);
            $ticked = $x->query('//input[@name="open_days[]" and @checked]')->length;
            if (str_ends_with($name, '-term-error')) {
                $check($approval->hasAttribute('checked'), "$name a failed semester-dates save keeps the saved approval ticked");
                $check($ticked === 6, "$name a failed semester-dates save keeps the saved open days ticked");
                $check($x->query('//input[@id="start-1" and @value="2026-12-01"]')->length === 1 && $x->query('//input[@id="end-1" and @value="2026-11-01"]')->length === 1, "$name the entered dates are kept");
                $check($x->query('//input[@id="end-1" and @autofocus and @aria-invalid="true" and @aria-describedby="term-1-errors"]')->length === 1, "$name the invalid last day takes focus and is described by its error");
                $check($x->query('//div[@id="term-1-errors"]//li')->length === 1, "$name the term error summary exists for aria-describedby");
                $check($x->query('//*[@autofocus]')->length === 1, "$name exactly one autofocus (the invalid field)");
            } elseif (str_ends_with($name, '-errors')) {
                $check(! $approval->hasAttribute('checked'), "$name an intentionally unticked approval stays unticked after a failed rules save");
                $check($ticked === 2, "$name the submitted open days (Mon, Fri) stay ticked after a failed rules save");
            } else {
                $check($approval->hasAttribute('checked') && $ticked === 6, "$name saved approval and open days shown");
                $check($x->query('//*[@autofocus]')->length === 0 && $x->query('//*[@aria-invalid]')->length === 0, "$name an ordinary load has no autofocus and no aria-invalid");
            }
        }
    }

    return $count;
}
