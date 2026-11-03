<?php

namespace Tests\Feature\Frontend;

use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

require_once dirname(__DIR__, 2).'/Support/layout-runtime.php';

class LayoutTest extends TestCase
{
    public function test_shell_presentation_contracts_without_a_database(): void
    {
        config(['session.driver' => 'array', 'cache.default' => 'array', 'mail.default' => 'array']);
        view()->addNamespace('foundations', base_path('tests/Fixtures/views'));
        view()->share('errors', new ViewErrorBag);
        $this->assertGreaterThan(0, \layoutContracts());
    }
}
