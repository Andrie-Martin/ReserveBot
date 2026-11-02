<?php

namespace Database\Seeders;

use App\Models\ClassSchedule;
use App\Models\Course;
use App\Models\Department;
use App\Models\Room;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The steps every department goes through. Each department has its own
 * seeder (BscpeSeeder, or a copy of ExampleDepartmentSeeder) that only lists
 * its data. This class turns names into IDs, so nothing depends on
 * hard-coded IDs, and it only ever ADDS rows.
 */
abstract class DepartmentSeeder extends Seeder
{
    /** ['name' => ..., 'slug' => ..., 'contact_email' => ...] */
    abstract protected function department(): array;

    /** The semester the timetable is for: ['name' => ..., 'start_date' => ..., 'end_date' => ...] */
    abstract protected function term(): array;

    /** Every room the department uses, shared ones included: [[name, building, capacity], ...] */
    abstract protected function rooms(): array;

    /** One row per block on the room schedule (format: see BscpeSeeder) */
    abstract protected function timetable(): array;

    /** [category => [item => how many the department owns]], in the request form's order */
    abstract protected function equipment(): array;

    /** Booking rules that differ from the defaults in the department_configs migration */
    protected function bookingRules(): array
    {
        return [];
    }

    /** First accounts: [[name, email, role], ...]. They get the password Password123! and pick their own at the first login */
    protected function accounts(): array
    {
        return [];
    }

    public function run(): void
    {
        $info = $this->department();

        if (Department::where('slug', $info['slug'])->exists()) {
            $this->command?->warn("Department '{$info['slug']}' is already in the database. Nothing was added.");

            return;
        }

        // All or nothing: if any row fails, none of this department's rows are kept
        $department = DB::transaction(function () use ($info) {
            $department = Department::create($info);
            $department->config()->create($this->bookingRules());

            // Shared by every department: reused if another department already added it
            $term = Term::firstOrCreate(['name' => $this->term()['name']], $this->term());

            // Rooms are building-wide: a room another department already added is reused, not
            // duplicated, so both departments' bookings get checked against each other
            $roomIds = [];
            foreach ($this->rooms() as [$name, $building, $capacity]) {
                $roomIds[$name] = Room::firstOrCreate(['name' => $name], [
                    'building' => $building,
                    'capacity' => $capacity,
                ])->id;
            }
            $department->rooms()->attach(array_values($roomIds));

            foreach ($this->timetable() as $row) {
                ClassSchedule::create($this->timetableEntry($row, $department, $term, $roomIds));
            }

            $categoryOrder = 0;
            foreach ($this->equipment() as $categoryName => $items) {
                $category = $department->equipmentCategories()->create([
                    'name' => $categoryName,
                    'sort_order' => ++$categoryOrder,
                ]);
                $itemOrder = 0;
                foreach ($items as $itemName => $totalQty) {
                    $category->equipment()->create([
                        'name' => $itemName,
                        'total_qty' => $totalQty,
                        'sort_order' => ++$itemOrder,
                    ]);
                }
            }

            foreach ($this->accounts() as [$name, $email, $role]) {
                User::create([
                    'department_id' => $department->id,
                    'name' => $name,
                    'email' => Str::lower($email),
                    'role' => $role,
                    'password' => 'Password123!',   // hashed automatically by the User model
                    'must_change_password' => true, // they pick their own at the first login
                ]);
            }

            return $department;
        });

        // Check: compare these numbers with the lists in the seeder
        $entries = $department->classSchedules();
        $this->command?->info(sprintf(
            '%s: %d rooms, %d timetable rows (%d blocked), %d equipment items, %d accounts',
            $department->name,
            $department->rooms()->count(),
            $entries->count(),
            $department->classSchedules()->where('entry_type', 'Blocked')->count(),
            $department->equipmentCategories()->withCount('equipment')->get()->sum('equipment_count'),
            $department->users()->count(),
        ));
    }

    /** One timetable row -> the columns of a class_schedules row */
    private function timetableEntry(array $row, Department $department, Term $term, array $roomIds): array
    {
        [$day, $roomName, $start, $end, $code] = $row;

        if (! isset($roomIds[$roomName])) {
            throw new RuntimeException("Room {$roomName} is in the timetable but not in rooms(). Add it to rooms().");
        }

        $entry = [
            'term_id' => $term->id,
            'department_id' => $department->id,
            'room_id' => $roomIds[$roomName],
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
        ];

        if ($code === 'BLOCKED') {
            return $entry + ['entry_type' => 'Blocked', 'note' => $row[5] ?? null];
        }

        [, , , , , $title, $sectionName, $instructor] = $row + [5 => null, 6 => null, 7 => null];

        $section = $sectionName === null ? null : Section::firstOrCreate(
            ['name' => $sectionName],
            ['program' => Str::before($sectionName, ' ')],      // 'BSCPE 4-5' -> 'BSCPE'
        );

        return $entry + [
            'entry_type' => 'Class',
            'course_id' => Course::firstOrCreate(['code' => $code, 'title' => $title])->id,
            'section_id' => $section?->id,
            'instructor' => $instructor,
        ];
    }
}
