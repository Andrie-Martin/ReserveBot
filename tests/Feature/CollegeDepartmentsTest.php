<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentConfig;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\Concerns\UsesDisposableDatabase;
use Tests\TestCase;

/**
 * The College admin sets departments up: it adds a department (which starts empty, with the usual booking rules),
 * can rename it, and gives it Admins. Database-backed: see Tests\Concerns\UsesDisposableDatabase.
 */
class CollegeDepartmentsTest extends TestCase
{
    use UsesDisposableDatabase;

    private User $college;

    private Department $cpe;

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
        $this->cpeAdmin = $this->makeAccount($this->cpe, 'admin', ['name' => 'Cpe Office Admin']);
    }

    protected function tearDown(): void
    {
        $this->stopDisposableDatabase();
        parent::tearDown();
    }

    // ---- adding a department ----

    public function test_the_college_admin_adds_a_department_that_starts_empty_with_the_usual_rules(): void
    {
        $name = 'Electronics Engineering '.uniqid();

        $this->signedInAs($this->college)->get('/college')->assertOk()->assertSee('+ Add a department');
        $response = $this->post('/college/departments', ['name' => '  '.str_replace(' ', '   ', $name).' ', 'contact_email' => ' ECE.Office@Gmail.com ']);

        $department = Department::where('name', $name)->sole();
        $response->assertRedirect('/college/departments/'.$department->id)
            ->assertSessionHas('status', fn ($message) => str_contains($message, 'Added '.$name.'.') && str_contains($message, 'give it an Admin'));
        $this->assertSame('ece.office@gmail.com', $department->contact_email);
        $this->assertNotSame('', $department->slug);
        $this->assertLessThanOrEqual(30, strlen($department->slug));

        // The usual booking rules, and nothing else yet
        $rules = DepartmentConfig::where('department_id', $department->id)->sole();
        $this->assertSame('Mon,Tue,Wed,Thu,Fri,Sat', $rules->open_days);
        $this->assertTrue((bool) $rules->requires_approval);
        $this->assertSame(0, $department->rooms()->count());
        $this->assertSame(0, $department->users()->count());
        $this->assertSame(0, $department->classSchedules()->count());
        $this->assertSame(0, $department->equipmentCategories()->count());

        // It is on the Departments page, marked as having no Admin, and its own page asks for the first one
        $this->get('/college')->assertOk()->assertSee($name)->assertSee('This department has no Admin yet.');
        $this->get('/college/departments/'.$department->id)->assertOk()
            ->assertSee('Admins of '.$name)->assertSee('to make this department’s first Admin');
    }

    public function test_a_department_needs_a_name_that_no_other_department_has(): void
    {
        $this->signedInAs($this->college)->from('/college');
        $before = Department::count();

        $this->post('/college/departments', ['name' => '   '])->assertRedirect('/college')
            ->assertSessionHasErrorsIn('department', ['name' => 'Type the department’s name.']);
        $this->post('/college/departments', ['name' => strtoupper($this->cpe->name)])
            ->assertSessionHasErrorsIn('department', ['name' => 'There is already a department with that name.']);
        $this->post('/college/departments', ['name' => str_repeat('x', 101)])->assertSessionHasErrorsIn('department', 'name');
        $this->post('/college/departments', ['name' => 'Good Name '.uniqid(), 'contact_email' => 'not-an-email'])
            ->assertSessionHasErrorsIn('department', 'contact_email');

        $this->assertSame($before, Department::count());
    }

    public function test_two_departments_with_alike_names_get_different_internal_codes(): void
    {
        $this->signedInAs($this->college);
        $stem = 'Mechanical Engineering Department '.uniqid();     // longer than the 30-character code
        $this->post('/college/departments', ['name' => $stem.' North'])->assertSessionHasNoErrors();
        $this->post('/college/departments', ['name' => $stem.' South'])->assertSessionHasNoErrors();

        $slugs = Department::where('name', 'like', $stem.'%')->pluck('slug');
        $this->assertCount(2, $slugs);
        $this->assertCount(2, $slugs->unique());
        $slugs->each(fn ($slug) => $this->assertLessThanOrEqual(30, strlen($slug)));
    }

    // ---- renaming ----

    public function test_the_college_admin_renames_a_department_and_its_accounts_see_the_new_name(): void
    {
        $this->signedInAs($this->college)->put('/college/departments/'.$this->cpe->id, ['name' => 'Computer Engineering Renamed', 'contact_email' => ''])
            ->assertRedirect('/college/departments/'.$this->cpe->id)->assertSessionHas('status', 'Saved Computer Engineering Renamed.');
        $this->assertSame('Computer Engineering Renamed', $this->cpe->fresh()->name);
        $this->assertNull($this->cpe->fresh()->contact_email);

        // Keeping its own name is fine; taking another department's is not
        $this->put('/college/departments/'.$this->cpe->id, ['name' => 'Computer Engineering Renamed'])->assertSessionHasNoErrors();
        $other = $this->makeDepartment('Another Test Department '.uniqid());
        $this->put('/college/departments/'.$this->cpe->id, ['name' => $other->name])
            ->assertSessionHasErrorsIn('department', ['name' => 'There is already a department with that name.']);

        $this->signedInAs($this->cpeAdmin)->get('/admin')->assertOk()->assertSee('Computer Engineering Renamed');
    }

    // ---- only the College admin ----

    public function test_nobody_else_can_add_or_rename_a_department(): void
    {
        $this->post('/college/departments', ['name' => 'Guest Department'])->assertRedirect('/login');

        $block = $this->makeBlock();
        foreach ([$this->cpeAdmin, $this->makeAccount($this->cpe, 'lab_staff'), $this->makeAccount($this->cpe, 'rep', ['section_id' => $block->id])] as $account) {
            $this->signedInAs($account);
            $this->post('/college/departments', ['name' => 'Sneaky Department'])->assertForbidden();
            $this->put('/college/departments/'.$this->cpe->id, ['name' => 'Sneaky Name'])->assertForbidden();
        }
        $this->assertSame(0, Department::whereIn('name', ['Guest Department', 'Sneaky Department'])->count());
        $this->assertSame('Computer Engineering Test Department', $this->cpe->fresh()->name);
    }

    // ---- a new department is usable by its Admin straight away ----

    public function test_the_first_admin_of_a_new_department_can_open_every_page_while_it_is_still_empty(): void
    {
        $name = 'Civil Engineering '.uniqid();
        $this->signedInAs($this->college)->post('/college/departments', ['name' => $name])->assertSessionHasNoErrors();
        $department = Department::where('name', $name)->sole();
        $this->post('/college/departments/'.$department->id.'/admins', ['name' => 'Civil Admin', 'email' => 'civil.'.uniqid().'@gmail.com', 'password' => 'Starting123'])
            ->assertSessionHasNoErrors();
        $admin = User::where('department_id', $department->id)->sole();
        $admin->forceFill(['must_change_password' => false, 'email_verified_at' => now()])->save();

        $this->signedInAs($admin);
        $this->get('/admin')->assertOk()->assertSee($name);
        foreach (['/today', '/requests', '/rooms', '/manage/rooms', '/manage/equipment', '/manage/rules', '/manage/timetable', '/accounts', '/account'] as $page) {
            $this->get($page)->assertOk();
        }
        // and can start adding its data: a room
        $this->post('/manage/rooms', ['name' => 'CE-'.substr(uniqid(), -6)])->assertSessionHasNoErrors();
        $this->assertSame(1, $department->rooms()->count());
    }
}
