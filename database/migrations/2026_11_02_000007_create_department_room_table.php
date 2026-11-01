<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Which rooms each department can book (many-to-many)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_room', function (Blueprint $table) {
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained();
            $table->primary(['department_id', 'room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_room');
    }
};
