<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Block accounts (sandbox issue 6). A 'rep' account now belongs to a block, not to one person: the block's
 * representative is named on the account (users.name) and can be changed.
 *
 *  - rep_name: the representative when the booking was made, copied onto the booking so that changing the
 *    representative later doesn't rewrite who stood for the block on older bookings.
 *
 * Bookings that already exist get the account's current representative.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('rep_name', 100)->nullable()->after('user_id');
        });

        // A plain UPDATE ... (subquery) works on MariaDB and on SQLite alike
        DB::statement("UPDATE reservations SET rep_name = (SELECT name FROM users WHERE users.id = reservations.user_id AND users.role = 'rep')");
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('rep_name');
        });
    }
};
