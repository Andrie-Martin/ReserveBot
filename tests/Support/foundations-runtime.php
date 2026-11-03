<?php

// Standalone CLI checks use the existing Laravel runtime, never a production route.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

function foundationsApplication(): Illuminate\Foundation\Application
{
    $root = dirname(__DIR__, 2);
    require_once $root.'/vendor/autoload.php';
    $cache = sys_get_temp_dir().'/reservebot-foundations-'.getmypid();
    if (! is_dir($cache)) {
        mkdir($cache, 0700, true);
    }
    foreach (['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => $cache.'/no-config.php', 'VIEW_COMPILED_PATH' => $cache,
        'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'APP_URL' => 'http://reservebot.test'] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    // Any accidental DB access fails; nothing can reach the configured application database.
    $app['config']->set('database.connections', []);
    $app['config']->set('database.default', 'foundations-no-database');
    $app['config']->set('session.driver', 'array');
    $app['config']->set('cache.default', 'array');
    $app['config']->set('mail.default', 'array');
    $app['config']->set('view.compiled', $cache);
    $app->instance('request', Request::create('http://reservebot.test/login'));
    $app['url']->forceRootUrl('http://reservebot.test');
    $app['view']->addNamespace('foundations', $root.'/tests/Fixtures/views');
    $app['view']->share('errors', new ViewErrorBag);

    return $app;
}

function foundationsData(): array
{
    $errors = (new ViewErrorBag)
        ->put('example', new MessageBag(['email' => ['Enter a valid example email.']]))
        ->put('other', new MessageBag(['email' => ['Other form error must remain isolated.']]));
    $user = new App\Models\User(['name' => 'Example Person', 'role' => 'rep']);
    $user->setRelation('department', new App\Models\Department(['name' => 'Example department']));
    $user->setRelation('section', null);

    return ['errors' => $errors, 'user' => $user, 'hideUser' => true];
}

function foundationsDocument(string $html): DOMXPath
{
    $doc = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($doc);
}

function foundationsBooking(bool $second): string
{
    $data = foundationsData();
    $day = Carbon\CarbonImmutable::parse('2026-11-10 00:00', 'Asia/Manila');
    $rules = App\Support\BookingRules::for(new App\Models\DepartmentConfig(App\Models\DepartmentConfig::DEFAULTS), $day->subDay());
    $data += [
        'rules' => $rules, 'whenError' => null, 'picked' => [], 'canBookClass' => true,
        'for' => 'other', 'sessions' => [], 'days' => [$day],
        'when' => $second ? ['session' => null, 'day' => $day, 'from' => 600, 'to' => 660,
            'params' => ['for' => 'other', 'date' => '2026-11-10', 'from' => '10:00', 'to' => '11:00']] : null,
        'needDefault' => 'both', 'preRoom' => null, 'categories' => collect(), 'rooms' => collect(),
        'changeUrl' => '#sample-change',
    ];

    return view('bookings.create', $data)->render();
}

function foundationsCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function foundationsContracts(): int
{
    $html = view('foundations::gallery', foundationsData())->render();
    $x = foundationsDocument($html);
    $checks = 0;
    $check = function (bool $result, string $message) use (&$checks): void {
        foundationsCheck($result, $message);
        $checks++;
    };
    $one = fn (string $query) => $x->query($query)->length === 1;
    $check($one('//button[@id="save-example" and @type="submit" and @name="intent" and @value="save" and @form="sample-form" and @formaction="#sample-save"]'), 'Submitter attributes changed');
    $check($one('//button[@id="default-button" and @type="button" and @data-example="forwarded"]'), 'Default button or data forwarding failed');
    $check($one('//input[@id="example-email" and @name="contact[email]" and @required and @aria-invalid="true"]'), 'Field attributes or invalid state failed');
    $check($x->evaluate('string(//input[@id="example-email"]/@aria-describedby)') === 'external-help example-email-hint example-email-error', 'Description IDs must be composed');
    $check($one('//label[@for="example-email"]'), 'Field label missing');
    $check($one('//input[@id="override-id" and @name="explicit"]'), 'Explicit input ID must win');
    $check($one('//select[@id="example-select"]/option[@selected and @value="two"]'), 'Select option semantics changed');
    $check($one('//textarea[@id="example-notes" and @name="notes"]'), 'Textarea attributes missing');
    $check($x->evaluate('string(//textarea[@id="example-notes"])') === '<example & notes>', 'Textarea escaping changed');
    $check($x->query('//script')->length === 0 && $x->query('//img[@onerror]')->length === 0, 'Content was not escaped');
    $check(str_contains($html, '&lt;script&gt;sample&lt;/script&gt;'), 'Badge label must be escaped');
    $check(! str_contains($html, 'Other form error must remain isolated.'), 'Named error bag leaked');
    $check($one('//*[@id="summary"]//*[@role="alert"]'), 'Composed error summary must have one alert');
    $check($x->query('//*[@id="empty-summary"]')->length === 0, 'Empty error summary should not render');
    $check($one('//input[@type="password" and not(@value)]') && $one('//input[@type="file" and not(@value)]'), 'Sensitive controls unexpectedly populated');
    $check($one('//a[@id="example-link" and @href="#examples" and @rel="help"]'), 'Link semantics changed');
    $check($one('//*[@id="example-table" and @role="region" and @aria-label and @tabindex="0"]//table/caption'), 'Accessible table wrapper missing');
    $check($one('//*[@id="disabled-button" and @disabled]'), 'Native disabled state missing');
    $ids = [];
    foreach ($x->query('//*[@id]') as $element) {
        $id = $element->getAttribute('id');
        $check(! isset($ids[$id]), 'Duplicate ID '.$id);
        $ids[$id] = true;
    }
    foreach ($x->query('//*[@aria-describedby]') as $element) {
        foreach (explode(' ', $element->getAttribute('aria-describedby')) as $id) {
            $check(isset($ids[$id]), 'Missing description '.$id);
        }
    }
    foreach (['auth.login', 'errors.403', 'errors.404', 'errors.413', 'errors.419', 'errors.429', 'errors.500'] as $view) {
        $render = view($view, ['hideUser' => true])->render();
        $check(str_contains($render, 'css/foundations/components.css?v='), $view.' must render without a database');
    }
    foreach (['rep', 'lab_staff', 'admin'] as $role) {
        auth()->setUser(new App\Models\User(['name' => 'Example '.$role, 'role' => $role]));
        foreach (['errors.413', 'errors.500'] as $view) {
            $render = view($view)->render();
            $check(! str_contains($render, 'class="topnav"') && ! str_contains($render, 'Example '.$role), $view.' must hide user context for '.$role);
        }
    }
    app('auth')->forgetGuards();
    $check(str_contains(view('foundations::conditional', foundationsData())->render(), 'tt-class-only'), 'Existing conditional fixtures failed to render');
    $check(str_contains(foundationsBooking(false), 'book-form step1'), 'Existing booking step one failed to render');
    $check(str_contains(foundationsBooking(true), 'book-form step2'), 'Existing booking step two failed to render');

    return $checks;
}
