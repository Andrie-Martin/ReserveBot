<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 11: replacing the timetable (a new semester, or changes during one)
//   timetable_drafts:  a department's new timetable while it's being made, from an uploaded Excel or
//                      CSV file or on the site. Nothing else reads it until "Replace the timetable"
//                      saves it into class_schedules. One per department.
//   timetable_backups: a copy of the timetable in use, saved each time it's replaced, so it can be
//                      brought back. Rows keep names (room, course, block), not IDs.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('rows');                                   // [{id, line, day, start, end, room, type, code, title, block, instructor, note, notes}, ...]
            $table->unsignedInteger('next_id')->default(1);         // the id the next added row gets
            $table->enum('source', ['site', 'upload', 'backup']);   // how it started
            $table->string('source_name', 150)->nullable();         // the uploaded file's name, or which backup
            $table->boolean('edited')->default(false);              // changed on the site since
            $table->string('term_name', 60)->nullable();            // the semester it's for
            $table->date('term_start')->nullable();
            $table->date('term_end')->nullable();
            $table->unsignedInteger('version')->default(1);         // +1 on every change: "Replace" saves only what was checked
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('timetable_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('term_name', 60)->nullable();            // its semester then
            $table->date('term_start')->nullable();
            $table->date('term_end')->nullable();
            $table->json('rows');                                   // [{day, start, end, room, type, code, title, block, instructor, note}, ...]
            $table->unsignedSmallInteger('class_count');
            $table->unsignedSmallInteger('blocked_count');
            $table->string('reason', 200);                          // what replaced it, e.g. 'Replaced by the file “2nd sem.xlsx”'
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['department_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_backups');
        Schema::dropIfExists('timetable_drafts');
    }
};
