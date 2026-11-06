<?php

namespace App\Http\Controllers;

use App\Models\DepartmentConfig;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Bookings;
use App\Services\ClassChecks;
use App\Services\StaffDesk;
use App\Support\BookingRules;
use App\Support\StaffRules;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 7: the lab staff's day. Today's bookings, grouped by what to do next, with buttons to
 * hand out the key and items, take them back, mark a no-show, and cancel or move a booking.
 * Admins can do all of this too. Everyone only works on their own department's bookings.
 */
class TodayController extends Controller
{
    /** How far back the day picker goes (for checking what happened) */
    private const DAYS_BACK = 7;

    public function __construct(private StaffDesk $desk, private Bookings $bookings, private ClassChecks $checks) {}

    /** GET /today?date=2026-11-03 */
    public function index(Request $request): View
    {
        $user = $request->user()->load('department.config');
        $config = DepartmentConfig::forDepartment($user->department);
        $today = CarbonImmutable::today();
        $first = $today->subDays(self::DAYS_BACK);
        $last = $today->addDays($config->max_days_ahead);

        $date = $today;
        $raw = (string) $request->query('date');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) && ($picked = CarbonImmutable::createFromFormat('!Y-m-d', $raw))
            && $picked->toDateString() === $raw && $picked->between($first, $last)) {
            $date = $picked;
        }

        $all = Reservation::with(['user.section', 'room', 'equipment', 'classSchedule.course'])
            ->whereRelation('user', 'department_id', $user->department_id)
            ->where('start_time', '>=', $date)
            ->where('start_time', '<', $date->addDay())
            ->orderBy('start_time')
            ->get();

        return view('today.index', [
            'department' => $user->department,
            'date' => $date,
            'prevUrl' => $date->gt($first) ? route('today', ['date' => $date->subDay()->toDateString()]) : null,
            'nextUrl' => $date->lt($last) ? route('today', ['date' => $date->addDay()->toDateString()]) : null,
            'staff' => new StaffRules($config->no_show_grace_minutes, CarbonImmutable::now()),
            'inUse' => $all->where('status', 'InUse')->values(),
            'toHandOut' => $all->where('status', 'Confirmed')->values(),
            'waiting' => $all->where('status', 'Pending')->values(),
            'done' => $all->whereIn('status', ['Completed', 'NoShow', 'Cancelled', 'Rejected'])->values(),
            // Phase 10: the department's classes this day: face-to-face or online?
            'classes' => $this->checks->forDepartment($user->department_id, $date),
        ]);
    }

    /** The rep came: the key and items are handed out (Approved → In use) */
    public function handOut(Request $request, Reservation $reservation): RedirectResponse
    {
        [$user, $staff] = $this->context($request, $reservation);
        if (! $staff->canHandOut($reservation->status, $reservation->start_time)) {
            return back()->with('problem', $this->notYetOrWrongStatus($staff, $reservation, 'handed out'));
        }

        return $this->result($this->desk->setStatus($reservation, 'Confirmed', 'InUse', $user, 'Key and items handed out'),
            $reservation, 'Handed out booking #'.$reservation->id.' to '.$reservation->user->name.'.');
    }

    /** Everything came back (In use → Completed), with an optional note about missing or damaged items */
    public function returned(Request $request, Reservation $reservation): RedirectResponse
    {
        [$user, $staff] = $this->context($request, $reservation);
        $data = $request->validateWithBag('return'.$reservation->id, [
            'note' => ['nullable', 'string', 'max:255'],
        ], ['note.max' => 'Keep the note under 255 characters.']);
        if (! $staff->canMarkReturned($reservation->status)) {
            return back()->with('problem', 'Booking #'.$reservation->id.' isn’t in use, so it can’t be marked returned.');
        }

        $note = filled($data['note'] ?? null) ? trim($data['note']) : null;

        return $this->result($this->desk->setStatus($reservation, 'InUse', 'Completed', $user, $note ?? 'Everything returned', $note),
            $reservation, 'Booking #'.$reservation->id.' is completed. The room and items are free again.');
    }

    /** Nobody came (Approved → No-show); frees the room and items */
    public function noShow(Request $request, Reservation $reservation): RedirectResponse
    {
        [$user, $staff] = $this->context($request, $reservation);
        if (! $staff->canMarkNoShow($reservation->status, $reservation->start_time)) {
            return back()->with('problem', $this->notYetOrWrongStatus($staff, $reservation, 'a no-show'));
        }

        return $this->result($this->desk->setStatus($reservation, 'Confirmed', 'NoShow', $user,
            'Nobody came for the key and items'),
            $reservation, 'Booking #'.$reservation->id.' is marked a no-show. The room and items are free again.');
    }

    /** Lab staff cancel an approved booking, with a reason the rep sees */
    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        [$user, $staff] = $this->context($request, $reservation);
        $data = $request->validateWithBag('cancel', [
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Give a reason, so the rep knows why.',
            'reason.max' => 'Keep the reason under 255 characters.',
        ]);
        if (! $staff->canChange($reservation->status, $reservation->end_time)) {
            return back()->with('problem', 'Only approved bookings that haven’t ended can be cancelled here.');
        }

        $reason = trim($data['reason']);

        return $this->result($this->desk->setStatus($reservation, 'Confirmed', 'Cancelled', $user, $reason, $reason),
            $reservation, 'Booking #'.$reservation->id.' is cancelled. The room and items are free again.');
    }

    /** Lab staff move an approved booking to another day, time and/or room, with a reason */
    public function move(Request $request, Reservation $reservation): RedirectResponse
    {
        [$user, $staff] = $this->context($request, $reservation);
        $data = $request->validateWithBag('move', [
            'date' => ['required', 'date_format:Y-m-d'],
            'from' => ['required', 'date_format:H:i'],
            'to' => ['required', 'date_format:H:i'],
            'room' => ['nullable', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'date.*' => 'Pick a day from the list.',
            'from.*' => 'Pick a start time from the list.',
            'to.*' => 'Pick an end time from the list.',
            'reason.required' => 'Give a reason, so the rep knows why.',
            'reason.max' => 'Keep the reason under 255 characters.',
        ]);
        if (! $staff->canChange($reservation->status, $reservation->end_time)) {
            return back()->with('problem', 'Only approved bookings that haven’t ended can be moved.');
        }

        $rules = BookingRules::for(DepartmentConfig::forDepartment($user->department));
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $data['date']);
        $from = DepartmentConfig::minutes($data['from']);
        $to = DepartmentConfig::minutes($data['to']);
        if ($problem = $rules->checkMove($day, $from, $to)) {
            return back()->withErrors(['move' => $problem], 'move')->withInput();
        }

        // A booking with a room keeps one (any free room of the department); equipment-only stays roomless
        $room = null;
        if ($reservation->room_id) {
            $room = $user->department->rooms()->where('is_active', true)->whereKey((int) ($data['room'] ?? 0))->first();
            if (! $room) {
                return back()->withErrors(['room' => 'Pick a room from the list.'], 'move')->withInput();
            }
        }

        $same = $day->addMinutes($from)->eq($reservation->start_time) && $day->addMinutes($to)->eq($reservation->end_time)
            && $room?->id === $reservation->room_id;
        if ($same) {
            return back()->withErrors(['move' => 'That’s the same day, time and room it already has.'], 'move')->withInput();
        }

        $this->bookings->move($reservation, $user, $day, $from, $to, $room, trim($data['reason']));

        return redirect()->route('bookings.show', $reservation)->with('status', 'Booking #'.$reservation->id.' is moved. The rep sees the new time and room, and your reason.');
    }

    /** The signed-in lab staff or admin, and the rules for now. Only their own department's bookings. */
    private function context(Request $request, Reservation $reservation): array
    {
        /** @var User $user */
        $user = $request->user()->load('department.config');
        abort_unless($reservation->user->department_id === $user->department_id, 404);
        $config = DepartmentConfig::forDepartment($user->department);

        return [$user, new StaffRules($config->no_show_grace_minutes, CarbonImmutable::now())];
    }

    /** Why hand-out / no-show was refused: the booking's day hasn't come yet, or its status doesn't allow it */
    private function notYetOrWrongStatus(StaffRules $staff, Reservation $reservation, string $what): string
    {
        if ($reservation->status === 'Confirmed' && $staff->isFutureDay($reservation->start_time)) {
            return 'Booking #'.$reservation->id.' is on '.$reservation->start_time->format('l, F j').', so it can’t be '.$what.' yet.';
        }

        return 'Booking #'.$reservation->id.' is '.strtolower($reservation->statusLabel()).', so it can’t be '.$what.'.';
    }

    private function result(bool $changed, Reservation $reservation, string $message): RedirectResponse
    {
        return $changed
            ? back()->with('status', $message)
            : back()->with('problem', 'Booking #'.$reservation->id.' just changed: it’s now '
                .strtolower($reservation->fresh()->statusLabel()).'.');
    }
}
