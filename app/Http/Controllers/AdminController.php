<?php

namespace App\Http\Controllers;

use App\Services\DeskStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function home(Request $request): View
    {
        $user = $request->user()->load('department', 'section');

        return view('home', [
            'title' => 'Admin',
            'user' => $user,
            'intro' => 'Approve requests, run the day, and manage the department and its accounts.',
            'stats' => DeskStats::for($user),
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
