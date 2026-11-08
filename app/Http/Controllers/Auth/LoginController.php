<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        if (RateLimiter::tooManyAttempts($accountKey, self::MAX_FAILURES_PER_ACCOUNT_AND_IP)
            || RateLimiter::tooManyAttempts($ipKey, self::MAX_FAILURES_PER_IP)) {
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
            RateLimiter::hit($accountKey, self::DECAY_SECONDS);
            RateLimiter::hit($ipKey, self::DECAY_SECONDS);

            return back()
                ->withErrors(['email' => 'Wrong email or password.'])
                ->withInput(['email' => $email]);
        }

        RateLimiter::clear($accountKey);

        // New session ID on every login (stops "session fixation" attacks)
        $request->session()->regenerate();

        return redirect($request->user()->homeUrl());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
