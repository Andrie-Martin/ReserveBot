<?php

namespace Tests\Feature\Frontend;

use PHPUnit\Framework\TestCase;

/**
 * Source-level guards for the groupmate feedback pass (no database, no browser). They pin the CSS/markup contracts the
 * real-browser checks (tests/Support/group-feedback-browser.cjs) measure, and the shared components being reused.
 */
final class GroupFeedbackPresentationTest extends TestCase
{
    private function read(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 3).'/'.$path);
    }

    public function test_disclaimer_and_floating_descriptor_are_gone_from_source_and_css(): void
    {
        foreach (['resources/views/auth/login.blade.php', 'resources/views/auth/forgot.blade.php', 'resources/views/auth/reset.blade.php', 'resources/views/layouts/partials/header.blade.php', 'resources/views/auth/_aside.blade.php'] as $file) {
            $source = $this->read($file);
            $this->assertStringNotContainsString('Unofficial student design', $source, $file);
            $this->assertStringNotContainsString('Not an official PUP website', $source, $file);
            $this->assertStringNotContainsString('Room &amp; equipment reservation system', $source, $file);
        }
        $this->assertStringNotContainsString('rb-auth-disclaimer', $this->read('public/css/auth.css'));
        $this->assertStringNotContainsString('rb-header__description', $this->read('public/css/layout.css'));
    }

    public function test_status_note_is_the_one_shared_component_for_both_email_states(): void
    {
        $component = $this->read('resources/views/components/ui/status-note.blade.php');
        $this->assertStringContainsString("'rb-status'", $component);
        $this->assertStringContainsString('aria-hidden="true"', $component, 'the icon is decorative; the title carries the meaning');
        foreach (['resources/views/account/_email-state.blade.php', 'resources/views/layouts/partials/email-notice.blade.php'] as $file) {
            $source = $this->read($file);
            $this->assertStringContainsString('<x-ui.status-note', $source, $file);
            $this->assertStringNotContainsString('<x-ui.alert', $source, $file);
            $this->assertStringContainsString('route(\'email.resend\')', $source, $file);
        }
        // The generic alert used for errors and warnings that must not be missed is unchanged.
        $css = $this->read('public/css/foundations/components.css');
        $this->assertStringContainsString('.rb-alert { padding: var(--rb-space-4);', $css);
        $this->assertStringContainsString('.rb-status {', $css);
    }

    public function test_the_destructive_outline_variant_is_shared_by_both_button_components(): void
    {
        foreach (['button', 'link-button'] as $name) {
            $this->assertStringContainsString("'danger-outline'", $this->read("resources/views/components/ui/$name.blade.php"));
        }
        $this->assertStringContainsString('.rb-button--danger-outline {', $this->read('public/css/foundations/components.css'));
    }

    public function test_free_rooms_timetable_uses_one_column_definition_and_card_mode_on_narrow_boards(): void
    {
        $css = $this->read('public/css/availability.css');
        $this->assertSame(1, substr_count($css, 'grid-template-columns: var(--tt-name) var(--tt-track) var(--tt-book) var(--tt-schedule)'), 'axis and rows share one column system');
        $this->assertStringNotContainsString('11rem minmax(25rem, 1fr) 5rem', $css, 'the older duplicated column definitions are gone');
        $this->assertStringContainsString('.rb-availability__boardwrap { container-type: inline-size;', $css);
        $this->assertMatchesRegularExpression('/@container \(max-width: 50rem\) \{\s*\.rb-availability__axis \{ display: none; \}/', $css);
        $this->assertStringContainsString('.rb-availability__room-book .rb-button, .rb-availability .rb-availability__btn-schedule { width: 100%;', $css, 'action buttons fill fixed cells so counters cannot change widths');
    }

    public function test_calendar_marks_today_and_selected_with_different_shapes(): void
    {
        $css = $this->read('public/css/availability.css');
        $this->assertMatchesRegularExpression('/\.rb-availability__today \.rb-availability__daynum \{[^}]*border-radius: var\(--rb-radius-pill\);[^}]*background: var\(--rb-color-maroon\)/', $css);
        $this->assertMatchesRegularExpression('/\.rb-availability__selected \{[^}]*inset 0 0 0 2px var\(--rb-color-maroon\)/', $css);
    }

    public function test_drawer_divider_uses_the_neutral_border_token(): void
    {
        $this->assertMatchesRegularExpression('/\.rb-availability__schedule-section--free \{[^}]*border-top: 1px solid var\(--rb-border-warm\)/', $this->read('public/css/availability.css'));
    }

    public function test_my_bookings_phone_card_places_every_part_explicitly(): void
    {
        $css = $this->read('public/css/booking.css');
        foreach (['.rb-booking .brow-when { grid-column: 1; grid-row: 1;', '.rb-booking .brow-what { grid-column: 1 / -1; grid-row: 2; }', '.rb-booking .brow-side { display: contents; }', '@media (max-width: 24rem)'] as $needle) {
            $this->assertStringContainsString($needle, $css);
        }
    }
}
