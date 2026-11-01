<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Every manual change by lab staff or an admin, with the reason
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('override_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained();
            $table->foreignId('changed_by')->constrained('users');
            $table->enum('action', ['Rescheduled', 'Cancelled', 'StatusChanged', 'EquipmentUpdated']);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->string('reason');
            $table->timestamp('created_at')->useCurrent();     // when the change was made
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('override_logs');
    }
};
