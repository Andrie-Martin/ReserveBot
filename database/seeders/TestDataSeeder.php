<?php

namespace Database\Seeders;

use App\Models\ClassSchedule;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Test accounts and three sample reservations, for trying the site.
 * Don't run this on the real (production) database.
 *
 * All test accounts use the password:  Password123!
 */
class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'admin@reservebot.test')->exists()) {
            $this->command?->warn('The test accounts are already in the database. Nothing was added.');

            return;
        }

        $bscpe = Department::where('slug', 'bscpe')->firstOrFail();

        $admin = $this->account($bscpe, 'BSCpE Office Admin', 'admin@reservebot.test', 'admin');
        $this->account($bscpe, 'Lab Assistant', 'staff@reservebot.test', 'lab_staff');
        $bea = $this->account($bscpe, 'Bea Prieto', 'rep1@reservebot.test', 'rep', 'BSCPE 4-5');
        $maria = $this->account($bscpe, 'Maria Santos', 'rep2@reservebot.test', 'rep', 'BSCPE 3-1');

        // Dated relative to today, so they stay useful
        $nextTuesday = today()->next(Carbon::TUESDAY);
        $nextMonday = today()->next(Carbon::MONDAY);
        $nextThursday = today()->next(Carbon::THURSDAY);

        // (a) Bea (BSCPE 4-5) books next Tuesday's CMPE 451 Introduction to Cybersecurity class. Approved.
        $class = ClassSchedule::where('day_of_week', 'Tue')
            ->whereRelation('section', 'name', 'BSCPE 4-5')
            ->whereRelation('course', 'code', 'CMPE 451')
            ->firstOrFail();

        $booking = Reservation::create([
            'user_id' => $bea->id,
            'room_id' => $class->room_id,
            'class_schedule_id' => $class->id,
            'purpose' => 'Lecture with slides',
            'start_time' => $nextTuesday->copy()->setTimeFromTimeString($class->start_time),
            'end_time' => $nextTuesday->copy()->setTimeFromTimeString($class->end_time),
            'status' => 'Confirmed',
            'reviewed_by' => $admin->id,
        ]);
        $booking->equipment()->attach(
            $this->items($bscpe, ['Projector', 'Projector Power Cord', 'HDMI Cable']),
            ['qty_requested' => 1],
        );

        // (b) Maria (BSCPE 3-1) asks for a free slot for a make-up class. Waiting for approval.
        $makeUp = Reservation::create([
            'user_id' => $maria->id,
            'room_id' => Room::where('name', 'CEA316')->value('id'),
            'purpose' => 'Make-up class',
            'start_time' => $nextMonday->copy()->setTime(8, 0),
            'end_time' => $nextMonday->copy()->setTime(10, 0),
            'status' => 'Pending',
            'other_items' => 'Bluetooth speaker x1',
        ]);
        $makeUp->equipment()->attach($this->items($bscpe, ['Extension Cord']), ['qty_requested' => 2]);

        // (c) Bea borrows equipment only (no room) for next Thursday's CMPE 451 class. Waiting for approval.
        $thursdayClass = ClassSchedule::where('day_of_week', 'Thu')
            ->whereRelation('section', 'name', 'BSCPE 4-5')
            ->whereRelation('course', 'code', 'CMPE 451')
            ->firstOrFail();

        $borrow = Reservation::create([
            'user_id' => $bea->id,
            'room_id' => null,
            'class_schedule_id' => $thursdayClass->id,
            'purpose' => 'Lab activity',
            'start_time' => $nextThursday->copy()->setTimeFromTimeString($thursdayClass->start_time),
            'end_time' => $nextThursday->copy()->setTimeFromTimeString($thursdayClass->end_time),
            'status' => 'Pending',
        ]);
        $borrow->equipment()->attach($this->items($bscpe, ['Digital Multi-Tester', 'Power Supply']), ['qty_requested' => 2]);

        $this->command?->info('Test data: 4 accounts (password Password123!), 3 sample reservations');
    }

    private function account(Department $department, string $name, string $email, string $role, ?string $section = null): User
    {
        return User::create([
            'department_id' => $department->id,
            'section_id' => $section ? Section::where('name', $section)->value('id') : null,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'password' => 'Password123!',   // hashed automatically by the User model
        ]);
    }

    /** IDs of this department's equipment with these names */
    private function items(Department $department, array $names): array
    {
        return Equipment::whereIn('name', $names)
            ->whereRelation('category', 'department_id', $department->id)
            ->pluck('id')
            ->all();
    }
}
