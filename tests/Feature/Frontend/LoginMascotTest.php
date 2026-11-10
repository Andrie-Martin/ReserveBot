<?php

namespace Tests\Feature\Frontend;

use PHPUnit\Framework\TestCase;

/**
 * Source-level guards for the animated login mascot (no database, no browser). The real-browser checks are
 * tests/Support/login-mascot-browser.cjs. The login mascot is independent of the sidebar mascot and its nine-pose state machine.
 */
final class LoginMascotTest extends TestCase
{
    private function read(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 3).'/'.$path);
    }

    private function css(): string
    {
        return preg_replace('/\/\*.*?\*\//s', '', $this->read('public/css/login-mascot.css'));
    }

    public function test_the_login_mascot_is_decorative_and_separate_from_the_sidebar_mascot(): void
    {
        $view = $this->read('resources/views/components/ui/login-mascot.blade.php');
        $this->assertStringContainsString('aria-hidden="true"', $view);
        $this->assertStringContainsString('focusable="false"', $view);
        $this->assertDoesNotMatchRegularExpression('/<(a|button|input|title|desc)\b|tabindex|data-pose|data-rb-mascot/', $view);
        $this->assertStringContainsString('pointer-events: none', $this->css());
        // The sidebar mascot keeps its own files and stays free of keyframes.
        $sidebar = $this->read('public/css/mascot.css');
        $this->assertStringNotContainsString('@keyframes', $sidebar);
        $this->assertStringNotContainsString('login-mascot', $sidebar.$this->read('public/js/mascot.js'));
        $this->assertStringNotContainsString('login-mascot', $this->read('resources/views/layouts/app.blade.php'), 'loaded by the login page only');
    }

    public function test_it_is_wired_into_the_login_page_only(): void
    {
        $login = $this->read('resources/views/auth/login.blade.php');
        $this->assertStringContainsString('css/login-mascot.css', $login);
        $this->assertStringContainsString('js/login-mascot.js', $login);
        $this->assertStringContainsString('rb-login-mascot--card', $login);
        $this->assertStringContainsString('rb-login-mascot--banner', $this->read('resources/views/auth/_aside.blade.php'));
        foreach (['forgot', 'reset', 'email_link'] as $other) {
            $this->assertStringNotContainsString('login-mascot', $this->read("resources/views/auth/{$other}.blade.php"));
        }
    }

    public function test_the_animation_is_css_only_slow_and_respects_reduced_motion(): void
    {
        $css = $this->css();
        // Every animation rule sits inside the no-preference block, so reduced motion shows the static normal smile by construction.
        $outside = preg_replace('/@media \(prefers-reduced-motion: no-preference\) \{.*\n\}\n?$/s', '', $css);
        $this->assertStringNotContainsString('animation', $outside);
        $this->assertStringNotContainsString('@keyframes', $outside);
        $this->assertStringContainsString('@media (prefers-reduced-motion: no-preference)', $css);
        $this->assertStringContainsString('animation-duration: 36s', $css);
        // Only transform and opacity are ever animated.
        preg_match_all('/@keyframes [\w-]+ \{(.*?)\n  \}|@keyframes [\w-]+ \{[^\n]*\}/s', $css, $blocks);
        $this->assertNotEmpty($blocks[0]);
        $declared = [];
        foreach ($blocks[0] as $block) {
            preg_match_all('/([a-z-]+)\s*:/', preg_replace('/@keyframes [\w-]+/', '', $block), $m);
            $declared = array_merge($declared, $m[1]);
        }
        $this->assertSame([], array_values(array_diff(array_unique($declared), ['opacity', 'transform', 'animation-timing-function'])));
        $this->assertStringNotContainsString('infinite alternate', $css);
        $this->assertStringContainsString('data-rb-hidden', $css);
    }

    public function test_the_script_only_pauses_for_hidden_tabs_and_uses_no_timers_or_libraries(): void
    {
        $js = $this->read('public/js/login-mascot.js');
        $this->assertStringContainsString('visibilitychange', $js);
        $this->assertDoesNotMatchRegularExpression('/setInterval|setTimeout|requestAnimationFrame|import |require\(/', $js);
    }

    public function test_login_form_and_the_existing_desktop_banner_contract_are_untouched(): void
    {
        $login = $this->read('resources/views/auth/login.blade.php');
        foreach (['route(\'login\')', 'field-id="email"', 'field-id="password"', 'password.request', '$retry ? \'new-password\' : \'current-password\'', 'autofocus', 'rb-auth-fineprint'] as $needle) {
            $this->assertStringContainsString($needle, $login);
        }
        $aside = $this->read('resources/views/auth/_aside.blade.php');
        $this->assertStringContainsString('class="rb-auth-login__art" aria-hidden="true"', $aside);
        $this->assertStringContainsString('Make room for your next big idea.', $aside);
        $this->assertStringContainsString('Space to learn. To build. To create.', $aside);
    }
}
