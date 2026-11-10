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

    public function test_spellings_the_database_ignores_share_one_canonical_address(): void
    {
        $plain = LoginController::accountKey('alice@example.com', '203.0.113.5');
        foreach (["ali\u{200B}ce@example.com", "ali\u{200C}\u{200D}ce@example.com", "al\u{2060}ice@example.com", "\u{FEFF}alice@example.com",
            "ali\u{200E}ce@example.com", "ali\u{0001}ce@example.com", "alice\u{0301}@example.com", 'al ice@example.com', 'ALICE@EXAMPLE.COM',
            "\u{FF41}lice@example.com", "al\u{00ED}ce@example.com"] as $spelling) {
            $this->assertSame($plain, LoginController::accountKey($spelling, '203.0.113.5'), 'Spelling should share the canonical bucket: '.json_encode($spelling));
        }
        $this->assertNotSame($plain, LoginController::accountKey('alicf@example.com', '203.0.113.5'));
    }

    public function test_a_resolved_account_uses_its_id_whatever_was_typed(): void
    {
        $a = LoginController::accountKey("ali\u{200B}ce@example.com", '203.0.113.5', 42);
        $this->assertSame($a, LoginController::accountKey('totally-different-spelling', '203.0.113.5', 42));
        $this->assertNotSame($a, LoginController::accountKey('alice@example.com', '203.0.113.5', 43));
        $this->assertNotSame($a, LoginController::accountKey('alice@example.com', '203.0.113.5'));
        $this->assertStringNotContainsString('alice', $a);
        $this->assertStringNotContainsString('42', explode('|', $a)[1], 'The account id itself is not exposed either.');
    }

    public function test_malformed_input_never_breaks_key_building(): void
    {
        foreach (["\xff\xfe\x00bad", str_repeat("\u{200B}", 10000), '', ' '] as $input) {
            $this->assertMatchesRegularExpression('/^login\|[0-9a-f]{64}\|203\.0\.113\.5$/', LoginController::accountKey($input, '203.0.113.5'));
        }
    }
}
