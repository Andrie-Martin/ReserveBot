<?php

require __DIR__.'/manage-runtime.php';
foundationsApplication();
try {
    if (($argv[1] ?? '') === '--check') {
        echo 'PASS: '.manageContracts()." isolated Phase 07 management assertions.\n";
    } elseif (($argv[1] ?? '') === '--export') {
        $directory = $argv[2] ?? throw new RuntimeException('Provide output directory');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        foreach (manageCases() as $name => $case) {
            file_put_contents($directory.'/'.$name.'.html', manageRender($case));
        }
        echo 'Exported '.count(manageCases())." manage states.\n";
    } else {
        echo manageRender(manageCases()[$argv[1] ?? 'manage-rooms-admin-normal']);
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
