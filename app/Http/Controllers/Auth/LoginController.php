<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $email = Str::lower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');

        // Turned-off accounts (is_active = 0) can't log in. Same message whether the
        // email or the password is wrong, so nobody can probe for accounts.
        $ok = $email !== '' && $password !== ''
            && Auth::attempt(['email' => $email, 'password' => $password, 'is_active' => true]);

        if (! $ok) {
            return back()
                ->withErrors(['email' => 'Wrong email or password.'])
                ->withInput(['email' => $email]);
        }

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
