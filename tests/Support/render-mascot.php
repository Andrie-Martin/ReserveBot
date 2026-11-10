<?php

// DB-free page renderer for the mascot/typography browser checks: php render-mascot.php <role> <route-name>
// Renders the real shell (navigation, mascot, header) for a role with <route-name> as the current route.
require __DIR__.'/layout-runtime.php';
foundationsApplication();
try {
    [$role, $route] = [$argv[1] ?? 'rep', $argv[2] ?? 'rep.home'];
    $data = layoutState($role, ['route' => $route, 'waiting' => 3]);
    echo preg_replace('/(<main id="rb-main"[^>]*>)/', '$1<h1>Page</h1><p>Fixture content.</p>', view('foundations::layout', array_replace(foundationsData(), $data))->render(), 1);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
