<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Section;
use App\Models\User;
use App\Notifications\ConnectEmail;
use App\Notifications\ResetPasswordLink;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Opt-in HTTP regression suite. Requires an independently provisioned, disposable
 * MariaDB schema; NEVER migrates/seeds/truncates. No delivery or live storage.
 * Enable only after reviewer authorization: RB_AUTH_HTTP_TESTS=1 and
 * RB_AUTH_TEST_DATABASE=<exact disposable database ending in _test>.
 * These checks are NOT browser E2E tests. Laravel normally bypasses CSRF in tests;
 * the CSRF test explicitly changes that testing-only bypass for its request.
 */
final class AuthWorkflowTest extends TestCase
{
    private bool $transactionStarted = false;
    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RB_AUTH_HTTP_TESTS') !== '1') {
            $this->markTestSkipped('Requires explicit authorization and a disposable, provisioned MariaDB test database.');
        }
        $connection = config('database.default');
        $settings = config('database.connections.'.$connection);
        $expected = (string) getenv('RB_AUTH_TEST_DATABASE');
        $this->assertTrue($this->app->environment('testing'));
        $this->assertNotEmpty($expected);
        $this->assertStringEndsWith('_test', $expected);
        $this->assertSame('mysql', $settings['driver']);
        $this->assertSame($expected, $settings['database']);
        $this->assertEmpty($settings['url'] ?? null, 'DB URL must not override isolation.');
        $this->assertSame('array', config('session.driver'));
        $this->assertSame('array', config('cache.default'));
        $this->assertSame('array', config('mail.default'));
        $this->assertSame($expected, DB::selectOne('select database() as name')->name);
        DB::beginTransaction();
        $this->transactionStarted = true;
        Notification::fake();
        Storage::fake('local');
        $this->department = Department::create(['name' => 'HTTP test department', 'slug' => 'test-'.Str::lower(Str::random(12))]);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->transactionStarted) {
                while (DB::transactionLevel() > 0) DB::rollBack();
            }
        } finally { parent::tearDown(); }
    }

    private function user(string $role = 'rep', array $attributes = []): User
    {
        return User::create(array_replace([
            'department_id' => $this->department->id, 'name' => 'Example account',
            'email' => 'http-'.Str::lower(Str::random(16)).'@gmail.com',
            'role' => $role, 'password' => 'Starting123', 'is_active' => true,
            'must_change_password' => false, 'school_id' => null,
        ], $attributes));
    }

    private function info(User $user, array $changes = []): array
    {
        return array_replace(['name' => $user->name, 'email' => $user->email, 'school_id' => '2021-00123-MN-0'], $changes);
    }

    public function test_guest_gates_role_redirects_login_normalization_and_logout(): void
    {
        $this->get('/account')->assertRedirect('/login');
        foreach (['rep' => '/rep', 'lab_staff' => '/staff', 'admin' => '/admin'] as $role => $home) {
            $user = $this->user($role);
            $this->withSession(['probe' => 'preserve-until-logout']);
            $oldId = session()->getId();
            $this->post('/login', ['email' => ' '.strtoupper($user->email).' ', 'password' => 'Starting123'])->assertRedirect($home);
            $this->assertAuthenticatedAs($user);
            $this->assertNotSame($oldId, session()->getId());
            $this->get('/login')->assertRedirect($home);
            $this->get('/')->assertRedirect($home);
            $oldToken = session()->token();
            $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('probe');
            $this->assertGuest();
            $this->assertNotSame($oldToken, session()->token());
        }
    }

    public function test_wrong_and_disabled_credentials_remain_generic_and_never_flash_password(): void
    {
        $user = $this->user();
        foreach ([true, false] as $active) {
            $user->update(['is_active' => $active]);
            $this->from('/login')->post('/login', ['email' => $user->email, 'password' => $active ? 'Wrong123' : 'Starting123'])
                ->assertRedirect('/login')->assertSessionHasErrors(['email' => 'Wrong email or password.'])
                ->assertSessionHas('_old_input.email', $user->email)->assertSessionMissing('_old_input.password');
            $this->assertGuest();
        }
    }

    // ---- QA F-01: login throttling (5 failures/min per email+IP, 20/min per IP) ----

    private function loginAs(string $email, string $password, string $ip = '203.0.113.10'): \Illuminate\Testing\TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->from('/login')->post('/login', ['email' => $email, 'password' => $password]);
    }

    private function assertWrongLogin(\Illuminate\Testing\TestResponse $response): void
    {
        $response->assertRedirect('/login')->assertSessionHasErrors(['email' => 'Wrong email or password.']);
        $this->assertGuest();
    }

    /** Asserts the throttled reply and returns the message shown to the visitor. */
    private function assertThrottled(\Illuminate\Testing\TestResponse $response): string
    {
        $response->assertRedirect('/login')->assertSessionHasErrors('email');
        $message = session('errors')->first('email');
        $this->assertMatchesRegularExpression('/^Too many login attempts\. Please try again in \d+ seconds?\.$/', $message);
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertGuest();

        return $message;
    }

    public function test_repeated_failures_are_throttled_and_even_the_right_password_is_blocked_while_locked(): void
    {
        $user = $this->user();
        for ($i = 0; $i < 5; $i++) {
            $this->assertWrongLogin($this->loginAs($user->email, 'Wrong123'));
        }
        $this->assertThrottled($this->loginAs($user->email, 'Wrong123'));
        $this->assertThrottled($this->loginAs($user->email, 'Starting123'));
        // Typing the same address differently (case, spaces) shares the same counter.
        $this->assertThrottled($this->loginAs(' '.strtoupper($user->email).' ', 'Starting123'));
    }

    public function test_unknown_accounts_are_throttled_identically_so_nothing_reveals_which_emails_exist(): void
    {
        $real = $this->user();
        $ghost = 'nobody-'.Str::lower(Str::random(10)).'@gmail.com';
        foreach ([$real->email, $ghost] as $email) {
            for ($i = 0; $i < 5; $i++) {
                $this->assertWrongLogin($this->loginAs($email, 'Wrong123', $email === $ghost ? '203.0.113.20' : '203.0.113.21'));
            }
        }
        $realMessage = $this->assertThrottled($this->loginAs($real->email, 'Wrong123', '203.0.113.21'));
        $ghostMessage = $this->assertThrottled($this->loginAs($ghost, 'Wrong123', '203.0.113.20'));
        // Same wording and same format for a real and a made-up account (seconds may differ by a tick).
        $this->assertSame(preg_replace('/\d+/', 'N', $realMessage), preg_replace('/\d+/', 'N', $ghostMessage));
    }

    public function test_one_locked_account_or_ip_does_not_lock_other_users(): void
    {
        $alice = $this->user();
        $bob = $this->user();
        for ($i = 0; $i < 6; $i++) {
            $this->loginAs($alice->email, 'Wrong123', '203.0.113.30');
        }
        $this->assertThrottled($this->loginAs($alice->email, 'Starting123', '203.0.113.30'));

        // A different account from the same IP, and the same account from another IP, are unaffected.
        $this->loginAs($bob->email, 'Starting123', '203.0.113.30')->assertRedirect('/rep');
        $this->assertAuthenticatedAs($bob);
        $this->post('/logout');
        $this->loginAs($alice->email, 'Starting123', '203.0.113.31')->assertRedirect('/rep');
        $this->assertAuthenticatedAs($alice);
    }

    public function test_successful_login_clears_the_failure_count_and_keeps_session_behaviour(): void
    {
        $user = $this->user();
        for ($i = 0; $i < 4; $i++) {
            $this->assertWrongLogin($this->loginAs($user->email, 'Wrong123'));
        }
        $this->withSession(['probe' => 'x']);
        $oldId = session()->getId();
        $this->loginAs($user->email, 'Starting123')->assertRedirect('/rep');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldId, session()->getId());
        $this->post('/logout')->assertRedirect('/login');
        // The counter restarted: four more failures are still plain "wrong password", not throttled.
        for ($i = 0; $i < 4; $i++) {
            $this->assertWrongLogin($this->loginAs($user->email, 'Wrong123'));
        }
    }

    public function test_login_works_again_after_the_retry_window(): void
    {
        $user = $this->user();
        for ($i = 0; $i < 6; $i++) {
            $this->loginAs($user->email, 'Wrong123');
        }
        $this->assertThrottled($this->loginAs($user->email, 'Starting123'));
        $this->assertLessThanOrEqual(60, (int) $this->loginAs($user->email, 'Starting123')->headers->get('Retry-After'));

        $this->travel(61)->seconds();
        $this->loginAs($user->email, 'Starting123')->assertRedirect('/rep');
        $this->assertAuthenticatedAs($user);
    }

    public function test_one_ip_spraying_many_emails_is_throttled_but_other_ips_are_not(): void
    {
        $user = $this->user();
        for ($i = 0; $i < 20; $i++) {
            $this->assertWrongLogin($this->loginAs('spray'.$i.'-'.Str::lower(Str::random(6)).'@gmail.com', 'Wrong123', '203.0.113.40'));
        }
        $this->assertThrottled($this->loginAs('fresh-'.Str::lower(Str::random(6)).'@gmail.com', 'Wrong123', '203.0.113.40'));
        $this->assertThrottled($this->loginAs($user->email, 'Starting123', '203.0.113.40'));
        $this->loginAs($user->email, 'Starting123', '203.0.113.41')->assertRedirect('/rep');
    }

    public function test_throttled_visitor_sees_the_retry_message_on_the_login_page_and_it_is_shown_once(): void
    {
        $user = $this->user();
        for ($i = 0; $i < 5; $i++) {
            $this->loginAs($user->email, 'Wrong123');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->followingRedirects()->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'Wrong123'])
            ->assertOk()->assertSee('Too many login attempts. Please try again in')->assertSee('name="password"', false);
        $this->get('/login')->assertOk()->assertDontSee('Too many login attempts');
    }

    public function test_a_very_long_email_is_still_throttled(): void
    {
        $long = str_repeat('x', 400).'@example.com';
        for ($i = 0; $i < 5; $i++) {
            $this->assertWrongLogin($this->loginAs($long, 'Wrong123', '203.0.113.50'));
        }
        $this->assertThrottled($this->loginAs($long, 'Wrong123', '203.0.113.50'));
    }

    public function test_disabled_session_and_onboarding_gates(): void
    {
        $user = $this->user('rep', ['is_active' => false]);
        $this->actingAs($user)->get('/account')->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
        $user->update(['is_active' => true, 'must_change_password' => true]);
        $this->actingAs($user)->get('/account')->assertRedirect('/welcome');
        $this->get('/welcome')->assertOk()->assertSee('school_id', false);
        $this->get('/photos/'.$user->id)->assertNotFound();
        $this->post('/logout')->assertRedirect('/login');
    }

    public function test_onboarding_password_policy_confirmation_school_id_and_completion(): void
    {
        $user = $this->user('rep', ['must_change_password' => true]);
        $this->actingAs($user);
        foreach ([['password' => 'Starting123', 'password_confirmation' => 'Starting123'], ['password' => 'short', 'password_confirmation' => 'other']] as $data) {
            $this->from('/welcome')->put('/welcome', $data)->assertSessionHasErrors(['password', 'school_id']);
        }
        $this->put('/welcome', ['password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123', 'school_id' => '202100123mn0'])
            ->assertRedirect('/account')->assertSessionHas('status');
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertSame('2021-00123-MN-0', $user->fresh()->school_id);
        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
    }

    public function test_staff_optional_and_admin_absent_onboarding_school_fields(): void
    {
        foreach (['lab_staff', 'admin'] as $role) {
            $user = $this->user($role, ['must_change_password' => true]);
            $page = $this->actingAs($user)->get('/welcome')->assertOk();
            if ($role === 'admin') $page->assertDontSee('name="school_id"', false);
            $this->put('/welcome', ['password' => 'OwnPassword123', 'password_confirmation' => 'OwnPassword123'])->assertRedirect('/account');
            $this->assertFalse($user->fresh()->must_change_password);
            // End this user's session before the next role signs in.
            $this->post('/logout')->assertRedirect('/login');
        }
    }

    public function test_information_bag_email_password_rule_legacy_exception_and_connection_reset(): void
    {
        $user = $this->user('rep', ['email' => 'legacy@example.invalid']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($user)->put('/account', $this->info($user, ['name' => 'Changed name']))->assertRedirect('/account')->assertSessionHasNoErrors();
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->put('/account', $this->info($user, ['email' => 'new@gmail.com']))->assertSessionHasErrorsIn('info', 'current_password');
        $this->put('/account', $this->info($user, ['email' => 'new@example.invalid', 'current_password' => 'Starting123']))->assertSessionHasErrorsIn('info', 'email');
        $this->put('/account', $this->info($user, ['email' => 'new@gmail.com', 'current_password' => 'Wrong123']))->assertSessionHasErrorsIn('info', 'current_password');
        $this->put('/account', $this->info($user, ['email' => 'new@gmail.com', 'current_password' => 'Starting123']))->assertRedirect('/account')->assertSessionHas('status');
        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user, ConnectEmail::class);
        $this->get('/book')->assertRedirect('/account')->assertSessionHas('problem');
        $this->get('/my-bookings')->assertOk();
    }

    public function test_staff_can_choose_section_but_rep_cannot_change_block_and_admin_has_no_school_field(): void
    {
        $section = Section::create(['name' => 'TEST-'.Str::random(8), 'program' => 'TEST']);
        $staff = $this->user('lab_staff');
        $this->actingAs($staff)->put('/account', $this->info($staff, ['school_id' => null, 'section_id' => $section->id]))->assertSessionHasNoErrors();
        $this->assertSame($section->id, $staff->fresh()->section_id);
        $rep = $this->user();
        $this->actingAs($rep)->put('/account', $this->info($rep, ['section_id' => $section->id]))->assertSessionHasNoErrors();
        $this->assertNull($rep->fresh()->section_id);
        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/account')->assertDontSee('name="school_id"', false);
    }

    public function test_password_change_requires_current_different_confirmed_password_and_invalidates_stale_session(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        foreach ([['Wrong123','Different123','Different123'], ['Starting123','Starting123','Starting123'], ['Starting123','Different123','Mismatch123']] as [$current,$password,$confirmation]) {
            $this->put('/account/password', ['current_password' => $current, 'password' => $password, 'password_confirmation' => $confirmation])->assertSessionHasErrors();
        }
        $oldHash = $user->password;
        $this->put('/account/password', ['current_password' => 'Starting123', 'password' => 'Different123', 'password_confirmation' => 'Different123'])
            ->assertRedirect('/account/password')->assertSessionHas('status');
        $this->assertTrue(Hash::check('Different123', $user->fresh()->password));
        $this->actingAs($user->fresh())->withSession(['password_hash_web' => $oldHash])->get('/account')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_recovery_generic_response_inactive_account_and_mail_failure(): void
    {
        $active = $this->user(); $inactive = $this->user('rep', ['is_active' => false]);
        foreach ([$active->email, $inactive->email, 'missing@example.invalid'] as $email) {
            $this->from('/forgot-password')->post('/forgot-password', ['email' => $email])->assertRedirect('/forgot-password')->assertSessionHas('status', fn ($s) => str_starts_with($s, 'If '));
        }
        Notification::assertSentTo($active, ResetPasswordLink::class);
        Notification::assertNotSentTo($inactive, ResetPasswordLink::class);
        Password::shouldReceive('broker')->once()->andThrow(new \RuntimeException('Test-only mail exception'));
        $this->post('/forgot-password', ['email' => $active->email])->assertSessionHasErrors('email');
    }

    public function test_reset_token_success_reuse_expiry_wrong_email_and_disabled_account(): void
    {
        $user = $this->user('rep', ['must_change_password' => true]);
        $token = Password::broker()->createToken($user);
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'Recovered123', 'password_confirmation' => 'Recovered123'];
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk()->assertSee($token);
        $this->post('/reset-password', array_replace($data, ['email' => 'wrong@example.invalid']))->assertSessionHasErrors('email');
        $this->post('/reset-password', $data)->assertRedirect('/login')->assertSessionHas('status');
        $fresh = $user->fresh();
        $this->assertFalse($fresh->must_change_password);
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertNotNull($fresh->remember_token);
        $this->assertTrue(Hash::check('Recovered123', $fresh->password));
        $this->assertGuest();
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->travel(61)->minutes();
        $data['token'] = Password::broker()->createToken($fresh);
        $this->travel(61)->minutes();
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $data['token'] = Password::broker()->createToken($fresh);
        $fresh->update(['is_active' => false]);
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->travelBack();
    }

    public function test_signed_connection_success_reuse_changed_hash_and_invalid_signature_statuses(): void
    {
        $user = $this->user(); $url = $user->connectEmailUrl();
        $this->get($url)->assertOk()->assertSee('Email connected');
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->get($url)->assertOk();
        $user->update(['email' => 'changed@gmail.com']);
        $this->get($url)->assertOk()->assertSee('This link isn’t working');
        $this->get($url.'&tampered=1')->assertForbidden();
        $expired = URL::temporarySignedRoute('email.connect', now()->subMinute(), ['user' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($expired)->assertForbidden()->assertSee('This link has expired');
    }

    public function test_resend_feedback_rate_limit_and_connected_noop(): void
    {
        $user = $this->user(); $this->actingAs($user);
        $this->from('/account')->post('/account/email-link')->assertRedirect('/account')->assertSessionHas('connect_note');
        Notification::assertSentToTimes($user, ConnectEmail::class, 1);
        $this->post('/account/email-link')->assertSessionHas('connect_note', fn ($s) => str_contains($s, 'seconds'));
        Notification::assertSentToTimes($user, ConnectEmail::class, 1);
        RateLimiter::clear('connect-email:'.$user->id);
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->post('/account/email-link')->assertSessionHas('connect_note', 'Your email is already connected.');
    }

    public function test_email_change_and_resend_keep_mail_failure_feedback(): void
    {
        $user = $this->user();
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('Test-only transport failure'));
        $this->actingAs($user)->put('/account', $this->info($user, ['email' => 'changed@gmail.com', 'current_password' => 'Starting123']))
            ->assertRedirect('/account')->assertSessionHas('problem');
        $this->assertSame('changed@gmail.com', $user->fresh()->email);
        $this->post('/account/email-link')->assertSessionHas('connect_note', fn ($s) => str_contains($s, 'couldn’t be sent'));
    }

    public function test_debug_off_upload_and_server_errors_show_hidden_user_layout(): void
    {
        config(['app.debug' => false]);
        // Test-process routes only; no application route files or deployments change.
        foreach ([413, 500] as $code) {
            \Illuminate\Support\Facades\Route::get('/test-only-error-'.$code, fn () => abort($code));
        }
        $this->get('/test-only-error-413')->assertStatus(413)->assertSee('File too big')->assertDontSee('Log out');
        $this->get('/test-only-error-500')->assertStatus(500)->assertSee('Something went wrong')->assertDontSee('Log out');
    }

    public function test_route_throttles_return_429(): void
    {
        for ($i=0; $i<5; $i++) $this->post('/forgot-password', ['email' => 'missing@example.invalid'])->assertRedirect();
        $this->post('/forgot-password', ['email' => 'missing@example.invalid'])->assertStatus(429);
        for ($i=0; $i<10; $i++) $this->post('/reset-password', [])->assertRedirect();
        $this->post('/reset-password', [])->assertStatus(429);
        $user=$this->user(); $url=$user->connectEmailUrl();
        for ($i=0; $i<20; $i++) $this->get($url)->assertOk();
        $this->get($url)->assertStatus(429);
    }

    public function test_photo_validation_private_access_replacement_and_independent_removal(): void
    {
        if (!function_exists('imagecreatetruecolor')) $this->markTestSkipped('Valid image fixtures require GD.');
        $user=$this->user(); $this->actingAs($user);
        $this->post('/account/photo', [])->assertSessionHasErrorsIn('photo', 'photo');
        foreach ([UploadedFile::fake()->create('not-image.txt', 10, 'text/plain'), UploadedFile::fake()->image('small.png', 50,50), UploadedFile::fake()->image('large.png',100,100)->size(5121)] as $file) {
            $this->post('/account/photo', ['photo'=>$file])->assertSessionHasErrorsIn('photo','photo');
        }
        $this->post('/account/photo', ['photo'=>UploadedFile::fake()->image('face.png',200,200)])->assertRedirect('/account');
        $path=$user->fresh()->photo_path; Storage::disk('local')->assertExists($path);
        $url=$user->fresh()->photoUrl();
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'max-age=604800, private');
        $this->post('/account/photo', ['photo'=>UploadedFile::fake()->image('replacement.png',200,200)])->assertRedirect('/account');
        Storage::disk('local')->assertMissing($path);
        $this->assertNotSame($url,$user->fresh()->photoUrl());
        $staff=$this->user('lab_staff');
        // Each viewer needs a separate session; keep it intact within their own requests.
        $this->post('/logout')->assertRedirect('/login');
        $this->actingAs($staff)->get($user->fresh()->photoUrl())->assertOk();
        $this->post('/logout')->assertRedirect('/login');
        $this->actingAs($this->user())->get($user->fresh()->photoUrl())->assertNotFound();
        $other=Department::create(['name'=>'Other test department','slug'=>'other-'.Str::random(10)]);
        $this->post('/logout')->assertRedirect('/login');
        $this->actingAs($this->user('admin',['department_id'=>$other->id]))->get($user->fresh()->photoUrl())->assertNotFound();
        $path=$user->fresh()->photo_path;
        $this->post('/logout')->assertRedirect('/login');
        $this->actingAs($user->fresh())->delete('/account/photo')->assertRedirect('/account');
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($user->fresh()->photo_path);
        $this->get('/photos/'.$user->id)->assertNotFound();
    }

    public function test_role_denial_not_found_and_csrf_failure_preserve_http_errors(): void
    {
        $this->actingAs($this->user())->get('/accounts')->assertForbidden();
        $this->get('/this-route-does-not-exist')->assertNotFound();
        // Force the middleware's unit-test bypass off without changing application source.
        $this->app->instance('env', 'csrf-verification');
        config(['app.debug'=>false]);
        try { $this->post('/account/email-link', ['_token'=>'expired-token'])->assertStatus(419); }
        finally { $this->app->instance('env', 'testing'); }
    }
}
