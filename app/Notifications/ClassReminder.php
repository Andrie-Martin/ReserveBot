<?php

namespace App\Notifications;

use App\Models\ClassSchedule;
use App\Models\User;
use App\Services\ClassChecks;
use App\Support\ClassCheckRules;
use App\Support\ReminderTimes;
use App\Support\TimeSlots;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Phase 10: the email to a block's rep about one class meeting ($date), sent by reminders:send.
 *  - 'ask':    is it face-to-face? Two buttons. Online frees the room.
 *  - 'freed':  nobody confirmed by the 5-hour email, so the room was freed. One button to take it back.
 *  - 'remind': it's face-to-face. One button in case it turns online.
 * $mark: 'day', '5h' or '1h'. $now: when it's sent (for words like "tomorrow").
 */
class ClassReminder extends Notification
{
    public function __construct(
        private ClassSchedule $class,
        private CarbonImmutable $date,
        private string $kind,
        private string $mark,
        private CarbonImmutable $now,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $rep): MailMessage
    {
        $checks = app(ClassChecks::class);
        [$start, $end] = $checks->meeting($this->class, $this->date);
        $name = ClassChecks::name($this->class);
        $title = $this->class->course?->title;
        $room = $this->class->room->name;
        $block = $this->class->section?->name ?? 'Your block';
        $when = ReminderTimes::when($start, $this->now);
        $span = TimeSlots::span($start->hour * 60 + $start->minute, $end->hour * 60 + $end->minute);
        $what = $name.($title ? ' '.$title : '');
        $keep = ['url' => $checks->link($this->class, $this->date, $rep, 'f2f'), 'color' => 'success'];
        $free = ['url' => $checks->link($this->class, $this->date, $rep, 'online'), 'color' => 'primary'];

        [$subject, $heading, $intro, $buttons, $outro] = match ($this->kind) {
            'ask' => [
                'Is '.$name.' face-to-face '.$when.'?',
                'Is '.$name.' face-to-face?',
                $block.' has '.$what.' '.$when.' ('.$span.') in '.$room.'. Is it face-to-face?',
                [$keep + ['label' => 'Yes, face-to-face: keep '.$room], $free + ['label' => 'No, online or no class: free '.$room]],
                ClassCheckRules::freesIfUnanswered($this->now, $start)
                    ? 'If nobody answers by '.ReminderTimes::when(ClassCheckRules::deadline($start), $this->now).', '.$room.' is freed so others can book it.'
                    : $room.' stays reserved for the class until you answer, so please answer if it’s online.',
            ],
            'freed' => [
                $room.' was freed: '.$name.' '.$when.' wasn’t confirmed',
                $room.' was freed',
                'Nobody confirmed that '.$what.' '.$when.' ('.$span.') is face-to-face, so '.$room.' is now free for others to book.',
                [$keep + ['label' => 'It’s face-to-face: take '.$room.' back']],
                'Taking it back works as long as nobody has booked the room.',
            ],
            default => [
                'Reminder: '.$name.' '.$when.' in '.$room,
                $name.' is face-to-face '.$when,
                'Reminder: '.$what.' is face-to-face '.$when.' ('.$span.') in '.$room.'.',
                [$free + ['label' => 'It’s online now: free '.$room]],
                'If nothing changed, you don’t need to do anything.',
            ],
        };

        return (new MailMessage)->subject($subject)->markdown('mail.class_reminder', [
            'rep' => $rep->name,
            'heading' => $heading,
            'intro' => $intro,
            'buttons' => $buttons,
            'outro' => $outro,
        ]);
    }
}
