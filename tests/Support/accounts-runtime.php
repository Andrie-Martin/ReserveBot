<?php

use App\Models\Department;
use App\Models\Reservation;
use App\Models\Section;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

require_once __DIR__.'/layout-runtime.php';

/** Managed-account fixtures (admin list and edit). Sample data only; no database, no mail. */
function accountsCases(): array
{
    $cases = [];
    foreach (['normal', 'empty', 'errors', 'noemail', 'long'] as $s) {
        $cases['accounts-index-'.$s] = ['index', $s];
    }
    foreach (['rep', 'lab', 'inactive', 'errors', 'nohistory', 'long'] as $s) {
        $cases['accounts-edit-'.$s] = ['edit', $s];
    }

    return $cases;
}

function accountsUser(int $id, string $name, string $role, array $o = []): User
{
    $u = new User(['name' => $name, 'role' => $role, 'email' => $o['email'] ?? strtolower(str_replace(' ', '.', $name)).'@example.invalid', 'school_id' => $o['school'] ?? null]);
    $u->id = $id;
    $u->is_active = $o['active'] ?? true;
    $u->must_change_password = $o['must'] ?? false;
    $u->email_verified_at = ($o['connected'] ?? true) ? '2026-11-01 00:00:00' : null;
    $u->setRelation('section', $o['section'] ?? null);

    return $u;
}

function accountsRender(array $case): string
{
    [$page, $state] = $case;
    $now = CarbonImmutable::parse('2026-11-04 11:30:00', 'Asia/Manila');
    Carbon\Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);
    $long = $state === 'long';
    layoutState('admin', ['route' => $page === 'index' ? 'accounts.index' : 'accounts.edit', 'waiting' => 3]);
    $admin = auth()->user();
    $admin->id = 91;
    $admin->setRelation('department', new Department(['name' => 'Example Engineering']));
    $errors = new ViewErrorBag;
    $old = [];
    $sec = fn (int $id, string $name, string $program = 'BSCPE') => tap(new Section(['name' => $name, 'program' => $program]), fn ($s) => $s->id = $id);
    $blocks = collect([$sec(12, '3-2'), $sec(13, '3-3'), $sec(14, '4-1')]);
    $sections = collect(['BSCPE' => collect([$sec(12, '3-2'), $sec(13, '3-3')]), 'BSECE' => collect([$sec(21, '2-1', 'BSECE')])]);
    $nm = fn (string $n) => $long ? str_repeat('LongPersonName', 5) : $n;
    $mail = fn (string $n) => $long ? str_repeat('very.long.address.', 4).'@example.invalid' : null;

    if ($page === 'index') {
        $reps = $state === 'empty' ? collect() : collect([
            accountsUser(101, $nm('Ana Cruz'), 'rep', ['section' => $sec(12, '3-2'), 'school' => '2021-00123-MN-0', 'email' => $mail('a')]),
            accountsUser(102, 'Ben Reyes', 'rep', ['section' => $sec(13, '3-3'), 'school' => '2021-00456-MN-0', 'connected' => false, 'must' => true]),
            accountsUser(103, 'Carla Dizon', 'rep', ['section' => $sec(14, '4-1'), 'active' => false]),
        ]);
        $staff = $state === 'empty' ? collect() : collect([accountsUser(201, 'Dan Lopez', 'lab_staff', ['school' => '2019-00001-MN-0']), accountsUser(202, 'Eli Tan', 'lab_staff', ['section' => $sec(21, '2-1', 'BSECE'), 'connected' => false])]);
        $admins = collect([$admin, accountsUser(301, 'Fay Gomez', 'admin')]);
        if ($state === 'errors') {
            $errors->put('add', new MessageBag(['email' => ['That email is already used by another account.'], 'password' => ['Use at least 8 characters.']]));
            $old = ['role' => 'lab_staff', 'name' => 'Old example name', 'email' => 'old@example.invalid', 'school_id' => '2020-00001-MN-0', 'password' => 'MUST-NOT-RENDER-PW'];
        }
        session()->put('_old_input', $old);
        app('view')->share('errors', $errors);

        return view('accounts.index', ['admin' => $admin, 'groups' => ['Block reps' => $reps, 'Lab staff' => $staff, 'Admins' => $admins], 'blocks' => $blocks, 'sections' => $sections, 'emailSends' => $state !== 'noemail'])->render();
    }

    $role = $state === 'lab' ? 'lab_staff' : 'rep';
    $acct = accountsUser(101, $nm('Ana Cruz'), $role, ['section' => $sec(12, '3-2'), 'school' => '2021-00123-MN-0', 'active' => $state !== 'inactive', 'must' => in_array($state, ['inactive', 'errors'], true), 'connected' => ! in_array($state, ['inactive', 'errors'], true), 'email' => $mail('a')]);
    $booking = function (int $id, string $status, int $add) use ($now) {
        $r = new Reservation(['purpose' => 'Example lecture with slides', 'status' => $status, 'room_id' => 11]);
        $r->id = $id;
        $r->start_time = $now->startOfDay()->addDays($add)->addHours(13);
        $r->end_time = $now->startOfDay()->addDays($add)->addHours(15);
        $r->setRelation('room', (object) ['id' => 11, 'name' => 'CE-305']);

        return $r;
    };
    $upcoming = in_array($state, ['nohistory'], true) ? collect() : collect([$booking(401, 'Pending', 1), $booking(402, 'Confirmed', 3)]);
    if ($state === 'errors') {
        $errors->put('details', new MessageBag(['email' => ['That email is already used by another account.']]));
        $errors->put('password', new MessageBag(['password' => ['Use at least 8 characters.']]));
        $old = ['_token' => 'x', 'name' => 'Old example name', 'email' => 'old@example.invalid', 'role' => 'rep', 'block_id' => '13', 'password' => 'MUST-NOT-RENDER-PW'];
    }
    session()->put('_old_input', $old);
    app('view')->share('errors', $errors);

    return view('accounts.edit', ['account' => $acct, 'blocks' => $blocks, 'sections' => $sections, 'upcoming' => $upcoming, 'bookingCount' => $state === 'nohistory' ? 0 : 7, 'hasHistory' => $state !== 'nohistory'])->render();
}

