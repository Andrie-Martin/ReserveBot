<?php

namespace Tests\Feature\Frontend;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/Support/auth-runtime.php';

final class AuthPresentationTest extends TestCase
{
    public function test_actual_auth_views_preserve_presentation_contracts(): void
    {
        foundationsApplication();
        $this->assertGreaterThan(0, authContracts());
    }
}
