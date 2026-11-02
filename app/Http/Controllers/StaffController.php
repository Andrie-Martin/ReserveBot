<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function home(Request $request): View
    {
        $user = $request->user()->load('department', 'section');

        // Reservations happening today in this department
        $today = Reservation::whereIn('status', ['Confirmed', 'InUse'])
            ->whereDate('start_time', today())
            ->whereRelation('user', 'department_id', $user->department_id)
            ->count();

        return view('home', [
            'title' => 'Lab staff',
            'user' => $user,
            'intro' => "This is where you will review requests and run the day's room schedule.",
            'stat' => ['value' => $today, 'label' => $today === 1 ? 'reservation today' : 'reservations today'],
            'classes' => null,
            'ready' => [
                ['label' => 'Review requests', 'url' => route('requests')],
                ['label' => 'See which rooms are free', 'url' => route('rooms')],
            ],
            'comingNext' => [
                "Today's schedule for every room",
                'Verify a reservation and issue the key and equipment',
                'Mark items returned, or mark a no-show',
                'Reschedule or cancel a reservation, with a reason',
                'Manage rooms, equipment and stock counts',
                'Department settings and reports',
            ],
        ]);
    }
}
