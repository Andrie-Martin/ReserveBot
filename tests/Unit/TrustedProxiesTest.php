<?php

namespace Tests\Unit;

use App\Support\TrustedProxies;
use PHPUnit\Framework\TestCase;

/** Sandbox issue 1: the TRUSTED_PROXIES setting only accepts exact addresses and ranges, never "everyone". */
class TrustedProxiesTest extends TestCase
{
    public function test_nothing_is_trusted_by_default(): void
    {
        foreach ([null, '', '   ', ',', ' , , '] as $value) {
            $this->assertNull(TrustedProxies::parse($value), var_export($value, true));
        }
    }

    public function test_addresses_and_ranges_are_listed_once_each(): void
    {
        $this->assertSame(['127.0.0.1'], TrustedProxies::parse('127.0.0.1'));
        $this->assertSame(['127.0.0.1', '172.18.0.0/16', '::1'], TrustedProxies::parse(' 127.0.0.1 ,172.18.0.0/16, ::1,127.0.0.1 '));
        $this->assertSame(['2001:db8::/32'], TrustedProxies::parse('2001:db8::/32'));
    }

    public function test_trust_everyone_is_refused(): void
    {
        foreach (['*', '**', 'REMOTE_ADDR', '0.0.0.0/0', '::/0', '10.0.0.0/1'] as $value) {
            $this->assertNull(TrustedProxies::parse($value), $value);
        }
        // A good address next to a refused one: the good one is kept, the other is dropped
        $this->assertSame(['10.0.0.5'], TrustedProxies::parse('*, 10.0.0.5'));
    }

    public function test_anything_that_is_not_an_address_is_dropped(): void
    {
        foreach (['ngrok', 'localhost', '300.1.1.1', '10.0.0.1/33', '10.0.0.1/abc', '10.0.0.1/', 'http://10.0.0.1'] as $value) {
            $this->assertNull(TrustedProxies::parse($value), $value);
        }
    }
}
