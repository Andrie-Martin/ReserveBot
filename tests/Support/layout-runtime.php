<?php

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

require_once __DIR__.'/foundations-runtime.php';
require_once dirname(__DIR__, 2).'/vendor/autoload.php';

/** Compiles the real Eloquent count query, but has no PDO or network connection. */
class LayoutRecordingConnection extends Connection
{
    public array $queries = [];

    public int $waiting = 0;

    public function select($query, $bindings = [], $useReadPdo = true, array $fetchUsing = [])
    {
        if (! str_contains($query, 'count(*) as')) {
            throw new RuntimeException('Unexpected layout query: '.$query);
        }
        $this->queries[] = [$query, $bindings];

        return [(object) ['aggregate' => $this->waiting]];
    }
}

function layoutConnection(): LayoutRecordingConnection
{
    static $connection;
    if (! $connection) {
        $connection = new LayoutRecordingConnection(fn () => throw new RuntimeException('No PDO allowed'));
    }
    config(['database.default' => 'layout-recording', 'database.connections' => ['layout-recording' => ['driver' => 'layout-recording']]]);
    app('db')->extend('layout-recording', fn () => $connection);

    return $connection;
}

function layoutState(string $role = 'rep', array $options = []): array
{
    $connection = layoutConnection();
    $connection->queries = [];
    $connection->waiting = $options['waiting'] ?? 0;
    app('auth')->forgetGuards();
    $session = app('session')->driver();
    $session->flush();
    $session->put('_token', 'isolated-layout-csrf');
    if (isset($options['note'])) {
        $session->put('connect_note', $options['note']);
    }
    $request = Request::create('http://reservebot.test/example');
    $request->setLaravelSession($session);
    $route = new Route('GET', 'example', fn () => null);
    $route->name($options['route'] ?? match ($role) {
        'rep' => 'rep.home', 'lab_staff' => 'staff.home', 'admin' => 'admin.home', default => 'login',
    });
    $request->setRouteResolver(fn () => $route);
    $route->bind($request);
    app()->instance('request', $request);
    if ($role !== 'guest') {
        $user = new User([
            'name' => $options['name'] ?? 'Example '.$role,
            'role' => $role, 'email' => 'example@example.invalid', 'department_id' => 73,
            'must_change_password' => $options['onboarding'] ?? false, 'photo_path' => $options['photo'] ?? null,
        ]);
        $user->id = 91;
        $user->email_verified_at = ($options['connected'] ?? true) ? '2026-11-01 00:00:00' : null;
        auth()->setUser($user);
    }

    return ['hideUser' => $options['hideUser'] ?? false, 'mainClass' => $options['mainClass'] ?? '', 'cardClass' => $options['cardClass'] ?? ''];
}

function layoutRender(string $role = 'rep', array $options = []): string
{
    return view('foundations::layout', layoutState($role, $options))->render();
}

