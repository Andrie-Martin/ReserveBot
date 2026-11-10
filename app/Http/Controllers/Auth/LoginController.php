<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
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

    private const LOCK_SECONDS = 10;        // longest one attempt may hold its account's turn

    private const LOCK_WAIT_SECONDS = 5;    // longest an attempt waits for its turn before being refused

    private const ADMISSION_WAIT_SECONDS = 3.0;   // longest an attempt waits for in-flight attempts from its IP to settle

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

        // Attempts for one account (from one IP) run one at a time. Under the lock the account counter holds exactly the failures
        // so far: each attempt checks it, runs the password check and, only if that fails, adds one. Simultaneous wrong guesses
        // therefore cannot exceed the limit (the 6th waits, sees 5 failures and is refused at once), and a correct password never
        // uses any of the allowance, so simultaneous correct logins cannot refuse one another. Different accounts do not wait.
        $lock = Cache::store(config('cache.limiter'))->lock('login-lock|'.$accountKey, self::LOCK_SECONDS)
            ->betweenBlockedAttemptsSleepFor(50);   // a waiting attempt looks again every 50 ms (default 250 ms)
        try {
            $lock->block(self::LOCK_WAIT_SECONDS);
        } catch (LockTimeoutException) {
            return $this->tooManyAttempts($request, $email, $accountKey, $ipKey);   // fail closed
        }

        $admitted = false;
        try {
            if (RateLimiter::tooManyAttempts($accountKey, self::MAX_FAILURES_PER_ACCOUNT_AND_IP)) {
                return $this->tooManyAttempts($request, $email, $accountKey, $ipKey);
            }

            // The IP counter is shared by many accounts, so it cannot be serialized without making everyone behind one address
            // wait for each other. Instead an attempt is admitted only if the recorded failures plus the attempts already in
            // flight still fit the limit, which holds even if every one of them fails. A successful login never uses an IP slot.
            if (! $this->admitIp($ipKey)) {
                return $this->tooManyAttempts($request, $email, $accountKey, $ipKey);
            }
            $admitted = true;

            // Turned-off accounts (is_active = 0) can't log in. Same message whether the
            // email or the password is wrong, so nobody can probe for accounts.
            $ok = $email !== '' && $password !== ''
                && Auth::attempt(['email' => $email, 'password' => $password, 'is_active' => true]);

            if (! $ok) {
                $this->count($accountKey);   // record the failure on both counters before the in-flight slot is given up
                $this->count($ipKey);

                return back()
                    ->withErrors(['email' => 'Wrong email or password.'])
                    ->withInput(['email' => $email]);
            }

            // Only failures count: a success clears the account counter and leaves the IP counter alone.
            RateLimiter::clear($accountKey);
        } finally {
            if ($admitted) {
                $this->leaveIp($ipKey);
            }
            $lock->release();
        }

        // New session ID on every login (stops "session fixation" attacks)
        $request->session()->regenerate();

        return redirect($request->user()->homeUrl());
    }

    private function tooManyAttempts(Request $request, string $email, string $accountKey, string $ipKey): RedirectResponse
    {
        event(new Lockout($request));
        $seconds = max(RateLimiter::availableIn($accountKey), RateLimiter::availableIn($ipKey), 1);

        return back()
            ->withErrors(['email' => 'Too many login attempts. Please try again in '.$seconds.' '.Str::plural('second', $seconds).'.'])
            ->withInput(['email' => $email])
            ->header('Retry-After', $seconds);
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

    /**
     * Admission to the password check for one IP. Two numbers are kept: the failures recorded so far (the "login-ip" counter,
     * which is what the limit is about) and the attempts in flight right now (a short-lived counter). An attempt is admitted when
     * failures + in-flight (itself included) <= the limit; otherwise it waits a little for the in-flight ones to finish, because
     * each either succeeds (leaves no trace) or fails (is recorded) and then the answer is certain. With the limit already reached
     * it is refused at once. The in-flight number is read after incrementing and failures are recorded before it is decremented,
     * so no attempt can slip through unseen.
     */
    private function admitIp(string $ipKey): bool
    {
        $cache = Cache::store(config('cache.limiter'));
        $inFlightKey = $ipKey.':inflight';
        $deadline = microtime(true) + self::ADMISSION_WAIT_SECONDS;
        do {
            $cache->add($inFlightKey, 0, self::LOCK_SECONDS * 2);
            $inFlight = (int) $cache->increment($inFlightKey);
            $failures = (int) $cache->get($ipKey, 0);
            if ($failures < self::MAX_FAILURES_PER_IP && $failures + $inFlight <= self::MAX_FAILURES_PER_IP) {
                return true;
            }
            $this->leaveIp($ipKey);
            if ($failures >= self::MAX_FAILURES_PER_IP) {
                return false;
            }
            usleep(100000);
        } while (microtime(true) < $deadline);

        return false;
    }

    /**
     * Gives up this attempt's in-flight place. The step down is one atomic decrement, so two attempts leaving at the
     * same moment can never both act on the same stale reading. If that step took the counter below zero (it expired
     * and started again while this attempt was in flight), the step is given straight back: the counter never stays negative.
     */
    private function leaveIp(string $ipKey): void
    {
        $cache = Cache::store(config('cache.limiter'));
        $inFlightKey = $ipKey.':inflight';
        $cache->add($inFlightKey, 0, self::LOCK_SECONDS * 2);   // an expired counter starts again from zero, with its short life
        $left = $cache->decrement($inFlightKey);
        if ($left !== false && (int) $left < 0) {
            $cache->increment($inFlightKey);
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
