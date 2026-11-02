<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AccountFields;
use App\Support\AccountRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Phase 9: "Forgot password?" on the login page. The link is emailed (it works for 60 minutes,
 * once). The page never says whether an email has an account, so nobody can probe for accounts.
 * Turned-off accounts get no link.
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']], ['email.*' => 'Type the email of your account.']);
        $email = AccountRules::email($request->input('email'));

        try {
            PasswordBroker::broker()->sendResetLink(['email' => $email, 'is_active' => true]);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['email' => 'Sending emails isn’t working right now. Ask your department’s admin to set a new password for you.']);
        }

        return back()->with('status', 'If '.$email.' has an account here, we sent it a link to pick a new password. It works for '
            .config('auth.passwords.users.expire', 60).' minutes. Check your spam folder too.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => AccountFields::password(confirmed: true),
        ], ['email.*' => 'Type the email of your account.'] + AccountFields::MESSAGES);

        $status = PasswordBroker::broker()->reset([
            'email' => AccountRules::email($request->input('email')),
            'is_active' => true,
            'password' => $request->input('password'),
            'password_confirmation' => $request->input('password_confirmation'),
            'token' => $request->input('token'),
        ], function (User $user, string $password) {
            $user->forceFill([
                'password' => $password,
                'must_change_password' => false,
                'email_verified_at' => $user->email_verified_at ?? now(),   // the emailed link proves the email works
                'remember_token' => Str::random(60),
            ])->save();
        });

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'This link has expired or was already used, or it’s for another email. Ask for a new link.']);
        }

        return redirect()->route('login')->with('status', 'Your password is changed. Log in with it.');
    }
}
