<?php

namespace Tests\Feature\Frontend;

use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

require_once dirname(__DIR__, 2).'/Support/foundations-runtime.php';

class FoundationsTest extends TestCase
{
    public function test_presentation_contracts_without_database_access(): void
    {
        config(['database.connections' => [], 'database.default' => 'foundations-no-database']);
        view()->addNamespace('foundations', base_path('tests/Fixtures/views'));
        view()->share('errors', new ViewErrorBag);

        $this->assertGreaterThan(0, \foundationsContracts());
    }
}
