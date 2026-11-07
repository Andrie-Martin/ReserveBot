<?php

namespace Tests\Feature\Frontend;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/Support/classcheck-runtime.php';

final class ClassCheckPresentationTest extends TestCase
{
    public function test_actual_class_check_view_preserves_presentation_contracts(): void
    {
        foundationsApplication();
        $this->assertGreaterThan(0, checkContracts());
    }
}
