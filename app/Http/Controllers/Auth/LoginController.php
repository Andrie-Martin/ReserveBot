<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    // QA F-01. Only FAILED attempts count. The account counter is keyed on the account the typed address resolves to
    // (or, for an address that matches nothing, on its canonical form), and every reply looks the same for real and
    // made-up emails.
    public const MAX_FAILURES_PER_ACCOUNT_AND_IP = 5;   // same email from the same IP
    public const MAX_FAILURES_PER_IP = 20;              // one IP trying many emails
    public const DECAY_SECONDS = 60;

    /**
     * What the account counter is keyed on, for an address that matches no account: the typed address in a
     * canonical form. The database compares emails with utf8mb4_unicode_ci, which gives zero-width characters, bidi marks,
     * control characters and combining marks no weight, so `rep<ZWSP>2@x` is the same account as `rep2@x`. Those
     * characters are removed here (with spaces, case and accents) so every spelling of a made-up address also
     * shares one counter, exactly as the spellings of a real one do.
     */
    public static function canonicalEmail(string $email): string
    {
        $email = Str::lower(trim($email));
        $stripped = preg_replace('/[\p{Cf}\p{Cc}\p{Mn}\p{Zs}\p{Zl}\p{Zp}]+/u', '', $email);

        return Str::transliterate($stripped ?? $email);
    }

    /**
     * Counter key for one account from one IP. The identity is hashed, so the key has a fixed length (the database cache
     * store cuts or refuses keys over 255 characters) and no typed address ever reaches the cache table. For an
     * existing account the identity is its id, found with the same lookup the login uses, so every spelling the
     * database accepts shares one counter; otherwise it is the canonical typed address.
     */
    public static function accountKey(string $email, string $ip, ?int $accountId = null): string
    {
        $identity = $accountId !== null ? 'account:'.$accountId : 'typed:'.self::canonicalEmail($email);

        return 'login|'.hash('sha256', $identity).'|'.$ip;
    }

    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $email = Str::lower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');

        // Resolve the account with the same comparison Auth::attempt uses (the database collation), so that
        // spellings the database treats as one address cannot be spread over several counters.
        $accountId = $email === '' ? null : User::query()->where('email', $email)->value('id');
        $accountKey = self::accountKey($email, (string) $request->ip(), $accountId === null ? null : (int) $accountId);
        $ipKey = 'login-ip|'.$request->ip();

        // Count first, then decide. Checking the counters and only recording the failure after the (slow) password check let
        // every request that arrived together pass the check, so a burst got far more than the limit. Each increment is
        // atomic in the cache, so each request gets its own number and only the first N run the password check.
        $accountAttempts = $this->count($accountKey);
        $ipAttempts = 0;
        if ($accountAttempts <= self::MAX_FAILURES_PER_ACCOUNT_AND_IP) {
            $ipAttempts = $this->count($ipKey);
            if ($ipAttempts > self::MAX_FAILURES_PER_IP) {
                $this->refund($accountKey);   // refused for the IP, so neither counter is charged
                $this->refund($ipKey);
            }
        }

        if ($accountAttempts > self::MAX_FAILURES_PER_ACCOUNT_AND_IP || $ipAttempts > self::MAX_FAILURES_PER_IP) {
            event(new Lockout($request));
            $seconds = max(RateLimiter::availableIn($accountKey), RateLimiter::availableIn($ipKey), 1);

            return back()
                ->withErrors(['email' => 'Too many login attempts. Please try again in '.$seconds.' '.Str::plural('second', $seconds).'.'])
                ->withInput(['email' => $email])
                ->header('Retry-After', $seconds);
        }

        // Turned-off accounts (is_active = 0) can't log in. Same message whether the
        // email or the password is wrong, so nobody can probe for accounts.
        $ok = $email !== '' && $password !== ''
            && Auth::attempt(['email' => $email, 'password' => $password, 'is_active' => true]);

        if (! $ok) {
            // Already counted above.
            return back()
                ->withErrors(['email' => 'Wrong email or password.'])
                ->withInput(['email' => $email]);
        }

        // Only failures count: a success clears the account counter and gives its IP slot back.
        RateLimiter::clear($accountKey);
        $this->refund($ipKey);

        // New session ID on every login (stops "session fixation" attacks)
        $request->session()->regenerate();

        return redirect($request->user()->homeUrl());
    }

    /**
     * Adds one to a counter and returns the new total. Uses the cache's own atomic add and increment, with the same
     * key and ":timer" layout as Laravel's RateLimiter (so availableIn() and clear() keep working). RateLimiter::increment()
     * is not used because, when two requests create a counter at the same moment, the loser writes the value 1 back and
     * erases increments the others already made.
     */
    private function count(string $key): int
    {
        $cache = Cache::store(config('cache.limiter'));
        $cache->add($key.':timer', Carbon::now()->addSeconds(self::DECAY_SECONDS)->getTimestamp(), self::DECAY_SECONDS);
        $cache->add($key, 0, self::DECAY_SECONDS);
        $total = $cache->increment($key);
        if ($total === false) {   // the counter expired between the two calls
            $cache->add($key, 0, self::DECAY_SECONDS);
            $total = $cache->increment($key);
        }

        return (int) $total;
    }

    /** Gives one count back (never below zero, and nothing if the counter has already expired). */
    private function refund(string $key): void
    {
        $cache = Cache::store(config('cache.limiter'));
        if ((int) $cache->get($key, 0) > 0) {
            $cache->decrement($key);
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
