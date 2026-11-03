<?php

require __DIR__.'/foundations-runtime.php';
foundationsApplication();
$mode = $argv[1] ?? 'gallery';
if ($mode === '--check') {
    echo 'PASS: '.foundationsContracts()." component/render contracts; database connections disabled.\n";
} elseif (in_array($mode, ['gallery', 'conditional'], true)) {
    echo view('foundations::'.$mode, foundationsData())->render();
} elseif ($mode === 'legacy') {
    echo view('auth.login')->render();
} elseif (in_array($mode, ['booking-first', 'booking-second'], true)) {
    echo foundationsBooking($mode === 'booking-second');
} else {
    fwrite(STDERR, "Usage: php tests/Support/render-foundations.php [gallery|conditional|legacy|booking-first|booking-second|--check]\n");
    exit(2);
}
