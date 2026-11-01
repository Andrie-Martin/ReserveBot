<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Rooms are building-wide and can be shared by departments (CEA302 is shared
// by BSCpE and ECE), so a room does NOT belong to one department.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();              // e.g. 'CEA300'
            $table->string('building', 60)->nullable();
            $table->integer('capacity')->nullable();           // NULL = not recorded yet
            $table->string('fixed_equipment_note', 200)->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
