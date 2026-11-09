<?php

namespace App\Http\Controllers;

use App\Models\DepartmentConfig;
use App\Models\Equipment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Bookings;
use App\Support\BookingRules;
use App\Support\StaffRules;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Phase 5: booking a room, equipment, or both, always with a reason.
 *
 * Step 1 (GET /book): what it's for (one of your classes, or something else) and when.
 * Step 2 (the same page, once "when" checks out): what you need, the room, the equipment and the reason.
 * Sending step 2 (POST /book) checks everything again and saves the booking.
 */
class BookingController extends Controller
{
    /** Past and closed bookings shown per page on My bookings */
    private const PAST_PER_PAGE = 10;

    /** The three kinds of booking */
    public const NEEDS = ['both' => 'Room and equipment', 'room' => 'Room only', 'equipment' => 'Equipment only'];

    public function __construct(private Bookings $bookings) {}

    public function create(Request $request): View
    {
        [$user, $rules] = $this->context($request);
        $for = $this->forOf($request, $user);
        [$when, $whenError] = $this->when($request, $for, $user, $rules);
        if ($request->boolean('change')) {
            $when = null;          // "Change" goes back to step 1 with the same choices
        }

        $view = [
            'user' => $user,
            'rules' => $rules,
            'canBookClass' => $this->canBookClass($user),
            'for' => $for,
            'when' => $when,
            'whenError' => $whenError,
            'picked' => $request->only('session', 'date', 'from', 'to', 'room'),
        ];

        if (! $when) {
            // Step 1 lists the rep's class sessions and the days you can book
            $view['sessions'] = $this->canBookClass($user) ? $this->bookings->sessions($user, $rules) : [];
            $view['days'] = $rules->days();
            if (! $view['sessions'] && ! $request->has('for')) {
                $view['for'] = 'other';      // nothing left to book for a class, so start on "Something else"
            }

            return view('bookings.create', $view);
        }

        // Step 2: what's free at that time. Coming from a room's Book button starts on "Room only".
        $view['needDefault'] = $request->query('room') ? 'room' : 'both';
        $view['changeUrl'] = route('book', $when['params'] + array_filter(['room' => $request->query('room')]) + ['change' => 1]);
        $view['categories'] = $this->bookings->equipment($user->department, $when['start'], $when['end']);
        if ($when['session']) {
            $classRoom = $when['session']['class']->room;
            $view['classRoom'] = $classRoom;
            $view['classRoomTaken'] = $this->bookings->roomTaken($classRoom->id, $when['start'], $when['end']);
        } else {
            $rooms = $user->department->rooms()->where('is_active', true)->orderBy('name')->get();
            $view['rooms'] = $this->bookings->rooms($rooms, $when['day'], $when['from'], $when['to'], $user);
            $view['preRoom'] = (int) $request->query('room');
        }

        return view('bookings.create', $view);
    }

