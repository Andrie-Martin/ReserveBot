<?php

namespace App\Http\Controllers;

use App\Models\ClassSchedule;
use App\Models\Reservation;
use App\Models\Section;
use App\Models\User;
use App\Services\Photos;
use App\Support\AccountFields;
use App\Support\EmailSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Phase 9 (admins only): the department's accounts. Admins make block rep and lab staff
 * accounts with a starting password (the person picks their own at first login), edit them,
 * set a new password, turn them off, and delete them. An account with bookings or history is
 * turned off instead of deleted, so nothing it did is lost.
 */
class AccountsController extends Controller
{
    private const MESSAGES = [
        'role.*' => 'Pick Block rep or Lab staff.',
        'block_id.required' => 'Pick the block this rep represents.',
        'block_id.*' => 'Pick a block from the list.',
        'section_id.*' => 'Pick a section from the list.',
    ];

    /** This department's blocks (the sections in its timetable), loaded once per request */
    private ?Collection $blocks = null;

    public function index(Request $request): View
    {
        $admin = $request->user();
        $accounts = User::with('section')->where('department_id', $admin->department_id)->get();
        $byName = fn (User $user) => Str::lower($user->name);

        return view('accounts.index', [
            'admin' => $admin,
            'groups' => [
                'Block reps' => $accounts->where('role', 'rep')->sortBy(fn (User $user) => ($user->section?->name ?? '~').' '.Str::lower($user->name), SORT_NATURAL),
                'Lab staff' => $accounts->where('role', 'lab_staff')->sortBy($byName),
                'Admins' => $accounts->where('role', 'admin')->sortBy($byName),
            ],
            'blocks' => $this->blocks($admin),
            'sections' => $this->sections(),
            'emailSends' => EmailSetup::sends(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $admin = $request->user();
        AccountFields::tidy($request);
        $data = $request->validateWithBag('add',
            $this->rules($admin, (string) $request->input('role')) + ['password' => AccountFields::password()],
            self::MESSAGES + AccountFields::MESSAGES);

        $account = User::create([
            'department_id' => $admin->department_id,
            'role' => $data['role'],
            'section_id' => $data['role'] === 'rep' ? $data['block_id'] : ($data['section_id'] ?? null),
            'name' => $data['name'],
            'email' => $data['email'],
            'school_id' => $data['school_id'] ?? null,
            'password' => $data['password'],
            'must_change_password' => true,
            'is_active' => true,
        ]);
        $account->load('section', 'department');

        $message = 'Made an account for '.$account->name.' ('.$account->roleLabel()
            .($account->role === 'rep' ? ', '.$account->section->name : '').'). Give them the starting password; they’ll pick their own when they first log in.';
        if (! $account->sendConnectEmail(welcome: true)) {
            return redirect()->route('accounts.index')->with('status', $message)
                ->with('problem', 'The welcome email to '.$account->email.' couldn’t be sent (see README, Email). After they log in, they can send the link again from My account.');
        }

        return redirect()->route('accounts.index')->with('status', $message.' We emailed '.$account->email.' a link to connect their email.');
    }

    public function edit(Request $request, User $account): View
    {
        $admin = $request->user();
        $this->check($admin, $account);

        return view('accounts.edit', [
            'account' => $account->load('section'),
            'blocks' => $this->blocks($admin),
            'sections' => $this->sections(),
            'upcoming' => $this->upcoming($account),
            'bookingCount' => $account->reservations()->count(),
            'hasHistory' => $account->hasHistory(),
        ]);
    }

    public function update(Request $request, User $account): RedirectResponse
    {
        $admin = $request->user();
        $this->check($admin, $account);
        AccountFields::tidy($request);
        $data = $request->validateWithBag('details',
            $this->rules($admin, (string) $request->input('role'), $account),
            self::MESSAGES + AccountFields::MESSAGES);

        $wasActive = $account->is_active;
        $emailChanged = $data['email'] !== $account->email;
        $account->fill([
            'name' => $data['name'],
            'role' => $data['role'],
            'section_id' => $data['role'] === 'rep' ? $data['block_id'] : ($data['section_id'] ?? null),
            'school_id' => $data['school_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);
        if ($emailChanged) {
            $account->email = $data['email'];
            $account->email_verified_at = null;     // the new email needs its own link
        }
        $account->save();

        $message = match (true) {
            $wasActive && ! $account->is_active => $this->turnedOff($account),
            ! $wasActive && $account->is_active => $account->name.' is turned on: the account can log in again.',
            default => 'Saved '.$account->name.'.',
        };
        $problem = null;
        if ($emailChanged) {
            if ($account->sendConnectEmail()) {
                $message .= ' We emailed '.$account->email.' a link to connect it.';
            } else {
                $problem = 'The email to '.$account->email.' couldn’t be sent (see README, Email). They can send the link again from My account.';
            }
        }

        return redirect()->route('accounts.edit', $account)->with('status', $message)->with('problem', $problem);
    }

    /** The admin types a new password; the person picks their own at the next login */
    public function password(Request $request, User $account): RedirectResponse
    {
        $this->check($request->user(), $account);
        $data = $request->validateWithBag('password', ['password' => AccountFields::password()], AccountFields::MESSAGES);

        $account->forceFill([
            'password' => $data['password'],
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ])->save();

        return redirect()->route('accounts.edit', $account)->with('status', 'Set a new password for '.$account->name
            .'. Give it to them; they’ll pick their own when they next log in. Any device logged in to the account is logged out.');
    }

    /** Deletes an account, or turns it off when deleting would lose bookings or history */
    public function destroy(Request $request, User $account): RedirectResponse
    {
        $this->check($request->user(), $account);

        if ($account->hasHistory()) {
            $wasActive = $account->is_active;
            $account->update(['is_active' => false]);

            return redirect()->route('accounts.edit', $account)->with('status', $account->name.' has bookings or history, so the account was '
                .($wasActive ? 'turned off' : 'kept turned off').' instead of deleted: it can’t log in, and its bookings and history stay.'
                .($wasActive ? ' '.$this->upcomingNote($account) : ''));
        }

        Photos::delete($account->photo_path);
        DB::table('password_reset_tokens')->where('email', $account->email)->delete();
        $name = $account->name;
        $account->delete();

        return redirect()->route('accounts.index')->with('status', 'Deleted '.$name.'’s account.');
    }

    /**
     * The checks shared by adding and editing ($account: the one being edited). A rep's email and
     * block depend on the role picked; a rep keeping their current email is fine.
     */
    private function rules(User $admin, string $role, ?User $account = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'role' => ['required', Rule::in(User::MANAGED_ROLES)],
            'email' => AccountFields::email($role, $account?->id, $account?->role === 'rep' ? $account->email : null),
            'block_id' => [Rule::requiredIf($role === 'rep'), 'nullable', 'integer', Rule::in($this->blocks($admin)->pluck('id')->all())],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'school_id' => AccountFields::schoolId(false, $account?->id),
        ];
    }

    private function turnedOff(User $account): string
    {
        return trim($account->name.' is turned off: the account can’t log in anymore. '.$this->upcomingNote($account));
    }

    private function upcomingNote(User $account): string
    {
        $count = $this->upcoming($account)->count();

        return match (true) {
            $count === 1 => 'It still has 1 upcoming booking, listed below. Cancel it if it’s no longer needed.',
            $count > 1 => 'It still has '.$count.' upcoming bookings, listed below. Cancel them if they’re no longer needed.',
            default => '',
        };
    }

    /** The account's bookings that haven't ended and still hold a room or items */
    private function upcoming(User $account): Collection
    {
        return Reservation::with('room')
            ->where('user_id', $account->id)
            ->blocking()
            ->where('end_time', '>', now())
            ->orderBy('start_time')
            ->get();
    }

    /** A rep's block must be one of the department's: the sections in its timetable */
    private function blocks(User $admin): Collection
    {
        return $this->blocks ??= Section::whereIn('id', ClassSchedule::where('department_id', $admin->department_id)
            ->whereNotNull('section_id')
            ->select('section_id'))
            ->orderBy('name')
            ->get();
    }

    /** Lab staff pick their own section from every section, by program */
    private function sections(): Collection
    {
        return Section::orderBy('program')->orderBy('name')->get()->groupBy('program');
    }

    /** Admins manage the reps and lab staff of their own department only */
    private function check(User $admin, User $account): void
    {
        abort_unless($account->department_id === $admin->department_id && in_array($account->role, User::MANAGED_ROLES, true), 404);
    }
}
