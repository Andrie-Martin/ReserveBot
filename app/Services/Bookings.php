<?php

namespace App\Services;

use App\Models\ClassSchedule;
use App\Models\Department;
use App\Models\DepartmentConfig;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\OverrideLog;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Support\BookingRules;
use App\Support\TimeSlots;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 5: making a booking. One booking can hold a room, equipment, or both, for one of a rep's
 * classes or for something else (a make-up class, a meeting, an event).
 *
 * A session is one meeting of a class on one day. Its key is "<class_schedules.id>_<date>", e.g. "32_2026-11-03".
 */
class Bookings
{
    public function __construct(private RoomAvailability $availability) {}

    /**
     * The rep's class sessions they can still book: inside the booking window and the semester,
     * far enough ahead, and not booked yet. Sorted by day, then time.
     *
     * @return list<array{key: string, class: ClassSchedule, day: CarbonImmutable, from: int, to: int, start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function sessions(User $rep, BookingRules $rules): array
    {
        if (! $rep->section_id) {
            return [];
        }

        $classes = ClassSchedule::with(['course', 'room', 'term'])
            ->where('section_id', $rep->section_id)
            ->where('entry_type', 'Class')
            ->orderBy('start_time')
            ->get();

        $booked = Reservation::blocking()
            ->whereIn('class_schedule_id', $classes->pluck('id'))
            ->where('start_time', '>=', $rules->today())
            ->get(['class_schedule_id', 'start_time'])
            ->map(fn ($r) => $r->class_schedule_id.'_'.$r->start_time->toDateString())
            ->all();

        $sessions = [];
        for ($day = $rules->today(); $day->lte($rules->lastDay()); $day = $day->addDay()) {
            foreach ($classes as $class) {
                $session = $this->session($class, $day);
                if ($session && ! in_array($session['key'], $booked, true) && $rules->checkSession($session['start']) === null) {
                    $sessions[] = $session;
                }
            }
        }

        return $sessions;
    }

    /**
     * The session for a key like "32_2026-11-03", checked for this rep.
     *
     * @return array{0: ?array, 1: ?string} [session, problem]
     */
    public function findSession(User $rep, ?string $key, BookingRules $rules): array
    {
        $notYours = 'Pick one of your classes from the list.';
        if (! preg_match('/^(\d+)_(\d{4}-\d{2}-\d{2})$/', (string) $key, $m)) {
            return [null, $notYours];
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $m[2]);
        $class = ClassSchedule::with(['course', 'room', 'term'])->where('entry_type', 'Class')->find($m[1]);
        $session = $class && $day && $day->toDateString() === $m[2] ? $this->session($class, $day) : null;
        if (! $session || $class->section_id !== $rep->section_id) {
            return [null, $notYours];
        }

        if ($problem = $rules->checkSession($session['start'])) {
            return [null, $problem];
        }
        if ($this->sessionBooked($class->id, $day)) {
            return [null, 'This class session is already booked.'];
        }

        return [$session, null];
    }

    /**
     * Each room with what's in its way from $from to $to on $day (null = free).
     *
     * @return list<array{room: Room, clash: ?array}>
     */
    public function rooms(Collection $rooms, CarbonImmutable $day, int $from, int $to, User $viewer): array
    {
        $busy = $this->availability->busy($rooms->pluck('id'), $day, $viewer);

        return $rooms->map(fn ($room) => [
            'room' => $room,
            'clash' => TimeSlots::firstClash($busy[$room->id] ?? [], $from, $to),
        ])->all();
    }

    /** Another booking holding $roomId between $start and $end, or null (used for a class's own room) */
    public function roomTaken(int $roomId, CarbonImmutable $start, CarbonImmutable $end): ?Reservation
    {
        return Reservation::blocking()->where('room_id', $roomId)->overlapping($start, $end)->first();
    }

