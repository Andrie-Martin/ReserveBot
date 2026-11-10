<?php

namespace App\Support;

use App\Models\ClassSchedule;
use App\Models\Equipment;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * When a room or an equipment item may be deleted for real.
 *
 * Deleting is only for a record entered by mistake: one that nothing has ever pointed at. Anything with history (a booking of
 * any status, a class in the timetable, another department that uses it) keeps its record, because bookings, hand-out history and
 * the timetable refer to it. Those are turned off instead (they stay in the lists, can't be booked, and old bookings keep them).
 * The database also refuses (restrict foreign keys, no cascade), so a booking that slips in at the same moment can't be orphaned.
 *
 * Every method returns a list of plain-language reasons; an empty list means "safe to delete".
 */
final class ResourceDeletion
{
    public const TURN_OFF_ADVICE = 'Turn it off instead: untick “Can be booked” and save. It stays in the lists and old bookings keep it.';

    /** MariaDB/MySQL error numbers: a foreign key refused the change (1451 parent row is referenced, 1452 child row has no parent, 1216/1217 older wording). */
    private const FOREIGN_KEY_ERRORS = [1451, 1452, 1216, 1217];

    /** MariaDB/MySQL error numbers: the database was too busy to finish (1205 lock wait timeout, 1213 deadlock). */
    private const BUSY_ERRORS = [1205, 1213];

    /**
     * What to tell the person when the database refuses a delete, and a log line for whoever runs the system.
     *
     * Only a foreign-key refusal means "something started using it"; a busy database and anything else are different problems
     * and are worded so. Nothing was changed in any case (the delete runs in one transaction). The technical message goes to the
     * log only, never to the page.
     *
     * @param  array<string, mixed>  $context  ids for the log line
     */
    public static function messageForFailure(QueryException $e, string $name, array $context = []): string
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        $context += ['mysql_error' => $code, 'sqlstate' => $e->errorInfo[0] ?? null];

        if (in_array($code, self::FOREIGN_KEY_ERRORS, true)) {
            Log::warning('Delete refused by a foreign key', $context);

            return 'Something started using '.$name.' while you were deleting it, so it was kept. '.self::TURN_OFF_ADVICE;
        }
        if (in_array($code, self::BUSY_ERRORS, true)) {
            Log::warning('Delete skipped because the database was busy', $context);

            return 'ReserveBot was busy and '.$name.' was not deleted. Nothing was changed. Wait a moment and try again.';
        }
        Log::error('Delete failed with an unexpected database error', $context + ['exception' => $e]);

        return $name.' could not be deleted because of a problem on our side. Nothing was changed. Try again, and tell whoever runs ReserveBot if it keeps happening.';
    }

    /** For a list row that was loaded withCount(['departments', 'reservations', 'classSchedules']): is Delete worth offering? The real check is roomBlockers(). */
    public static function roomLooksDeletable(Room $room): bool
    {
        return (int) $room->departments_count <= 1 && (int) $room->reservations_count === 0 && (int) $room->class_schedules_count === 0;
    }

    /** For a list row loaded withCount('reservations'). */
    public static function equipmentLooksDeletable(Equipment $item): bool
    {
        return (int) $item->reservations_count === 0;
    }

    /**
     * @param  int  $departmentId  the department doing the deleting (the room must be on its list)
     * @return list<string>
     */
    public static function roomBlockers(Room $room, int $departmentId): array
    {
        $reasons = [];

        $others = $room->departments()->where('departments.id', '!=', $departmentId)->pluck('name');
        if ($others->isNotEmpty()) {
            $reasons[] = 'It’s shared with '.$others->join(', ', ' and ').', which still use it.';
        }

        $bookings = Reservation::where('room_id', $room->id)->count();
        if ($bookings > 0) {
            $reasons[] = $bookings === 1
                ? 'It has 1 booking on record (past, upcoming or closed). Deleting would erase that history.'
                : 'It has '.$bookings.' bookings on record (past, upcoming or closed). Deleting would erase that history.';
        }

        $classes = ClassSchedule::where('room_id', $room->id)->count();
        if ($classes > 0) {
            $reasons[] = $classes === 1
                ? 'It’s used by 1 class in the timetable (this or an earlier semester).'
                : 'It’s used by '.$classes.' classes in the timetable (this or an earlier semester).';
        }

        return $reasons;
    }

    /** @return list<string> */
    public static function equipmentBlockers(Equipment $item): array
    {
        $bookings = DB::table('reservation_equipment')->where('equipment_id', $item->id)->count();
        if ($bookings === 0) {
            return [];
        }

        return [$bookings === 1
            ? 'It’s on 1 booking on record (past, upcoming or closed). Deleting would erase that history.'
            : 'It’s on '.$bookings.' bookings on record (past, upcoming or closed). Deleting would erase that history.'];
    }
}
