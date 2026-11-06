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

    /** QA F-02: seven calendar columns must fit a 390px card; the "events" word stays in the accessible name. */
    public function test_mobile_calendar_fits_card_and_keeps_event_word_accessible(): void
    {
        $root = dirname(__DIR__, 3);
        $css = file_get_contents($root.'/public/css/availability.css');
        $day = file_get_contents($root.'/resources/views/rooms/_calendar-day.blade.php');
        $this->assertStringNotContainsString('min-width: 24.5rem', $css, 'Calendar must not be wider than a 390px card.');
        $this->assertMatchesRegularExpression('/\.rb-availability__event-word\s*\{[^}]*clip-path/', $css);
        $this->assertStringContainsString('rb-availability__event-word', $day);
        $this->assertStringContainsString("'event' : 'events'", $day, 'The word must stay in the DOM for assistive technology.');
    }
}
