<?php

namespace App\Support;

/** Phase 9: is the site set up to really send emails (through Gmail), or only to write them down? */
final class EmailSetup
{
    /** MAIL_MAILER=log or array: emails go to the log (storage/logs/laravel.log), not to anyone's inbox */
    public static function sends(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    /**
     * The warning shown while mail is only logged. It is about outgoing mail from this site (welcome, reminder and
     * approval emails), not about whether a person's own address is connected on My account.
     */
    public static function logOnlyNotice(): string
    {
        return 'This site isn’t delivering email. Welcome links, reminders and approval emails are only written to a log file (MAIL_MAILER='
            .config('mail.default').'), so nobody receives them. Each person’s “Email connected” status is separate and only shows their address. '
            .'Set up a real mail service to deliver messages: see the README, “Email”.';
    }
}
