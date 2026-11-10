<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Runs with:  php artisan db:seed
     * or, to rebuild the whole database:  php artisan migrate:fresh --seed
     */
    public function run(): void
    {
        $this->call([
            BscpeSeeder::class,      // the BSCpE department: rooms, timetable, equipment
            TestDataSeeder::class,   // test accounts and sample reservations (not for real use)
            CollegeAdminSeeder::class,  // the test College admin, who makes the departments' Admin accounts (not for real use)
        ]);
    }
}
