<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Booking rules, one row per department (1:1 with departments)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_configs', function (Blueprint $table) {
            $table->foreignId('department_id')->primary()->constrained()->cascadeOnDelete();
            $table->boolean('requires_approval')->default(true);
            $table->set('open_days', ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])
                ->default('Mon,Tue,Wed,Thu,Fri,Sat');
            $table->time('business_hours_start')->default('07:30:00');
            $table->time('business_hours_end')->default('21:00:00');
            $table->integer('slot_minutes')->default(30);
            $table->integer('min_booking_minutes')->default(30);
            $table->integer('max_booking_minutes')->default(180);  // for bookings NOT tied to a scheduled class
            $table->integer('max_days_ahead')->default(14);
            $table->integer('min_notice_minutes')->default(60);
            $table->integer('cancel_cutoff_minutes')->default(60);
            $table->integer('no_show_grace_minutes')->default(15);
        });

        DB::statement('ALTER TABLE department_configs
            ADD CONSTRAINT chk_department_configs_hours CHECK (business_hours_end > business_hours_start)');
    }

    public function down(): void
    {
        Schema::dropIfExists('department_configs');
    }
};
