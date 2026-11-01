<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Semester dates, shared by every department.
// A class schedule only blocks rooms between start_date and end_date.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();              // e.g. '1st Semester AY 2026-2027'
            $table->date('start_date');
            $table->date('end_date');
        });

        DB::statement('ALTER TABLE terms ADD CONSTRAINT chk_terms_dates CHECK (end_date > start_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
