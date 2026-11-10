<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ConnectEmail;
use Carbon\CarbonImmutable;
use Database\Seeders\CollegeAdminSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\UsesDisposableDatabase;
use Tests\TestCase;

/**
 * Sandbox issue 8: the College admin (Option B of the multi-department discussion). A college-wide account that
 * sees every department and makes, edits, turns off, deletes and resets the password of the departments' Admin
 * accounts, and can do nothing else: no bookings, rooms, equipment, or block and lab staff accounts.
 * Database-backed: see Tests\Concerns\UsesDisposableDatabase.
 */
class CollegeAdminTest extends TestCase
{
    use UsesDisposableDatabase;

    private User $college;

    private Department $cpe;

    private Department $ece;

    private User $cpeAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startDisposableDatabase();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 08:00', 'Asia/Manila'));
        $this->college = User::create(['department_id' => null, 'role' => 'college_admin', 'name' => 'CEA College Admin',
            'email' => 'college.'.uniqid().'@gmail.com', 'password' => 'Starting123', 'is_active' => true, 'must_change_password' => false]);
        $this->college->forceFill(['email_verified_at' => now()])->save();
        $this->cpe = $this->makeDepartment('Computer Engineering Test Department');
        $this->ece = $this->makeDepartment('Electronics Engineering Test Department');
        $this->cpeAdmin = $this->makeAccount($this->cpe, 'admin', ['name' => 'Cpe Office Admin']);
    }

    protected function tearDown(): void
    {
        $this->stopDisposableDatabase();
        parent::tearDown();
    }

    private function adminForm(array $changes = []): array
    {
        return array_replace(['name' => 'Ana Reyes', 'email' => 'ana.'.uniqid().'@gmail.com', 'password' => 'Starting123'], $changes);
    }

    // ---- what it sees ----

    public function test_it_lands_on_the_departments_page_and_sees_every_department_at_a_glance(): void
    {
        $this->makeAccount($this->cpe, 'lab_staff');
        $block = $this->makeBlock();
        $rep = $this->makeAccount($this->cpe, 'rep', ['section_id' => $block->id]);
        $room = $this->makeRoom($this->cpe);
        $this->makeBooking($rep, $room, '2026-11-10 13:00', 'Pending');

        $this->post('/login', ['email' => $this->college->email, 'password' => 'Starting123'])->assertRedirect('/college');
        $this->get('/')->assertRedirect('/college');
        $page = $this->get('/college')->assertOk()
            ->assertSee('Departments')
            ->assertSee('College admin')
            ->assertSee($this->cpe->name)
            ->assertSee($this->ece->name)
            ->assertSee('This department has no Admin yet.');     // the ECE test department
        $page->assertSee(route('college.departments.show', $this->cpe), false)
            ->assertSee(route('college.departments.show', $this->ece), false);

        // The menu holds its own page only: none of the department pages
        $page->assertDontSee(route('today'), false)->assertDontSee(route('requests'), false)
            ->assertDontSee(route('accounts.index'), false)->assertDontSee(route('rooms'), false);

        $this->get('/college/departments/'.$this->cpe->id)->assertOk()
            ->assertSee('Admins of '.$this->cpe->name)->assertSee('Cpe Office Admin');
    }

    // ---- who may open what ----

    public function test_only_the_college_admin_opens_the_college_pages(): void
    {
        $this->get('/college')->assertRedirect('/login');

        $block = $this->makeBlock();
        $others = [
            $this->cpeAdmin,
            $this->makeAccount($this->cpe, 'lab_staff'),
            $this->makeAccount($this->cpe, 'rep', ['section_id' => $block->id]),
        ];
        foreach ($others as $account) {
            $this->signedInAs($account);
            $this->get('/college')->assertForbidden();
            $this->get('/college/departments/'.$this->cpe->id)->assertForbidden();
            $this->get('/college/admins/'.$this->cpeAdmin->id)->assertForbidden();
            $this->post('/college/departments/'.$this->cpe->id.'/admins', $this->adminForm())->assertForbidden();
            $this->put('/college/admins/'.$this->cpeAdmin->id, ['name' => 'Hacked', 'email' => $this->cpeAdmin->email])->assertForbidden();
            $this->put('/college/admins/'.$this->cpeAdmin->id.'/password', ['password' => 'Hacked12345'])->assertForbidden();
            $this->delete('/college/admins/'.$this->cpeAdmin->id)->assertForbidden();
        }
        $this->assertSame(1, User::where('department_id', $this->cpe->id)->where('role', 'admin')->count());
        $this->assertSame('Cpe Office Admin', $this->cpeAdmin->fresh()->name);
        $this->assertTrue(Hash::check('Starting123', $this->cpeAdmin->fresh()->password));
    }

    public function test_the_college_admin_can_do_nothing_inside_a_department(): void
    {
        $block = $this->makeBlock();
        $this->giveBlockAClass($this->cpe, $block);
        $rep = $this->makeAccount($this->cpe, 'rep', ['section_id' => $block->id]);
        $room = $this->makeRoom($this->cpe);
        $booking = $this->makeBooking($rep, $room, '2026-11-10 13:00', 'Pending');

        $this->signedInAs($this->college);
        foreach (['/rooms', '/today', '/requests', '/accounts', '/manage/rooms', '/manage/equipment', '/manage/rules', '/manage/timetable',
            '/book', '/my-bookings', '/bookings/'.$booking->id, '/rep', '/staff', '/admin'] as $page) {
            $this->get($page)->assertForbidden();
        }
        // No bookings to decide, no rooms to add, no block or lab staff accounts to make
        $this->post('/bookings/'.$booking->id.'/approve')->assertForbidden();
        $this->post('/bookings/'.$booking->id.'/reject', ['reason' => 'no'])->assertForbidden();
        $this->post('/manage/rooms', ['name' => 'CEA999'])->assertForbidden();
        $this->post('/accounts', ['role' => 'lab_staff', 'name' => 'Someone', 'email' => 'someone@gmail.com', 'password' => 'Starting123'])->assertForbidden();
        $this->assertSame('Pending', $booking->fresh()->status);
        $this->assertSame(1, User::where('department_id', $this->cpe->id)->where('role', 'rep')->count());
    }

    public function test_it_manages_admin_accounts_only(): void
    {
        $block = $this->makeBlock();
        $rep = $this->makeAccount($this->cpe, 'rep', ['section_id' => $block->id]);
        $staff = $this->makeAccount($this->cpe, 'lab_staff');
        $otherCollege = User::create(['department_id' => null, 'role' => 'college_admin', 'name' => 'Another College Admin',
            'email' => 'college2.'.uniqid().'@gmail.com', 'password' => 'Starting123']);

        $this->signedInAs($this->college);
        foreach ([$rep, $staff, $otherCollege, $this->college] as $account) {
            $this->get('/college/admins/'.$account->id)->assertNotFound();
            $this->put('/college/admins/'.$account->id, ['name' => 'Changed', 'email' => $account->email])->assertNotFound();
            $this->put('/college/admins/'.$account->id.'/password', ['password' => 'Changed12345'])->assertNotFound();
            $this->delete('/college/admins/'.$account->id)->assertNotFound();
            $this->assertNotSame('Changed', $account->fresh()->name);
        }
    }

    // ---- making a department's Admin ----

    public function test_it_makes_a_departments_first_admin_with_a_starting_password(): void
    {
        $form = $this->adminForm(['name' => '  Ana   Reyes ', 'email' => ' Ana.Reyes.'.uniqid().'@Gmail.com ']);

        $this->signedInAs($this->college)->post('/college/departments/'.$this->ece->id.'/admins', $form)
            ->assertRedirect('/college/departments/'.$this->ece->id)
            ->assertSessionHas('status', fn ($message) => str_contains($message, 'Made an Admin account for Ana Reyes ('.$this->ece->name.').'));

        $admin = User::where('department_id', $this->ece->id)->sole();
        $this->assertSame('admin', $admin->role);
        $this->assertSame('Ana Reyes', $admin->name);
        $this->assertSame(strtolower(trim($form['email'])), $admin->email);
        $this->assertTrue($admin->must_change_password, 'they pick their own password at the first login');
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('Starting123', $admin->password));
        Notification::assertSentTo($admin, ConnectEmail::class);

        $this->get('/college')->assertOk()->assertDontSee('This department has no Admin yet.');
    }

    public function test_the_form_checks_the_name_email_and_password(): void
    {
        $this->signedInAs($this->college);
        $url = '/college/departments/'.$this->ece->id.'/admins';
        $this->from('/college/departments/'.$this->ece->id)->post($url, $this->adminForm(['name' => '']))->assertSessionHasErrorsIn('add', ['name' => 'Type the admin’s name.']);
        $this->post($url, $this->adminForm(['email' => 'not-an-email']))->assertSessionHasErrorsIn('add', 'email');
        $this->post($url, $this->adminForm(['email' => $this->cpeAdmin->email]))->assertSessionHasErrorsIn('add', ['email' => 'Another account already uses that email.']);
        $this->post($url, $this->adminForm(['password' => 'short']))->assertSessionHasErrorsIn('add', 'password');
        $this->assertSame(0, User::where('department_id', $this->ece->id)->count());
        $this->post('/college/departments/999999/admins', $this->adminForm())->assertNotFound();
    }

    public function test_the_new_admin_logs_in_picks_a_password_and_runs_their_own_department(): void
    {
        $form = $this->adminForm();
        $this->signedInAs($this->college)->post('/college/departments/'.$this->ece->id.'/admins', $form)->assertSessionHasNoErrors();
        $admin = User::where('department_id', $this->ece->id)->sole();

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => $form['email'], 'password' => 'Starting123'])->assertRedirect('/admin');
        $this->get('/admin')->assertRedirect('/welcome');
        $this->put('/welcome', ['password' => 'MyOwnPass123', 'password_confirmation' => 'MyOwnPass123'])->assertRedirect('/account');
        $this->get('/admin')->assertOk();
        $this->get('/accounts')->assertOk()->assertSee('Admin accounts are made and looked after by the College admin, not here.');
        $this->get('/college')->assertForbidden();
        $this->assertFalse($admin->fresh()->must_change_password);
    }

    // ---- looking after an Admin ----

    public function test_it_edits_an_admin_and_a_new_email_needs_connecting_again(): void
    {
        $this->signedInAs($this->college)->get('/college/admins/'.$this->cpeAdmin->id)->assertOk()
            ->assertSee('Edit Cpe Office Admin')->assertSee($this->cpe->name);

        $this->put('/college/admins/'.$this->cpeAdmin->id, ['name' => 'Cpe Dept Chair', 'email' => $this->cpeAdmin->email, 'is_active' => 1])
            ->assertRedirect('/college/admins/'.$this->cpeAdmin->id)->assertSessionHas('status', 'Saved Cpe Dept Chair.');
        $this->assertNotNull($this->cpeAdmin->fresh()->email_verified_at);

        $newEmail = 'chair.'.uniqid().'@gmail.com';
        $this->put('/college/admins/'.$this->cpeAdmin->id, ['name' => 'Cpe Dept Chair', 'email' => $newEmail, 'is_active' => 1])
            ->assertSessionHas('status', fn ($message) => str_contains($message, 'We emailed '.$newEmail.' a link to connect it.'));
        $fresh = $this->cpeAdmin->fresh();
        $this->assertSame($newEmail, $fresh->email);
        $this->assertNull($fresh->email_verified_at);
        $this->assertSame($this->cpe->id, $fresh->department_id, 'still the same department');
        $this->assertSame('admin', $fresh->role);
        Notification::assertSentTo($fresh, ConnectEmail::class);
    }

    public function test_turning_an_admin_off_logs_them_out_and_says_when_the_department_has_none_left(): void
    {
        $second = $this->makeAccount($this->cpe, 'admin', ['name' => 'Second Admin']);

        $this->signedInAs($this->college)->put('/college/admins/'.$second->id, ['name' => 'Second Admin', 'email' => $second->email])
            ->assertSessionHas('status', 'Second Admin is turned off: the account can’t log in anymore.');
        $this->put('/college/admins/'.$this->cpeAdmin->id, ['name' => 'Cpe Office Admin', 'email' => $this->cpeAdmin->email])
            ->assertSessionHas('status', 'Cpe Office Admin is turned off: the account can’t log in anymore. '.$this->cpe->name.' now has no Admin who can log in.');
        $this->get('/college')->assertOk()->assertSee('No Admin of this department can log in.');

        // The turned-off Admin is logged out at their next click, and told who to ask
        $this->signedInAs($this->cpeAdmin->fresh())->get('/admin')
            ->assertRedirect('/login')->assertSessionHasErrors(['email' => 'This account is turned off. Ask the College admin.']);
        $this->post('/login', ['email' => $this->cpeAdmin->email, 'password' => 'Starting123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        // And back on
        $this->signedInAs($this->college)->put('/college/admins/'.$this->cpeAdmin->id, ['name' => 'Cpe Office Admin', 'email' => $this->cpeAdmin->email, 'is_active' => 1])
            ->assertSessionHas('status', 'Cpe Office Admin is turned on: the account can log in again.');
        $this->assertTrue($this->cpeAdmin->fresh()->is_active);
    }

    public function test_it_sets_a_new_password_for_an_admin_who_is_locked_out(): void
    {
        $this->signedInAs($this->college)->put('/college/admins/'.$this->cpeAdmin->id.'/password', ['password' => 'short'])
            ->assertSessionHasErrorsIn('password', 'password');
        $this->put('/college/admins/'.$this->cpeAdmin->id.'/password', ['password' => 'Recover12345'])
            ->assertRedirect('/college/admins/'.$this->cpeAdmin->id)
            ->assertSessionHas('status', fn ($message) => str_contains($message, 'Set a new password for Cpe Office Admin.'));

        $fresh = $this->cpeAdmin->fresh();
        $this->assertTrue(Hash::check('Recover12345', $fresh->password));
        $this->assertTrue($fresh->must_change_password, 'they pick their own again at the next login');

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => $fresh->email, 'password' => 'Starting123'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $fresh->email, 'password' => 'Recover12345'])->assertRedirect('/admin');
    }

    public function test_delete_removes_an_admin_with_no_history_and_only_turns_off_one_with_history(): void
    {
        $fresh = $this->makeAccount($this->cpe, 'admin', ['name' => 'Fresh Admin']);
        $this->signedInAs($this->college)->delete('/college/admins/'.$fresh->id)
            ->assertRedirect('/college/departments/'.$this->cpe->id)->assertSessionHas('status', 'Deleted Fresh Admin’s account.');
        $this->assertNull(User::find($fresh->id));

        // This Admin approved a booking: that history must stay
        $block = $this->makeBlock();
        $rep = $this->makeAccount($this->cpe, 'rep', ['section_id' => $block->id]);
        $booking = $this->makeBooking($rep, $this->makeRoom($this->cpe), '2026-11-10 13:00', 'Confirmed', ['reviewed_by' => $this->cpeAdmin->id]);

        $this->delete('/college/admins/'.$this->cpeAdmin->id)
            ->assertRedirect('/college/admins/'.$this->cpeAdmin->id)
            ->assertSessionHas('status', fn ($message) => str_contains($message, 'was turned off instead of deleted'));
        $kept = User::find($this->cpeAdmin->id);
        $this->assertNotNull($kept);
        $this->assertFalse($kept->is_active);
        $this->assertSame($this->cpeAdmin->id, Reservation::find($booking->id)->reviewed_by);
    }

    // ---- the account itself ----

    public function test_my_account_works_for_an_account_with_no_department(): void
    {
        $this->signedInAs($this->college)->get('/account')->assertOk()->assertSee('College admin')->assertDontSee('Program and block');
        $this->put('/account', ['name' => 'CEA Dean’s Office', 'email' => $this->college->email])->assertRedirect('/account')->assertSessionHas('status');
        $this->assertSame('CEA Dean’s Office', $this->college->fresh()->name);
        $this->get('/account/password')->assertOk();
    }

    public function test_the_database_gives_a_college_admin_no_department_and_everyone_else_one(): void
    {
        $this->assertNull($this->college->department_id);
        foreach ([
            fn () => User::whereKey($this->college->id)->update(['department_id' => $this->cpe->id]),       // a College admin inside a department
            fn () => User::whereKey($this->cpeAdmin->id)->update(['department_id' => null]),                 // a department Admin without one
        ] as $wrong) {
            try {
                $wrong();
                $this->fail('The database must refuse this.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('chk_users_department', $e->getMessage());
            }
        }
    }

    public function test_the_command_makes_a_college_admin_who_must_pick_a_password(): void
    {
        $email = 'dean.office.'.uniqid().'@gmail.com';
        $this->artisan('college-admin:make', ['name' => '  CEA   Dean Office ', 'email' => strtoupper($email), '--password' => 'Starting123'])
            ->expectsOutputToContain('Made the College admin account for CEA Dean Office')
            ->assertExitCode(0);

        $made = User::where('email', $email)->sole();
        $this->assertSame('college_admin', $made->role);
        $this->assertNull($made->department_id);
        $this->assertSame('CEA Dean Office', $made->name);
        $this->assertTrue($made->must_change_password);
        $this->assertTrue(Hash::check('Starting123', $made->password));

        // The same email again, a weak password, a bad email: nothing is made
        $this->artisan('college-admin:make', ['name' => 'Again', 'email' => $email, '--password' => 'Starting123'])->assertExitCode(1);
        $this->artisan('college-admin:make', ['name' => 'Weak', 'email' => 'weak.'.uniqid().'@gmail.com', '--password' => 'short'])->assertExitCode(1);
        $this->artisan('college-admin:make', ['name' => 'Bad', 'email' => 'not-an-email', '--password' => 'Starting123'])->assertExitCode(1);
        $this->assertSame(1, User::where('email', $email)->count());
        $this->assertSame(0, User::whereIn('name', ['Again', 'Weak', 'Bad'])->count());
    }

    public function test_the_seeder_adds_the_test_college_admin_once(): void
    {
        $this->assertSame(0, User::where('email', CollegeAdminSeeder::EMAIL)->count());
        $this->seed(CollegeAdminSeeder::class);
        $this->seed(CollegeAdminSeeder::class);

        $test = User::where('email', CollegeAdminSeeder::EMAIL)->sole();
        $this->assertSame('college_admin', $test->role);
        $this->assertNull($test->department_id);
        $this->assertFalse((bool) $test->must_change_password);
        $this->assertNotNull($test->email_verified_at);
        $this->assertTrue(Hash::check('Password123!', $test->password));
    }
}
