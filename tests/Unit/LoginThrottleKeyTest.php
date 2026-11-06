<?php

namespace Tests\Unit;

use App\Http\Controllers\Auth\LoginController;
use PHPUnit\Framework\TestCase;

/** QA F-01 follow-up: the throttle key must stay short and must not contain the typed email. */
class LoginThrottleKeyTest extends TestCase
{
    public function test_key_has_a_fixed_short_length_whatever_is_typed(): void
    {
        foreach (['a@b.co', str_repeat('a', 300).'@example.com', str_repeat('é', 5000), ''] as $email) {
            $key = LoginController::accountKey($email, '2001:0db8:85a3:0000:0000:8a2e:0370:7334');
            $this->assertLessThan(150, strlen($key));   // cache prefix + ":timer" must stay under 255
            $this->assertStringEndsWith('|2001:0db8:85a3:0000:0000:8a2e:0370:7334', $key);   // IP never cut off
        }
    }

    public function test_key_does_not_contain_the_typed_email(): void
    {
        $this->assertStringNotContainsString('alice', LoginController::accountKey('alice@example.com', '203.0.113.5'));
    }

    public function test_same_address_typed_differently_gives_one_key_and_other_inputs_differ(): void
    {
        $key = LoginController::accountKey('alice@example.com', '203.0.113.5');
        $this->assertSame($key, LoginController::accountKey('  ALICE@Example.COM ', '203.0.113.5'));
        $this->assertNotSame($key, LoginController::accountKey('bob@example.com', '203.0.113.5'));
        $this->assertNotSame($key, LoginController::accountKey('alice@example.com', '203.0.113.6'));
    }
}
