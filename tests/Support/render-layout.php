<?php

require __DIR__.'/layout-runtime.php';
foundationsApplication();
try {
if (($argv[1] ?? '') === '--check') {
    echo 'PASS: '.layoutContracts()." isolated shell contracts; no PDO/database connection.\n";
    exit;
}
if (($argv[1] ?? '') === '--export') {
    $directory = $argv[2] ?? sys_get_temp_dir().'/reservebot-layout-evidence';
    if (! is_dir($directory)) {
        mkdir($directory, 0700, true);
    }
    $cases = [
        'rep' => ['rep', []], 'staff' => ['lab_staff', ['waiting' => 12]], 'admin' => ['admin', ['waiting' => 12]],
        'guest' => ['guest', []], 'onboarding' => ['rep', ['onboarding' => true]],
        'unconnected' => ['rep', ['connected' => false]], 'note' => ['rep', ['connected' => false, 'note' => 'Example resend response']],
        'hidden' => ['admin', ['hideUser' => true]],
        'long' => ['admin', ['name' => str_repeat('ExampleLongName', 12), 'waiting' => 123456]],
        'wide' => ['rep', ['mainClass' => 'wide']],
        'narrow' => ['rep', ['cardClass' => 'narrow']], 'booking-width' => ['rep', ['cardClass' => 'book-card']],
        'account-width' => ['rep', ['cardClass' => 'account-card']], 'timetable-width' => ['admin', ['cardClass' => 'tt-card']],
    ];
    foreach ($cases as $file => [$role, $options]) {
        file_put_contents($directory.'/'.$file.'.html', layoutRender($role, $options));
    }
    foreach (['gallery', 'conditional'] as $view) {
        layoutState('rep');
        file_put_contents($directory.'/'.$view.'.html', view('foundations::'.$view, array_replace(foundationsData(), ['hideUser' => false]))->render());
    }
    foreach ([false, true] as $second) {
        layoutState('rep');
        file_put_contents($directory.'/booking-'.($second ? 'second' : 'first').'.html', foundationsBooking($second, false));
    }
    layoutState('guest');
    file_put_contents($directory.'/legacy.html', view('auth.login')->render());
    echo "Exported isolated fixtures to ".$directory."\n";
    exit;
}
$role = $argv[1] ?? 'rep';
$options = json_decode($argv[2] ?? '{}', true, flags: JSON_THROW_ON_ERROR);
$data = layoutState($role, $options);
$view = $options['view'] ?? 'foundations::layout';
if ($view === 'booking-first' || $view === 'booking-second') {
    echo foundationsBooking($view === 'booking-second', false);
} else {
    echo view($view, array_replace(foundationsData(), $data))->render();
}

} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
