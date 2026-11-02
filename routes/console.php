<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Phase 10: reminder and "is your class face-to-face?" emails, checked every minute.
// They only go out while `php artisan schedule:work` is running (Docker: the scheduler container).
Schedule::command('reminders:send')->everyMinute()->withoutOverlapping(10);
