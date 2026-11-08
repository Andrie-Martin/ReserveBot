<?php

require_once __DIR__.'/layout-runtime.php';

/** Sample state only; never saves models, opens PDO, or sends mail. */
function authCases(): array
{
    $cases = [
        'login' => ['auth.login', 'guest', ['route' => 'login']],
        'login-error' => ['auth.login', 'guest', ['route' => 'login', 'errors' => ['default' => ['email' => ['Wrong email or password.']]], 'old' => ['email' => 'old@example.invalid', 'password' => 'MUST-NOT-RENDER']]],
        'login-status' => ['auth.login', 'guest', ['route' => 'login', 'status' => 'Your password is changed. Log in with it.']],
        'forgot' => ['auth.forgot', 'guest', ['route' => 'password.request']],
        'forgot-status' => ['auth.forgot', 'guest', ['route' => 'password.request', 'status' => 'If example@example.invalid has an account here, we sent it a link.']],
        'forgot-error' => ['auth.forgot', 'guest', ['route' => 'password.request', 'errors' => ['default' => ['email' => ['Sending emails isn’t working right now.']]], 'old' => ['email' => 'old@example.invalid']]],
        'reset' => ['auth.reset', 'guest', ['route' => 'password.reset']],
        'reset-error' => ['auth.reset', 'guest', ['route' => 'password.reset', 'errors' => ['default' => ['email' => ['This link has expired or was already used.'], 'password' => ['The two new passwords don’t match.']]], 'old' => ['email' => 'old@example.invalid', 'password' => 'MUST-NOT-RENDER', 'password_confirmation' => 'MUST-NOT-RENDER']]],
    ];
    foreach (['rep', 'lab_staff', 'admin'] as $role) {
        $cases['welcome-'.$role] = ['account.welcome', $role, ['route' => 'welcome', 'onboarding' => true]];
        $cases['welcome-existing-'.$role] = ['account.welcome', $role, ['route' => 'welcome', 'onboarding' => true, 'school' => '2021-00123-MN-0']];
        $cases['welcome-error-'.$role] = ['account.welcome', $role, ['route' => 'welcome', 'onboarding' => true, 'errors' => ['default' => ['school_id' => ['Type the School ID number.'], 'password' => ['Pick a different password.']]], 'old' => ['school_id' => 'old-id', 'password' => 'MUST-NOT-RENDER']]];
        $cases['account-'.$role] = ['account.info', $role, ['route' => 'account', 'connected' => false]];
        $cases['account-connected-'.$role] = ['account.info', $role, ['route' => 'account', 'photo' => 'photos/example.jpg', 'section' => true]];
        $cases['account-errors-'.$role] = ['account.info', $role, ['route' => 'account', 'connected' => false, 'photo' => 'photos/example.jpg', 'status' => 'Saved information.', 'problem' => 'Saved, but the email could not be sent.', 'note' => 'Wait 42 seconds.', 'errors' => ['info' => ['name' => ['Info name error'], 'email' => ['Info email error'], 'current_password' => ['Info password error'], 'school_id' => ['Info school error'], 'section_id' => ['Info section error']], 'photo' => ['photo' => ['Photo error']], 'default' => ['email' => ['WRONG-BAG-MUST-NOT-RENDER']]], 'old' => ['name' => 'Old <name>', 'email' => 'old@example.invalid', 'school_id' => 'old-id', 'section_id' => 8, 'current_password' => 'MUST-NOT-RENDER', 'photo' => 'MUST-NOT-RENDER']]];
        $cases['password-'.$role] = ['account.password', $role, ['route' => 'password.edit', 'connected' => false]];
        $cases['password-errors-'.$role] = ['account.password', $role, ['route' => 'password.edit', 'note' => 'Already connected.', 'status' => 'Your password is changed.', 'errors' => ['default' => ['current_password' => ['Wrong current password'], 'password' => ['Pick a different password'], 'password_confirmation' => ['Confirm password']]], 'old' => ['current_password' => 'MUST-NOT-RENDER', 'password' => 'MUST-NOT-RENDER']]];
    }
    $cases['account-photo-only'] = ['account.info', 'rep', ['route' => 'account', 'errors' => ['photo' => ['photo' => ['Photo only error']]]]];
    $cases['account-info-only'] = ['account.info', 'rep', ['route' => 'account', 'errors' => ['info' => ['email' => ['Info only error']]]]];
    $cases['account-long'] = ['account.info', 'lab_staff', ['route' => 'account', 'name' => str_repeat('LongExampleName', 10), 'connected' => false]];
    foreach (['guest', 'rep', 'admin'] as $role) {
        foreach ([true, false] as $ok) $cases['link-'.$role.'-'.(int) $ok] = ['auth.email_link', $role, ['route' => 'email.connect', 'ok' => $ok]];
    }
    $cases['link-class-expired'] = ['auth.email_link', 'guest', ['ok' => false, 'message' => 'This link has expired (it works until the class ends) or isn’t complete. Reps can also answer on their home page.']];
    foreach ([403, 404, 413, 419, 422, 429, 500] as $code) {
        foreach (['guest', 'rep', 'admin'] as $role) $cases['error-'.$code.'-'.$role] = ['errors.'.$code, $role, []];
    }
    return $cases;
}

