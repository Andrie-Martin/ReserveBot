<?php

namespace App\Services;

use App\Models\ClassSchedule;
use App\Models\DepartmentConfig;
use App\Models\Reservation;
use App\Models\Term;
use App\Models\User;
use App\Support\TimeSlots;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Reads the timetable and bookings for the free rooms page.
 *
 * busy():   what keeps each room busy on one day (every department's classes and bookings,
 *           because rooms are shared)
 * agenda(): what the viewer has on each day, for the month calendar
 */
class RoomAvailability
{
    public const DAY_NAMES = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    public const STATUS_WORDS = ['Pending' => 'waiting for approval', 'Confirmed' => 'confirmed', 'InUse' => 'in use now'];

    /** True when the date falls inside a semester, so the timetable applies */
    public function inTerm(CarbonImmutable $date): bool
    {
        return Term::where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->exists();
    }

    /**
     * Busy items for each room on $date, worded for $viewer.
     * Each item: kind (class, blocked or booking), start and end (minutes after midnight),
     * label, detail (lines for a tooltip), status, mine.
     *
     * @return array<int, list<array>> room id => items sorted by start
     */
    public function busy(Collection $roomIds, CarbonImmutable $date, User $viewer): array
    {
        $staffView = $viewer->role !== 'rep';
        $dayStart = $date->startOfDay();
        $dayEnd = $dayStart->addDay();
        $busy = [];

        $classes = ClassSchedule::with(['course', 'section'])
            ->whereIn('room_id', $roomIds)
            ->where('day_of_week', self::DAY_NAMES[$date->dayOfWeekIso])
            ->whereHas('term', fn ($term) => $term
                ->where('start_date', '<=', $date->toDateString())
                ->where('end_date', '>=', $date->toDateString()))
            ->get();

        $bookings = Reservation::with(['user.section'])
            ->whereIn('room_id', $roomIds)
            ->whereIn('status', Reservation::BLOCKING_STATUSES)
            ->where('start_time', '<', $dayEnd)
            ->where('end_time', '>', $dayStart)
            ->get();

        // A booking of a class session shows on that class instead of separately
        $sessions = $bookings->whereNotNull('class_schedule_id')->keyBy('class_schedule_id');
        $shownSessions = [];

        foreach ($classes as $entry) {
            $start = DepartmentConfig::minutes($entry->start_time);
            $end = DepartmentConfig::minutes($entry->end_time);

            if ($entry->entry_type === 'Blocked') {
                $note = $entry->note ?: 'Not available';
                $busy[$entry->room_id][] = ['kind' => 'blocked', 'start' => $start, 'end' => $end,
                    'status' => null, 'mine' => false, 'label' => $note, 'detail' => [$note]];

                continue;
            }

            $code = $entry->course?->code ?? 'Class';
            $section = $entry->section?->name;
            $session = $sessions->get($entry->id);
            if ($session && $session->room_id === $entry->room_id) {
                $shownSessions[] = $session->id;
            } else {
                $session = null;
            }
            $mine = ! $staffView && $section !== null && $entry->section_id === $viewer->section_id;

            $detail = [trim($code.' '.($entry->course?->title ?? ''))];
            if ($staffView && $entry->instructor) {
                $detail[] = 'Instructor: '.$entry->instructor;
            }
            if ($session && $staffView) {
                $detail[] = 'Room booked by '.$session->user->name;
            }

            $busy[$entry->room_id][] = [
                'kind' => 'class', 'start' => $start, 'end' => $end, 'status' => $session?->status, 'mine' => $mine,
                'label' => ($mine ? 'Your class · ' : '').($section ? $code.' · '.$section : $code),
                'detail' => $detail,
            ];
        }

        foreach ($bookings as $booking) {
            if (in_array($booking->id, $shownSessions, true)) {
                continue;
            }

            [$start, $end] = $this->clampToDay($booking, $dayStart, $dayEnd);
            $booker = $booking->user;
            $block = $booker->section?->name;
            $mine = ! $staffView && $booking->user_id === $viewer->id;

            if ($staffView) {
                $who = $booker->name.($block ? ' ('.$block.')' : '');
                $label = 'Booked by '.$who;
                $detail = ['Purpose: '.($booking->purpose ?: 'scheduled class')];
            } else {
                $label = $mine ? 'Your booking' : 'Booked by '.($block ?: 'staff');
                $detail = $mine && $booking->purpose ? ['Purpose: '.$booking->purpose] : [];
            }

            $busy[$booking->room_id][] = ['kind' => 'booking', 'start' => $start, 'end' => $end,
                'status' => $booking->status, 'mine' => $mine, 'label' => $label, 'detail' => $detail];
        }

        foreach ($busy as &$items) {
            usort($items, fn ($a, $b) => $a['start'] <=> $b['start']);
        }

        return $busy;
    }

