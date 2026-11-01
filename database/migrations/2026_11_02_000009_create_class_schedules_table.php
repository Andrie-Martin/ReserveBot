<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The weekly timetable (from the department's room schedule)
//   entry_type 'Class'   = a scheduled class; only that section may book the slot
//   entry_type 'Blocked' = room not available (e.g. another department's day)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_id')->constrained();
            $table->foreignId('department_id')->constrained();     // department that manages this entry
            $table->foreignId('room_id')->constrained();
            $table->enum('day_of_week', ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('entry_type', ['Class', 'Blocked'])->default('Class');
            $table->foreignId('course_id')->nullable()->constrained();
            $table->foreignId('section_id')->nullable()->constrained();
            $table->string('instructor', 60)->nullable();
            $table->string('note', 150)->nullable();

            // Speeds up "what's in this room on this day?"
            $table->index(['term_id', 'room_id', 'day_of_week', 'start_time'], 'class_schedules_room_day_index');
        });

        DB::statement('ALTER TABLE class_schedules
            ADD CONSTRAINT chk_class_schedules_time  CHECK (end_time > start_time),
            ADD CONSTRAINT chk_class_schedules_class CHECK (entry_type = \'Blocked\' OR course_id IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedules');
    }
};
