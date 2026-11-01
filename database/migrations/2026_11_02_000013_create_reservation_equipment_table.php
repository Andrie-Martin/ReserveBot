<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Equipment requested per reservation (many-to-many, with a quantity)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_equipment', function (Blueprint $table) {
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment');
            $table->integer('qty_requested');
            $table->primary(['reservation_id', 'equipment_id']);
        });

        DB::statement('ALTER TABLE reservation_equipment
            ADD CONSTRAINT chk_reservation_equipment_qty CHECK (qty_requested > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_equipment');
    }
};
