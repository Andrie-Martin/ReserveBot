<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 (booking) changes to reservations:
 *  - room_id can be empty: an equipment-only booking doesn't hold a room
 *  - purpose (the reason) is required on every booking, class sessions included
 * Lab staff and admins both approve, so reviewed_by can be either.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Older class-session bookings had no reason. Give them one before the new rule is added.
        DB::table('reservations')
            ->where(fn ($q) => $q->whereNull('purpose')->orWhere('purpose', ''))
            ->update(['purpose' => 'Class session']);

        // Old rule: a class OR a purpose. New rule: always a purpose (added again below).
        DB::statement('ALTER TABLE reservations DROP CONSTRAINT chk_reservations_purpose');

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable()->change();
            $table->string('purpose', 150)->nullable(false)->change();
        });

        DB::statement("ALTER TABLE reservations ADD CONSTRAINT chk_reservations_purpose CHECK (purpose <> '')");
    }

    public function down(): void
    {
        // Equipment-only bookings have no room, so the old table can't hold them
        $noRoom = DB::table('reservations')->whereNull('room_id')->count();
        if ($noRoom > 0) {
            throw new RuntimeException("Can't undo: {$noRoom} equipment-only booking(s) have no room. Delete them first.");
        }

        DB::statement('ALTER TABLE reservations DROP CONSTRAINT chk_reservations_purpose');

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable(false)->change();
            $table->string('purpose', 150)->nullable()->change();
        });

        DB::statement('ALTER TABLE reservations
            ADD CONSTRAINT chk_reservations_purpose CHECK (class_schedule_id IS NOT NULL OR purpose IS NOT NULL)');
    }
};
