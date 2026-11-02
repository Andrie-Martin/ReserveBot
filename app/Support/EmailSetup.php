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
}
