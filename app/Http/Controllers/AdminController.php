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
            'intro' => 'This is where you will approve requests and manage the department.',
            'stat' => [
                'value' => $pending,
                'label' => $pending === 1 ? 'request waiting for approval' : 'requests waiting for approval',
            ],
            'classes' => null,
            'comingNext' => [
                'Approve or reject pending requests',
                'Manage rooms, equipment and stock counts',
                'Create accounts for reps and lab staff',
                'Department settings and reports',
            ],
        ]);
    }
}