function accountsContracts(): int
{
    $count = 0;
    $check = function ($ok, $message) use (&$count) {
        foundationsCheck((bool) $ok, $message);
        $count++;
    };
    foreach (accountsCases() as $name => $case) {
        $html = accountsRender($case);
        $x = foundationsDocument($html);
        $check(! str_contains($html, 'MUST-NOT-RENDER-PW'), "$name password never rendered back");
        $check($x->query('//main//h1')->length === 1, "$name one main heading");
        $ids = [];
        foreach ($x->query('//*[@id]') as $node) {
            $id = $node->getAttribute('id');
            $check(! isset($ids[$id]), "$name unique ID $id");
            $ids[$id] = true;
        }
        foreach ($x->query('//main//input[not(@type="hidden")]|//main//select|//main//textarea') as $node) {
            $id = $node->getAttribute('id');
            if (in_array($node->getAttribute('type'), ['radio', 'checkbox'], true)) {
                $check($x->query('ancestor::label', $node)->length === 1, "$name choice wrapped by label");

                continue;
            }
            $check($id && $x->query('//label[@for="'.$id.'"]')->length === 1, "$name labelled $id");
        }
        foreach ($x->query('//main//form[@method="post"]') as $form) {
            $check($x->query('.//input[@name="_token" and @value="isolated-layout-csrf"]', $form)->length === 1, "$name csrf");
        }
        $check($x->query('//link[contains(@href,"css/manage.css?v=")]')->length === 1, "$name manage stylesheet");
        if ($case[0] === 'index') {
            $check($x->query('//form[@action="'.route('accounts.store').'"]//input[@name="role"]')->length === 2, "$name add form roles");
            $check($x->query('//form[@action="'.route('accounts.store').'"]//input[@name="password" and @required]')->length === 1, "$name starting password");
        } else {
            $check($x->query('//form[@action="'.route('accounts.update', 101).'"]//input[@name="_method" and @value="PUT"]')->length === 1, "$name update spoofing");
            $check($x->query('//form[@action="'.route('accounts.password', 101).'"]//input[@name="password" and @required]')->length === 1, "$name password form");
            $check(str_contains($x->query('//form[.//input[@name="_method" and @value="DELETE"]]')->item(0)->getAttribute('onsubmit'), 'confirm('), "$name delete confirmation");
        }
    }

    return $count;
}
