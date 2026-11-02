<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function home(Request $request): View
    {
        $user = $request->user()->load('department', 'section');

        // Requests from this department waiting for approval
        $pending = Reservation::where('status', 'Pending')
            ->whereRelation('user', 'department_id', $user->department_id)
            ->count();

        return view('home', [
            'title' => 'Admin',
            'user' => $user,
            'intro' => 'Approve requests, run the day, and manage the department and its accounts.',
            'stat' => [
                'value' => $pending,
                'label' => $pending === 1 ? 'request waiting for approval' : 'requests waiting for approval',
            ],
            'classes' => null,
            'ready' => [
                ['label' => 'Review requests', 'url' => route('requests')],
                ['label' => "Today's schedule", 'url' => route('today')],
                ['label' => 'See which rooms are free', 'url' => route('rooms')],
                ['label' => 'Manage rooms, equipment and rules', 'url' => route('manage.rooms')],
                ['label' => 'Timetable (new semester)', 'url' => route('manage.timetable')],
                ['label' => 'Rep and lab staff accounts', 'url' => route('accounts.index')],
            ],
            'comingNext' => [
                'Reminders by Facebook Messenger, once the site is online',
            ],
        ]);
    }
}
