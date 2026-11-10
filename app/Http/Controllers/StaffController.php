<?php

namespace App\Http\Controllers;

use App\Services\DeskStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function home(Request $request): View
    {
        $user = $request->user()->load('department', 'section');

        return view('home', [
            'title' => 'Lab staff',
            'user' => $user,
            'intro' => 'Review requests and run the day’s room schedule.',
            'stats' => DeskStats::for($user),
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
