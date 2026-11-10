<?php

require __DIR__.'/availability-runtime.php';
foundationsApplication();
try {
    if (($argv[1] ?? '') === '--check') {
        echo 'PASS: '.availabilityContracts()." isolated Phase 04 presentation assertions.\n";
    } elseif (($argv[1] ?? '') === '--export') {
        $directory = $argv[2] ?? throw new RuntimeException('Provide output directory');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        foreach (availabilityCases() as $name => $case) {
            file_put_contents($directory.'/'.$name.'.html', availabilityRender($case));
        }
        echo 'Exported '.count(availabilityCases())." states.\n";
    } else {
        echo availabilityRender(availabilityCases()[$argv[1] ?? 'home-rep-normal']);
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
