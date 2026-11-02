<?php

namespace App\Console\Commands;

use App\Models\ClassCheck;
use App\Models\ClassSchedule;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\BookingReminder;
use App\Notifications\ClassReminder;
use App\Services\ClassChecks;
use App\Support\ClassCheckRules;
use App\Support\ReminderTimes;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Phase 10: sends the reminder emails that are due. routes/console.php runs it every minute
 * while `php artisan schedule:work` is running (Docker: the scheduler container).
 *
 *  - Approved bookings: a reminder to the rep who made it, 1 day, 5 hours and 1 hour before.
 *  - Timetable classes: the block's reps are asked "is it face-to-face?" (ClassReminder).
 *
 * Emails due from 10 PM to 6 AM go out at 9 PM the night before (App\Support\ReminderTimes).
 * reminder_log keeps each one from going out twice.
 */
class SendReminders extends Command
{
    protected $signature = 'reminders:send {--at= : Act as if it were this time, for testing, e.g. "2026-11-05 08:30"}';

    protected $description = 'Emails reps about their bookings and classes 1 day, 5 hours and 1 hour before';

    private int $sent = 0;

    private int $failed = 0;

    public function handle(ClassChecks $checks): int
    {
        $now = $this->option('at') ? CarbonImmutable::parse($this->option('at')) : CarbonImmutable::now();

        $this->classes($checks, $now);
        $this->bookings($now);

        if ($this->sent || $this->failed) {
            $this->info($now->format('Y-m-d H:i').': sent '.$this->sent.' email(s)'.($this->failed ? ', '.$this->failed.' could not be sent (see the log)' : '').'.');
        }

        return self::SUCCESS;
    }

    /** Timetable classes starting within the next day */
    private function classes(ClassChecks $checks, CarbonImmutable $now): void
    {
        $until = $now->addMinutes(ReminderTimes::MARKS['day'] + 1);
        for ($day = $now->startOfDay(); $day->lte($until); $day = $day->addDay()) {
            $classes = ClassSchedule::with(['course', 'room', 'section', 'term'])
                ->where('entry_type', 'Class')
                ->whereNotNull('room_id')
                ->whereNotNull('section_id')
                ->where('day_of_week', $day->format('D'))
                ->whereHas('term', fn ($term) => $term->where('start_date', '<=', $day->toDateString())->where('end_date', '>=', $day->toDateString()))
                ->get();
            if ($classes->isEmpty()) {
                continue;
            }

            $states = $checks->states($classes, $day, $now);
            foreach ($classes as $class) {
                [$start] = $checks->meeting($class, $day);
                $mark = ReminderTimes::due($start, $now);
                $state = $states[$class->id];
                // A meeting booked as a class session gets the booking's own reminders instead
                $kind = $mark && $state !== 'booked' ? ClassCheckRules::email($mark, $state) : null;
                if (! $kind) {
                    continue;
                }

                foreach ($checks->reps($class) as $rep) {
                    $done = DB::table('reminder_log')->where('user_id', $rep->id)->where('class_schedule_id', $class->id)
                        ->where('class_date', $day->toDateString())->where('mark', $mark)->exists();
                    if ($done || ! $this->send($rep, new ClassReminder($class, $day, $kind, $mark, $now))) {
                        continue;
                    }
                    DB::table('reminder_log')->insert(['user_id' => $rep->id, 'class_schedule_id' => $class->id,
                        'class_date' => $day->toDateString(), 'mark' => $mark, 'sent_at' => $now]);

                    // The first "is it face-to-face?" that went out: from now on, no answer can free the room
                    if ($kind === 'ask') {
                        $check = ClassCheck::firstOrCreate(['class_schedule_id' => $class->id, 'class_date' => $day->toDateString()]);
                        if (! $check->asked_at) {
                            $check->update(['asked_at' => $now]);
                        }
                    }
                }
            }
        }
    }

    /** Approved bookings starting within the next day */
    private function bookings(CarbonImmutable $now): void
    {
        $bookings = Reservation::with(['user', 'room', 'equipment'])
            ->where('status', 'Confirmed')
            ->where('start_time', '>', $now)
            ->where('start_time', '<=', $now->addMinutes(ReminderTimes::MARKS['day'] + 1))
            ->get();

        foreach ($bookings as $booking) {
            $mark = ReminderTimes::due($booking->start_time->toImmutable(), $now);
            $rep = $booking->user;
            if (! $mark || ! $rep->is_active || ! $rep->hasConnectedEmail()) {
                continue;
            }
            $done = DB::table('reminder_log')->where('user_id', $rep->id)->where('reservation_id', $booking->id)->where('mark', $mark)->exists();
            if ($done || ! $this->send($rep, new BookingReminder($booking, $now))) {
                continue;
            }
            DB::table('reminder_log')->insert(['user_id' => $rep->id, 'reservation_id' => $booking->id, 'mark' => $mark, 'sent_at' => $now]);
        }
    }

    private function send(User $user, Notification $email): bool
    {
        try {
            $user->notify($email);
            $this->sent++;

            return true;
        } catch (Throwable $e) {
            report($e);     // the reason goes to storage/logs/laravel.log
            $this->failed++;

            return false;
        }
    }
}
