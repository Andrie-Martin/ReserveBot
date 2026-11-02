<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AccountFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Phase 9: passwords of the logged-in user.
 *  - Welcome page: after an admin typed a password (a new account or a reset), the user picks
 *    their own before anything else, plus their School ID if it's missing.
 *  - My account, Password tab: anyone can change their password by typing the current one.
 * Other devices logged in to the account are logged out (the 'auth.session' check in routes/web.php).
 */
class PasswordController extends Controller
{
    public function welcome(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user->must_change_password) {
            return redirect($user->homeUrl());
        }

        return view('account.welcome', ['user' => $user, 'askSchoolId' => $this->asksSchoolId($user)]);
    }

    public function saveWelcome(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->must_change_password) {
            return redirect($user->homeUrl());
        }
        AccountFields::tidy($request);

        $rules = ['password' => AccountFields::password(confirmed: true)];
        if ($this->asksSchoolId($user)) {
            $rules['school_id'] = AccountFields::schoolId($user->role === 'rep', $user->id);
        }
        $validator = Validator::make($request->all(), $rules, AccountFields::MESSAGES);
        $validator->after(function ($validator) use ($request, $user) {
            if (! $validator->errors()->has('password') && Hash::check((string) $request->input('password'), $user->password)) {
                $validator->errors()->add('password', 'Pick a password different from the one your admin gave you.');
            }
        });
        $data = $validator->validate();

        $user->password = $data['password'];
        $user->must_change_password = false;
        if (array_key_exists('school_id', $data)) {
            $user->school_id = $data['school_id'];
        }
        $user->save();

        $next = $user->hasConnectedEmail()
            ? 'Check your information below. You can add a photo too.'
            : 'Next: open the email we sent to '.$user->email.' and click “Connect my email”.'
                .($user->role === 'rep' ? ' You can book once it’s connected.' : '');

        return redirect()->route('account')->with('status', 'Your password is saved. '.$next);
    }

    public function edit(Request $request): View
    {
        return view('account.password', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'current_password'],
            'password' => AccountFields::password(confirmed: true),
        ], AccountFields::MESSAGES);
        $validator->after(function ($validator) use ($request) {
            if ($validator->errors()->isEmpty() && $request->input('password') === $request->input('current_password')) {
                $validator->errors()->add('password', 'Pick a password different from your current one.');
            }
        });
        $data = $validator->validate();

        $user->update(['password' => $data['password']]);

        return redirect()->route('password.edit')->with('status', 'Your password is changed. Any other device logged in to your account is logged out.');
    }

    /** Reps and lab staff without a School ID are asked for it on the welcome page */
    private function asksSchoolId(User $user): bool
    {
        return in_array($user->role, User::MANAGED_ROLES, true) && $user->school_id === null;
    }
}
