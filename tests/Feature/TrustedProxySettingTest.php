<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Sandbox issue 1: the visitor's address that the login lockout counts on. With no trusted proxy (the default) a
 * forwarded address is ignored; with the proxy's own address in TRUSTED_PROXIES the real visitor behind it is used,
 * and a forwarded address from anyone else is still ignored. Needs no database.
 */
class TrustedProxySettingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/__test-ip', fn (Request $request) => $request->ip());
    }

    private function seenAddress(string $connectingFrom, string $forwardedFor): string
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $connectingFrom])
            ->get('/__test-ip', ['X-Forwarded-For' => $forwardedFor])
            ->assertOk()->getContent();
    }

    public function test_by_default_no_proxy_is_trusted_and_a_forwarded_address_is_ignored(): void
    {
        $this->assertNull(config('trustedproxy.proxies'));
        $this->assertSame('172.18.0.1', $this->seenAddress('172.18.0.1', '203.0.113.7'));
    }

    public function test_a_listed_proxy_passes_on_the_real_visitor(): void
    {
        config(['trustedproxy.proxies' => ['172.18.0.1']]);
        $this->assertSame('203.0.113.7', $this->seenAddress('172.18.0.1', '203.0.113.7'));
        $this->assertSame('203.0.113.8', $this->seenAddress('172.18.0.1', '203.0.113.8'), 'two visitors behind the proxy are told apart');
    }

    public function test_a_forwarded_address_from_anyone_else_is_still_ignored(): void
    {
        config(['trustedproxy.proxies' => ['172.18.0.1']]);
        $this->assertSame('198.51.100.9', $this->seenAddress('198.51.100.9', '203.0.113.7'), 'a visitor cannot fake their address');
    }
}
