<?php

require_once __DIR__.'/layout-runtime.php';

/** Signed class-check page fixtures (the page the email buttons open). Sample data only; nothing is answered or sent. */
function checkCases(): array
{
    $cases = [];
    foreach (['waiting', 'f2f', 'online', 'freed', 'picked', 'freed-picked', 'online-picked', 'mismatch', 'stale', 'booked', 'ended', 'stranger', 'status', 'problem', 'long'] as $s) {
        $cases['check-guest-'.$s] = ['guest', $s];
    }
    foreach (['waiting', 'online', 'ended', 'stranger'] as $s) {
        $cases['check-rep-'.$s] = ['rep', $s];
    }
    $cases['check-lab_staff-waiting'] = ['lab_staff', 'waiting'];

    return $cases;
}

/** The state a fixture models, and what the email button's ?answer= says. 'mismatch' and 'stale' are a leftover ?answer= that disagrees with the saved answer. */
function checkModel(string $role, string $state): array
{
    $key = ['f2f' => 'f2f', 'online' => 'online', 'picked' => 'waiting', 'freed-picked' => 'freed', 'online-picked' => 'online', 'mismatch' => 'f2f', 'stale' => 'online', 'freed' => 'freed', 'booked' => 'booked'][$state] ?? 'waiting';
    $picked = ['picked' => 'online', 'freed-picked' => 'f2f', 'online-picked' => 'online', 'mismatch' => 'online', 'stale' => 'f2f'][$state] ?? null;

    return [$key, $picked];
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
    [$stateKey, $picked] = checkModel($role, $state);
    $ended = $state === 'ended';
    $isRep = $state !== 'stranger';
    $start = $now->startOfDay()->addHours($ended ? 8 : 13);
    $end = $start->addHours(2);
    $rep = new App\Models\User(['name' => $long ? str_repeat('LongRepName', 6) : 'Ana Cruz', 'role' => 'rep']);

    return view('classes.check', [
        'class' => $class, 'name' => App\Services\ClassChecks::name($class), 'start' => $start,
        'span' => App\Support\TimeSlots::span($start->hour * 60 + $start->minute, $end->hour * 60 + $end->minute),
        'state' => $stateKey, 'label' => App\Services\ClassChecks::LABELS[$stateKey],
        'picked' => $picked,
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
        // Large text / short window: a link to the answers, and the result message takes focus only after an answer (never on a plain load).
        $check($x->query('//script[contains(@src,"js/check.js?v=")]')->length === 1, "$name page script");
        $check($x->query('//a[@href="#check-answers"]')->length === ($open ? 1 : 0), "$name jump link to the answers only while they exist");
        $check($x->query('//form[@id="check-answers"][@tabindex="-1"]')->length === ($open ? 1 : 0), "$name answers form is the jump target");
        $focusTargets = $x->query('//*[@autofocus]');
        $hasResult = in_array($case[1], ['status', 'problem'], true);
        $check($focusTargets->length === (int) $hasResult, "$name autofocus only on a result message (".$focusTargets->length.')');
        foreach ($focusTargets as $target) { $check($target->getAttribute('id') === 'check-result' && $target->getAttribute('tabindex') === '-1', "$name the focused element is the result message"); }
        // A state with no answer to give offers a way on (a real link), and a state with answers does not.
        $check($x->query('//a[contains(@class,"check-next")][@href]')->length === ($open ? 0 : 1), "$name way on only from a state with no answer to give");
        // The saved answer, not a leftover ?answer=, decides what is marked; the mark is text, not tint alone.
        [$key, $emailChoice] = checkModel($case[0], $case[1]);
        $saved = in_array($key, ['f2f', 'online'], true) && $open ? $key : null;
        $marked = $x->query('//button[@name="answer"][contains(concat(" ", normalize-space(@class), " "), " current ")]');
        $cued = $x->query('//button[@name="answer"][contains(concat(" ", normalize-space(@class), " "), " picked ")]');
        $check($marked->length === ($saved ? 1 : 0), "$name saved answer marked once (".$marked->length.')');
        $check($cued->length === (($open && $saved === null && $emailChoice) ? 1 : 0), "$name email choice marked only while nothing is saved (".$cued->length.')');
        foreach ($marked as $button) {
            $check($button->getAttribute('value') === $saved, "$name the marked card is the saved answer");
            $check(str_contains($button->textContent, 'Your answer'), "$name saved answer says so in text");
            $check(! str_contains($button->getAttribute('class'), 'picked'), "$name saved answer is not also a cue");
        }
        foreach ($cued as $button) {
            $check($button->getAttribute('value') === $emailChoice && str_contains($button->textContent, 'Chosen in your email'), "$name email choice says so in text");
        }
        foreach ($x->query('//button[@name="answer"]') as $button) {
            $check((str_contains($button->textContent, 'Your answer') ? 1 : 0) === (str_contains(' '.$button->getAttribute('class').' ', ' current ') ? 1 : 0), "$name text and mark agree");
        }
    }

    return $count;
}
