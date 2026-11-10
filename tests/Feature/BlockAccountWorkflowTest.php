<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Reservation;
use App\Models\Section;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\Concerns\UsesDisposableDatabase;
use Tests\TestCase;

/**
 * Sandbox issue 6: block accounts. One account per block, the representative named on it and changeable by the
 * department's admin (Accounts) and by the block itself (My account), and bookings that keep the representative
 * they were made under. Database-backed: see Tests\Concerns\UsesDisposableDatabase.
 */
class BlockAccountWorkflowTest extends TestCase
{
    use UsesDisposableDatabase;

    private Department $department;

    private User $admin;

    private Section $block;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startDisposableDatabase();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 08:00', 'Asia/Manila'));   // a Monday morning
        $this->department = $this->makeDepartment();
        $this->admin = $this->makeAccount($this->department, 'admin', ['name' => 'Office Admin']);
        $this->block = $this->makeBlock();
        $this->giveBlockAClass($this->department, $this->block);
    }

    protected function tearDown(): void
    {
        $this->stopDisposableDatabase();
        parent::tearDown();
    }

    private function blockAccount(?Section $block = null, string $representative = 'Bea Prieto'): User
    {
        return $this->makeAccount($this->department, 'rep', ['section_id' => ($block ?? $this->block)->id, 'name' => $representative]);
    }

    private function form(array $changes = []): array
    {
        return array_replace(['role' => 'rep', 'name' => 'Bea Prieto', 'email' => 'bea.'.uniqid().'@gmail.com',
            'block_id' => $this->block->id, 'password' => 'Starting123'], $changes);
    }

    // ---- the admin makes block accounts ----

    public function test_admin_makes_the_account_of_a_block_with_its_representative_named(): void
    {
        $this->signedInAs($this->admin)->post('/accounts', $this->form())
            ->assertRedirect('/accounts')
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Made the block account for '.$this->block->name.' (representative: Bea Prieto).'));

        $account = User::where('role', 'rep')->where('section_id', $this->block->id)->sole();
        $this->assertSame('Bea Prieto', $account->name);
        $this->assertSame('Block account', $account->roleLabel());
        $this->assertSame($this->block->name, $account->displayName());
    }

    public function test_a_block_has_one_account_only(): void
    {
        $first = $this->blockAccount();

        $this->signedInAs($this->admin)->from('/accounts')->post('/accounts', $this->form(['name' => 'Second Person']))
            ->assertRedirect('/accounts')
            ->assertSessionHasErrorsIn('add', ['block_id' => $this->block->name.' already has a block account (representative: Bea Prieto). Each block has one account: edit that one instead, for example to change its representative.']);

        $this->assertSame([$first->id], User::where('role', 'rep')->where('section_id', $this->block->id)->pluck('id')->all());
    }

    public function test_another_block_can_have_its_own_account(): void
    {
        $this->blockAccount();
        $other = $this->makeBlock();
        $this->giveBlockAClass($this->department, $other);

        $this->signedInAs($this->admin)->post('/accounts', $this->form(['block_id' => $other->id, 'name' => 'Maria Santos']))
            ->assertRedirect('/accounts')->assertSessionHasNoErrors();
        $this->assertSame(1, User::where('role', 'rep')->where('section_id', $other->id)->count());
    }

    public function test_an_account_cannot_be_moved_to_a_block_that_already_has_one(): void
    {
        $this->blockAccount();
        $other = $this->makeBlock();
        $this->giveBlockAClass($this->department, $other);
        $second = $this->blockAccount($other, 'Maria Santos');

        $this->signedInAs($this->admin)->from('/accounts/'.$second->id)->put('/accounts/'.$second->id, [
            'role' => 'rep', 'name' => 'Maria Santos', 'email' => $second->email, 'block_id' => $this->block->id, 'is_active' => 1,
        ])->assertSessionHasErrorsIn('details', 'block_id');
        $this->assertSame($other->id, $second->fresh()->section_id);

        // A lab staff account turned into a block account follows the same rule
        $staff = $this->makeAccount($this->department, 'lab_staff', ['name' => 'Lab Assistant']);
        $this->put('/accounts/'.$staff->id, ['role' => 'rep', 'name' => 'Lab Assistant', 'email' => $staff->email, 'block_id' => $this->block->id, 'is_active' => 1])
            ->assertSessionHasErrorsIn('details', 'block_id');
        $this->assertSame('lab_staff', $staff->fresh()->role);
    }

    // ---- the representative is changeable ----

    public function test_admin_changes_the_representative_and_the_account_stays_the_blocks(): void
    {
        $account = $this->blockAccount();

        $this->signedInAs($this->admin)->put('/accounts/'.$account->id, [
            'role' => 'rep', 'name' => 'Carlo Reyes', 'email' => $account->email, 'block_id' => $this->block->id, 'is_active' => 1,
        ])->assertRedirect('/accounts/'.$account->id)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Saved '.$this->block->name.'. Its representative is now Carlo Reyes.');

        $fresh = $account->fresh();
        $this->assertSame('Carlo Reyes', $fresh->name);
        $this->assertSame($this->block->id, $fresh->section_id);
        $this->assertSame($account->email, $fresh->email, 'same login');
    }

    public function test_the_block_changes_its_own_representative_in_my_account(): void
    {
        $account = $this->blockAccount();

        $this->signedInAs($account)->get('/account')->assertOk()
            ->assertSee('Representative’s name')
            ->assertSee('Change it when a new representative takes over.');

        $this->put('/account', ['name' => 'Carlo Reyes', 'email' => $account->email, 'school_id' => '2021-00123-MN-0'])
            ->assertRedirect('/account')
            ->assertSessionHas('status', 'Saved. '.$this->block->name.'’s representative is now Carlo Reyes.');
        $this->assertSame('Carlo Reyes', $account->fresh()->name);

        $this->put('/account', ['name' => '', 'email' => $account->email, 'school_id' => '2021-00123-MN-0'])
            ->assertSessionHasErrorsIn('info', ['name' => 'Type the name of your block’s representative.']);
    }

    public function test_lab_staff_still_have_a_plain_name(): void
    {
        $staff = $this->makeAccount($this->department, 'lab_staff', ['name' => 'Lab Assistant']);
        $this->signedInAs($staff)->get('/account')->assertOk()->assertDontSee('Representative’s name');
        $this->put('/account', ['name' => 'Lab Assistant Two', 'email' => $staff->email])
            ->assertSessionHas('status', 'Saved your information.');
    }

    // ---- what the pages call it ----

    public function test_the_pages_call_the_account_by_its_block_and_name_the_representative(): void
    {
        $account = $this->blockAccount();

        $this->signedInAs($account)->get('/rep')->assertOk()
            ->assertSee('Welcome, '.$this->block->name)
            ->assertSee('Representative: Bea Prieto')
            ->assertSee('Block account')
            ->assertDontSee('Block rep');

        $this->signedInAs($this->admin)->get('/accounts')->assertOk()
            ->assertSee('Block accounts')
            ->assertSee('Representative')
            ->assertSee($this->block->name)
            ->assertSee('Bea Prieto')
            ->assertDontSee('Block reps');
        $this->get('/accounts/'.$account->id)->assertOk()
            ->assertSee('Edit '.$this->block->name)
            ->assertSee('Representative: Bea Prieto')
            ->assertSee('Representative’s name');
    }

    // ---- bookings keep the representative they were made under ----

    public function test_a_booking_records_the_representative_and_keeps_it_when_the_representative_changes(): void
    {
        $account = $this->blockAccount();
        $room = $this->makeRoom($this->department);
        $staff = $this->makeAccount($this->department, 'lab_staff', ['name' => 'Lab Assistant']);

        $this->signedInAs($account)->post('/book', ['for' => 'other', 'date' => '2026-11-10', 'from' => '13:00', 'to' => '15:00',
            'need' => 'room', 'room' => $room->id, 'purpose' => 'Group study'])->assertSessionHasNoErrors();
        $booking = Reservation::where('user_id', $account->id)->sole();
        $this->assertSame('Bea Prieto', $booking->rep_name);

        // A new representative takes over
        $account->update(['name' => 'Carlo Reyes']);

        $this->signedInAs($staff)->get('/bookings/'.$booking->id)->assertOk()
            ->assertSee($this->block->name.' · Rep: Bea Prieto')
            ->assertDontSee('Rep: Carlo Reyes');
        $this->get('/requests')->assertOk()->assertSee($this->block->name.' · Rep: Bea Prieto');

        // What the block books from now on carries the new name
        $this->signedInAs($account->fresh())->post('/book', ['for' => 'other', 'date' => '2026-11-11', 'from' => '13:00', 'to' => '15:00',
            'need' => 'room', 'room' => $room->id, 'purpose' => 'Second study'])->assertSessionHasNoErrors();
        $this->assertSame(['Bea Prieto', 'Carlo Reyes'], Reservation::where('user_id', $account->id)->orderBy('id')->pluck('rep_name')->all());
    }
}
