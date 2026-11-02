<?php

namespace App\Services;

use App\Models\ClassCheck;
use App\Models\ClassSchedule;
use App\Models\DepartmentConfig;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Support\ClassCheckRules;
use App\Support\TimeSlots;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Phase 10: is a class meeting face-to-face? A meeting is one timetable class on one date.
 *
 * The block's reps answer with the buttons in the reminder emails or on their home page; lab staff
 * and admins can answer on the Today page. 'Online' frees the class's room, and so does no answer
 * by the time the 5-hour email goes out (rules: App\Support\ClassCheckRules). A freed room shows
 * as free on Free rooms and anyone can book it. 'Face-to-face' takes it back if nobody has.
 *
 * States: 'f2f', 'online', 'freed', 'waiting', plus 'booked' (the rep booked the meeting as a class
 * session: it keeps its room, and the booking's own reminders are sent instead).
 */
class ClassChecks
{
    public const LABELS = ClassCheckRules::LABELS + ['booked' => 'Booked as a class session'];

    /** The colour of each state's tag (public/css/style.css) */
    public const BADGES = ['f2f' => 'st-ok', 'booked' => 'st-ok', 'online' => 'st-freed', 'freed' => 'st-freed', 'waiting' => 'st-wait'];

    /** The meeting's start and end on $date, or null if the class doesn't meet that day */
    public function meeting(ClassSchedule $class, CarbonImmutable $date): ?array
    {
        if ($class->entry_type !== 'Class' || ! $class->room_id || $date->format('D') !== $class->day_of_week) {
            return null;
        }
        $class->loadMissing('term');
        $day = $date->toDateString();
        if ($day < $class->term->start_date->toDateString() || $day > $class->term->end_date->toDateString()) {
            return null;
        }

        return [
            $date->startOfDay()->addMinutes(DepartmentConfig::minutes($class->start_time)),
            $date->startOfDay()->addMinutes(DepartmentConfig::minutes($class->end_time)),
        ];
    }

    /**
     * The state of each class's meeting on $date.
     *
     * @return array<int, string> class id => state
     */
    public function states(Collection $classes, CarbonImmutable $date, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $classes = $classes->where('entry_type', 'Class');
        if ($classes->isEmpty()) {
            return [];
        }

        $ids = $classes->pluck('id');
        $checks = $this->checksOn($ids, $date);
        $booked = Reservation::blocking()
            ->whereIn('class_schedule_id', $ids)
            ->where('start_time', '>=', $date->startOfDay())
            ->where('start_time', '<', $date->startOfDay()->addDay())
            ->pluck('class_schedule_id')
            ->all();

        $states = [];
        foreach ($classes as $class) {
            if (in_array($class->id, $booked)) {
                $states[$class->id] = 'booked';

                continue;
            }
            $check = $checks->get($class->id);
            $start = $date->startOfDay()->addMinutes(DepartmentConfig::minutes($class->start_time));
            $states[$class->id] = ClassCheckRules::state($check?->answer, $check?->asked_at?->toImmutable(), $start, $now);
        }

        return $states;
    }

    /** Ids of the classes whose room is free on $date (online, or not confirmed in time) */
    public function freedIds(Collection $classes, CarbonImmutable $date): array
    {
        return array_keys(array_filter($this->states($classes, $date), fn (string $state) => ClassCheckRules::roomFree($state)));
    }

    /** The answers for these classes on $date, by class id */
    public function checksOn(Collection $classIds, CarbonImmutable $date): Collection
    {
        return ClassCheck::with('answeredBy')
            ->whereIn('class_schedule_id', $classIds)
            ->where('class_date', $date->toDateString())
            ->get()
            ->keyBy('class_schedule_id');
    }

    /**
     * Class meetings from now until $days ahead, with their state, for a block (a rep's home page)
     * or for a department on one day (the Today page). Each: class, date, start, end, state, check.
     */
    public function forBlock(int $sectionId, int $days = 7): array
    {
        $classes = $this->classes()->where('section_id', $sectionId)->get();
        $now = CarbonImmutable::now();
        $list = [];
        for ($day = $now->startOfDay(); $day->lte($now->addDays($days)); $day = $day->addDay()) {
            foreach ($this->meetingsOn($classes, $day, $now) as $meeting) {
                if ($meeting['end']->gt($now)) {
                    $list[] = $meeting;
                }
            }
        }

        return $list;
    }

    public function forDepartment(int $departmentId, CarbonImmutable $date): array
    {
        return $this->meetingsOn($this->classes()->where('department_id', $departmentId)->get(), $date, CarbonImmutable::now());
    }

    /**
     * Saves an answer for one meeting and says what happened: [ok, message].
     * 'f2f' after the room was freed takes it back, unless someone has booked it since.
     *
     * @param  string  $via  'email', 'site' or 'staff'
     */
    public function answer(ClassSchedule $class, CarbonImmutable $date, string $answer, User $by, string $via): array
    {
        $class->loadMissing(['course', 'room']);
        $meeting = $this->meeting($class, $date);
        if (! $meeting) {
            return [false, 'That class doesn’t meet on that day.'];
        }
        [$start, $end] = $meeting;
        $now = CarbonImmutable::now();
        if ($now->gte($end)) {
            return [false, 'This class has already ended.'];
        }
        $name = self::name($class);
        $room = $class->room->name;
        $when = $start->format('l, F j').', '.TimeSlots::span(DepartmentConfig::minutes($class->start_time), DepartmentConfig::minutes($class->end_time));

        return DB::transaction(function () use ($class, $date, $answer, $by, $via, $start, $end, $now, $name, $room, $when) {
            // The same lock a booking of this room takes, so the two can't cross
            Room::whereKey($class->room_id)->lockForUpdate()->first();

            $booking = Reservation::blocking()
                ->where('class_schedule_id', $class->id)
                ->where('start_time', '>=', $date->startOfDay())
                ->where('start_time', '<', $date->startOfDay()->addDay())
                ->first();
            if ($booking && $answer === 'online') {
                return [false, $name.' on '.$start->format('l, F j').' is booked as a class session (booking #'.$booking->id.'). Cancel that booking first if the class is online.'];
            }

            $check = ClassCheck::firstOrNew(['class_schedule_id' => $class->id, 'class_date' => $date->toDateString()]);
            $state = ClassCheckRules::state($check->answer, $check->asked_at?->toImmutable(), $start, $now);
            if ($answer === 'f2f' && ClassCheckRules::roomFree($state)) {
                $taken = Reservation::blocking()->where('room_id', $class->room_id)->overlapping($start, $end)->first();
                if ($taken) {
                    return [false, 'Someone booked '.$room.' ('.$taken->start_time->format('g:i A').'–'.$taken->end_time->format('g:i A')
                        .') after it was freed, so '.$name.' can’t have it back. Look for another room on Free rooms.'];
                }
            }

            $check->fill(['answer' => $answer, 'answered_by' => $by->id, 'answered_via' => $via, 'answered_at' => $now])->save();

            return [true, $answer === 'f2f'
                ? $name.' ('.$when.') is face-to-face: '.$room.' stays reserved for it.'
                : $name.' ('.$when.') is online or has no class: '.$room.' is free for others then.'];
        }, 3);
    }

    /** The block's reps who get the emails: turned on, with their email connected */
    public function reps(ClassSchedule $class): Collection
    {
        return User::where('role', 'rep')
            ->where('section_id', $class->section_id)
            ->where('is_active', true)
            ->whereNotNull('email_verified_at')
            ->get();
    }

    /** An email button's address: it works without logging in, until the class ends */
    public function link(ClassSchedule $class, CarbonImmutable $date, User $rep, string $answer): string
    {
        [, $end] = $this->meeting($class, $date);

        return URL::temporarySignedRoute('class-check', $end, [
            'class' => $class->id, 'date' => $date->toDateString(), 'user' => $rep->id, 'answer' => $answer,
        ]);
    }

    /** 'CMPE 451' */
    public static function name(ClassSchedule $class): string
    {
        return $class->course?->code ?? 'Your class';
    }

    /** Timetable classes that have a room and a block */
    private function classes()
    {
        return ClassSchedule::with(['course', 'room', 'section', 'term'])
            ->where('entry_type', 'Class')
            ->whereNotNull('room_id')
            ->whereNotNull('section_id')
            ->orderBy('start_time');
    }

    /** The meetings of $classes on $day, with their state and answer */
    private function meetingsOn(Collection $classes, CarbonImmutable $day, CarbonImmutable $now): array
    {
        $meeting = [];
        foreach ($classes as $class) {
            if ($times = $this->meeting($class, $day)) {
                $meeting[$class->id] = [$class, $times];
            }
        }
        if (! $meeting) {
            return [];
        }

        $todays = collect(array_column($meeting, 0));
        $states = $this->states($todays, $day, $now);
        $checks = $this->checksOn($todays->pluck('id'), $day);

        return array_values(array_map(fn ($pair) => [
            'class' => $pair[0],
            'date' => $day,
            'start' => $pair[1][0],
            'end' => $pair[1][1],
            'state' => $states[$pair[0]->id],
            'check' => $checks->get($pair[0]->id),
        ], $meeting));
    }
}
