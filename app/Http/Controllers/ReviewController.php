<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 6: lab staff and admins approve or reject booking requests, as they are.
 * Approving keeps the room and items held; rejecting (with a reason) frees them.
 * Each person only sees requests from their own department.
 */
class ReviewController extends Controller
{
    private const WITH = ['user.section', 'room', 'equipment', 'classSchedule.course', 'reviewer'];

    /** GET /requests: what's waiting (soonest first), and the last few decisions */
    public function index(Request $request): View
    {
        $user = $request->user();

        $waiting = Reservation::with(self::WITH)
            ->where('status', 'Pending')
            ->whereRelation('user', 'department_id', $user->department_id)
            ->orderBy('start_time')
            ->get();

        $decided = Reservation::with(self::WITH)
            ->whereIn('status', ['Confirmed', 'Rejected'])
            ->whereNotNull('reviewed_by')
            ->whereRelation('user', 'department_id', $user->department_id)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return view('requests.index', ['waiting' => $waiting, 'decided' => $decided]);
    }

    public function approve(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->checkDepartment($request->user(), $reservation);

        return $this->decide($request, $reservation, 'Confirmed', null);
    }

    public function reject(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->checkDepartment($request->user(), $reservation);

        // Each request on the page has its own form, so its errors go in its own bag
        $data = $request->validateWithBag('reject'.$reservation->id, [
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Give a reason, so the rep knows what to change.',
            'reason.max' => 'Keep the reason under 255 characters.',
        ]);

        return $this->decide($request, $reservation, 'Rejected', trim($data['reason']));
    }

    /**
     * Changes a Pending booking to $status. The update only happens if it's still Pending,
     * so if two people decide at the same moment, only the first one counts.
     */
    private function decide(Request $request, Reservation $reservation, string $status, ?string $reason): RedirectResponse
    {
        $changed = Reservation::whereKey($reservation->id)
            ->where('status', 'Pending')
            ->update([
                'status' => $status,
                'status_reason' => $reason,
                'reviewed_by' => $request->user()->id,
                'updated_at' => now(),
            ]);

        if (! $changed) {
            $now = $reservation->fresh();

            return back()->with('problem', 'Booking #'.$reservation->id.' is no longer waiting: it’s '
                .strtolower($now->statusLabel()).'.');
        }

        $who = $reservation->user->displayName();

        return back()->with('status', $status === 'Confirmed'
            ? 'Approved booking #'.$reservation->id.' for '.$who.'.'
            : 'Rejected booking #'.$reservation->id.' for '.$who.'. The room and items are free again.');
    }

    /** Lab staff and admins can only decide on their own department's bookings */
    private function checkDepartment(User $user, Reservation $reservation): void
    {
        abort_unless($reservation->user->department_id === $user->department_id, 404);
    }
}
