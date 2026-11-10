<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Services\DepartmentSetup;
use Carbon\CarbonImmutable;
use Tests\Concerns\UsesDisposableDatabase;
use Tests\TestCase;

/**
 * A department the College admin just added is empty. Its Admin (and lab staff) are asked, on their home page, to
 * add what it needs: rooms, the timetable and block accounts (needed), equipment and lab staff accounts (optional).
 * The card goes away once the needed steps are done, and the College admin sees what is still missing.
 * Database-backed: see Tests\Concerns\UsesDisposableDatabase.
 */
class DepartmentSetupTest extends TestCase
{
    use UsesDisposableDatabase;

    private Department $new;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startDisposableDatabase();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 08:00', 'Asia/Manila'));
        $this->new = $this->makeDepartment('Brand New Test Department');
        $this->admin = $this->makeAccount($this->new, 'admin', ['name' => 'New Dept Admin']);
    }

    protected function tearDown(): void
    {
        $this->stopDisposableDatabase();
        parent::tearDown();
    }

    /** [step key => done?] as DepartmentSetup sees the new department now */
    private function done(): array
    {
        return collect(DepartmentSetup::for($this->new, $this->admin)['steps'])->pluck('done', 'key')->all();
    }

    public function test_the_admin_of_an_empty_department_is_asked_for_its_data(): void
    {
        $page = $this->signedInAs($this->admin)->get('/admin')->assertOk();

        $page->assertSee('Set up Brand New Test Department')
            ->assertSee('0 of 3 so far')
            ->assertSeeInOrder(['Add your rooms', 'Add the class timetable', 'List your equipment', 'Make lab staff accounts', 'Make the block accounts'])
            ->assertSee('Needed')->assertSee('Optional')
            ->assertSee('Booking rules are already set to the usual ones');
        // Each step links to the page where it is done
        foreach (['manage.rooms', 'manage.timetable', 'manage.equipment', 'accounts.index'] as $route) {
            $page->assertSee('href="'.route($route).'"', false);
        }

        $setup = DepartmentSetup::for($this->new, $this->admin);
        $this->assertFalse($setup['complete']);
        $this->assertSame(['rooms', 'timetable', 'blocks'], $setup['left']);
        $this->assertSame(['rooms' => true, 'timetable' => true, 'equipment' => false, 'staff' => false, 'blocks' => true],
            collect($setup['steps'])->pluck('needed', 'key')->all());
    }

    public function test_each_step_is_ticked_off_as_the_data_is_added_and_the_card_leaves_when_the_needed_ones_are_done(): void
    {
        $this->assertSame(['rooms' => false, 'timetable' => false, 'equipment' => false, 'staff' => false, 'blocks' => false], $this->done());

        // 1. a room
        $room = $this->makeRoom($this->new);
        $this->assertTrue($this->done()['rooms']);
        $this->signedInAs($this->admin)->get('/admin')->assertOk()->assertSee('Set up Brand New Test Department')->assertSee('1 of 3 so far');

        // 2. the timetable (a class for a block)
        $block = $this->makeBlock();
        $this->giveBlockAClass($this->new, $block, $room);
        $this->assertTrue($this->done()['timetable']);
        $this->get('/admin')->assertOk()->assertSee('2 of 3 so far');

        // 3. the block's account: the last needed step. Equipment and lab staff are optional, so the card goes away without them.
        $this->makeAccount($this->new, 'rep', ['section_id' => $block->id, 'name' => 'Ella Cruz']);
        $setup = DepartmentSetup::for($this->new, $this->admin);
        $this->assertTrue($setup['complete']);
        $this->assertSame([], $setup['left']);
        $this->assertFalse($this->done()['equipment']);
        $this->assertFalse($this->done()['staff']);
        $this->get('/admin')->assertOk()->assertDontSee('Set up Brand New Test Department')->assertSee('Welcome, New Dept Admin');
    }

    public function test_optional_steps_count_too_but_never_keep_the_card_open(): void
    {
        $this->makeEquipment($this->new);
        $this->makeAccount($this->new, 'lab_staff');
        $done = $this->done();
        $this->assertTrue($done['equipment']);
        $this->assertTrue($done['staff']);

        // Still open: the three needed steps are missing
        $this->assertSame(['rooms', 'timetable', 'blocks'], DepartmentSetup::for($this->new, $this->admin)['left']);
        $this->signedInAs($this->admin)->get('/admin')->assertOk()->assertSee('Set up Brand New Test Department')->assertSee('(1 so far)');
    }

    public function test_lab_staff_see_the_steps_they_can_do_and_are_told_the_admin_makes_the_accounts(): void
    {
        $staff = $this->makeAccount($this->new, 'lab_staff', ['name' => 'New Dept Staff']);

        $page = $this->signedInAs($staff)->get('/staff')->assertOk()->assertSee('Set up Brand New Test Department');
        foreach (['manage.rooms', 'manage.timetable', 'manage.equipment'] as $route) {
            $page->assertSee('href="'.route($route).'"', false);
        }
        $page->assertDontSee('href="'.route('accounts.index').'"', false)       // lab staff can't open Accounts
            ->assertSee('Your department’s Admin does this');

        $links = collect(DepartmentSetup::for($this->new, $staff)['steps'])->pluck('url', 'key');
        $this->assertNull($links['staff']);
        $this->assertNull($links['blocks']);
        $this->assertNotNull($links['rooms']);
    }

    public function test_a_department_that_has_everything_and_a_block_account_never_see_the_card(): void
    {
        $block = $this->makeBlock();
        $this->giveBlockAClass($this->new, $block);          // also adds a room
        $rep = $this->makeAccount($this->new, 'rep', ['section_id' => $block->id]);
        $staff = $this->makeAccount($this->new, 'lab_staff');

        $this->signedInAs($this->admin)->get('/admin')->assertOk()->assertDontSee('Set up Brand New Test Department');
        $this->signedInAs($staff)->get('/staff')->assertOk()->assertDontSee('Set up Brand New Test Department');
        $this->signedInAs($rep)->get('/rep')->assertOk()->assertDontSee('Set up Brand New Test Department');
    }

    public function test_one_departments_data_never_counts_for_another(): void
    {
        $other = $this->makeDepartment('Other Full Test Department');
        $block = $this->makeBlock();
        $this->giveBlockAClass($other, $block);
        $this->makeAccount($other, 'rep', ['section_id' => $block->id]);
        $this->makeEquipment($other);
        $this->makeAccount($other, 'lab_staff');

        $counts = DepartmentSetup::counts([$this->new->id, $other->id]);
        $this->assertSame(['rooms' => 0, 'timetable' => 0, 'equipment' => 0, 'staff' => 0, 'blocks' => 0], $counts[$this->new->id]);
        $this->assertSame(['rooms' => 1, 'timetable' => 1, 'equipment' => 1, 'staff' => 1, 'blocks' => 1], $counts[$other->id]);
        $this->assertTrue(DepartmentSetup::fromCounts($counts[$other->id])['complete']);
        $this->assertFalse(DepartmentSetup::fromCounts($counts[$this->new->id])['complete']);
    }

    public function test_the_college_admin_sees_which_departments_are_not_set_up_yet(): void
    {
        $college = User::create(['department_id' => null, 'role' => 'college_admin', 'name' => 'CEA College Admin',
            'email' => 'college.'.uniqid().'@gmail.com', 'password' => 'Starting123', 'is_active' => true, 'must_change_password' => false]);
        $college->forceFill(['email_verified_at' => now()])->save();

        $this->signedInAs($college)->get('/college')->assertOk()
            ->assertSee('Still to set up: rooms, the timetable and block accounts.');
        $this->get('/college/departments/'.$this->new->id)->assertOk()
            ->assertSee('This department isn’t set up yet. Still to add: rooms, the timetable and block accounts.');

        $room = $this->makeRoom($this->new);
        $block = $this->makeBlock();
        $this->giveBlockAClass($this->new, $block, $room);
        $this->get('/college')->assertOk()->assertSee('Still to set up: block accounts.');

        $this->makeAccount($this->new, 'rep', ['section_id' => $block->id]);
        $this->get('/college')->assertOk()->assertDontSee('Still to set up');
        $this->get('/college/departments/'.$this->new->id)->assertOk()->assertDontSee('isn’t set up yet');
    }

    public function test_the_missing_steps_read_as_a_plain_list(): void
    {
        $this->assertSame('rooms, the timetable and block accounts', DepartmentSetup::leftInWords(['rooms', 'timetable', 'blocks']));
        $this->assertSame('the timetable and block accounts', DepartmentSetup::leftInWords(['timetable', 'blocks']));
        $this->assertSame('block accounts', DepartmentSetup::leftInWords(['blocks']));
        $this->assertSame('', DepartmentSetup::leftInWords([]));
    }
}
