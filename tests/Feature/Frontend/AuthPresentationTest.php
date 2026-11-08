<?php

namespace Tests\Feature\Frontend;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/Support/auth-runtime.php';

final class AuthPresentationTest extends TestCase
{
    protected function tearDown(): void
    {
        \foundationsRelease();
        parent::tearDown();
    }

    public function test_actual_auth_views_preserve_presentation_contracts(): void
    {
        foundationsApplication();
        $this->assertGreaterThan(0, authContracts());
    }

    public function test_login_promotional_card_is_not_hidden_by_viewport_height(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3).'/public/css/auth.css');
        preg_match_all('/@media\s*\(([^)]*)\)\s*\{(.*?)\n\}/s', $css, $blocks, PREG_SET_ORDER);
        $this->assertNotEmpty($blocks);
        foreach ($blocks as [, $query, $body]) {
            if (str_contains($query, 'max-height')) {
                $this->assertDoesNotMatchRegularExpression('/\.rb-auth-login__art\s*\{[^}]*display\s*:\s*none/', $body, 'Viewport height alone must not hide the maroon card (100% zoom on a 900px screen).');
            }
        }
        // Width-based rules still own the tablet/mobile behaviour.
        $this->assertMatchesRegularExpression('/@media \(max-width: 63\.999rem\)\s*\{.*?\.rb-auth-login__art\s*\{\s*display:\s*none/s', $css);
    }
}
