<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only lets the listed roles through, e.g. ->middleware('role:lab_staff,admin').
 * Checked on the server for every request, so hiding a link is never the only protection.
 */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! in_array($user->role, $roles, true)) {
            abort(403);   // shows resources/views/errors/403.blade.php
        }

        return $next($request);
    }
}
