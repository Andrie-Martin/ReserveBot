<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Phase 9: the checks of the account forms (My account, the admin's Accounts pages and the
 * password pages), written once so every form says the same thing.
 */
final class AccountFields
{
    public const PASSWORD_HINT = 'At least 8 characters, with letters and numbers.';

    public const MESSAGES = [
        'name.required' => 'Type a name.',
        'name.max' => 'Keep the name under 100 characters.',
        'email.required' => 'Type an email address.',
        'email.email' => 'That doesn’t look like an email address.',
        'email.max' => 'Keep the email under 150 characters.',
        'email.unique' => 'Another account already uses that email.',
        'password.required' => 'Type a password.',
        'password.min' => 'Use at least 8 characters, with letters and numbers.',
        'password.max' => 'Keep the password under 64 characters.',
        'password.regex' => 'Use at least 8 characters, with letters and numbers.',
        'password.confirmed' => 'The two new passwords don’t match.',
        'current_password.required' => 'Type your current password.',
        'current_password.current_password' => 'That’s not your current password.',
    ];

    /** Tidies typed values before they're checked: single spaces in the name, the email in small letters */
    public static function tidy(Request $request): void
    {
        $tidy = [];
        if ($request->has('name')) {
            $tidy['name'] = preg_replace('/\s+/u', ' ', trim((string) $request->input('name')));
        }
        if ($request->has('email')) {
            $tidy['email'] = AccountRules::email($request->input('email'));
        }
        $request->merge($tidy);
    }

    /** A new password: at least 8 characters, with letters and numbers */
    public static function password(bool $confirmed = false): array
    {
        return ['bail', 'required', 'string', 'min:8', 'max:64', 'regex:/[A-Za-z]/', 'regex:/[0-9]/', ...($confirmed ? ['confirmed'] : [])];
    }

    /**
     * An account's email. A block account's must be Gmail or PUP webmail; $keep is its current
     * email, which stays allowed (accounts made before this rule, like the test accounts).
     */
    public static function email(string $role, ?int $ignoreId = null, ?string $keep = null): array
    {
        return ['bail', 'required', 'string', 'max:150', 'email:rfc', Rule::unique('users', 'email')->ignore($ignoreId),
            function (string $attribute, mixed $value, Closure $fail) use ($role, $keep) {
                if ($role === 'rep' && $value !== $keep && ! AccountRules::isRepEmail((string) $value)) {
                    $fail('A block account’s email must be Gmail (…@gmail.com) or PUP webmail (…@iskolarngbayan.pup.edu.ph).');
                }
            }];
    }
}
