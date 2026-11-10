<?php

namespace App\Http\Controllers;

use App\Models\ClassSchedule;
use App\Models\User;
use App\Services\ClassChecks;
use App\Support\TimeSlots;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 10: answering "is this class face-to-face?" for one class meeting.
 *  - email(): the buttons in the reminder emails. A signed link for one rep that works without
 *    logging in until the class ends. It opens a page; the answer is saved only when they press
 *    a button there (so email link checkers can't answer by opening the link).
 *  - asRep(): a rep's home page, for their block's classes
 *  - asStaff(): the Today page, for lab staff and admins of the class's department
 */
class ClassCheckController extends Controller
{
    private const ANSWERS = ['f2f', 'online'];

    public function __construct(private ClassChecks $checks) {}

    /** GET shows the class and the two buttons; POST saves the answer */
    public function email(Request $request, ClassSchedule $class, string $date, User $user): View|RedirectResponse
    {
        $day = $this->day($date);
        $meeting = $day ? $this->checks->meeting($class, $day) : null;
        abort_unless($meeting, 404);
        $isRep = $user->role === 'rep' && $user->is_active && $user->section_id === $class->section_id;

        if ($request->isMethod('post')) {
            if (! $isRep) {
                return back()->with('problem', 'This link isn’t for '.$user->displayName().' anymore. Answer on your home page instead.');
            }
            $answer = (string) $request->input('answer');
            abort_unless(in_array($answer, self::ANSWERS, true), 422);
            [$ok, $message] = $this->checks->answer($class, $day, $answer, $user, 'email');

            return back()->with($ok ? 'status' : 'problem', $message);
        }

        $class->loadMissing(['course', 'room', 'section']);
        [$start, $end] = $meeting;
        $state = $this->checks->states(collect([$class]), $day)[$class->id];

        return view('classes.check', [
            'class' => $class,
            'name' => ClassChecks::name($class),
            'start' => $start,
            'span' => TimeSlots::span($start->hour * 60 + $start->minute, $end->hour * 60 + $end->minute),
            'state' => $state,
            'label' => ClassChecks::LABELS[$state],
            'picked' => in_array($request->query('answer'), self::ANSWERS, true) ? $request->query('answer') : null,
            'open' => CarbonImmutable::now()->lt($end) && $state !== 'booked' && $isRep,
            'isRep' => $isRep,
            'rep' => $user,
        ]);
    }

    /** A rep answers for one of their block's classes (home page) */
    public function asRep(Request $request, ClassSchedule $class, string $date): RedirectResponse
    {
        $rep = $request->user();
        abort_unless($class->section_id !== null && $class->section_id === $rep->section_id, 404);

        return $this->save($request, $class, $date, $rep, 'site');
    }

    /** Lab staff or an admin answers for a class of their department (Today page) */
    public function asStaff(Request $request, ClassSchedule $class, string $date): RedirectResponse
    {
        $staff = $request->user();
        abort_unless($class->department_id === $staff->department_id, 404);

        return $this->save($request, $class, $date, $staff, 'staff');
    }

    private function save(Request $request, ClassSchedule $class, string $date, User $by, string $via): RedirectResponse
    {
        $day = $this->day($date);
        $answer = (string) $request->input('answer');
        abort_unless($day && in_array($answer, self::ANSWERS, true), 404);
        [$ok, $message] = $this->checks->answer($class, $day, $answer, $by, $via);

        return back()->with($ok ? 'status' : 'problem', $message);
    }

    /** '2026-11-05' -> that day, or null */
    private function day(string $date): ?CarbonImmutable
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? CarbonImmutable::createFromFormat('!Y-m-d', $date) : null;

        return $day && $day->toDateString() === $date ? $day : null;
    }
}
