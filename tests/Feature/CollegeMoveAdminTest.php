<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\AdminMoved;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\UsesDisposableDatabase;
use Tests\TestCase;

/**
 * The College admin assigns a department Admin to another department (a new one, for example). The account, its
 * login and what it did before stay; its pages follow the new department from the next click.
 * Database-backed: see Tests\Concerns\UsesDisposableDatabase.
 */
class CollegeMoveAdminTest extends TestCase
{
    use UsesDisposableDatabase;

    private User $college;

    private Department $cpe;

    private Department $ece;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startDisposableDatabase();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 08:00', 'Asia/Manila'));
        $this->college = User::create(['department_id' => null, 'role' => 'college_admin', 'name' => 'CEA College Admin',
            'email' => 'college.'.uniqid().'@gmail.com', 'password' => 'Starting123', 'is_active' => true, 'must_change_password' => false]);
        $this->college->forceFill(['email_verified_at' => now()])->save();
        $this->cpe = $this->makeDepartment('Computer Engineering Test Department');
        $this->ece = $this->makeDepartment('Electronics Engineering Test Department');     // new and empty
        $this->admin = $this->makeAccount($this->cpe, 'admin', ['name' => 'Ana Reyes']);
    }

    protected function tearDown(): void
    {
        $this->stopDisposableDatabase();
        parent::tearDown();
    }

    public function test_an_admin_is_moved_from_their_own_page_and_their_pages_follow(): void
    {
        $second = $this->makeAccount($this->cpe, 'admin', ['name' => 'Second Admin']);     // so CpE keeps an Admin
        $room = $this->makeRoom($this->cpe);

        $this->signedInAs($this->college)->get('/college/admins/'.$this->admin->id)->assertOk()
            ->assertSee('Move to this department')->assertSee($this->ece->name);

        $this->put('/college/admins/'.$this->admin->id.'/department', ['department_id' => $this->ece->id])
            ->assertRedirect('/college/departments/'.$this->ece->id)
            ->assertSessionHas('status', fn ($message) => str_contains($message, 'Moved Ana Reyes from '.$this->cpe->name.' to '.$this->ece->name.'.')
                && ! str_contains($message, 'now has no Admin')
                && str_contains($message, 'We emailed '.$this->admin->email.' about it.'));

        $moved = $this->admin->fresh();
        $this->assertSame($this->ece->id, $moved->department_id);
        $this->assertSame('admin', $moved->role);
        $this->assertSame($this->admin->email, $moved->email, 'same login');
        Notification::assertSentTo($moved, AdminMoved::class);

        // The Admin's pages now belong to the new department: its name, and none of the old department's things
        $this->signedInAs($moved)->get('/admin')->assertOk()->assertSee($this->ece->name)->assertDontSee($this->cpe->name);
        $this->get('/manage/rooms')->assertOk()->assertDontSee($room->name);
        $this->get('/accounts')->assertOk()->assertDontSee('Second Admin');
        $this->get('/accounts/'.$second->id)->assertNotFound();

        // And the College admin sees them listed under the new department
        $this->signedInAs($this->college)->get('/college/departments/'.$this->ece->id)->assertOk()->assertSee('Ana Reyes');
        // On the old department's page she is no longer one of its Admins, only someone who could be moved back
        $this->get('/college/departments/'.$this->cpe->id)->assertOk()->assertSee('Second Admin')
            ->assertSee('Ana Reyes · now in '.$this->ece->name)
            ->assertDontSee(route('college.admins.edit', $moved), false);
    }

    public function test_an_admin_is_moved_from_the_departments_page_too(): void
    {
        $this->signedInAs($this->college)->get('/college/departments/'.$this->ece->id)->assertOk()
            ->assertSee('Move an existing Admin here')->assertSee('Ana Reyes · now in '.$this->cpe->name);

        $this->post('/college/departments/'.$this->ece->id.'/admins/move', ['account_id' => $this->admin->id])
            ->assertRedirect('/college/departments/'.$this->ece->id)
            ->assertSessionHas('status', fn ($message) => str_contains($message, 'Moved Ana Reyes from')
                && str_contains($message, $this->cpe->name.' now has no Admin who can log in.'));     // Ana was its only Admin

        $this->assertSame($this->ece->id, $this->admin->fresh()->department_id);
        $this->get('/college')->assertOk()->assertSee('This department has no Admin yet.');     // CpE now
    }

    public function test_what_the_admin_did_before_stays_with_the_old_department(): void
    {
        $block = $this->makeBlock();
        $rep = $this->makeAccount($this->cpe, 'rep', ['section_id' => $block->id]);
        $booking = $this->makeBooking($rep, $this->makeRoom($this->cpe), '2026-11-10 13:00', 'Confirmed', ['reviewed_by' => $this->admin->id]);
        $staff = $this->makeAccount($this->cpe, 'lab_staff');

        $this->signedInAs($this->college)->put('/college/admins/'.$this->admin->id.'/department', ['department_id' => $this->ece->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->admin->id, Reservation::find($booking->id)->reviewed_by);
        $this->signedInAs($staff)->get('/bookings/'.$booking->id)->assertOk()->assertSee('Ana Reyes');     // still "Approved by"
        // The moved Admin can no longer open the old department's booking
        $this->signedInAs($this->admin->fresh())->get('/bookings/'.$booking->id)->assertNotFound();
    }

    public function test_moving_needs_a_real_other_department_and_a_real_admin(): void
    {
        $this->signedInAs($this->college)->from('/college/admins/'.$this->admin->id);

        $this->put('/college/admins/'.$this->admin->id.'/department', ['department_id' => $this->cpe->id])
            ->assertSessionHasErrorsIn('move', ['department_id' => 'Ana Reyes is already an Admin of '.$this->cpe->name.'.']);
        $this->put('/college/admins/'.$this->admin->id.'/department', ['department_id' => 999999])->assertSessionHasErrorsIn('move', 'department_id');
        $this->put('/college/admins/'.$this->admin->id.'/department', [])->assertSessionHasErrorsIn('move', 'department_id');

        // Only Admin accounts can be moved: not block accounts, lab staff or College admins
        $block = $this->makeBlock();
        $rep = $this->makeAccount($this->cpe, 'rep', ['section_id' => $block->id]);
        $staff = $this->makeAccount($this->cpe, 'lab_staff');
        foreach ([$rep, $staff, $this->college] as $account) {
            $this->put('/college/admins/'.$account->id.'/department', ['department_id' => $this->ece->id])->assertNotFound();
            $this->post('/college/departments/'.$this->ece->id.'/admins/move', ['account_id' => $account->id])
                ->assertSessionHasErrorsIn('move', ['account_id' => 'Pick an Admin from the list.']);
        }
        $this->assertSame($this->cpe->id, $rep->fresh()->department_id);
        $this->assertSame($this->cpe->id, $staff->fresh()->department_id);
        $this->assertSame($this->cpe->id, $this->admin->fresh()->department_id);
        Notification::assertNothingSent();
    }

    public function test_nobody_else_can_move_an_admin(): void
    {
        $block = $this->makeBlock();
        foreach ([$this->admin, $this->makeAccount($this->cpe, 'lab_staff'), $this->makeAccount($this->cpe, 'rep', ['section_id' => $block->id])] as $account) {
            $this->signedInAs($account);
            $this->put('/college/admins/'.$this->admin->id.'/department', ['department_id' => $this->ece->id])->assertForbidden();
            $this->post('/college/departments/'.$this->ece->id.'/admins/move', ['account_id' => $this->admin->id])->assertForbidden();
        }
        $this->assertSame($this->cpe->id, $this->admin->fresh()->department_id);
    }

    public function test_the_email_tells_the_admin_which_department_they_run_now(): void
    {
        $mail = (new AdminMoved($this->cpe, $this->ece))->toMail($this->admin);
        $text = $mail->subject.' '.implode(' ', array_merge($mail->introLines, $mail->outroLines));

        $this->assertStringContainsString('You now run the '.$this->ece->name, $text);
        $this->assertStringContainsString('from the '.$this->cpe->name.' to the '.$this->ece->name, $text);
        $this->assertStringContainsString('Your email and password stay the same.', $text);
    }
}
