<?php
require __DIR__.'/booking-runtime.php';
foundationsApplication();
try {
    if (($argv[1] ?? '') === '--check') echo 'PASS: '.bookingContracts()." isolated Phase 04 booking assertions.\n";
    elseif (($argv[1] ?? '') === '--export') {
        $directory = $argv[2] ?? throw new RuntimeException('Provide output directory');
        if (! is_dir($directory)) mkdir($directory, 0700, true);
        foreach (bookingCases() as $name => $case) file_put_contents($directory.'/'.$name.'.html', bookingRender($case));
        echo 'Exported '.count(bookingCases())." booking states.\n";
    } else echo bookingRender(bookingCases()[$argv[1] ?? 'book-step1-class']);
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); }
