<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A course is identified by code AND title, because some codes are shared by
// different courses (e.g. CMPE 453 is both Software Processes and Database
// Implementation on the BSCpE timetable).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);                        // e.g. 'CMPE 453'
            $table->string('title', 100)->nullable();          // NULL when the timetable shows no title
            $table->unique(['code', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
