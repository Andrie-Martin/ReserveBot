<?php
namespace Tests\Feature\Frontend;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/Support/availability-runtime.php';
final class AvailabilityPresentationTest extends TestCase
{
    public function test_dashboard_and_availability_presentation_contracts(): void
    {
        foundationsApplication();
        $this->assertGreaterThan(0,availabilityContracts());
    }
}
