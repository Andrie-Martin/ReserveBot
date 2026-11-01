<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// A view (saved query) that shows the timetable with names instead of IDs.
// Handy in phpMyAdmin: open timetable_view like a table.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW timetable_view AS
            SELECT cs.id         AS schedule_id,
                   t.name        AS term,
                   d.slug        AS department,
                   r.name        AS room,
                   cs.day_of_week,
                   cs.start_time,
                   cs.end_time,
                   cs.entry_type,
                   c.code        AS course_code,
                   c.title       AS course_title,
                   s.name        AS section,
                   cs.instructor,
                   cs.note
            FROM class_schedules cs
            JOIN terms t        ON t.id = cs.term_id
            JOIN departments d  ON d.id = cs.department_id
            JOIN rooms r        ON r.id = cs.room_id
            LEFT JOIN courses c  ON c.id = cs.course_id
            LEFT JOIN sections s ON s.id = cs.section_id
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS timetable_view');
    }
};
