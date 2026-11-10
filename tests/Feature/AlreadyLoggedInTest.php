<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Sandbox issue 10. A browser keeps one login for all its tabs. When the login form is sent from a browser that
 * is already logged in (it was still open in another tab), the first account stays and the person is told why,
 * instead of the second login being dropped without a word. Two accounts at the same time work in two browsers
 * or a private window. Needs no database: nothing typed is looked up.
 */
class AlreadyLoggedInTest extends TestCase
{
    private function account(string $role = 'rep', string $name = 'Bea Prieto', string $email = 'bea@gmail.com'): User
    {
        $user = new User(['name' => $name, 'email' => $email, 'role' => $role, 'is_active' => true]);
        $user->id = 501;

        return $user;
    }

    public function test_a_second_login_keeps_the_first_account_and_says_why(): void
    {
        $bea = $this->account();

        $this->actingAs($bea)->from('/login')->post('/login', ['email' => 'staff@gmail.com', 'password' => 'Password123'])
            ->assertRedirect('/rep')
            ->assertSessionHas('login_notice', fn ($message) => str_contains($message, 'already logged in as Bea Prieto (Block rep)')
                && str_contains($message, 'log out first')
                && str_contains($message, 'private window'))
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($bea);
    }

    public function test_each_role_is_sent_to_its_own_home_page(): void
    {
        foreach (['rep' => '/rep', 'lab_staff' => '/staff', 'admin' => '/admin'] as $role => $home) {
            $this->actingAs($this->account($role))->post('/login', ['email' => 'someone.else@gmail.com', 'password' => 'x'])
                ->assertRedirect($home)->assertSessionHas('login_notice');
        }
    }

    public function test_typing_the_same_account_just_goes_home_without_a_message(): void
    {
        $bea = $this->account();
        foreach (['bea@gmail.com', '  BEA@Gmail.com ', ''] as $typed) {
            $this->actingAs($bea)->post('/login', ['email' => $typed, 'password' => 'anything'])
                ->assertRedirect('/rep')->assertSessionMissing('login_notice');
        }
    }

    public function test_nothing_typed_is_checked_or_counted_against_the_login_limit(): void
    {
        $bea = $this->account();
        for ($i = 0; $i < 30; $i++) {   // more than both lockout limits (5 and 20)
            $this->actingAs($bea)->withServerVariables(['REMOTE_ADDR' => '203.0.113.60'])
                ->post('/login', ['email' => 'guess'.$i.'@gmail.com', 'password' => 'Wrong123'])
                ->assertRedirect('/rep')
                ->assertSessionHas('login_notice', fn ($message) => ! str_contains($message, 'Too many login attempts'));
        }
        $this->assertNull(Cache::get('login-ip|203.0.113.60'));
        $this->assertNull(Cache::get('login-ip|203.0.113.60:inflight'));
    }

    public function test_opening_the_login_page_while_logged_in_still_goes_home_quietly(): void
    {
        $this->actingAs($this->account('lab_staff', 'Lab Assistant'))->get('/login')
            ->assertRedirect('/staff')->assertSessionMissing('login_notice');
    }
}
