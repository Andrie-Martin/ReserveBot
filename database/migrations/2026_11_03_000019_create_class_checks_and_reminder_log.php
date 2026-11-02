<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 10: reminder emails and the "is your class face-to-face?" check.
//  - class_checks: one row per class meeting (a timetable class on one date) that someone answered
//    or was asked about. 'online' frees the class's room; no answer frees it when the 5-hour email
//    goes out, but only if the block's rep got the day-before email.
//  - reminder_log: which emails went out, so nobody gets the same one twice.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_schedule_id')->constrained()->cascadeOnDelete();
            $table->date('class_date');
            $table->enum('answer', ['f2f', 'online'])->nullable();       // empty = nobody answered yet
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('answered_via', ['email', 'site', 'staff', 'booking'])->nullable();
            $table->dateTime('answered_at')->nullable();
            $table->dateTime('asked_at')->nullable();                     // when the first "face-to-face?" email went out
            $table->unique(['class_schedule_id', 'class_date']);
        });

        Schema::create('reminder_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();             // who got it
            $table->foreignId('reservation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('class_schedule_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('class_date')->nullable();
            $table->enum('mark', ['day', '5h', '1h']);                     // 1 day, 5 hours or 1 hour before
            $table->dateTime('sent_at');
            // Empty (NULL) columns never clash, so each rule only covers its own kind of email
            $table->unique(['user_id', 'reservation_id', 'mark']);
            $table->unique(['user_id', 'class_schedule_id', 'class_date', 'mark']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_log');
        Schema::dropIfExists('class_checks');
    }
};
