<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Phase 9: what the Information tab and the account rules need.
//  - school_id: PUP student number, e.g. 2021-00123-MN-0 (reps and lab staff), one account each
//  - photo_path: the profile photo, in storage/app/private/photos (never public)
//  - email_verified_at: when the emailed "connect your email" link was clicked
//  - must_change_password: set when an admin types a password; the user picks their own at the next login
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->string('school_id', 20)->nullable()->unique()->after('section_id');
            $table->string('photo_path')->nullable()->after('school_id');
        });

        // Accounts made before this update (the test accounts use made-up emails) count as connected
        DB::table('users')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['school_id']);
            $table->dropColumn(['email_verified_at', 'must_change_password', 'school_id', 'photo_path']);
        });
    }
};
