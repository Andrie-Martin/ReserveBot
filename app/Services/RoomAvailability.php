<?php

namespace App\Services;

use App\Models\ClassCheck;
use App\Models\ClassSchedule;
use App\Models\DepartmentConfig;
use App\Models\Reservation;
use App\Models\Term;
use App\Models\User;
use App\Support\BlockedReason;
use App\Support\ClassCheckRules;
use App\Support\TimeSlots;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Reads the timetable and bookings for the free rooms page.
 *
 * busy():   what keeps each room busy on one day (every department's classes and bookings,
 *           because rooms are shared). Phase 10: a class that's online, or wasn't confirmed
 *           face-to-face in time, doesn't keep its room busy (App\Services\ClassChecks).
 * agenda(): what the viewer has on each day, for the month calendar
 */
class RoomAvailability
{
    public function __construct(private ClassChecks $checks) {}

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
     * label, detail (lines for a tooltip), status, mine, schedule_id and reservation_id (which entry it is).
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

        // Phase 10: classes that are online today, or weren't confirmed face-to-face in time, free their room
        $freed = $this->checks->freedIds($classes, $date);

        foreach ($classes as $entry) {
            if (in_array($entry->id, $freed)) {
                continue;
            }
            $start = DepartmentConfig::minutes($entry->start_time);
            $end = DepartmentConfig::minutes($entry->end_time);

            if ($entry->entry_type === 'Blocked') {
                // Wording only (see BlockedReason): the stored note, the block and its times are unchanged.
                $reason = BlockedReason::text($entry->note);
                $label = 'Unavailable — '.$reason;
                $busy[$entry->room_id][] = ['kind' => 'blocked', 'start' => $start, 'end' => $end,
                    'status' => null, 'mine' => false, 'label' => $label, 'reason' => $reason, 'detail' => [$label],
                    'schedule_id' => $entry->id, 'reservation_id' => null];

                continue;
            }

            $code = $entry->course?->code ?? 'Class';
            $section = $entry->section?->name;
            $session = $sessions->get($entry->id);
            if ($session && $session->room_id === $entry->room_id && self::atClassTime($session, $entry)) {
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
                $detail[] = 'Room booked by '.$session->bookedBy();
            }

            $busy[$entry->room_id][] = [
                'kind' => 'class', 'start' => $start, 'end' => $end, 'status' => $session?->status, 'mine' => $mine,
                'schedule_id' => $entry->id, 'reservation_id' => $session?->id,
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
                $who = $booking->bookedBy();     // "BSCPE 4-5 (Rep: Bea Prieto)"
                $label = 'Booked by '.$who;
                $detail = ['Purpose: '.($booking->purpose ?: 'scheduled class')];
            } else {
                $label = $mine ? 'Your booking' : 'Booked by '.($block ?: 'staff');
                $detail = $mine && $booking->purpose ? ['Purpose: '.$booking->purpose] : [];
            }

            $busy[$booking->room_id][] = ['kind' => 'booking', 'start' => $start, 'end' => $end,
                'status' => $booking->status, 'mine' => $mine, 'label' => $label, 'detail' => $detail,
                'schedule_id' => $booking->class_schedule_id, 'reservation_id' => $booking->id];
        }

        foreach ($busy as &$items) {
            usort($items, fn ($a, $b) => $a['start'] <=> $b['start']);
        }

