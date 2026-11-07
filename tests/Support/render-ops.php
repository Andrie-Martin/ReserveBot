<?php
require __DIR__.'/ops-runtime.php';
foundationsApplication();
try {
    if (($argv[1] ?? '') === '--check') echo 'PASS: '.opsContracts()." isolated Phase 05 operations assertions.\n";
    elseif (($argv[1] ?? '') === '--export') {
        $directory = $argv[2] ?? throw new RuntimeException('Provide output directory');
        if (! is_dir($directory)) mkdir($directory, 0700, true);
        foreach (opsCases() as $name => $case) file_put_contents($directory.'/'.$name.'.html', opsRender($case));
        echo 'Exported '.count(opsCases())." operations states.\n";
    } else echo opsRender(opsCases()[$argv[1] ?? 'today-lab_staff-normal']);
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); }
