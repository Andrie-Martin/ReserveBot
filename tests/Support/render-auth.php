<?php

require __DIR__.'/auth-runtime.php';
foundationsApplication();
try {
    if (($argv[1] ?? '') === '--check') {
        echo 'PASS: '.authContracts()." isolated authentication presentation assertions.\n";
    } elseif (($argv[1] ?? '') === '--export') {
        $directory = $argv[2] ?? throw new RuntimeException('Provide an output directory');
        if (!is_dir($directory)) mkdir($directory, 0700, true);
        foreach (authCases() as $name => $case) file_put_contents($directory.'/'.$name.'.html', authRender($case));
        echo 'Exported '.count(authCases())." states to $directory\n";
    } else {
        echo authRender(authCases()[$argv[1] ?? 'login']);
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
