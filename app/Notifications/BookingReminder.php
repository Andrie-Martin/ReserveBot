<?php

namespace App\Notifications;

use App\Models\Reservation;
use App\Models\User;
use App\Support\ReminderTimes;
use App\Support\TimeSlots;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Phase 10: a reminder of an approved booking, 1 day, 5 hours and 1 hour before it (reminders:send) */
class BookingReminder extends Notification
{
    public function __construct(private Reservation $booking, private CarbonImmutable $now) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $rep): MailMessage
    {
        $b = $this->booking;
        $start = $b->start_time->toImmutable();
        $when = ReminderTimes::when($start, $this->now);
        $where = $b->room?->name;

        $mail = (new MailMessage)
            ->subject('Reminder: your booking '.$when.($where ? ' in '.$where : ''))
            ->greeting('Hi '.$rep->name.',')
            ->line('Your booking “'.$b->purpose.'” is '.$when.' ('.TimeSlots::span($b->start_time->hour * 60 + $b->start_time->minute, $b->end_time->isSameDay($b->start_time) ? $b->end_time->hour * 60 + $b->end_time->minute : 1440).').')
            ->line($where ? 'Room: '.$where : 'No room: equipment only.');
        if ($b->hasEquipment()) {
            $mail->line('Items: '.$b->itemsText());
        }

        return $mail
            ->line('Pick up the key and items from the lab staff.')
            ->action('View booking', route('bookings.show', $b))
            ->line('Can’t make it anymore? Open the booking to cancel it, so the room and items go to someone else.');
    }
}
