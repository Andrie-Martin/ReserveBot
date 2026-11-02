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
        'school_id.required' => 'Type the School ID number, e.g. 2021-00123-MN-0.',
        'school_id.max' => 'A School ID looks like 2021-00123-MN-0.',
        'school_id.regex' => 'A School ID looks like 2021-00123-MN-0.',
        'school_id.unique' => 'Another account already has that School ID.',
        'password.required' => 'Type a password.',
        'password.min' => 'Use at least 8 characters, with letters and numbers.',
        'password.max' => 'Keep the password under 64 characters.',
        'password.regex' => 'Use at least 8 characters, with letters and numbers.',
        'password.confirmed' => 'The two new passwords don’t match.',
        'current_password.required' => 'Type your current password.',
        'current_password.current_password' => 'That’s not your current password.',
    ];

    /** Tidies typed values before they're checked: the email in small letters, the School ID with its dashes */
    public static function tidy(Request $request): void
    {
        $tidy = [];
        if ($request->has('name')) {
            $tidy['name'] = preg_replace('/\s+/u', ' ', trim((string) $request->input('name')));
        }
        if ($request->has('email')) {
            $tidy['email'] = AccountRules::email($request->input('email'));
        }
        if ($request->has('school_id')) {
            $tidy['school_id'] = AccountRules::schoolId($request->input('school_id')) ?: null;
        }
        $request->merge($tidy);
    }

    /** A new password: at least 8 characters, with letters and numbers */
    public static function password(bool $confirmed = false): array
    {
        return ['bail', 'required', 'string', 'min:8', 'max:64', 'regex:/[A-Za-z]/', 'regex:/[0-9]/', ...($confirmed ? ['confirmed'] : [])];
    }

    /**
     * An account's email. A block rep's must be Gmail or PUP webmail; $keep is a rep's current
     * email, which stays allowed (accounts made before this rule, like the test accounts).
     */
    public static function email(string $role, ?int $ignoreId = null, ?string $keep = null): array
    {
        return ['bail', 'required', 'string', 'max:150', 'email:rfc', Rule::unique('users', 'email')->ignore($ignoreId),
            function (string $attribute, mixed $value, Closure $fail) use ($role, $keep) {
                if ($role === 'rep' && $value !== $keep && ! AccountRules::isRepEmail((string) $value)) {
                    $fail('A block rep’s email must be Gmail (…@gmail.com) or PUP webmail (…@iskolarngbayan.pup.edu.ph).');
                }
            }];
    }

    /** A PUP School ID, e.g. 2021-00123-MN-0, on one account only */
    public static function schoolId(bool $required, ?int $ignoreId = null): array
    {
        return ['bail', $required ? 'required' : 'nullable', 'string', 'max:20', 'regex:'.AccountRules::SCHOOL_ID_PATTERN,
            Rule::unique('users', 'school_id')->ignore($ignoreId)];
    }
}
