<?php
require __DIR__.'/timetable-runtime.php';
foundationsApplication();
try {
    if (($argv[1] ?? '') === '--check') echo 'PASS: '.ttContracts()." isolated Phase 08 timetable assertions.\n";
    elseif (($argv[1] ?? '') === '--export') {
        $directory = $argv[2] ?? throw new RuntimeException('Provide output directory');
        if (! is_dir($directory)) mkdir($directory, 0700, true);
        foreach (ttCases() as $name => $case) file_put_contents($directory.'/'.$name.'.html', ttRender($case));
        echo 'Exported '.count(ttCases())." timetable states.\n";
    } else echo ttRender(ttCases()[$argv[1] ?? 'tt-overview-admin-inuse']);
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); }
