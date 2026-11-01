<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Room bookings
//   Statuses that hold the slot: Pending, Confirmed, InUse
//   class_schedule_id set  = booking a session of the rep's own scheduled class
//   class_schedule_id NULL = extra booking (make-up class, meeting); purpose required
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('room_id')->constrained();
            $table->foreignId('class_schedule_id')->nullable()->constrained();
            $table->string('purpose', 150)->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->enum('status', ['Pending', 'Confirmed', 'Rejected', 'Cancelled', 'InUse', 'Completed', 'NoShow'])
                ->default('Pending');
            $table->string('status_reason')->nullable();       // why it was rejected or cancelled
            $table->text('other_items')->nullable();           // items not on the list, e.g. 'Item x2'
            $table->foreignId('reviewed_by')->nullable()->constrained('users');  // admin who approved/rejected
            $table->timestamps();

            $table->index(['room_id', 'start_time', 'end_time']);
            $table->index(['user_id', 'start_time']);
            $table->index('status');
        });

        DB::statement('ALTER TABLE reservations
            ADD CONSTRAINT chk_reservations_time    CHECK (end_time > start_time),
            ADD CONSTRAINT chk_reservations_purpose CHECK (class_schedule_id IS NOT NULL OR purpose IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
