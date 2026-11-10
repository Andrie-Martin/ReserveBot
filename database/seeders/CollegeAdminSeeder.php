<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The TEST College admin account, for trying the site (sandbox issue 8). Don't run this on the real
 * (production) database: make a real College admin with `php artisan college-admin:make` instead.
 *
 *   college@reservebot.test   password: Password123!
 *
 * Like the other test accounts it counts as having connected its email and picked its own password, so it works
 * right away. DatabaseSeeder runs this for a new database. For a database that already has data, add it with:
 *
 *   php artisan db:seed --class=CollegeAdminSeeder
 *
 * Running it twice does nothing the second time.
 */
class CollegeAdminSeeder extends Seeder
{
    public const EMAIL = 'college@reservebot.test';

    public function run(): void
    {
        if (User::where('email', self::EMAIL)->exists()) {
            $this->command?->warn('The test College admin account is already in the database. Nothing was added.');

            return;
        }

        $admin = User::create([
            'department_id' => null,            // college-wide: no department
            'name' => 'CEA College Admin',
            'email' => self::EMAIL,
            'role' => 'college_admin',
            'password' => 'Password123!',       // hashed automatically by the User model
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->command?->info('Test College admin: '.self::EMAIL.' (password Password123!)');
    }
}