        return $busy;
    }

    /**
     * What the viewer has on each day from $from to $to, for the month calendar.
     * Reps: their block's classes and their own bookings. Lab staff and admins: the department's bookings.
     * Each item: kind (class or booking), start and end (minutes), room (or 'Equipment only' with noRoom),
     * text (short, for the calendar), title (longer, for the day's list), status, tag (what was booked and
     * its status), url (the booking's page, if the viewer may open it), schedule_id (classes), tip.
     *
     * @return array<string, list<array>> 'Y-m-d' => items sorted by time
     */
    public function agenda(CarbonImmutable $from, CarbonImmutable $to, User $viewer): array
    {
        $staffView = $viewer->role !== 'rep';

        $classes = $staffView || ! $viewer->section_id ? collect() : ClassSchedule::with(['room', 'course', 'term'])
            ->where('section_id', $viewer->section_id)
            ->where('entry_type', 'Class')
            ->orderBy('start_time')
            ->get();

        // Staff: the department's bookings. Reps: their own, plus any booking of their block's classes
        // (a block can have more than one rep).
        $bookings = Reservation::with(['room', 'user.section', 'classSchedule.course', 'equipment'])
            ->whereIn('status', Reservation::BLOCKING_STATUSES)
            ->where('start_time', '>=', $from->startOfDay())
            ->where('start_time', '<', $to->addDay()->startOfDay())
            ->when(
                $staffView,
                fn ($q) => $q->whereRelation('user', 'department_id', $viewer->department_id),
                fn ($q) => $q->where(fn ($q) => $q->where('user_id', $viewer->id)->orWhereIn('class_schedule_id', $classes->pluck('id'))),
            )
            ->orderBy('start_time')
            ->get();

        // Phase 10: the block's face-to-face answers for these days
        $checks = $classes->isEmpty() ? collect() : ClassCheck::whereIn('class_schedule_id', $classes->pluck('id'))
            ->whereBetween('class_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn ($check) => $check->class_schedule_id.'_'.$check->class_date->toDateString());
        $now = CarbonImmutable::now();

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
                // A booking of this session, still at the class's own time (and room, if it has one).
                // Once lab staff move it, it shows as a booking of its own instead.
                $session = $dayBookings->first(fn ($b) => $b->class_schedule_id === $class->id
                    && ($b->room_id === null || $b->room_id === $class->room_id) && self::atClassTime($b, $class));
                if ($session) {
                    $sessionIds[] = $session->id;
                }
                $start = DepartmentConfig::minutes($class->start_time);
                $end = DepartmentConfig::minutes($class->end_time);
                $course = trim(($class->course?->code ?? 'Class').' '.($class->course?->title ?? ''));
                $check = $checks->get($class->id.'_'.$key);
                $state = $dayBookings->contains('class_schedule_id', $class->id) ? 'booked'
                    : ClassCheckRules::state($check?->answer, $check?->asked_at?->toImmutable(), $day->startOfDay()->addMinutes($start), $now);
                $items[] = [
                    'kind' => 'class', 'start' => $start, 'end' => $end, 'room' => $class->room->name, 'noRoom' => false,
                    // Phase 10: 'Face-to-face', 'Online · room freed' or 'Not confirmed · room freed' (nothing while waiting)
                    'check' => in_array($state, ['f2f', 'online', 'freed'], true) ? ClassChecks::LABELS[$state] : null,
                    'freed' => ClassCheckRules::roomFree($state),
                    'text' => $class->course?->code ?? 'Class', 'title' => $course, 'status' => $session?->status,
                    'tag' => $session ? self::tag($session) : null,
                    'url' => $session && $session->user_id === $viewer->id ? route('bookings.show', $session) : null,
                    'schedule_id' => $class->id,
                    // lab staff moved this session's booking to another time or room (it shows on its own)
                    'booked_elsewhere' => ! $session && $dayBookings->contains('class_schedule_id', $class->id),
                    'tip' => $course.', '.TimeSlots::range($start, $end).' in '.$class->room->name
                        .($session ? ' (booked: '.self::tag($session).')' : ''),
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
                $who = $booking->bookedBy();
                $where = $booking->room?->name;   // empty for an equipment-only booking
                $items[] = [
                    'kind' => 'booking', 'start' => $start, 'end' => $end, 'room' => $where ?? 'Equipment only', 'noRoom' => $where === null,
                    'text' => $staffView ? $booker->displayName() : $what,
                    'title' => $staffView ? $who.': '.$what : 'Your booking: '.$what,
                    'status' => $booking->status,
                    'tag' => self::tag($booking),
                    'url' => $staffView || $booking->user_id === $viewer->id ? route('bookings.show', $booking) : null,
                    'schedule_id' => null,
                    'tip' => ($staffView ? $who.': ' : '').$what.', '.TimeSlots::range($start, $end)
                        .($where ? ' in '.$where : '').($booking->hasEquipment() ? ' · '.$booking->itemsText() : '')
                        .' ('.self::STATUS_WORDS[$booking->status].')',
                ];
            }

            usort($items, fn ($a, $b) => $a['start'] <=> $b['start']);
            $agenda[$key] = $items;
        }

        return $agenda;
    }

    /** True when a class-session booking is still at its class's own start and end time */
    private static function atClassTime(Reservation $booking, ClassSchedule $class): bool
    {
        return $booking->start_time->format('H:i:s') === $class->start_time
            && $booking->end_time->format('H:i:s') === $class->end_time;
    }

    /** 'Room and equipment · waiting for approval' */
    public static function tag(Reservation $booking): string
    {
        return $booking->needLabel().' · '.self::STATUS_WORDS[$booking->status];
    }

    /** A booking's start and end on this day, in minutes (cut at midnight if it runs over) */
    private function clampToDay(Reservation $booking, CarbonImmutable $dayStart, CarbonImmutable $dayEnd): array
    {
        $start = $booking->start_time->lt($dayStart) ? 0 : $booking->start_time->hour * 60 + $booking->start_time->minute;
        $end = $booking->end_time->gte($dayEnd) ? 24 * 60 : $booking->end_time->hour * 60 + $booking->end_time->minute;

        return [$start, $end];
    }
}