    /**
     * What the viewer has on each day from $from to $to, for the month calendar.
     * Reps: their block's classes and their own bookings. Lab staff and admins: the department's bookings.
     * Each item: kind (class or booking), start and end (minutes), room, text (short, for the calendar),
     * title (longer, for the day's list), status, tip.
     *
     * @return array<string, list<array>> 'Y-m-d' => items sorted by time
     */
    public function agenda(CarbonImmutable $from, CarbonImmutable $to, User $viewer): array
    {
        $staffView = $viewer->role !== 'rep';

        $bookings = Reservation::with(['room', 'user.section', 'classSchedule.course'])
            ->whereIn('status', Reservation::BLOCKING_STATUSES)
            ->where('start_time', '>=', $from->startOfDay())
            ->where('start_time', '<', $to->addDay()->startOfDay())
            ->when(
                $staffView,
                fn ($q) => $q->whereRelation('user', 'department_id', $viewer->department_id),
                fn ($q) => $q->where('user_id', $viewer->id),
            )
            ->orderBy('start_time')
            ->get();

        $classes = $staffView || ! $viewer->section_id ? collect() : ClassSchedule::with(['room', 'course', 'term'])
            ->where('section_id', $viewer->section_id)
            ->where('entry_type', 'Class')
            ->orderBy('start_time')
            ->get();

        $agenda = [];
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $key = $day->toDateString();
            $dayName = self::DAY_NAMES[$day->dayOfWeekIso];
            $dayBookings = $bookings->filter(fn ($b) => $b->start_time->toDateString() === $key);
            $items = [];
            $sessionIds = [];

            foreach ($classes as $class) {
                if ($class->day_of_week !== $dayName
                    || $key < $class->term->start_date->toDateString()
                    || $key > $class->term->end_date->toDateString()) {
                    continue;
                }
                $session = $dayBookings->firstWhere('class_schedule_id', $class->id);
                if ($session) {
                    $sessionIds[] = $session->id;
                }
                $start = DepartmentConfig::minutes($class->start_time);
                $end = DepartmentConfig::minutes($class->end_time);
                $course = trim(($class->course?->code ?? 'Class').' '.($class->course?->title ?? ''));
                $items[] = [
                    'kind' => 'class', 'start' => $start, 'end' => $end, 'room' => $class->room->name,
                    'text' => $class->course?->code ?? 'Class', 'title' => $course, 'status' => $session?->status,
                    'tip' => $course.', '.TimeSlots::range($start, $end).' in '.$class->room->name
                        .($session ? ' (room booked, '.self::STATUS_WORDS[$session->status].')' : ''),
                ];
            }

            foreach ($dayBookings as $booking) {
                if (in_array($booking->id, $sessionIds, true)) {
                    continue;
                }
                $start = $booking->start_time->hour * 60 + $booking->start_time->minute;
                $end = $booking->end_time->isSameDay($booking->start_time) ? $booking->end_time->hour * 60 + $booking->end_time->minute : 24 * 60;
                $what = $booking->purpose ?: ($booking->classSchedule?->course?->code ?? 'Booking');
                $booker = $booking->user;
                $who = $booker->name.($booker->section ? ' ('.$booker->section->name.')' : '');
                $items[] = [
                    'kind' => 'booking', 'start' => $start, 'end' => $end, 'room' => $booking->room->name,
                    'text' => $staffView ? ($booker->section?->name ?? $booker->name) : $what,
                    'title' => $staffView ? $who.': '.$what : 'Your booking: '.$what,
                    'status' => $booking->status,
                    'tip' => ($staffView ? $who.': ' : '').$what.', '.TimeSlots::range($start, $end).' in '.$booking->room->name
                        .' ('.self::STATUS_WORDS[$booking->status].')',
                ];
            }

            usort($items, fn ($a, $b) => $a['start'] <=> $b['start']);
            $agenda[$key] = $items;
        }

        return $agenda;
    }

    /** A booking's start and end on this day, in minutes (cut at midnight if it runs over) */
    private function clampToDay(Reservation $booking, CarbonImmutable $dayStart, CarbonImmutable $dayEnd): array
    {
        $start = $booking->start_time->lt($dayStart) ? 0 : $booking->start_time->hour * 60 + $booking->start_time->minute;
        $end = $booking->end_time->gte($dayEnd) ? 24 * 60 : $booking->end_time->hour * 60 + $booking->end_time->minute;

        return [$start, $end];
    }
}
