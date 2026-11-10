<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 9: a block account books only after clicking the emailed link that connects its Gmail or
 * PUP webmail. Their bookings already made can still be seen and cancelled.
 */
class RequireConnectedEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role === 'rep' && ! $user->hasConnectedEmail()) {
            return redirect()->route('account')->with('problem',
                'Connect your email before you book: open the email we sent to '.$user->email.' and click “Connect my email”. No email? Send it again below.');
        }

        return $next($request);
    }
}
