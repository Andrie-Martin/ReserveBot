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
            'intro' => 'Book rooms and equipment for your block, and check which rooms are free.',
            'stat' => null,
            'classes' => $classes,
            'ready' => [
                ['label' => 'See which rooms are free', 'url' => route('rooms')],
                ['label' => 'Book a room or equipment', 'url' => route('book')],
            ],
            'comingNext' => [
                'See all your bookings in one list, and cancel them',
            ],
        ]);
    }
}
