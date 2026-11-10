<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sandbox issue 7. The ID number leaves the profiles and is asked where it matters: when equipment is borrowed.
 *
 *  - reservations.borrower_name, borrower_id_number: who will borrow the equipment, typed on the booking form
 *    (required whenever a booking includes equipment; empty for a room-only booking).
 *  - users.school_id is removed. Before it goes, equipment bookings that already exist get the account's
 *    representative and School ID as their borrower, so nothing that was known is lost.
 *
 * MariaDB only (like the other reservations migrations): it adds a named CHECK constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('borrower_name', 100)->nullable()->after('other_items');
            $table->string('borrower_id_number', 20)->nullable()->after('borrower_name');     // e.g. 2021-00123-MN-0
        });

        // Equipment bookings made before this change: the account's own name and ID were what staff checked
        DB::statement('
            UPDATE reservations r
            JOIN users u ON u.id = r.user_id
            SET r.borrower_name = COALESCE(r.rep_name, u.name), r.borrower_id_number = u.school_id
            WHERE u.school_id IS NOT NULL
              AND (r.other_items IS NOT NULL OR EXISTS (SELECT 1 FROM reservation_equipment e WHERE e.reservation_id = r.id))
        ');

        // A borrower is a name and an ID number together, or neither
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT chk_reservations_borrower CHECK (
            (borrower_name IS NULL AND borrower_id_number IS NULL) OR (borrower_name IS NOT NULL AND borrower_id_number IS NOT NULL))');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['school_id']);
            $table->dropColumn('school_id');
        });
    }

    public function down(): void
    {
        // The ID numbers that were on the accounts can't be brought back; the column returns empty
        Schema::table('users', function (Blueprint $table) {
            $table->string('school_id', 20)->nullable()->unique()->after('section_id');
        });

        DB::statement('ALTER TABLE reservations DROP CONSTRAINT chk_reservations_borrower');
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['borrower_name', 'borrower_id_number']);
        });
    }
};
