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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Phase 9 (admins only): the department's accounts. Admins make block accounts and lab staff
 * accounts with a starting password (the person picks their own at first login), edit them,
 * set a new password, turn them off, and delete them. An account with bookings or history is
 * turned off instead of deleted, so nothing it did is lost.
 *
 * A block account belongs to its block: one account per block, with the block's representative named
 * on it. The representative can be changed here, or by the block in My account.
 */
class AccountsController extends Controller
{
    /** People shown per page on the Accounts list */
    private const PER_PAGE = 20;

    private const MESSAGES = [
        'role.*' => 'Pick Block account or Lab staff.',
        'block_id.required' => 'Pick the block this account is for.',
        'block_id.*' => 'Pick a block from the list.',
        'section_id.*' => 'Pick a section from the list.',
    ];

    /** This department's blocks (the sections in its timetable), loaded once per request */
    private ?Collection $blocks = null;

    public function index(Request $request): View
    {
        $admin = $request->user();
        $accounts = User::with('section')->where('department_id', $admin->department_id)->orderBy('id')->get();
        $byName = fn (User $user) => Str::lower($user->name);

        // Same order as before (block accounts, then lab staff, then admins), cut into pages of PER_PAGE people.
        $ordered = [
            'Block accounts' => $accounts->where('role', 'rep')->sortBy(fn (User $user) => ($user->section?->name ?? '~').' '.Str::lower($user->name), SORT_NATURAL)->values(),
            'Lab staff' => $accounts->where('role', 'lab_staff')->sortBy($byName)->values(),
            'Admins' => $accounts->where('role', 'admin')->sortBy($byName)->values(),
        ];
        $everyone = collect($ordered)->flatten(1)->values();
        $lastPage = max(1, (int) ceil($everyone->count() / self::PER_PAGE));
        $page = min(max(1, (int) $request->query('page', 1)), $lastPage);
        $pageIds = $everyone->forPage($page, self::PER_PAGE)->pluck('id')->all();
        $paginator = new LengthAwarePaginator(
            $everyone->forPage($page, self::PER_PAGE)->values(), $everyone->count(), self::PER_PAGE, $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('accounts.index', [
            'admin' => $admin,
            'groups' => collect($ordered)->map(fn (Collection $people) => $people->filter(fn (User $user) => in_array($user->id, $pageIds, true))->values())->all(),
            'groupTotals' => collect($ordered)->map->count()->all(),
            'paginator' => $paginator,
            'blocks' => $this->blocks($admin),
            'sections' => $this->sections(),
            'emailSends' => EmailSetup::sends(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $admin = $request->user();
        AccountFields::tidy($request);
        $role = (string) $request->input('role');
        $data = $request->validateWithBag('add',
            $this->rules($admin, $role) + ['password' => AccountFields::password()],
            $this->messages($role));

        $account = $this->oneAccountPerBlock($data, null, 'add', fn () => User::create([
            'department_id' => $admin->department_id,
            'role' => $data['role'],
            'section_id' => $data['role'] === 'rep' ? $data['block_id'] : ($data['section_id'] ?? null),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'must_change_password' => true,
            'is_active' => true,
        ]));
        $account->load('section', 'department');

        $message = ($account->isBlock()
            ? 'Made the block account for '.$account->displayName().' (representative: '.$account->name.').'
            : 'Made an account for '.$account->name.' ('.$account->roleLabel().').')
            .' Give them the starting password; they’ll pick their own when they first log in.';
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
        $role = (string) $request->input('role');
        $data = $request->validateWithBag('details', $this->rules($admin, $role, $account), $this->messages($role));

        $wasActive = $account->is_active;
        $emailChanged = $data['email'] !== $account->email;
        $oldRepresentative = $account->representativeName();
        $this->oneAccountPerBlock($data, $account, 'details', function () use ($account, $data, $request, $emailChanged) {
            $account->fill([
                'name' => $data['name'],
                'role' => $data['role'],
                'section_id' => $data['role'] === 'rep' ? $data['block_id'] : ($data['section_id'] ?? null),
                'is_active' => $request->boolean('is_active'),
            ]);
            if ($emailChanged) {
                $account->email = $data['email'];
                $account->email_verified_at = null;     // the new email needs its own link
            }
            $account->save();

            return $account;
        });
        $account->load('section');

        $called = $account->displayName();
        $message = match (true) {
            $wasActive && ! $account->is_active => $this->turnedOff($account),
            ! $wasActive && $account->is_active => $called.' is turned on: the account can log in again.',
            default => 'Saved '.$called.'.',
        };
        if ($account->isBlock() && $oldRepresentative !== null && $oldRepresentative !== $account->name) {
            $message .= ' Its representative is now '.$account->name.'.';
        }
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

        return redirect()->route('accounts.edit', $account)->with('status', 'Set a new password for '.$account->displayName()
            .'. Give it to them; they’ll pick their own when they next log in. Any device logged in to the account is logged out.');
    }

    /** Deletes an account, or turns it off when deleting would lose bookings or history */
    public function destroy(Request $request, User $account): RedirectResponse
    {
        $this->check($request->user(), $account);

        if ($account->hasHistory()) {
            $wasActive = $account->is_active;
            $account->update(['is_active' => false]);

            return redirect()->route('accounts.edit', $account)->with('status', $account->displayName().' has bookings or history, so the account was '
                .($wasActive ? 'turned off' : 'kept turned off').' instead of deleted: it can’t log in, and its bookings and history stay.'
                .($wasActive ? ' '.$this->upcomingNote($account) : ''));
        }

        Photos::delete($account->photo_path);
        DB::table('password_reset_tokens')->where('email', $account->email)->delete();
        $name = $account->displayName();
        $account->delete();

        return redirect()->route('accounts.index')->with('status', 'Deleted '.$name.'’s account.');
    }

    /**
     * The checks shared by adding and editing ($account: the one being edited). A block account's email and
     * block depend on the role picked; a block account keeping its current email is fine.
     */
    private function rules(User $admin, string $role, ?User $account = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'role' => ['required', Rule::in(User::MANAGED_ROLES)],
            'email' => AccountFields::email($role, $account?->id, $account?->role === 'rep' ? $account->email : null),
            'block_id' => [Rule::requiredIf($role === 'rep'), 'nullable', 'integer', Rule::in($this->blocks($admin)->pluck('id')->all())],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
        ];
    }

    /** A block account's "name" is its representative, so the form's messages say so */
    private function messages(string $role): array
    {
        return ($role === 'rep' ? [
            'name.required' => 'Type the name of the block’s representative.',
            'name.max' => 'Keep the representative’s name under 100 characters.',
        ] : []) + self::MESSAGES + AccountFields::MESSAGES;
    }

    /**
     * One account per block. $save makes or changes the account; when that gives a block an account (a new block
     * account, another block picked, or a lab staff account turned into a block account), the block's row is locked
     * first and checked, so two admins saving at the same moment can't both give the same block an account.
     * An account that already has its block is saved as usual, even if an older duplicate exists.
     *
     * @param  callable(): User  $save
     */
    private function oneAccountPerBlock(array $data, ?User $account, string $bag, callable $save): User
    {
        $blockId = $data['role'] === 'rep' ? (int) $data['block_id'] : null;
        $takesBlock = $blockId !== null && ! ($account && $account->isBlock() && (int) $account->section_id === $blockId);
        if (! $takesBlock) {
            return $save();
        }

        return DB::transaction(function () use ($blockId, $account, $bag, $save) {
            $block = Section::whereKey($blockId)->lockForUpdate()->first();
            $taken = User::where('role', 'rep')->where('section_id', $blockId)
                ->when($account, fn ($query) => $query->whereKeyNot($account->id))
                ->orderBy('id')->first();
            if ($taken) {
                throw ValidationException::withMessages(['block_id' => ($block?->name ?? 'That block').' already has a block account (representative: '
                    .$taken->name.'). Each block has one account: edit that one instead, for example to change its representative.'])->errorBag($bag);
            }

            return $save();
        });
    }

    private function turnedOff(User $account): string
    {
        return trim($account->displayName().' is turned off: the account can’t log in anymore. '.$this->upcomingNote($account));
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

    /** A block account's block must be one of the department's: the sections in its timetable */
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

    /** Admins manage the block accounts and lab staff of their own department only */
    private function check(User $admin, User $account): void
    {
        abort_unless($account->department_id === $admin->department_id && in_array($account->role, User::MANAGED_ROLES, true), 404);
    }
}
