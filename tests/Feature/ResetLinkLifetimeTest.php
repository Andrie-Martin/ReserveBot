<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordLink;
use Tests\TestCase;

/**
 * Sandbox issue 11: the emailed "Forgot your password?" link works for 20 minutes (it was 60).
 * Needs no database. The expiry itself is checked against a real token in AuthWorkflowTest.
 */
class ResetLinkLifetimeTest extends TestCase
{
    public function test_the_reset_link_is_set_to_20_minutes(): void
    {
        $this->assertSame(20, config('auth.passwords.users.expire'));
    }

    public function test_the_email_says_20_minutes(): void
    {
        $user = new User(['name' => 'Bea Prieto', 'email' => 'bea@gmail.com']);
        $mail = (new ResetPasswordLink('a-token'))->toMail($user);
        $text = implode(' ', array_merge($mail->introLines, $mail->outroLines));

        $this->assertStringContainsString('The button works for 20 minutes.', $text);
        $this->assertStringNotContainsString('60 minutes', $text);
    }
}
