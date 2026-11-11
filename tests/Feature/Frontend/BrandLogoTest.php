<?php

namespace Tests\Feature\Frontend;

use PHPUnit\Framework\TestCase;

/**
 * Source-level guards for the header brand logo (mascot head), the favicon and the isolation of the mobile expression loop
 * (no database, no browser). The real-browser checks are tests/Support/brand-logo-browser.cjs.
 */
final class BrandLogoTest extends TestCase
{
    private function read(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 3).'/'.$path);
    }

    public function test_the_brand_uses_the_mascot_head_instead_of_the_pu_monogram(): void
    {
        $brand = $this->read('resources/views/components/ui/brand.blade.php');
        $this->assertStringContainsString('<x-ui.mascot-head', $brand);
        $this->assertStringNotContainsString('monogram', $brand);
        $this->assertStringContainsString('PUP College of Engineering', $brand, 'the institutional subtitle stays');
        $this->assertStringContainsString('<span class="rb-brand__name">ReserveBot</span>', $brand);
        $this->assertStringNotContainsString('monogram', $this->read('public/css/layout.css'));
        $head = $this->read('resources/views/components/ui/mascot-head.blade.php');
        $this->assertStringContainsString('aria-hidden="true"', $head);
        $this->assertStringContainsString('focusable="false"', $head);
        $this->assertSame(1, substr_count($this->read('resources/views/layouts/partials/header.blade.php'), '<x-ui.brand'), 'the one shared header renders the brand');
    }

    public function test_only_workspace_pages_request_the_expression_loop(): void
    {
        $this->assertStringContainsString(':animated="(bool) $workspace"', $this->read('resources/views/layouts/partials/header.blade.php'));
        $head = $this->read('resources/views/components/ui/mascot-head.blade.php');
        $this->assertSame(5, substr_count($head, 'class="rbh-x rbh-x--'), 'five expression overlays, only rendered when animated');
        $this->assertStringContainsString("@props(['animated' => false])", $head);
        $this->assertStringNotContainsString('auth/login', $this->read('resources/views/components/ui/brand.blade.php'));
    }

    public function test_the_loop_is_css_only_gated_to_the_sidebar_mascots_complement_and_reduced_motion(): void
    {
        $css = $this->read('public/css/brand-logo.css');
        $this->assertStringContainsString('.rbh-x { opacity: 0; }', $css, 'overlays hidden by default: first paint is the normal smile');
        $this->assertStringContainsString('(max-width: 79.999rem) and (prefers-reduced-motion: no-preference), (max-height: 39.499rem) and (prefers-reduced-motion: no-preference)', $css);
        $this->assertStringContainsString('.rb-shell--workspace .rbh[data-rb-head-expressive]', $css, 'never on guest/login');
        $this->assertSame(0, preg_match('/^(?!\s*\/\*).*(?<![-\w])(transform|width|height|margin|padding)\s*:/m', preg_replace(['/\/\*.*?\*\//s', '/\.rbh-x\s*\{[^}]*\}/'], '', $css)) , 'the loop touches no geometry');
        $this->assertStringNotContainsString('infinite', preg_replace('/@media.*?\n\}\n/s', '', $css), 'no animation outside the gated block');
        $sidebar = $this->read('public/css/mascot.css');
        $this->assertStringContainsString('@media (min-width: 80rem) and (min-height: 39.5rem)', $sidebar, 'the complement this loop relies on');
        $this->assertStringNotContainsString('rbh', $sidebar.$this->read('public/css/login-mascot.css'), 'sidebar and login mascots are untouched');
    }

    public function test_favicon_assets_exist_and_are_referenced(): void
    {
        foreach (['favicon.svg', 'favicon-32.png', 'favicon.ico', 'apple-touch-icon.png'] as $file) {
            $this->assertFileExists(dirname(__DIR__, 3).'/public/'.$file);
        }
        $layout = $this->read('resources/views/layouts/app.blade.php');
        foreach (["asset('favicon.svg')", "asset('favicon-32.png')", "asset('favicon.ico')", "asset('apple-touch-icon.png')", "css/brand-logo.css"] as $needle) {
            $this->assertStringContainsString($needle, $layout);
        }
        $svg = $this->read('public/favicon.svg');
        $this->assertStringContainsString('#6B1932', $svg);
        $this->assertStringContainsString('#F2EADF', $svg);
        $this->assertStringNotContainsString('<text', $svg, 'no lettering in the favicon');
        $this->assertStringNotContainsString('<animate', $svg);
        $this->assertSame("\x89PNG", substr($this->read('public/favicon-32.png'), 0, 4));
        $this->assertSame("\x00\x00\x01\x00", substr($this->read('public/favicon.ico'), 0, 4), 'a real ICO container');
    }
}
