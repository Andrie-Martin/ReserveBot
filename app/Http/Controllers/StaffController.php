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

        // Approved or in-use bookings starting today in this department. Waiting, done and cancelled ones
        // are left out on purpose: the Today page lists every status, so the label says what is counted.
        $today = Reservation::whereIn('status', ['Confirmed', 'InUse'])
            ->whereDate('start_time', today())
            ->whereRelation('user', 'department_id', $user->department_id)
            ->count();

        return view('home', [
            'title' => 'Lab staff',
            'user' => $user,
            'intro' => "This is where you will review requests and run the day's room schedule.",
            'stat' => ['value' => $today, 'label' => $today === 1 ? 'booking approved or in use today' : 'bookings approved or in use today'],
            'classes' => null,
            'ready' => [
                ['label' => "Today's schedule", 'url' => route('today')],
                ['label' => 'Review requests', 'url' => route('requests')],
                ['label' => 'See which rooms are free', 'url' => route('rooms')],
                ['label' => 'Manage rooms, equipment and rules', 'url' => route('manage.rooms')],
                ['label' => 'Timetable (new semester)', 'url' => route('manage.timetable')],
            ],
            'comingNext' => [
                'Reminders by Facebook Messenger, once the site is online',
            ],
        ]);
    }
}