    /** The department's equipment by category, each item with ->free = how many are free from $start to $end */
    public function equipment(Department $department, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $categories = EquipmentCategory::with(['equipment' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
            ->where('department_id', $department->id)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($category) => $category->equipment->isNotEmpty())
            ->values();

        $inUse = $this->inUse($categories->flatMap->equipment->pluck('id')->all(), $start, $end);
        foreach ($categories as $category) {
            foreach ($category->equipment as $item) {
                $item->free = max(0, $item->total_qty - ($inUse[$item->id] ?? 0));
            }
        }

        return $categories;
    }

    /**
     * Saves a booking. Everything is checked again inside a transaction, with the class, room and
     * equipment rows locked first, so two people can't take the same thing at the same moment.
     *
     * $b: user, session (or null), day, start, end, room (or null), items [equipment id => qty],
     *     other_items, purpose, rules
     */
    public function place(array $b): Reservation
    {
        return DB::transaction(function () use ($b) {
            // 1. Lock, always in this order: class, room, equipment
            if ($b['session']) {
                ClassSchedule::whereKey($b['session']['class']->id)->lockForUpdate()->first();
            }
            if ($b['room']) {
                Room::whereKey($b['room']->id)->lockForUpdate()->first();
            }
            $equipment = $b['items']
                ? Equipment::whereKey(array_keys($b['items']))->orderBy('id')->lockForUpdate()->get()->keyBy('id')
                : collect();

            // 2. Check again, now that nobody else can book them until we're done
            if ($b['session'] && $this->sessionBooked($b['session']['class']->id, $b['day'])) {
                throw ValidationException::withMessages(['when' => 'Someone just booked this class session.']);
            }
            if ($b['room']) {
                $this->checkRoom($b);
            }
            $inUse = $this->inUse(array_keys($b['items']), $b['start'], $b['end']);
            foreach ($b['items'] as $id => $qty) {
                $free = $equipment[$id]->total_qty - ($inUse[$id] ?? 0);
                if ($qty > $free) {
                    throw ValidationException::withMessages(['qty' => $free > 0
                        ? 'Only '.$free.' × '.$equipment[$id]->name.' '.($free === 1 ? 'is' : 'are').' free then.'
                        : 'No '.$equipment[$id]->name.' is free then.']);
                }
            }

            // 3. Save the booking and its items
            $reservation = Reservation::create([
                'user_id' => $b['user']->id,
                'room_id' => $b['room']?->id,
                'class_schedule_id' => $b['session']['class']->id ?? null,
                'purpose' => $b['purpose'],
                'start_time' => $b['start'],
                'end_time' => $b['end'],
                'status' => $b['rules']->needsApproval ? 'Pending' : 'Confirmed',
                'other_items' => $b['other_items'],
            ]);
            $reservation->equipment()->attach(
                collect($b['items'])->map(fn ($qty) => ['qty_requested' => $qty])->all()
            );

            return $reservation;
        }, 3);   // try again up to 3 times if MariaDB reports a deadlock
    }

    /**
     * Lab staff move an approved booking to another day, time and/or room (with a reason).
     * Like place(): checked again inside a transaction with the booking, room and items locked.
     * Throws a ValidationException (error bag 'move') when the room or an item isn't free then.
     */
    public function move(Reservation $booking, User $staff, CarbonImmutable $day, int $from, int $to, ?Room $room, string $reason): void
    {
        DB::transaction(function () use ($booking, $staff, $day, $from, $to, $room, $reason) {
            // 1. Lock: the booking, then the room, then its items
            $fresh = Reservation::whereKey($booking->id)->lockForUpdate()->first();
            if ($fresh->status !== 'Confirmed') {
                throw ValidationException::withMessages(['move' => 'This booking just changed: it’s now '
                    .strtolower($fresh->statusLabel()).'.'])->errorBag('move');
            }
            if ($room) {
                Room::whereKey($room->id)->lockForUpdate()->first();
            }
            $items = $booking->equipment()->get();
            Equipment::whereKey($items->pluck('id'))->orderBy('id')->lockForUpdate()->get();

            $start = $day->addMinutes($from);
            $end = $day->addMinutes($to);

            // 2. The room must be free then, not counting this booking or its own class
            if ($room) {
                $busy = array_filter(
                    $this->availability->busy(collect([$room->id]), $day, $staff)[$room->id] ?? [],
                    fn ($item) => $item['reservation_id'] !== $booking->id
                        && ! ($booking->class_schedule_id && $item['schedule_id'] === $booking->class_schedule_id),
                );
                if ($clash = TimeSlots::firstClash($busy, $from, $to)) {
                    throw ValidationException::withMessages(['room' => $room->name.' is busy then: '.$clash['label']
                        .' ('.TimeSlots::span($clash['start'], $clash['end']).').'])->errorBag('move');
                }
            }

            // 3. Enough of each item then, not counting this booking's own
            $inUse = $this->inUse($items->pluck('id')->all(), $start, $end, $booking->id);
            foreach ($items as $item) {
                $free = $item->total_qty - ($inUse[$item->id] ?? 0);
                if ($item->pivot->qty_requested > $free) {
                    throw ValidationException::withMessages(['items' => 'Only '.max(0, $free).' × '.$item->name
                        .' '.($free === 1 ? 'is' : 'are').' free then; this booking has '.$item->pivot->qty_requested.'.'])->errorBag('move');
                }
            }

            // 4. Save, and write down what changed
            $old = ['room' => $booking->room?->name, 'start' => $booking->start_time->format('Y-m-d H:i'), 'end' => $booking->end_time->format('Y-m-d H:i')];
            $fresh->update(['room_id' => $room?->id, 'start_time' => $start, 'end_time' => $end]);
            OverrideLog::create([
                'reservation_id' => $booking->id,
                'changed_by' => $staff->id,
                'action' => 'Rescheduled',
                'old_value' => $old,
                'new_value' => ['room' => $room?->name, 'start' => $start->format('Y-m-d H:i'), 'end' => $end->format('Y-m-d H:i')],
                'reason' => $reason,
            ]);
        }, 3);
    }

    /** Throws if the room is taken (a class or a booking) during the booking */
    private function checkRoom(array $b): void
    {
        $room = $b['room'];
        if ($b['session']) {
            // The class itself holds its room, so only another booking can be in the way
            if ($this->roomTaken($room->id, $b['start'], $b['end'])) {
                throw ValidationException::withMessages(['room' => $room->name.' is already booked during your class.']);
            }

            return;
        }

        $busy = $this->availability->busy(collect([$room->id]), $b['day'], $b['user']);
        $clash = TimeSlots::firstClash($busy[$room->id] ?? [], $this->minutes($b['start']), $this->minutes($b['end']));
        if ($clash) {
            throw ValidationException::withMessages(['room' => $room->name.' is busy then: '.$clash['label']
                .' ('.TimeSlots::span($clash['start'], $clash['end']).'). Pick another room.']);
        }
    }

    /** One class meeting on $day, if the class meets that day and the semester is on */
    private function session(ClassSchedule $class, CarbonImmutable $day): ?array
    {
        $date = $day->toDateString();
        if ($day->format('D') !== $class->day_of_week
            || $date < $class->term->start_date->toDateString()
            || $date > $class->term->end_date->toDateString()) {
            return null;
        }

        $from = DepartmentConfig::minutes($class->start_time);
        $to = DepartmentConfig::minutes($class->end_time);

        return [
            'key' => $class->id.'_'.$date,
            'class' => $class,
            'day' => $day,
            'from' => $from,
            'to' => $to,
            'start' => $day->addMinutes($from),
            'end' => $day->addMinutes($to),
        ];
    }

    private function sessionBooked(int $classId, CarbonImmutable $day): bool
    {
        return Reservation::blocking()
            ->where('class_schedule_id', $classId)
            ->where('start_time', '>=', $day)
            ->where('start_time', '<', $day->addDay())
            ->exists();
    }

    /**
     * How many of each item other bookings hold between $start and $end ($except: leave that booking out).
     * Same rule as rooms: Pending, Confirmed and InUse bookings that overlap.
     *
     * @return array<int, int> equipment id => quantity
     */
    private function inUse(array $equipmentIds, CarbonImmutable $start, CarbonImmutable $end, ?int $except = null): array
    {
        if (! $equipmentIds) {
            return [];
        }

        return DB::table('reservation_equipment as re')
            ->join('reservations as r', 'r.id', '=', 're.reservation_id')
            ->whereIn('r.status', Reservation::BLOCKING_STATUSES)
            ->where('r.start_time', '<', $end)
            ->where('r.end_time', '>', $start)
            ->when($except, fn ($q) => $q->where('r.id', '!=', $except))    // a booking being moved doesn't count against itself
            ->whereIn('re.equipment_id', $equipmentIds)
            ->groupBy('re.equipment_id')
            ->selectRaw('re.equipment_id, SUM(re.qty_requested) AS qty')
            ->pluck('qty', 'equipment_id')
            ->map(fn ($qty) => (int) $qty)
            ->all();
    }

    private function minutes(CarbonImmutable $time): int
    {
        return $time->hour * 60 + $time->minute;
    }
}