function layoutContracts(): int
{
    $checks = 0;
    $check = function ($condition, $message) use (&$checks): void {
        foundationsCheck($condition, $message);
        $checks++;
    };
    foreach (['guest' => 0, 'rep' => 4, 'lab_staff' => 6, 'admin' => 7] as $role => $links) {
        foreach ([0, 12] as $waiting) {
            $html = layoutRender($role, ['waiting' => $waiting, 'name' => '<script>unsafe</script>']);
            $x = foundationsDocument($html);
            $check($x->query('//nav[@aria-label="Main"]//a')->length === $links, $role.' menu count');
            $check($x->query('//div[@class="rb-shell__content" and @role="region" and @aria-label="Page content" and @tabindex="0"]')->length === ($role === 'guest' ? 0 : 1), 'Workspace has one named keyboard-accessible content region');
            $check($x->query('//nav[@aria-label="Main"]//svg[@aria-hidden="true" and @focusable="false"]')->length === $links, $role.' decorative local icons');
            $check(! str_contains($html, 'rb-nav-item__tile'), 'No placeholder navigation tiles');
            $check(! str_contains($html, 'ReserveBot · Concept') && ! str_contains($html, 'Unofficial design concept'), 'No prototype shell branding');
            $staff = in_array($role, ['lab_staff', 'admin']);
            $queries = layoutConnection()->queries;
            $check(count($queries) === ($staff ? 1 : 0), $role.' count query multiplicity');
            if ($staff) {
                $check($queries[0][1] === ['Pending', 73], 'Waiting count must retain Pending and department bindings');
                $check(str_contains($queries[0][0], 'exists') && str_contains($queries[0][0], 'users'), 'Waiting query relationship lost');
            }
            $check($x->query('//span[contains(@class,"rb-nav-item__count")]')->length === ($staff && $waiting ? 1 : 0), 'Waiting count display');
            $check(! str_contains($html, '<script>unsafe'), 'User name escaped');
            $check($x->query('//form[@method="post" and @action="'.route('logout').'"]')->length === ($role === 'guest' ? 0 : 1), 'One native logout form');
            if ($role !== 'guest') {
                $check($x->query('//form[@action="'.route('logout').'"]/input[@name="_token" and @value="isolated-layout-csrf"]')->length === 1, 'Logout CSRF');
                $check($x->query('//form[@action="'.route('logout').'"]/button[@type="submit"]')->length === 1, 'Logout native submit');
                $check($x->evaluate('string(//nav//a[1]/@href)') === url('/'), 'Home keeps root redirect');
            }
            $check($x->query('//nav//a[@href="'.route('accounts.index').'"]')->length === ($role === 'admin' ? 1 : 0), 'Accounts role gate');
            $check($x->query('//nav//a[@href="'.route('manage.equipment').'"]')->length === ($staff ? 1 : 0), 'Equipment role gate');
            $ids = [];
            foreach ($x->query('//*[@id]') as $node) {
                $id = $node->getAttribute('id');
                $check(! isset($ids[$id]), 'Duplicate ID '.$id);
                $ids[$id] = true;
            }
            foreach ($x->query('//*[@aria-controls]') as $node) {
                foreach (explode(' ', $node->getAttribute('aria-controls')) as $id) {
                    $check(isset($ids[$id]), 'Disclosure target '.$id);
                }
            }
        }
    }
    foreach (['rep', 'lab_staff', 'admin'] as $role) {
        foreach ([['onboarding' => true], ['hideUser' => true]] as $options) {
            $x = foundationsDocument(layoutRender($role, $options + ['connected' => false]));
            $check(count(layoutConnection()->queries) === 0, 'Reduced shell must never count');
            $check($x->query('//nav')->length === 0, 'Reduced shell must hide workspace');
            $check($x->query('//div[@class="rb-shell__content" and @tabindex]')->length === 0, 'Reduced shell retains natural content flow');
            $check($x->query('//a[@href="'.route('account').'"]')->length === 0, 'Reduced shell account link');
            $check($x->query('//form[@action="'.route('email.resend').'"]')->length === 0, 'Reduced shell email notice');
            $check($x->query('//form[@action="'.route('logout').'"]')->length === (isset($options['hideUser']) ? 0 : 1), 'Reduced shell logout');
        }
        foreach ([true, false] as $connected) {
            foreach (['account', 'password.edit', 'rooms'] as $route) {
                $x = foundationsDocument(layoutRender($role, compact('connected', 'route')));
                $expected = ! $connected && $route !== 'account';
                $check($x->query('//form[@action="'.route('email.resend').'"]')->length === (int) $expected, 'Email route/connection conditions');
                if ($expected) {
                    $check($x->query('//form[@action="'.route('email.resend').'"]/input[@name="_token"]')->length === 1, 'Email CSRF');
                    $check($x->query('//*[@role="status"]')->length === 1, 'Email status announcement');
                }
            }
        }
        $html = layoutRender($role, ['connected' => false, 'note' => '<Sent & waiting>']);
        $check(str_contains($html, '&lt;Sent &amp; waiting&gt;') && ! str_contains($html, 'Send it again'), 'Flash replaces resend and is escaped');
    }
    $activeCases = [
        ['rep', 'bookings.show', 'bookings.mine'], ['rep', 'book', 'book'], ['rep', 'rooms', 'rooms'],
        ['lab_staff', 'today', 'today'], ['lab_staff', 'requests', 'requests'],
        ['lab_staff', 'manage.rooms.edit', 'manage.rooms'], ['lab_staff', 'manage.rules', 'manage.rooms'],
        ['admin', 'manage.timetable.rows.edit', 'manage.rooms'], ['admin', 'manage.equipment.edit', 'manage.equipment'],
        ['admin', 'manage.categories.update', 'manage.equipment'], ['admin', 'accounts.edit', 'accounts.index'],
    ];
    foreach ($activeCases as [$role, $route, $destination]) {
        $x = foundationsDocument(layoutRender($role, ['route' => $route]));
        $check($x->query('//nav//a[@aria-current="page"]')->length === 1, 'Exactly one active item '.$route);
        $check($x->evaluate('string(//nav//a[@aria-current="page"]/@href)') === route($destination), 'Active destination '.$route);
    }
    foreach (['account', 'password.edit'] as $route) {
        $x = foundationsDocument(layoutRender('rep', ['route' => $route, 'photo' => 'example.jpg']));
        $check($x->query('//a[@href="'.route('account').'" and @aria-current="page"]')->length === 1, 'Account active state');
        $check(str_contains($x->evaluate('string(//img/@src)'), '/photos/91?v='), 'Private photo route and cache version');
    }
    // A throwing guard proves hideUser is evaluated before any auth resolution.
    app('auth')->forgetGuards();
    app('auth')->extend('layout-forbidden', fn () => throw new RuntimeException('Hidden page resolved auth'));
    $oldGuard = config('auth.guards.web');
    config(['auth.guards.web' => ['driver' => 'layout-forbidden']]);
    try {
        foreach (['errors.413', 'errors.500'] as $view) {
            $check(str_contains(view($view)->render(), 'rb-main'), $view.' avoids authentication');
        }
    } finally {
        config(['auth.guards.web' => $oldGuard]);
        app('auth')->forgetGuards();
    }

    return $checks;
}