function authRender(array $case): string
{
    [$view, $role, $options] = $case;
    layoutState($role, $options);
    $user = $role === 'guest' ? null : auth()->user();
    $section = new App\Models\Section(['name' => '3-2', 'program' => 'BSCPE']);
    $section->id = 8;
    if ($user) {
        $user->school_id = $options['school'] ?? null;
        $user->section_id = ($options['section'] ?? false) ? 8 : null;
        $user->setRelation('department', new App\Models\Department(['name' => 'Example Engineering']));
        $user->setRelation('section', ($options['section'] ?? false) ? $section : null);
    }
    foreach (['status', 'problem'] as $key) if (isset($options[$key])) session()->put($key, $options[$key]);
    session()->put('_old_input', $options['old'] ?? []);
    $errors = new Illuminate\Support\ViewErrorBag;
    foreach ($options['errors'] ?? [] as $bag => $messages) $errors->put($bag, new Illuminate\Support\MessageBag($messages));
    app('view')->share('errors', $errors);
    $ok = $options['ok'] ?? true;
    return view($view, [
        'user' => $user, 'sections' => collect(['BSCPE' => collect([$section])]),
        'askSchoolId' => $user && in_array($role, ['rep', 'lab_staff'], true) && $user->school_id === null,
        'token' => 'sample-reset-token', 'email' => 'reset@example.invalid', 'ok' => $ok,
        'message' => $options['message'] ?? ($ok ? 'Example email is connected.' : 'This link is for an email the account doesn’t use anymore.'),
    ])->render();
}