    public function store(Request $request): RedirectResponse
    {
        [$user, $rules] = $this->context($request);
        $for = $this->forOf($request, $user);
        [$when] = $this->when($request, $for, $user, $rules);
        if (! $when) {
            // The time no longer works (for example, the class was just booked): back to step 1, which says why
            return redirect()->route('book', $request->only('for', 'session', 'date', 'from', 'to', 'room'));
        }

        // Check the form. Room and equipment problems are added in after(), so every problem shows at once.
        $room = null;
        $items = [];        // [equipment id => how many]
        $otherItems = null;
        $validator = Validator::make($request->all(), [
            'need' => ['required', Rule::in(array_keys(self::NEEDS))],
            'room' => ['nullable', 'integer'],
            'qty' => ['nullable', 'array'],
            'qty.*' => ['nullable', 'integer', 'min:0', 'max:99'],
            'other_items' => ['nullable', 'string', 'max:200'],
            'purpose' => ['required', 'string', 'max:150'],
        ], [
            'need.*' => 'Choose what you need: room and equipment, room only, or equipment only.',
            'qty.*.*' => 'Pick how many of each item from the lists.',
            'purpose.required' => 'Please give a reason for the booking.',
            'purpose.max' => 'Keep the reason under 150 characters.',
            'other_items.max' => 'Keep the other items under 200 characters.',
        ]);

        $validator->after(function ($validator) use ($request, $when, $user, &$room, &$items, &$otherItems) {
            $need = $request->input('need');
            if (! array_key_exists((string) $need, self::NEEDS)) {
                return;     // the 'need' rule above already says what's wrong
            }

            // The room: the class's own room, or one picked from the department's rooms
            if ($need !== 'equipment') {
                $room = $when['session']
                    ? $when['session']['class']->room
                    : $user->department->rooms()->where('is_active', true)->whereKey((int) $request->input('room'))->first();
                if (! $room) {
                    $validator->errors()->add('room', 'Pick a free room from the list.');
                }
            }

            // The equipment: only the department's listed items, within each item's limit
            if ($need !== 'room') {
                $items = collect((array) $request->input('qty', []))
                    ->map(fn ($qty) => is_numeric($qty) ? (int) $qty : 0)
                    ->filter(fn ($qty) => $qty > 0)
                    ->all();
                $otherItems = filled($request->input('other_items')) ? trim((string) $request->input('other_items')) : null;
                $listed = Equipment::whereKey(array_keys($items))->where('is_active', true)
                    ->whereRelation('category', 'department_id', $user->department_id)
                    ->get()->keyBy('id');
                foreach ($items as $id => $qty) {
                    if (! isset($listed[$id])) {
                        $validator->errors()->add('qty', 'Pick equipment from the list.');
                    } elseif ($qty > $listed[$id]->max_per_request) {
                        $validator->errors()->add('qty', 'You can ask for up to '.$listed[$id]->max_per_request.' × '.$listed[$id]->name.'.');
                    }
                }
                if (! $items && ! $otherItems) {
                    $validator->errors()->add('qty', $need === 'both' ? 'Pick at least one item, or choose Room only.' : 'Pick at least one item.');
                }
            }
        });

        $data = $validator->validate();

        $reservation = $this->bookings->place([
            'user' => $user,
            'session' => $when['session'],
            'day' => $when['day'],
            'start' => $when['start'],
            'end' => $when['end'],
            'room' => $room,
            'items' => $items,
            'other_items' => $otherItems,
            'purpose' => trim($data['purpose']),
            'rules' => $rules,
        ]);

        return redirect()->route('bookings.show', $reservation)->with('status', $rules->needsApproval
            ? 'Booking sent. Lab staff or an admin will approve or reject it.'
            : 'Booked.');
    }

    /** One booking. Its owner can see it, and so can the lab staff and admins of the owner's department. */
    public function show(Request $request, Reservation $reservation): View
    {
        [$user, $rules] = $this->context($request);
        $reservation->load(['user.section', 'room', 'classSchedule.course', 'classSchedule.section', 'equipment.category', 'reviewer']);

        $isStaff = in_array($user->role, ['lab_staff', 'admin'], true)
            && $reservation->user->department_id === $user->department_id;
        $isOwner = $reservation->user_id === $user->id;
        abort_unless($isOwner || $isStaff, 404);

        $cancellable = $isOwner && in_array($reservation->status, Reservation::CANCELLABLE_STATUSES, true);
        $staff = $isStaff ? new StaffRules(DepartmentConfig::forDepartment($user->department)->no_show_grace_minutes, CarbonImmutable::now()) : null;

        return view('bookings.show', [
            'reservation' => $reservation,
            'history' => $reservation->overrideLogs()->with('changedBy')->orderBy('created_at')->orderBy('id')->get(),
            'isStaff' => $isStaff,
            'staff' => $staff,
            'canChange' => $isStaff && $staff->canChange($reservation->status, $reservation->end_time),
            'moveDays' => $isStaff ? $rules->days() : [],
            'starts' => $rules->starts(),
            'ends' => $rules->ends(),
            'rooms' => $isStaff ? $user->department->rooms()->where('is_active', true)->orderBy('name')->get() : collect(),
            'canReview' => $isStaff && $reservation->status === 'Pending',
            'canCancel' => $cancellable && $rules->canCancel($reservation->start_time),
            'tooLateToCancel' => $cancellable && ! $rules->canCancel($reservation->start_time),
            'cancelDeadline' => $rules->cancelDeadline($reservation->start_time),
            'cancelHours' => BookingRules::duration($rules->cancelMinutes),
        ]);
    }

    /** GET /my-bookings: the rep's bookings, coming up first, then past and closed ones */
    public function mine(Request $request): View
    {
        [$user, $rules] = $this->context($request);
        $now = CarbonImmutable::now();

        $mine = fn () => Reservation::with(['room', 'equipment', 'classSchedule.course'])->where('user_id', $user->id);

        // Coming up: still holding its room or items, and not over yet
        $upcoming = $mine()
            ->whereIn('status', Reservation::BLOCKING_STATUSES)->where('end_time', '>=', $now)
            ->orderBy('start_time')->get();

        // Past and closed: everything else, newest first, one page at a time (a page past the end shows the last page)
        $pastQuery = fn () => $mine()
            ->where(fn ($query) => $query->whereNotIn('status', Reservation::BLOCKING_STATUSES)->orWhere('end_time', '<', $now))
            ->orderByDesc('start_time')->orderBy('id');
        $lastPage = max(1, (int) ceil($pastQuery()->count() / self::PAST_PER_PAGE));
        $past = $pastQuery()
            ->paginate(self::PAST_PER_PAGE, ['*'], 'past_page', min(max(1, (int) $request->query('past_page', 1)), $lastPage))
            ->fragment('past')->withQueryString();

        return view('bookings.mine', [
            'upcoming' => $upcoming,
            'past' => $past,
            'rules' => $rules,
        ]);
    }

