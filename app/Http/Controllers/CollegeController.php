<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DepartmentConfig;
use App\Models\Reservation;
use App\Models\User;
use App\Services\DepartmentSetup;
use App\Services\Photos;
use App\Support\AccountFields;
use App\Support\EmailSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The College admin (sandbox issue 8, Option B of the multi-department discussion).
 *
 * A college-wide account that sets departments up and looks after their Admin accounts. It sees every department
 * at a glance, adds a department (and can rename it), and makes, edits, turns off, deletes and resets the password
 * of the departments' Admins. That is how a department gets its first Admin, and how an Admin who is locked out
 * gets back in. A new department starts empty: its own Admin then adds the rooms, timetable, equipment and accounts.
 *
 * It deliberately can do nothing inside a department: no bookings to approve, no rooms, equipment or timetable,
 * and no block or lab staff accounts (those stay with the department's own Admins). It manages 'admin' accounts
 * only, never another College admin: College admins are made with `php artisan college-admin:make`.
 * Every change is written to the log (who did what to which account; never a password).
 */
class CollegeController extends Controller
{
    private const MESSAGES = [
        'name.required' => 'Type the admin’s name.',
    ];

    private const DEPARTMENT_MESSAGES = [
        'name.required' => 'Type the department’s name.',
        'name.max' => 'Keep the department’s name under 100 characters.',
        'name.unique' => 'There is already a department with that name.',
        'contact_email.*' => 'That doesn’t look like an email address. Leave it empty if the department has none.',
    ];

    /** Every department, with how many accounts and rooms it has and how many requests are waiting */
    public function home(Request $request): View
    {
        $departments = Department::query()
            ->withCount([
                'rooms',
                'users as admins_count' => fn ($query) => $query->where('role', 'admin'),
                'users as active_admins_count' => fn ($query) => $query->where('role', 'admin')->where('is_active', true),
                'users as staff_count' => fn ($query) => $query->where('role', 'lab_staff'),
                'users as blocks_count' => fn ($query) => $query->where('role', 'rep'),
            ])
            ->orderBy('name')
            ->get();

        // Requests waiting for approval, per department (through the account that booked)
        $waiting = Reservation::query()->where('status', 'Pending')
            ->join('users', 'users.id', '=', 'reservations.user_id')
            ->groupBy('users.department_id')
            ->selectRaw('users.department_id as department_id, count(*) as waiting')
            ->pluck('waiting', 'department_id');

        // What each department still has to add before its blocks can book (its Admin sees the same steps)
        $setup = collect(DepartmentSetup::counts($departments->modelKeys()))
            ->map(fn (array $counts) => DepartmentSetup::fromCounts($counts)['left']);

        return view('college.home', [
            'user' => $request->user(),
            'departments' => $departments,
            'waiting' => $waiting,
            'setupLeft' => $setup,
        ]);
    }

    /**
     * Adds a department. It starts with the usual booking rules and nothing else: no rooms, timetable, equipment
     * or accounts yet. The next step is its first Admin (the page this goes to), who then adds the rest.
     */
    public function storeDepartment(Request $request): RedirectResponse
    {
        $data = $this->departmentData($request, 'department');

        $department = DB::transaction(function () use ($data) {
            $department = Department::create([
                'name' => $data['name'],
                'slug' => Department::uniqueSlug($data['name']),
                'contact_email' => $data['contact_email'] ?? null,
            ]);
            $department->config()->create(DepartmentConfig::DEFAULTS);     // open Mon to Sat, 7:30 AM to 9:00 PM, approval needed, ...

            return $department;
        });
        Log::info('College admin added a department', ['by' => $request->user()->id, 'department_id' => $department->id]);

        return redirect()->route('college.departments.show', $department)->with('status', 'Added '.$department->name
            .'. Next, give it an Admin below. The Admin then adds its rooms, timetable, equipment and accounts.');
    }

    /** Renames a department, or changes its contact email */
    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        $data = $this->departmentData($request, 'department', $department);
        $department->update(['name' => $data['name'], 'contact_email' => $data['contact_email'] ?? null]);
        Log::info('College admin changed a department', ['by' => $request->user()->id, 'department_id' => $department->id]);

        return redirect()->route('college.departments.show', $department)->with('status', 'Saved '.$department->name.'.');
    }

    /** One department's Admin accounts, and the form that adds one */
    public function department(Request $request, Department $department): View
    {
        return view('college.department', [
            'department' => $department,
            'admins' => User::where('department_id', $department->id)->where('role', 'admin')
                ->get()->sortBy(fn (User $admin) => Str::lower($admin->name))->values(),
            // Admins of the other departments, who could be moved here
            'setupLeft' => DepartmentSetup::for($department)['left'],
            'otherAdmins' => User::with('department')->where('role', 'admin')->whereNotNull('department_id')
                ->where('department_id', '!=', $department->id)->get()
                ->sortBy(fn (User $admin) => Str::lower($admin->department->name.' '.$admin->name))->values(),
            'emailSends' => EmailSetup::sends(),
        ]);
    }

    /** Makes an Admin account for the department, with a starting password the person replaces at the first login */
    public function store(Request $request, Department $department): RedirectResponse
    {
        AccountFields::tidy($request);
        $data = $request->validateWithBag('add', [
            'name' => ['required', 'string', 'max:100'],
            'email' => AccountFields::email('admin'),
            'password' => AccountFields::password(),
        ], self::MESSAGES + AccountFields::MESSAGES);

        $admin = User::create([
            'department_id' => $department->id,
            'role' => 'admin',
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'must_change_password' => true,
            'is_active' => true,
        ]);
        $this->log('made an Admin account', $request->user(), $admin);

        $message = 'Made an Admin account for '.$admin->name.' ('.$department->name.'). Give them the starting password; they’ll pick their own when they first log in.';
        $back = redirect()->route('college.departments.show', $department);
        if (! $admin->sendConnectEmail(welcome: true)) {
            return $back->with('status', $message)
                ->with('problem', 'The welcome email to '.$admin->email.' couldn’t be sent (see README, Email). After they log in, they can send the link again from My account.');
        }

        return $back->with('status', $message.' We emailed '.$admin->email.' a link to connect their email.');
    }

    public function edit(Request $request, User $account): View
    {
        $this->check($account);

        return view('college.admin', [
            'account' => $account->load('department'),
            'hasHistory' => $account->hasHistory(),
            'otherActiveAdmins' => $this->otherActiveAdmins($account),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    /** Name, email and whether the account can log in */
    public function update(Request $request, User $account): RedirectResponse
    {
        $this->check($account);
        AccountFields::tidy($request);
        $data = $request->validateWithBag('details', [
            'name' => ['required', 'string', 'max:100'],
            'email' => AccountFields::email('admin', $account->id),
        ], self::MESSAGES + AccountFields::MESSAGES);

        $wasActive = $account->is_active;
        $emailChanged = $data['email'] !== $account->email;
        $account->fill(['name' => $data['name'], 'is_active' => $request->boolean('is_active')]);
        if ($emailChanged) {
            $account->email = $data['email'];
            $account->email_verified_at = null;     // the new email needs its own link
        }
        $account->save();
        $this->log('changed an Admin account', $request->user(), $account, ['turned_off' => $wasActive && ! $account->is_active, 'turned_on' => ! $wasActive && $account->is_active, 'email_changed' => $emailChanged]);

        $message = match (true) {
            $wasActive && ! $account->is_active => $account->name.' is turned off: the account can’t log in anymore.'.$this->noAdminLeftNote($account),
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

        return redirect()->route('college.admins.edit', $account)->with('status', $message)->with('problem', $problem);
    }

    /** Assigns this Admin to the department picked on their page */
    public function move(Request $request, User $account): RedirectResponse
    {
        $this->check($account);
        $data = $request->validateWithBag('move', [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
        ], ['department_id.*' => 'Pick a department from the list.']);

        return $this->assign($request, $account, Department::findOrFail($data['department_id']));
    }

    /** Assigns the Admin picked on a department's page to that department */
    public function moveHere(Request $request, Department $department): RedirectResponse
    {
        $data = $request->validateWithBag('move', [
            'account_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'admin')],
        ], ['account_id.*' => 'Pick an Admin from the list.']);

        return $this->assign($request, User::findOrFail($data['account_id']), $department);
    }

    /**
     * Moves an Admin to another department. The account, its login and what it did before stay as they are; from
     * its next click it runs the new department and no longer sees the old one. Bookings it approved in the old
     * department keep its name. The Admin is emailed about it.
     */
    private function assign(Request $request, User $account, Department $to): RedirectResponse
    {
        $this->check($account);
        $from = $account->department;
        if ($to->is($from)) {
            return back()->withErrors(['department_id' => $account->name.' is already an Admin of '.$to->name.'.'], 'move')->withInput();
        }

        $account->update(['department_id' => $to->id]);
        $this->log('moved an Admin account to another department', $request->user(), $account, ['from_department_id' => $from->id]);

        $message = 'Moved '.$account->name.' from '.$from->name.' to '.$to->name.'. From their next click they run '.$to->name
            .' and no longer see '.$from->name.'.';
        $fromHasAdmin = User::where('department_id', $from->id)->where('role', 'admin')->where('is_active', true)->exists();
        if (! $fromHasAdmin) {
            $message .= ' '.$from->name.' now has no Admin who can log in.';
        }
        $back = redirect()->route('college.departments.show', $to);
        if (! $account->sendMovedEmail($from, $to)) {
            return $back->with('status', $message)->with('problem', 'The email telling '.$account->name.' couldn’t be sent (see README, Email). Let them know yourself.');
        }

        return $back->with('status', $message.' We emailed '.$account->email.' about it.');
    }

    /** Recovery: the College admin types a new password; the Admin picks their own at the next login */
    public function password(Request $request, User $account): RedirectResponse
    {
        $this->check($account);
        $data = $request->validateWithBag('password', ['password' => AccountFields::password()], AccountFields::MESSAGES);

        $account->forceFill([
            'password' => $data['password'],
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ])->save();
        $this->log('set a new password for an Admin account', $request->user(), $account);

        return redirect()->route('college.admins.edit', $account)->with('status', 'Set a new password for '.$account->name
            .'. Give it to them; they’ll pick their own when they next log in. Any device logged in to the account is logged out.');
    }

    /** Deletes an Admin account, or turns it off when deleting would lose what it approved, rejected or changed */
    public function destroy(Request $request, User $account): RedirectResponse
    {
        $this->check($account);
        $department = $account->department;

        if ($account->hasHistory()) {
            $wasActive = $account->is_active;
            $account->update(['is_active' => false]);
            $this->log('turned off an Admin account (it has history)', $request->user(), $account);

            return redirect()->route('college.admins.edit', $account)->with('status', $account->name.' has approved, rejected or changed bookings, so the account was '
                .($wasActive ? 'turned off' : 'kept turned off').' instead of deleted: it can’t log in, and that history stays.'.$this->noAdminLeftNote($account));
        }

        $name = $account->name;
        $this->log('deleted an Admin account', $request->user(), $account);
        Photos::delete($account->photo_path);
        DB::table('password_reset_tokens')->where('email', $account->email)->delete();
        $account->delete();

        return redirect()->route('college.departments.show', $department)->with('status', 'Deleted '.$name.'’s account.');
    }

    /** A department's name (single spaces, not used by another department) and its optional contact email */
    private function departmentData(Request $request, string $bag, ?Department $department = null): array
    {
        $request->merge([
            'name' => preg_replace('/\s+/u', ' ', trim((string) $request->input('name'))),
            'contact_email' => filled($request->input('contact_email')) ? Str::lower(trim((string) $request->input('contact_email'))) : null,
        ]);

        return $request->validateWithBag($bag, [
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->ignore($department?->id)],
            'contact_email' => ['nullable', 'string', 'max:150', 'email:rfc'],
        ], self::DEPARTMENT_MESSAGES);
    }

    /** The College admin manages department Admin accounts only: never block or lab staff accounts, never another College admin */
    private function check(User $account): void
    {
        abort_unless($account->role === 'admin' && $account->department_id !== null, 404);
    }

    private function otherActiveAdmins(User $account): int
    {
        return User::where('department_id', $account->department_id)->where('role', 'admin')->where('is_active', true)
            ->whereKeyNot($account->id)->count();
    }

    /** Said when turning an account off leaves its department with no Admin who can log in */
    private function noAdminLeftNote(User $account): string
    {
        return $this->otherActiveAdmins($account) === 0
            ? ' '.$account->department->name.' now has no Admin who can log in.'
            : '';
    }

    /** Who did what to which account, for whoever runs the system. Never a password or an email address. */
    private function log(string $what, User $by, User $account, array $extra = []): void
    {
        Log::info('College admin '.$what, ['by' => $by->id, 'account' => $account->id, 'department_id' => $account->department_id] + array_filter($extra));
    }
}
