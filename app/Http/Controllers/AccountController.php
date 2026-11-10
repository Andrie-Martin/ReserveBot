<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Services\Photos;
use App\Support\AccountFields;
use App\Support\Photo;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 9: My account, Information tab, for everyone: photo, name, email and, for block accounts and lab
 * staff, program/section. (No ID number here anymore: it is asked for the borrower on equipment bookings.)
 * A block account's "name" is the block's representative, which the
 * block can change here when a new representative takes over. Its block is set by an admin (it decides which
 * classes it can book for). Changing the email needs the current password and a new link.
 */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user()->load('department', 'section');

        return view('account.info', [
            'user' => $user,
            // Lab staff pick their own section; programs come from the sections, e.g. BSCPE 3-2 -> BSCPE
            'sections' => $user->role === 'lab_staff' ? Section::orderBy('program')->orderBy('name')->get()->groupBy('program') : collect(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        AccountFields::tidy($request);
        $emailChanged = $request->input('email') !== $user->email;

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'email' => AccountFields::email($user->role, $user->id, keep: $user->email),
            'current_password' => $emailChanged ? ['required', 'current_password'] : ['nullable'],
        ];
        if ($user->role === 'lab_staff') {
            $rules['section_id'] = ['nullable', 'integer', 'exists:sections,id'];
        }
        $data = $request->validateWithBag('info', $rules, [
            'current_password.required' => 'Type your current password to change your email.',
            'section_id.*' => 'Pick your section from the list.',
        ] + ($user->isBlock() ? [
            'name.required' => 'Type the name of your block’s representative.',
            'name.max' => 'Keep the representative’s name under 100 characters.',
        ] : []) + AccountFields::MESSAGES);

        $newRepresentative = $user->isBlock() && $data['name'] !== $user->name;
        $user->name = $data['name'];
        if ($user->role === 'lab_staff') {
            $user->section_id = $data['section_id'] ?? null;
        }
        if ($emailChanged) {
            $user->email = $data['email'];
            $user->email_verified_at = null;    // the new email needs its own link
        }
        $user->save();

        // A block that changed its representative is told so in plain words
        $saved = $newRepresentative
            ? 'Saved. '.$user->displayName().'’s representative is now '.$user->name.'.'
            : null;
        if (! $emailChanged) {
            return redirect()->route('account')->with('status', $saved ?? 'Saved your information.');
        }
        if (! $user->sendConnectEmail()) {
            return redirect()->route('account')->with('problem', 'Saved, but the email to '.$user->email.' couldn’t be sent. Try “Send the link again” in a while.');
        }

        return redirect()->route('account')->with('status', ($saved ?? 'Saved.').' We sent a link to '.$user->email.': click it to connect your new email.'
            .($user->role === 'rep' ? ' Until then you can’t book.' : ''));
    }

    public function photo(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validateWithBag('photo', [
            'photo' => ['bail', 'required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120',
                function (string $attribute, mixed $file, Closure $fail) {
                    $size = @getimagesize($file->getRealPath());
                    if (! $size) {
                        $fail('That file isn’t a photo we can open. Try another one.');
                    } elseif ($size[0] * $size[1] > Photo::MAX_PIXELS) {
                        $fail('That photo is too big to use. Pick a smaller one.');
                    }
                },
                'dimensions:min_width=100,min_height=100'],
        ], [
            'photo.required' => 'Pick a photo first.',
            'photo.file' => 'Pick a photo first.',
            'photo.uploaded' => 'That photo couldn’t be uploaded. Pick one under 5 MB.',
            'photo.mimes' => 'Use a JPG, PNG or WebP photo.',
            'photo.max' => 'That photo is over 5 MB. Pick a smaller one.',
            'photo.dimensions' => 'That photo is too small. Use one at least 100 × 100 pixels.',
        ]);

        $old = $user->photo_path;
        $user->update(['photo_path' => Photos::store($user, $request->file('photo'))]);
        Photos::delete($old);

        return redirect()->route('account')->with('status', 'Your photo is saved.');
    }

    public function removePhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        Photos::delete($user->photo_path);
        $user->update(['photo_path' => null]);

        return redirect()->route('account')->with('status', 'Your photo is removed.');
    }
}