function authContracts(): int
{
    $count = 0;
    $check = function ($ok, $message) use (&$count) { foundationsCheck((bool) $ok, $message); $count++; };
    foreach (authCases() as $name => $case) {
        $html = authRender($case);
        $x = foundationsDocument($html);
        $check(!str_contains($html, 'MUST-NOT-RENDER'), "$name sensitive old value");
        $check(!str_contains($html, 'WRONG-BAG-MUST-NOT-RENDER'), "$name bag isolation");
        $check($x->query('//main//h1')->length === 1, "$name one main heading");
        $check($x->query('//link[contains(@href,"css/auth.css?v=")]')->length === 1, "$name stylesheet");
        $ids = [];
        foreach ($x->query('//*[@id]') as $node) { $id = $node->getAttribute('id'); $check(!isset($ids[$id]), "$name unique ID $id"); $ids[$id] = true; }
        foreach ($x->query('//*[@aria-describedby]') as $node) foreach (explode(' ', $node->getAttribute('aria-describedby')) as $id) $check(isset($ids[$id]), "$name description $id exists");
        foreach ($x->query('//main//input[not(@type="hidden")]|//main//select') as $node) {
            $id = $node->getAttribute('id');
            $check($id && $x->query('//label[@for="'.$id.'"]')->length === 1, "$name labelled $id");
            if (in_array($node->getAttribute('type'), ['password', 'file'])) $check(!$node->hasAttribute('value'), "$name empty sensitive control");
        }
        foreach ($x->query('//form') as $form) {
            $check($form->getAttribute('method') === 'post', "$name native POST");
            $check($x->query('.//input[@name="_token" and @value="isolated-layout-csrf"]', $form)->length === 1, "$name csrf");
            $check($x->query('.//button[@type="submit"]', $form)->length === 1, "$name native submitter");
        }
        if (str_starts_with($name, 'account-')) {
            $check($x->query('//form[@enctype="multipart/form-data"]')->length === 1, "$name upload encoding");
            $check($x->query('//input[@name="current_password" and @required]')->length === 0, "$name email password optional HTML");
            $role = $case[1];
            $check($x->query('//select[@name="section_id"]')->length === (int)($role === 'lab_staff'), "$name section ownership");
            $check($x->query('//input[@name="school_id"]')->length === (int)($role !== 'admin'), "$name school condition");
            $check($x->query('//input[@name="school_id" and @required]')->length === (int)($role === 'rep'), "$name school required");
            $check($x->query('//input[@name="_method" and @value="DELETE"]')->length === (int)!empty($case[2]['photo']), "$name remove condition");
            $check($x->query('//main//form[contains(@action,"email-link")]')->length === (int)!(($case[2]['connected'] ?? true)), "$name resend condition");
            // Document order is keyboard order: information, email connection (when shown), photo upload, photo removal (when a photo exists).
            $order = [];
            foreach ($x->query('//main//form') as $form) {
                $action = $form->getAttribute('action');
                $order[] = str_ends_with($action, '/account') ? 'info'
                    : (str_contains($action, 'email-link') ? 'email'
                    : (str_ends_with($action, '/account/photo') ? ($x->query('.//input[@name="_method"]', $form)->length ? 'remove' : 'upload') : 'other'));
            }
            $expected = ['info', ...(($case[2]['connected'] ?? true) ? [] : ['email']), 'upload', ...(empty($case[2]['photo']) ? [] : ['remove'])];
            $check($order === $expected, "$name form order ".implode(',', $order)." expected ".implode(',', $expected));
        }
        if (str_starts_with($name, 'error-') || str_starts_with($name, 'link-')) {
            $check($x->query('//*[contains(concat(" ", normalize-space(@class), " "), " rb-auth-card--message ")]')->length === 1, "$name card sizes its heading to its width");
        }
        if (str_starts_with($name, 'welcome-')) {
            $ask = in_array($case[1], ['rep', 'lab_staff']) && !isset($case[2]['school']);
            $check($x->query('//input[@name="school_id"]')->length === (int)$ask, "$name asks school");
            $check($x->query('//nav[@aria-label="Workspace"]')->length === 0, "$name no workspace");
        }
        if (str_starts_with($name, 'login')) $check($x->query('//a[contains(@href,"forgot-password")]')->length === 1, "$name recovery retained");
        if (str_starts_with($name, 'reset')) $check($x->query('//input[@name="token" and @value="sample-reset-token"]')->length === 1, "$name reset token");
        if (str_starts_with($name, 'error-413') || str_starts_with($name, 'error-500')) $check(count(layoutConnection()->queries) === 0, "$name no count query");
    }
    // Test hidden-user rendering with an auth implementation that throws on resolution.
    $auth = app('auth');
    app()->instance('auth', new class { public function user() { throw new RuntimeException('Hidden error resolved auth'); } });
    try {
        foreach ([413, 500] as $code) $check(str_contains(view('errors.'.$code)->render(), 'Go to my home page'), "hidden $code no authentication");
    } finally { app()->instance('auth', $auth); }
    return $count;
}
