<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AccountFields;
use App\Support\AccountRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Makes a College admin account (sandbox issue 8). The College admin is the top account: it makes every
 * department's Admins, so it is not made on the website but here, by whoever runs ReserveBot:
 *
 *   php artisan college-admin:make "CEA Dean's Office" dean.office@gmail.com
 *   docker compose exec app php artisan college-admin:make "CEA Dean's Office" dean.office@gmail.com
 *
 * It asks for a starting password (typed twice, not shown). The person picks their own at the first login.
 * The same command is the way back in if a College admin is ever locked out: make a new one, then turn off the old.
 */
class MakeCollegeAdmin extends Command
{
    protected $signature = 'college-admin:make
        {name : The person or office, e.g. "CEA Dean\'s Office"}
        {email : The email they will log in with}
        {--password= : Starting password (you are asked for it when this is left out)}';

    protected $description = 'Makes a College admin account, which looks after every department’s Admin accounts';

    public function handle(): int
    {
        $name = (string) preg_replace('/\s+/u', ' ', trim((string) $this->argument('name')));
        $email = AccountRules::email($this->argument('email'));
        $password = $this->option('password');
        if ($password === null) {
            $password = (string) $this->secret('Starting password ('.AccountFields::PASSWORD_HINT.')');
            if ($password !== (string) $this->secret('Type it again')) {
                $this->error('The two passwords don’t match. Nothing was made.');

                return self::FAILURE;
            }
        }

        $validator = Validator::make(['name' => $name, 'email' => $email, 'password' => $password], [
            'name' => ['required', 'string', 'max:100'],
            'email' => AccountFields::email('college_admin'),
            'password' => AccountFields::password(),
        ], AccountFields::MESSAGES);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $problem) {
                $this->error($problem);
            }
            $this->line('Nothing was made.');

            return self::FAILURE;
        }

        $admin = User::create([
            'department_id' => null,            // college-wide: no department
            'role' => 'college_admin',
            'name' => $name,
            'email' => $email,
            'password' => $password,            // hashed automatically by the User model
            'must_change_password' => true,     // they pick their own at the first login
            'is_active' => true,
        ]);

        $this->info('Made the College admin account for '.$admin->name.' ('.$admin->email.').');
        $this->line('Give them the starting password; they’ll pick their own when they first log in.');
        $this->line($admin->sendConnectEmail(welcome: true)
            ? 'We emailed them a link to connect their email.'
            : 'The welcome email couldn’t be sent (see README, Email). They can send the link again from My account.');

        return self::SUCCESS;
    }
}
