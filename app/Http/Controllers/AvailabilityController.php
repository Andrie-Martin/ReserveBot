<?php

namespace App\Http\Controllers;

use App\Models\DepartmentConfig;
use App\Services\RoomAvailability;
use App\Support\BookingRules;
use App\Support\TimeSlots;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Phase 4: the free rooms page. A month calendar to pick a day, then each room's free times
 * that day, plus a search for rooms that are free for a whole stretch of time.
 */
class AvailabilityController extends Controller
{
    private CarbonImmutable $today;

    private CarbonImmutable $lastDay;

    private array $openDays = [];

    /** from/to of a search, kept in every day link so the search follows you to other days */
    private array $keep = [];

    public function index(Request $request, RoomAvailability $availability): View
    {
        $user = $request->user()->load('department.config', 'section');
        $department = $user->department;
        $rules = DepartmentConfig::forDepartment($department);
        $this->openDays = $rules->openDays();

        // Days you can pick: today up to the booking limit (max_days_ahead), on days the department is open
        $this->today = CarbonImmutable::today();
        $this->lastDay = $this->today->addDays($rules->max_days_ahead);
        [$date, $dateError] = $this->pickDate($request->query('date'));

        $step = $rules->slot_minutes;
        $opens = $rules->opensAt();
        $closes = $rules->closesAt();
        $slots = TimeSlots::slots($opens, $closes, $step);
        $isOpen = $this->isOpen($date);
        $now = $date->isToday() ? now()->hour * 60 + now()->minute : null;

        $rooms = $department->rooms()->where('is_active', true)->orderBy('name')->get();
        $busy = $isOpen ? $availability->busy($rooms->pluck('id'), $date, $user) : [];
        [$search, $searchError] = $isOpen ? $this->search($request, $slots, $step, $rooms, $busy, $now) : [null, null];
        if ($search) {
            $this->keep = ['from' => TimeSlots::hhmm($search['from']), 'to' => TimeSlots::hhmm($search['to'])];
        }

        // Phase 5: reps get Book buttons (a room with free time left; while searching, only rooms free then)
        $canBook = $user->role === 'rep';
        $roomList = $rooms->map(function ($room) use ($busy, $opens, $closes, $step, $now, $search, $canBook, $date) {
            $row = [
                'room' => $room,
                'free' => TimeSlots::freeRanges($busy[$room->id] ?? [], $opens, $closes, $step, $now),
                'busy' => $busy[$room->id] ?? [],
                'allDay' => $this->blockedAllDay($busy[$room->id] ?? [], $opens, $closes),
                'match' => $search ? ! isset($search['clashes'][$room->id]) : null,
                'clash' => $search['clashes'][$room->id] ?? null,
            ];
            $row['bookUrl'] = $canBook && $row['free'] && $row['match'] !== false
                ? route('book', ['for' => 'other', 'date' => $date->toDateString()] + $this->keep + ['room' => $room->id])
                : null;

            return $row;
        });

        // The month on show (Sunday to Saturday) and what the viewer has on each day of it
        [$gridStart, $gridEnd] = $this->monthGrid($date);
        $agenda = $availability->agenda($gridStart, $gridEnd, $user);
        $dayItems = $isOpen ? ($agenda[$date->toDateString()] ?? []) : [];
        if ($canBook) {
            // A Book button on each of the rep's classes that day that isn't booked yet and isn't too soon
            $bookingRules = BookingRules::for($rules);
            foreach ($dayItems as &$item) {
                if ($item['kind'] === 'class' && ! $item['status'] && $bookingRules->checkSession($date->addMinutes($item['start'])) === null) {
                    $item['bookUrl'] = route('book', ['for' => 'class', 'session' => $item['schedule_id'].'_'.$date->toDateString()]);
                }
            }
            unset($item);
        }
        $prevDay = $this->nextPickable($date, -1);
        $nextDay = $this->nextPickable($date, 1);

        return view('rooms.index', [
            'department' => $department,
            'staffView' => $user->role !== 'rep',
            'calendar' => $this->calendar($date, $gridStart, $gridEnd, $agenda),
            'todayUrl' => route('rooms', $this->keep).'#day',
            'lastDay' => $this->lastDay,
            'date' => $date,
            'dateError' => $dateError,
            'dayItems' => $dayItems,
            'canBook' => $canBook,
            'prevUrl' => $prevDay ? $this->dayUrl($prevDay) : null,
            'nextUrl' => $nextDay ? $this->dayUrl($nextDay) : null,
            'isOpen' => $isOpen,
            'inTerm' => $availability->inTerm($date),
            'slots' => $slots,
            'step' => $step,
            'now' => $now,
            'roomList' => $roomList,
            'search' => $search,
            'searchError' => $searchError,
        ]);
    }

    /** First and last day of the calendar: back to the Sunday before the 1st, on to the Saturday after the last day */
    private function monthGrid(CarbonImmutable $date): array
    {
        $first = $date->startOfMonth();
        $last = $date->endOfMonth()->startOfDay();

        return [$first->subDays($first->dayOfWeek), $last->addDays(6 - $last->dayOfWeek)];
    }

