<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/** Sandbox issue 8: the College admin role's name and home page. Needs no database. */
class CollegeAdminRoleTest extends TestCase
{
    private function account(string $role): User
    {
        $user = new User(['name' => 'Someone', 'email' => 'someone@gmail.com', 'role' => $role, 'is_active' => true]);
        $user->id = 777;
        $user->setRelation('section', null);
        $user->setRelation('department', null);

        return $user;
    }

    public function test_the_role_has_a_name_and_its_own_home_page(): void
    {
        $college = $this->account('college_admin');
        $this->assertSame('College admin', $college->roleLabel());
        $this->assertTrue($college->isCollegeAdmin());
        $this->assertStringEndsWith('/college', $college->homeUrl());
        $this->assertFalse($college->isBlock());
        $this->assertSame('Someone', $college->displayName());
    }

    public function test_the_other_roles_are_department_roles_and_are_not_college_admins(): void
    {
        $this->assertSame(['rep', 'lab_staff', 'admin'], User::DEPARTMENT_ROLES);
        foreach (User::DEPARTMENT_ROLES as $role) {
            $this->assertFalse($this->account($role)->isCollegeAdmin(), $role);
        }
        $this->assertNotContains('college_admin', User::MANAGED_ROLES, 'a department Admin cannot make or edit one');
    }

    public function test_a_college_admin_who_opens_the_login_page_goes_to_the_departments_page(): void
    {
        $this->actingAs($this->account('college_admin'))->get('/login')->assertRedirect('/college');
        $this->actingAs($this->account('college_admin'))->post('/login', ['email' => 'another@gmail.com', 'password' => 'x'])
            ->assertRedirect('/college')
            ->assertSessionHas('login_notice', fn ($message) => str_contains($message, 'already logged in as Someone (College admin)'));
    }
}