    /** POST /bookings/{reservation}/cancel: the rep cancels their own booking, with a reason */
    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        [$user, $rules] = $this->context($request);
        abort_unless($reservation->user_id === $user->id, 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Please say why you’re cancelling.',
            'reason.max' => 'Keep the reason under 255 characters.',
        ]);

        if (! in_array($reservation->status, Reservation::CANCELLABLE_STATUSES, true)) {
            return back()->with('problem', 'This booking can’t be cancelled: it’s '.strtolower($reservation->statusLabel()).'.');
        }
        if (! $rules->canCancel($reservation->start_time)) {
            return back()->with('problem', 'It’s too late to cancel here: bookings can be cancelled until '
                .BookingRules::duration($rules->cancelMinutes).' before they start. Please tell the lab staff.');
        }

        // Only if it's still Pending or Confirmed (lab staff might have just changed it)
        $changed = Reservation::whereKey($reservation->id)
            ->whereIn('status', Reservation::CANCELLABLE_STATUSES)
            ->update(['status' => 'Cancelled', 'status_reason' => trim($data['reason']), 'updated_at' => now()]);

        return $changed
            ? redirect()->route('bookings.show', $reservation)->with('status', 'Booking cancelled. The room and items are free again.')
            : back()->with('problem', 'This booking just changed. Please check its status.');
    }

    private function context(Request $request): array
    {
        $user = $request->user()->load('department.config', 'section');

        return [$user, BookingRules::for(DepartmentConfig::forDepartment($user->department))];
    }

    /** Only reps with a block have classes to book */
    private function canBookClass(User $user): bool
    {
        return $user->role === 'rep' && $user->section_id !== null;
    }

    /** 'class' (one of the rep's classes) or 'other' (a make-up class, meeting, event, ...) */
    private function forOf(Request $request, User $user): string
    {
        return $this->canBookClass($user) && $request->input('for', 'class') === 'class' ? 'class' : 'other';
    }

    /**
     * When the booking is: a class session, or a day with a start and an end time.
     * Returns [when, problem]; [null, null] if nothing was picked yet.
     */
    private function when(Request $request, string $for, User $user, BookingRules $rules): array
    {
        if ($for === 'class') {
            $key = $request->input('session');
            if (blank($key)) {
                return [null, $request->has('session') ? 'Pick one of your classes.' : null];
            }
            [$session, $problem] = $this->bookings->findSession($user, $key, $rules);
            if (! $session) {
                return [null, $problem];
            }

            return [[
                'session' => $session,
                'day' => $session['day'],
                'start' => $session['start'],
                'end' => $session['end'],
                'from' => $session['from'],
                'to' => $session['to'],
                'params' => ['for' => 'class', 'session' => $session['key']],
            ], null];
        }

        $from = $request->input('from');
        $to = $request->input('to');
        if (blank($from) && blank($to)) {
            return [null, $request->has('from') ? 'Pick a start and an end time.' : null];
        }
        $day = $this->parseDay($request->input('date'));
        if (! $day) {
            return [null, 'Pick a day from the list.'];
        }
        if (! $this->isTime($from) || ! $this->isTime($to)) {
            return [null, 'Pick a start and an end time from the lists.'];
        }

        $fromMinutes = DepartmentConfig::minutes($from);
        $toMinutes = DepartmentConfig::minutes($to);
        if ($problem = $rules->checkExtra($day, $fromMinutes, $toMinutes)) {
            return [null, $problem];
        }

        return [[
            'session' => null,
            'day' => $day,
            'start' => $day->addMinutes($fromMinutes),
            'end' => $day->addMinutes($toMinutes),
            'from' => $fromMinutes,
            'to' => $toMinutes,
            'params' => ['for' => 'other', 'date' => $day->toDateString(), 'from' => $from, 'to' => $to],
        ], null];
    }

    /** '2026-11-03' -> that day at midnight, or null if it isn't a real date */
    private function parseDay(?string $raw): ?CarbonImmutable
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $raw, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $raw);
    }

    /** '08:30' */
    private function isTime(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value) === 1;
    }
}