    /** The month calendar: weeks of days, each with its state, what's on, and a link if you can pick it */
    private function calendar(CarbonImmutable $selected, CarbonImmutable $gridStart, CarbonImmutable $gridEnd, array $agenda): array
    {
        $weeks = [];
        $i = 0;
        for ($day = $gridStart; $day->lte($gridEnd); $day = $day->addDay()) {
            $inWindow = $day->gte($this->today) && $day->lte($this->lastDay);
            $weeks[intdiv($i++, 7)][] = [
                'date' => $day,
                'inMonth' => $day->month === $selected->month,
                'state' => $inWindow ? ($this->isOpen($day) ? 'open' : 'closed') : ($day->lt($this->today) ? 'past' : 'later'),
                'isToday' => $day->isSameDay($this->today),
                'isSelected' => $day->isSameDay($selected),
                'items' => $agenda[$day->toDateString()] ?? [],
                'url' => $this->canPick($day) ? $this->dayUrl($day) : null,
            ];
        }

        $previous = $selected->startOfMonth()->subMonth();
        $next = $selected->startOfMonth()->addMonth();

        return [
            'title' => $selected->format('F Y'),
            'weeks' => $weeks,
            'prevUrl' => $this->monthUrl($this->today->max($previous), $previous->endOfMonth()),
            'nextUrl' => $this->monthUrl($next, $next->endOfMonth()),
        ];
    }

    /** A link to the first day you can pick from $from to $to, or null if there's none (for the month arrows) */
    private function monthUrl(CarbonImmutable $from, CarbonImmutable $to): ?string
    {
        for ($day = $from->startOfDay(); $day->lte($to); $day = $day->addDay()) {
            if ($this->canPick($day)) {
                return $this->dayUrl($day);
            }
        }

        return null;
    }

    /** The next day you can pick after $day ($direction 1) or before it ($direction -1), or null */
    private function nextPickable(CarbonImmutable $day, int $direction): ?CarbonImmutable
    {
        for ($try = $day->addDays($direction); $try->gte($this->today) && $try->lte($this->lastDay); $try = $try->addDays($direction)) {
            if ($this->isOpen($try)) {
                return $try;
            }
        }

        return null;
    }

    private function canPick(CarbonImmutable $day): bool
    {
        return $day->gte($this->today) && $day->lte($this->lastDay) && $this->isOpen($day);
    }

    private function isOpen(CarbonImmutable $day): bool
    {
        return in_array(RoomAvailability::DAY_NAMES[$day->dayOfWeekIso], $this->openDays, true);
    }

    private function dayUrl(CarbonImmutable $day): string
    {
        return route('rooms', ['date' => $day->toDateString()] + $this->keep).'#day';
    }

    /** The reason a room is closed the whole day (a blocked entry covering opening hours), or null */
    private function blockedAllDay(array $items, int $opens, int $closes): ?string
    {
        foreach ($items as $item) {
            if ($item['kind'] === 'blocked' && $item['start'] <= $opens && $item['end'] >= $closes) {
                return $item['label'];
            }
        }

        return null;
    }

    /**
     * The date asked for, or the first day you can pick (today, or the next open day) with a message
     * if the date is missing, invalid or out of range. A closed day asked for directly is kept; the page says it's closed.
     */
    private function pickDate(?string $raw): array
    {
        $first = $this->canPick($this->today) ? $this->today : ($this->nextPickable($this->today, 1) ?? $this->today);
        $showing = 'Showing '.($first->isToday() ? 'today' : $first->format('l, F j')).' instead.';

        if ($raw === null || $raw === '') {
            return [$first, null];
        }

        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return [$first, 'That date doesn’t look right. '.$showing];
        }

        $date = CarbonImmutable::parse($raw)->startOfDay();
        if ($date->lt($this->today) || $date->gt($this->lastDay)) {
            return [$first, 'You can look from today up to '.$this->lastDay->format('F j').'. '.$showing];
        }

        return [$date, null];
    }

    /** Checks from/to and works out which rooms are free for that whole stretch (clashes: room id => what's in the way) */
    private function search(Request $request, array $slots, int $step, Collection $rooms, array $busy, ?int $now): array
    {
        $from = $request->query('from');
        $to = $request->query('to');
        if (($from === null || $from === '') && ($to === null || $to === '')) {
            return [null, null];
        }

        $starts = array_map([TimeSlots::class, 'hhmm'], $slots);
        $ends = array_map(fn ($t) => TimeSlots::hhmm($t + $step), $slots);
        if (! in_array($from, $starts, true) || ! in_array($to, $ends, true)) {
            return [null, 'Pick a start and an end time from the lists.'];
        }

        $fromMin = DepartmentConfig::minutes($from);
        $toMin = DepartmentConfig::minutes($to);
        if ($toMin <= $fromMin) {
            return [null, 'Pick an end time that is after the start time.'];
        }

        $free = [];
        $clashes = [];
        foreach ($rooms as $room) {
            $clash = TimeSlots::firstClash($busy[$room->id] ?? [], $fromMin, $toMin);
            if ($clash === null) {
                $free[] = $room;
            } else {
                $clashes[$room->id] = $clash;
            }
        }

        return [[
            'from' => $fromMin,
            'to' => $toMin,
            'free' => $free,
            'clashes' => $clashes,
            'started' => $now !== null && $fromMin < $now,
        ], null];
    }
}
