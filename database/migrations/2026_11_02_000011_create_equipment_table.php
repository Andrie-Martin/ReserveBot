<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// total_qty is the whole stock; how many are free is worked out per time window
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('equipment_categories');  // department comes from the category
            $table->string('name', 60);
            $table->integer('total_qty')->default(0);
            $table->integer('max_per_request')->default(4);    // the request form allows x1 to x4
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unique(['category_id', 'name']);
        });

        DB::statement('ALTER TABLE equipment
            ADD CONSTRAINT chk_equipment_qty CHECK (total_qty >= 0 AND max_per_request > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
