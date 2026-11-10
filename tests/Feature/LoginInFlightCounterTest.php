<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Sandbox issue 5: the login limiter's "attempts in flight" counter for one IP is stepped down with one atomic
 * decrement and never stays below zero. Needs no database (the test cache is the in-memory array store).
 */
class LoginInFlightCounterTest extends TestCase
{
    private const KEY = 'login-ip|203.0.113.90';

    private function leave(): void
    {
        $method = new ReflectionMethod(LoginController::class, 'leaveIp');
        $method->invoke(new LoginController, self::KEY);
    }

    private function inFlight(): int
    {
        return (int) Cache::get(self::KEY.':inflight', 0);
    }

    public function test_leaving_steps_the_counter_down_by_one(): void
    {
        Cache::put(self::KEY.':inflight', 3, 20);
        $this->leave();
        $this->assertSame(2, $this->inFlight());
        $this->leave();
        $this->leave();
        $this->assertSame(0, $this->inFlight());
    }

    public function test_the_counter_never_stays_below_zero(): void
    {
        // Empty counter (it expired while the attempt was in flight): leaving must not leave it at -1.
        Cache::put(self::KEY.':inflight', 0, 20);
        $this->leave();
        $this->assertSame(0, $this->inFlight());

        // No counter at all: the same.
        Cache::forget(self::KEY.':inflight');
        $this->leave();
        $this->leave();
        $this->assertSame(0, $this->inFlight());

        // More leaves than places: every extra one is given back.
        Cache::put(self::KEY.':inflight', 1, 20);
        $this->leave();
        $this->leave();
        $this->leave();
        $this->assertSame(0, $this->inFlight());
    }

    public function test_a_counter_started_again_by_a_leave_still_expires(): void
    {
        Cache::forget(self::KEY.':inflight');
        $this->leave();
        $this->travel(21)->seconds();   // its life is 20 seconds: after that it is gone, not kept forever
        $this->assertNull(Cache::get(self::KEY.':inflight'));
    }
}
