<?php

require_once __DIR__.'/layout-runtime.php';

/** Signed class-check page fixtures (the page the email buttons open). Sample data only; nothing is answered or sent. */
function checkCases(): array
{
    $cases = [];
    foreach (['waiting', 'f2f', 'online', 'freed', 'picked', 'booked', 'ended', 'stranger', 'status', 'problem', 'long'] as $s) {
        $cases['check-guest-'.$s] = ['guest', $s];
    }
    foreach (['waiting', 'online', 'ended', 'stranger'] as $s) {
        $cases['check-rep-'.$s] = ['rep', $s];
    }
    $cases['check-lab_staff-waiting'] = ['lab_staff', 'waiting'];

    return $cases;
}

function checkRender(array $case): string
{
    [$role, $state] = $case;
    $now = Carbon\CarbonImmutable::parse('2026-11-04 11:30:00', 'Asia/Manila');
    Carbon\Carbon::setTestNow($now); Carbon\CarbonImmutable::setTestNow($now);
    $long = $state === 'long';
    layoutState($role, ['route' => 'class-check', 'waiting' => 0]);
    if ($role !== 'guest') { auth()->user()->setRelation('department', new App\Models\Department(['name' => 'Example Engineering'])); }
    $errors = new Illuminate\Support\ViewErrorBag;
    session()->put('_old_input', []);
    if ($state === 'status') { session()->flash('status', 'Saved: CMPE 402 is face-to-face, so CEA300 stays yours.'); }
    if ($state === 'problem') { session()->flash('problem', 'Someone booked CEA300 in the meantime, so it can’t be taken back.'); }
    app('view')->share('errors', $errors);

    $room = (object) ['id' => 11, 'name' => $long ? str_repeat('LongRoomName', 6) : 'CEA300'];
    $course = (object) ['code' => $long ? str_repeat('CMPE4020', 5) : 'CMPE 402', 'title' => $long ? str_repeat('Project Management ', 6) : 'Project Management'];
    $class = new App\Models\ClassSchedule(['entry_type' => 'Class']);
    $class->id = 21;
    $class->setRelation('course', $course);
    $class->setRelation('room', $room);
    $class->setRelation('section', new App\Models\Section(['name' => $long ? str_repeat('BSCPE4', 8) : '4-1', 'program' => 'BSCPE']));
    $stateKey = ['f2f' => 'f2f', 'online' => 'online', 'picked' => 'online', 'freed' => 'freed', 'booked' => 'booked'][$state] ?? ($role === 'rep' && $state === 'online' ? 'online' : 'waiting');
    if ($state === 'online') { $stateKey = 'online'; }
    $ended = $state === 'ended';
    $isRep = $state !== 'stranger';
    $start = $now->startOfDay()->addHours($ended ? 8 : 13);
    $end = $start->addHours(2);
    $rep = new App\Models\User(['name' => $long ? str_repeat('LongRepName', 6) : 'Ana Cruz', 'role' => 'rep']);

    return view('classes.check', [
        'class' => $class, 'name' => App\Services\ClassChecks::name($class), 'start' => $start,
        'span' => App\Support\TimeSlots::span($start->hour * 60 + $start->minute, $end->hour * 60 + $end->minute),
        'state' => $stateKey, 'label' => App\Services\ClassChecks::LABELS[$stateKey],
        'picked' => $state === 'picked' ? 'online' : null,
        'open' => ! $ended && $stateKey !== 'booked' && $isRep, 'isRep' => $isRep, 'rep' => $rep,
    ])->render();
}

function checkContracts(): int
{
    $count = 0;
    $check = function ($ok, $message) use (&$count) { foundationsCheck((bool) $ok, $message); $count++; };
    foreach (checkCases() as $name => $case) {
        $html = checkRender($case);
        $x = foundationsDocument($html);
        $check($x->query('//main//h1')->length === 1, "$name one main heading");
        $ids = [];
        foreach ($x->query('//*[@id]') as $node) { $id = $node->getAttribute('id'); $check(! isset($ids[$id]), "$name unique ID $id"); $ids[$id] = true; }
        $forms = $x->query('//main//form');
        $open = $x->query('//button[@name="answer"]')->length;
        $check($forms->length === ($open ? 1 : 0), "$name answer form only while the class is open");
        $check($open === 0 || $open === 2, "$name two answer buttons or none");
        foreach ($forms as $form) {
            $check($form->getAttribute('method') === 'post', "$name answers by POST only");
            $check($x->query('.//input[@name="_token" and @value="isolated-layout-csrf"]', $form)->length === 1, "$name csrf");
        }
        $check($x->query('//a[contains(@href,"answer=")]')->length === 0, "$name no answering link (GET never answers)");
        $check($x->query('//link[contains(@href,"css/check.css?v=")]')->length === 1, "$name check stylesheet");
        $check($x->query('//*[@role="status"]')->length === (int) ($case[1] === 'status'), "$name status message");
        $check($x->query('//*[@role="alert"]')->length === (int) ($case[1] === 'problem'), "$name problem message");
    }

    return $count;
}
