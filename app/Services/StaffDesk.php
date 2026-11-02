<?php

namespace App\Services;

use App\Models\OverrideLog;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Phase 7: lab staff (and admins) change a booking's status during its day: hand out, returned,
 * no-show, or cancel. Each change is written to override_logs (who, when, what, why).
 */
class StaffDesk
{
    /**
     * Moves $booking from status $from to $to, only if it's still $from (so if two people press
     * a button at the same moment, the first one counts). Returns false if it had already changed.
     *
     * $statusReason goes on the booking (the rep sees it); $logReason goes in the change log.
     */
    public function setStatus(Reservation $booking, string $from, string $to, User $staff, string $logReason, ?string $statusReason = null): bool
    {
        return DB::transaction(function () use ($booking, $from, $to, $staff, $logReason, $statusReason) {
            $changed = Reservation::whereKey($booking->id)
                ->where('status', $from)
                ->update(['status' => $to, 'status_reason' => $statusReason, 'updated_at' => now()]);

            if ($changed) {
                OverrideLog::create([
                    'reservation_id' => $booking->id,
                    'changed_by' => $staff->id,
                    'action' => $to === 'Cancelled' ? 'Cancelled' : 'StatusChanged',
                    'old_value' => ['status' => $from],
                    'new_value' => ['status' => $to],
                    'reason' => $logReason,
                ]);
            }

            return (bool) $changed;
        });
    }
}
