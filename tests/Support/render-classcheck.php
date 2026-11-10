<?php

require __DIR__.'/classcheck-runtime.php';
foundationsApplication();
try {
    if (($argv[1] ?? '') === '--check') {
        echo 'PASS: '.checkContracts()." isolated Phase 09 class-check assertions.\n";
    } elseif (($argv[1] ?? '') === '--export') {
        $directory = $argv[2] ?? throw new RuntimeException('Provide output directory');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        foreach (checkCases() as $name => $case) {
            file_put_contents($directory.'/'.$name.'.html', checkRender($case));
        }
        echo 'Exported '.count(checkCases())." class-check states.\n";
    } else {
        echo checkRender(checkCases()[$argv[1] ?? 'check-guest-waiting']);
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
