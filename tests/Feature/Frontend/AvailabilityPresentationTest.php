<?php

namespace Tests\Feature\Frontend;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/Support/availability-runtime.php';
final class AvailabilityPresentationTest extends TestCase
{
    protected function tearDown(): void
    {
        \foundationsRelease();
        parent::tearDown();
    }

    public function test_dashboard_and_availability_presentation_contracts(): void
    {
        foundationsApplication();
        $this->assertGreaterThan(0, availabilityContracts());
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

    /**
     * QA F-03: the label must never be squeezed to nothing beside the icon. The cause was `flex: none` neighbours plus a
     * label that could shrink to 0 on the same line; the label now has a real flex-basis, so the row wraps (label under
     * the icon) before the label can shrink, and `overflow-wrap: anywhere` (which shrinks min-content to one letter) stays out.
     */
    public function test_dashboard_shortcut_labels_cannot_shrink_to_nothing(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3).'/public/css/dashboard.css');
        preg_match_all('/\.rb-dashboard__action-label\s*\{([^}]*)\}/', $css, $rules);
        $this->assertNotEmpty($rules[1]);
        $declarations = implode(' ', $rules[1]);
        $this->assertStringNotContainsString('anywhere', $declarations);
        $this->assertMatchesRegularExpression('/flex:\s*1\s+1\s+[1-9][\d.]*rem/', $declarations, 'The label needs a rem flex-basis so the row wraps before it shrinks.');
        $this->assertMatchesRegularExpression('/\.rb-dashboard__action\s*\{[^}]*flex-wrap:\s*wrap/', $css);
        // Padding and icon are capped in px so they do not keep growing with the text size.
        $this->assertMatchesRegularExpression('/\.rb-dashboard__links \.rb-dashboard__action\s*\{[^}]*padding:[^;]*min\(var\(--rb-space-4\),\s*\d+px\)/', $css);
    }

    /** QA F-04: internal roadmap copy must not be rendered to users. */
    public function test_home_views_do_not_render_roadmap_copy(): void
    {
        $root = dirname(__DIR__, 3).'/resources/views';
        foreach (['/home.blade.php', '/home/_representative.blade.php', '/home/_meeting.blade.php'] as $view) {
            $this->assertStringNotContainsString('next phases', file_get_contents($root.$view), $view);
            $this->assertStringNotContainsString('comingNext', file_get_contents($root.$view), $view);
        }
    }
}
