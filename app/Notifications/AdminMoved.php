<?php

namespace App\Notifications;

use App\Models\Department;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a department Admin that the College admin assigned them to another department */
class AdminMoved extends Notification
{
    public function __construct(private Department $from, private Department $to) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $user): MailMessage
    {
        return (new MailMessage)
            ->subject('You now run the '.$this->to->name.' in ReserveBot')
            ->greeting('Hi '.$user->name.',')
            ->line('The College admin moved your Admin account from the '.$this->from->name.' to the '.$this->to->name.'.')
            ->line('Your email and password stay the same. From now on you see and run the '.$this->to->name.', and no longer the '.$this->from->name.'.')
            ->line('If the department is new, your home page lists what to add first: its rooms, timetable, equipment and accounts.')
            ->action('Open ReserveBot', route('login'));
    }
}
