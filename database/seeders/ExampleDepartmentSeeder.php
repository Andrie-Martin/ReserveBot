<?php

namespace Database\Seeders;

use App\Models\ClassSchedule;

/**
 * TEMPLATE for adding another department.
 *
 * DON'T RUN THIS FILE AS-IS. To add a department:
 *   1. Copy this file in the same folder, e.g. as EceSeeder.php,
 *      and change "class ExampleDepartmentSeeder" below to "class EceSeeder".
 *   2. Replace every line marked EXAMPLE with the department's real data.
 *   3. Run:  php artisan db:seed --class=EceSeeder
 *
 * It only adds rows. It never changes another department's data (except the
 * optional clean-up at the bottom, which is commented out). Running it twice
 * does nothing the second time, because the slug already exists.
 */
class ExampleDepartmentSeeder extends DepartmentSeeder
{
    protected function department(): array
    {
        return [
            'name' => 'Electronics Engineering Department',  // EXAMPLE
            'slug' => 'ece',                                 // EXAMPLE (short, lowercase, unique)
            'contact_email' => null,                         // EXAMPLE
        ];
    }

    protected function bookingRules(): array
    {
        // Leave a rule out to keep its default (see the department_configs migration)
        return [
            'requires_approval' => true,                     // EXAMPLE
            'open_days' => 'Mon,Tue,Wed,Thu,Fri,Sat',        // EXAMPLE
            'business_hours_start' => '07:30',               // EXAMPLE
            'business_hours_end' => '21:00',                 // EXAMPLE
        ];
    }

    protected function term(): array
    {
        // Reused if another department already added this semester
        return ['name' => '1st Semester AY 2026-2027', 'start_date' => '2026-09-01', 'end_date' => '2027-01-31'];
    }

    protected function rooms(): array
    {
        // List EVERY room the department uses, including shared ones. A room that
        // already exists (like CEA302) is reused, not duplicated, so both
        // departments' bookings are checked against each other.
        // [room, building, capacity]
        return [
            ['CEA302', 'CEA Building', null],                // EXAMPLE (shared with BSCpE)
            ['CEA401', 'CEA Building', 40],                  // EXAMPLE (new room)
        ];
    }

    protected function timetable(): array
    {
        // [day, room, start, end, course code, course title, section, instructor]
        // Blocked time (room not available): [day, room, start, end, 'BLOCKED', reason]
        return [
            ['Mon', 'CEA302', '07:30', '10:30', 'ECEN 101', 'Example Course A', 'BSECE 1-1', 'A. Example'],  // EXAMPLE
            ['Mon', 'CEA302', '10:30', '13:30', 'ECEN 102', 'Example Course B', 'BSECE 1-2', 'B. Example'],  // EXAMPLE
            ['Tue', 'CEA401', '07:30', '10:30', 'ECEN 101', 'Example Course A', 'BSECE 1-1', 'A. Example'],  // EXAMPLE
            ['Sat', 'CEA401', '07:30', '21:00', 'BLOCKED', 'Closed on Saturdays'],                           // EXAMPLE
        ];
    }

    protected function equipment(): array
    {
        // [category => [item => how many the department owns]], in the request form's order
        return [
            'Audiovisual Equipment' => [
                'Projector' => 3,                            // EXAMPLE
                'HDMI Cable' => 5,                           // EXAMPLE
            ],
            'Lab Instruments' => [
                'Function Generator' => 8,                   // EXAMPLE
            ],
        ];
    }

    protected function accounts(): array
    {
        // First accounts: at least one admin and one lab staff.
        // [name, email, role]. Password: Password123! (the site asks for a new one at the first login)
        return [
            ['ECE Office Admin', 'admin.ece@reservebot.test', 'admin'],         // EXAMPLE
            ['ECE Lab Assistant', 'staff.ece@reservebot.test', 'lab_staff'],    // EXAMPLE
        ];
    }

    public function run(): void
    {
        parent::run();

        // OPTIONAL clean-up for shared rooms.
        // BSCpE's timetable marks CEA302 as "Used by ECE" on Mon, Wed and Fri.
        // Once ECE's real classes are in, those placeholders can go, but ONLY if
        // ECE's own timetable covers all the time ECE needs the room. Otherwise
        // the uncovered hours become bookable by BSCpE.
        //
        // ClassSchedule::where('entry_type', 'Blocked')
        //     ->where('note', 'like', 'Used by ECE%')
        //     ->whereRelation('room', 'name', 'CEA302')
        //     ->delete();
    }
}
