<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 9, on every page for logged-in users:
 *  - an account an admin turned off is logged out right away, even mid-session
 *  - after an admin typed a password (new account or a reset), the user must pick their own first
 */
class AccountChecks
{
    /** Pages that still open while a new password is due */
    private const FIRST_LOGIN_PAGES = ['welcome', 'welcome.save', 'photos.show'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'This account is turned off. Ask your department’s admin.']);
        }

        if ($user && $user->must_change_password && ! $request->routeIs(...self::FIRST_LOGIN_PAGES)) {
            return redirect()->route('welcome');
        }

        return $next($request);
    }
}
