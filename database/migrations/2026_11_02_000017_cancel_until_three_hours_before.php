<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6: reps can cancel a booking until 3 hours before it starts (it was 1 hour).
 * New departments get 3 hours; departments still on the old 1-hour default move to 3 hours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('department_configs', function (Blueprint $table) {
            $table->integer('cancel_cutoff_minutes')->default(180)->change();
        });

        DB::table('department_configs')->where('cancel_cutoff_minutes', 60)->update(['cancel_cutoff_minutes' => 180]);
    }

    public function down(): void
    {
        Schema::table('department_configs', function (Blueprint $table) {
            $table->integer('cancel_cutoff_minutes')->default(60)->change();
        });

        DB::table('department_configs')->where('cancel_cutoff_minutes', 180)->update(['cancel_cutoff_minutes' => 60]);
    }
};
