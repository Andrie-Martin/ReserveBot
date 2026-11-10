<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Phase 9: the "Forgot password?" email */
class ResetPasswordLink extends Notification
{
    public function __construct(private string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $user): MailMessage
    {
        $minutes = (int) config('auth.passwords.users.expire', 20);

        return (new MailMessage)
            ->subject('Pick a new ReserveBot password')
            ->greeting('Hi '.$user->name.',')
            ->line('Someone (hopefully you) asked for a new password for your ReserveBot account.')
            ->action('Pick a new password', route('password.reset', ['token' => $this->token, 'email' => $user->email]))
            ->line('The button works for '.$minutes.' minutes. If you didn’t ask for this, ignore this email: your password stays the same.');
    }
}
