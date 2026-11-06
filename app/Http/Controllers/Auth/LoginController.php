<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    // QA F-01. Only FAILED attempts count. The counters are keyed on what was typed (never on whether
    // the account exists), so a throttled reply looks the same for real and made-up emails.
    public const MAX_FAILURES_PER_ACCOUNT_AND_IP = 5;   // same email from the same IP
    public const MAX_FAILURES_PER_IP = 20;              // one IP trying many emails
    public const DECAY_SECONDS = 60;

    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $email = Str::lower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');

        $accountKey = 'login|'.Str::transliterate($email).'|'.$request->ip();
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
