<?php

namespace Tests\Feature\Frontend;

use PHPUnit\Framework\TestCase;

/** Guards the first-paint layout: nothing the deferred layout.js does may move content that is already visible. */
final class PageLoadStabilityTest extends TestCase
{
    private function read(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 3).'/'.$path);
    }

    public function test_menu_toggle_is_in_the_first_paint_not_revealed_by_script(): void
    {
        $header = $this->read('resources/views/layouts/partials/header.blade.php');
        $this->assertStringContainsString('rb-menu-toggle', $header);
        $this->assertDoesNotMatchRegularExpression('/id="rb-menu-toggle"[^>]*\shidden>/s', $header, 'A hidden toggle is revealed by layout.js and shifts the header.');
    }

    public function test_mobile_menu_collapses_in_css_when_scripting_is_enabled(): void
    {
        $css = $this->read('public/css/layout.css');
        $this->assertMatchesRegularExpression('/@media \(scripting: enabled\)\s*\{[^}]*:not\(\[data-menu-open\]\) \.rb-account/s', $css);
        $this->assertMatchesRegularExpression('/@media \(scripting: none\)\s*\{\s*\.rb-menu-toggle\s*\{\s*display:\s*none/', $css);
    }

    public function test_fonts_are_preloaded_and_have_a_metric_matched_fallback(): void
    {
        $layout = $this->read('resources/views/layouts/app.blade.php');
        $this->assertMatchesRegularExpression('/rel="preload"[^>]*as="font"[^>]*crossorigin/', $layout);
        $this->assertStringContainsString('"ReserveBot Inter Fallback"', $this->read('public/css/foundations/tokens.css'));
        $this->assertStringContainsString('size-adjust', $this->read('public/css/foundations/fonts.css'));
    }
}
