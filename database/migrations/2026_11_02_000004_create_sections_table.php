<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blocks / class sections, e.g. 'BSCPE 4-2', 'BSME 2-2' (shared by every department)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
            $table->string('program', 10);                     // e.g. 'BSCPE', 'BSME'
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
