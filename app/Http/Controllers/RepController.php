<?php

namespace App\Http\Controllers;

use App\Models\ClassSchedule;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RepController extends Controller
{
    public function home(Request $request): View
    {
        $user = $request->user()->load('department', 'section');

        // The rep's block's classes for the current semester
        $classes = ClassSchedule::with('room', 'course')
            ->where('section_id', $user->section_id)
            ->whereNotNull('course_id')
            ->whereHas('term', fn ($term) => $term->current())
            ->orderBy('day_of_week')     // Mon, Tue, ... (the column's list order)
            ->orderBy('start_time')
            ->get();

        return view('home', [
            'title' => 'Home',
            'user' => $user,
            'intro' => 'This is where you will book rooms and equipment for your block.',
            'stat' => null,
            'classes' => $classes,
            'comingNext' => [
                'Check which rooms are free',
                'Book a session of one of your classes, or a free slot for a make-up class',
                'Request equipment like projectors and HDMI cables',
                'See and cancel your reservations',
            ],
        ]);
    }
}
