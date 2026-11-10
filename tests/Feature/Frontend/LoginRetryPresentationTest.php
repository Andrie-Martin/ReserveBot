<?php

namespace Tests\Feature\Frontend;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/Support/auth-runtime.php';

/**
 * Sandbox issue 9 (QA AUTH-01). A browser that has the account's password saved refilled it after a wrong
 * password, so pressing "Log in" again logged in with the saved one and it looked as if the wrong password had
 * been accepted. After a refused attempt the password box is now marked autocomplete="new-password" (browsers do
 * not fill a saved password into that) and is rendered empty; the first visit keeps "current-password" so saved
 * logins still work. Renders the real login view; needs no database.
 */
final class LoginRetryPresentationTest extends TestCase
{
    protected function tearDown(): void
    {
        \foundationsRelease();
        parent::tearDown();
    }

    private function passwordBox(string $case): \DOMElement
    {
        foundationsApplication();
        $document = foundationsDocument(authRender(authCases()[$case]));
        $boxes = $document->query('//main//form//input[@name="password"]');
        $this->assertSame(1, $boxes->length, "$case has one password box");

        return $boxes->item(0);
    }

    public function test_first_visit_lets_the_browser_fill_a_saved_password(): void
    {
        foreach (['login', 'login-status'] as $case) {
            $box = $this->passwordBox($case);
            $this->assertSame('current-password', $box->getAttribute('autocomplete'), $case);
            $this->assertFalse($box->hasAttribute('value'), $case);
        }
    }

    public function test_after_a_refused_attempt_the_browser_must_not_refill_the_saved_password(): void
    {
        $box = $this->passwordBox('login-error');
        $this->assertSame('new-password', $box->getAttribute('autocomplete'));
        $this->assertSame('password', $box->getAttribute('type'));
        $this->assertTrue($box->hasAttribute('required'), 'an empty box stops the form in the browser');
        $this->assertFalse($box->hasAttribute('value'), 'the typed password is never sent back to the page');
        $this->assertFalse($box->hasAttribute('readonly'));
    }

    public function test_the_email_stays_filled_in_after_a_refused_attempt(): void
    {
        foundationsApplication();
        $document = foundationsDocument(authRender(authCases()['login-error']));
        $email = $document->query('//main//form//input[@name="email"]')->item(0);
        $this->assertSame('old@example.invalid', $email->getAttribute('value'));
        $this->assertSame('username', $email->getAttribute('autocomplete'));
    }
}
