<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Phase 9: connecting an account's email (Gmail or PUP webmail for reps).
 * The emailed button is a signed link: it works without logging in (on a phone, say) for
 * User::EMAIL_LINK_DAYS days, and only for the email it was sent to.
 */
class EmailLinkController extends Controller
{
    public function connect(User $user, string $hash): View
    {
        if (! hash_equals(sha1($user->email), $hash)) {
            return view('auth.email_link', [
                'ok' => false,
                'message' => 'This link is for an email the account doesn’t use anymore. Log in and send a new link from My account.',
            ]);
        }

        if (! $user->hasConnectedEmail()) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return view('auth.email_link', [
            'ok' => true,
            'message' => $user->email.' is connected to '.$user->displayName().'’s account.'
                .($user->role === 'rep' ? ' You can book rooms and equipment now.' : ''),
        ]);
    }

    /** "Send it again", at most once a minute. The answer shows in the yellow bar or on My account. */
    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasConnectedEmail()) {
            return back()->with('connect_note', 'Your email is already connected.');
        }

        $key = 'connect-email:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->with('connect_note', 'We just sent one. You can send another in '.RateLimiter::availableIn($key).' seconds.');
        }
        RateLimiter::hit($key, 60);

        return back()->with('connect_note', $user->sendConnectEmail()
            ? 'We sent a new link to '.$user->email.'. It works for '.User::EMAIL_LINK_DAYS.' days. Check your spam folder too.'
            : 'The email couldn’t be sent. Try again in a while, or tell your admin.');
    }
}
