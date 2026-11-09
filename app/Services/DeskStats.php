<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The three counts on the lab staff and admin dashboards. Read-only, and always limited to the viewer's own department
 * (through the person who made the booking, the same rule the Requests and Today pages use).
 */
class DeskStats
{
    /** @return list<array{label: string, value: int, note: string}> */
    public static function for(User $user): array
    {
        $waiting = Reservation::waitingCountFor($user);

        // Approved or in-use bookings starting today. Waiting, done and cancelled ones are left out on purpose:
        // the Today page lists every status, so the label says what is counted.
        $today = Reservation::whereIn('status', ['Confirmed', 'InUse'])
            ->whereDate('start_time', today())
            ->whereRelation('user', 'department_id', $user->department_id)
            ->count();

        return [
            ['label' => $waiting === 1 ? 'Request waiting for approval' : 'Requests waiting for approval', 'value' => $waiting, 'note' => 'Room and equipment requests not yet decided'],
            ['label' => $today === 1 ? 'Booking approved or in use today' : 'Bookings approved or in use today', 'value' => $today, 'note' => 'Starting today; waiting and finished excluded'],
            ['label' => 'Equipment units in use', 'value' => self::equipmentUnitsInUse($user), 'note' => 'Listed items handed out and not yet returned'],
        ];
    }

    /**
     * Units of listed equipment on bookings that staff have handed out and not yet marked returned (status "In use now").
     * It is a count of units (the quantities requested), not of bookings. Items typed in as free text have no quantity
     * and are not counted.
     */
    public static function equipmentUnitsInUse(User $user): int
    {
        return (int) DB::table('reservation_equipment')
            ->join('reservations', 'reservations.id', '=', 'reservation_equipment.reservation_id')
            ->join('users', 'users.id', '=', 'reservations.user_id')
            ->where('reservations.status', 'InUse')
            ->where('users.department_id', $user->department_id)
            ->sum('reservation_equipment.qty_requested');
    }
}
