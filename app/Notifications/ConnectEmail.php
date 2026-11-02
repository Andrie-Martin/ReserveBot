<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Phase 9: the email with the "Connect my email" button. New accounts get a welcome with it.
 * The starting password is never emailed: the admin gives it to the person.
 */
class ConnectEmail extends Notification
{
    public function __construct(private bool $welcome = false) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $user): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->welcome ? 'Your ReserveBot account is ready' : 'Connect your email to ReserveBot')
            ->greeting('Hi '.$user->name.',');

        if ($this->welcome) {
            $mail->line('An admin of the '.$user->department->name.' made you a ReserveBot account ('.$user->roleLabel().').')
                ->line('Log in at '.route('login').' with this email and the starting password your admin gives you. You’ll pick your own password after that.');
        }

        return $mail
            ->line('Click the button to connect this email to your account.'
                .($user->role === 'rep' ? ' You can book rooms and equipment once it’s connected.' : ''))
            ->action('Connect my email', $user->connectEmailUrl())
            ->line('The button works for '.User::EMAIL_LINK_DAYS.' days. If you didn’t expect this email, you can ignore it.');
    }
}
