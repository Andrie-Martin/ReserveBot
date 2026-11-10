<?php

require __DIR__.'/accounts-runtime.php';
foundationsApplication();
try {
    if (($argv[1] ?? '') === '--check') {
        echo 'PASS: '.accountsContracts()." isolated Phase 06 account assertions.\n";
    } elseif (($argv[1] ?? '') === '--export') {
        $directory = $argv[2] ?? throw new RuntimeException('Provide output directory');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        foreach (accountsCases() as $name => $case) {
            file_put_contents($directory.'/'.$name.'.html', accountsRender($case));
        }
        echo 'Exported '.count(accountsCases())." account states.\n";
    } else {
        echo accountsRender(accountsCases()[$argv[1] ?? 'accounts-index-normal']);
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
