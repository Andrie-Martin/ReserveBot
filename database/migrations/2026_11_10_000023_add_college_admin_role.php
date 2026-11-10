<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sandbox issue 8: the College admin (Option B of the multi-department discussion).
 *
 * A college-wide account that makes and looks after each department's Admin accounts. It belongs to no
 * department, so:
 *  - users.role gains 'college_admin'
 *  - users.department_id may be empty, but only for a College admin: everyone else still needs a department
 *    (CHECK chk_users_department).
 *
 * MariaDB only (like the other migrations that add a named CHECK constraint).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('rep', 'lab_staff', 'admin', 'college_admin') NOT NULL DEFAULT 'rep'");
        DB::statement('ALTER TABLE users MODIFY department_id BIGINT UNSIGNED NULL');
        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_department CHECK (
            (role = 'college_admin' AND department_id IS NULL) OR (role <> 'college_admin' AND department_id IS NOT NULL))");
    }

    public function down(): void
    {
        // The old table can't hold an account without a department
        $collegeAdmins = DB::table('users')->where('role', 'college_admin')->count();
        if ($collegeAdmins > 0) {
            throw new RuntimeException("Can't undo: {$collegeAdmins} College admin account(s) exist. Delete them first.");
        }

        DB::statement('ALTER TABLE users DROP CONSTRAINT chk_users_department');
        DB::statement('ALTER TABLE users MODIFY department_id BIGINT UNSIGNED NOT NULL');
        DB::statement("ALTER TABLE users MODIFY role ENUM('rep', 'lab_staff', 'admin') NOT NULL DEFAULT 'rep'");
    }
};
