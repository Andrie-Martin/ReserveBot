<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentConfig;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Opt-in HTTP regression suite for the groupmate feedback pass (login/account polish, compact email status, calendar,
 * mobile bookings/timetable, schedule drawer, Today review control and safe Delete for rooms/equipment).
 * Same isolation contract as FrontendConsistencyTest: a disposable, already-migrated MariaDB schema
 * (RB_MANAGE_HTTP_TESTS=1, RB_MANAGE_TEST_DATABASE=..._test), each test inside a rolled-back transaction.
 */
final class GroupFeedbackTest extends TestCase
{
    private bool $transactionStarted = false;
    private Department $department;
    private Department $otherDepartment;
    private Room $room;
    private User $admin;
    private User $staff;
    private User $rep;
    private User $otherAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RB_MANAGE_HTTP_TESTS') !== '1') {
            $this->markTestSkipped('Requires explicit authorization and a disposable, provisioned MariaDB test database.');
        }
        $expected = (string) getenv('RB_MANAGE_TEST_DATABASE');
        $settings = config('database.connections.'.config('database.default'));
        $this->assertTrue($this->app->environment('testing'));
        $this->assertStringEndsWith('_test', $expected);
        $this->assertSame($expected, $settings['database']);
        $this->assertSame('array', config('session.driver'));
        $this->assertSame($expected, DB::selectOne('select database() as name')->name);
        DB::beginTransaction();
        $this->transactionStarted = true;

        $this->department = $this->makeDepartment('Feedback test');
        $this->otherDepartment = $this->makeDepartment('Other feedback test');
        $section = Section::create(['name' => '4-'.Str::random(6), 'program' => 'BSCPE']);
        $this->room = Room::create(['name' => 'Fb-'.Str::random(10), 'capacity' => 30, 'is_active' => true]);
        $this->department->rooms()->attach($this->room);
        $this->admin = $this->person($this->department, 'admin', 'Zed Admin');
        $this->staff = $this->person($this->department, 'lab_staff', 'Lou Staff');
        $this->rep = $this->person($this->department, 'rep', 'Rita Rep', $section->id);
        $this->otherAdmin = $this->person($this->otherDepartment, 'admin', 'Olga Other');
    }

    protected function tearDown(): void
    {
        try {
            if ($this->transactionStarted) {
                while (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    private function makeDepartment(string $name): Department
    {
        $department = Department::create(['name' => $name, 'slug' => 'fb-'.Str::lower(Str::random(12))]);
        DepartmentConfig::create(['department_id' => $department->id] + DepartmentConfig::DEFAULTS);

        return $department;
    }

    private function person(Department $department, string $role, string $name, ?int $sectionId = null, bool $connected = true): User
    {
        $user = User::create([
            'department_id' => $department->id, 'section_id' => $sectionId, 'name' => $name,
            'email' => Str::lower(Str::random(14)).'@gmail.com', 'role' => $role, 'password' => 'Starting123',
            'is_active' => true, 'must_change_password' => false,
        ]);
        if ($connected) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }

    /** auth.session ties a session to one password hash, so each change of user starts a fresh session. */
    private function as(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function booking(string $status, \DateTimeInterface $start, ?User $owner = null, ?Room $room = null, string $purpose = 'Feedback test'): Reservation
    {
        return Reservation::create(['user_id' => ($owner ?? $this->rep)->id, 'room_id' => ($room ?? $this->room)->id, 'purpose' => $purpose,
            'start_time' => $start, 'end_time' => (clone $start)->modify('+1 hour'), 'status' => $status]);
    }

    // ---- 1 & 6: login page / header -------------------------------------------------------------------------------

    public function test_no_student_design_disclaimer_or_floating_descriptor_on_any_guest_or_signed_in_page(): void
    {
        $pages = [
            'login' => $this->get('/login'),
            'forgot' => $this->get('/forgot-password'),
            'reset' => $this->get('/reset-password/'.Str::random(20).'?email=x@example.com'),
        ];
        foreach ($pages as $name => $response) {
            $html = $response->assertOk()->getContent();
            $this->assertStringNotContainsString('Unofficial', $html, $name);
            $this->assertStringNotContainsString('student design', $html, $name);
            $this->assertStringNotContainsString('Not an official', $html, $name);
            $this->assertStringNotContainsString('rb-auth-disclaimer', $html, $name);
            $this->assertStringNotContainsString('Room &amp; equipment reservation system', $html, $name);
            $this->assertStringNotContainsString('rb-header__description', $html, $name);
            $this->assertStringContainsString('PUP College of Engineering', $html, "$name keeps the truthful college identity");
            $this->assertStringContainsString('ReserveBot', $html);
        }
        // The login form is untouched.
        $this->get('/login')->assertSee('name="email"', false)->assertSee('name="password"', false)->assertSee('Forgot your password?');

        foreach ([$this->rep, $this->staff, $this->admin] as $user) {
            $html = $this->as($user)->get('/account')->assertOk()->getContent();
            $this->assertStringNotContainsString('rb-header__description', $html, $user->role);
            $this->assertStringContainsString('PUP College of Engineering', $html);
        }
    }

    // ---- 2: profile photo actions -----------------------------------------------------------------------------------

    public function test_photo_actions_share_one_row_and_remove_cannot_submit_the_upload_form(): void
    {
        foreach ([$this->rep, $this->staff, $this->admin] as $user) {
            $user->forceFill(['photo_path' => 'photos/'.$user->id.'-test.jpg'])->save();
            $html = $this->as($user)->get('/account')->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/<div class="rb-account-photo__actions">\s*<button type="submit"[^>]*>Replace photo<\/button>\s*<button type="submit"[^>]*rb-button--danger-outline[^>]*form="photo-remove-form"[^>]*>Remove photo<\/button>\s*<\/div>/', $html, $user->role.': both actions in one row');
            // Remove belongs to its own DELETE form, never to the upload form.
            $this->assertMatchesRegularExpression('/<form id="photo-remove-form" method="post" action="'.preg_quote(route('account.photo.remove'), '/').'">\s*<input type="hidden" name="_token"[^>]*>\s*<input type="hidden" name="_method" value="DELETE">\s*<\/form>/', $html);
            $this->assertMatchesRegularExpression('/<form id="photo-upload-form" method="post" action="'.preg_quote(route('account.photo'), '/').'" enctype="multipart\/form-data">/', $html);
            $this->assertSame(1, substr_count($html, 'form="photo-remove-form"'));
            $this->assertStringContainsString('accept="image/jpeg,image/png,image/webp"', $html);
        }

        // No photo yet: Upload only, no Remove control and no stray DELETE form.
        $html = $this->as($this->person($this->department, 'rep', 'No Photo'))->get('/account')->assertOk()->getContent();
        $this->assertStringContainsString('>Upload photo</button>', $html);
        $this->assertStringNotContainsString('Remove photo', $html);
        $this->assertStringNotContainsString('photo-remove-form', $html);
    }

    public function test_photo_validation_errors_still_show_with_the_new_action_row(): void
    {
        $response = $this->as($this->rep)->from('/account')->post('/account/photo', []);
        $response->assertRedirect('/account')->assertSessionHasErrors('photo', null, 'photo');
        $this->get('/account')->assertOk()->assertSee('rb-account-photo__actions', false);
    }

    // ---- 3 & 7: compact email status --------------------------------------------------------------------------------

    public function test_connected_email_is_a_compact_status_row_with_accurate_details(): void
    {
        foreach ([$this->rep, $this->staff, $this->admin] as $user) {
            $html = $this->as($user)->get('/account')->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<div class="rb-status rb-tone-success rb-account-email"[^>]*>/', $html, $user->role);
            $this->assertStringContainsString('>Email connected</h2>', $html);
            $this->assertStringContainsString($user->email.' · since '.$user->email_verified_at->format('F j, Y'), $html);
            $this->assertStringNotContainsString('rb-alert', $html, 'no large filled alert box for a routine state');
            $this->assertStringNotContainsString(route('email.resend'), $html, 'nothing to resend once connected');
        }
    }

    public function test_disconnected_email_status_keeps_the_instruction_the_address_and_the_resend_action(): void
    {
        foreach ([['rep', true], ['lab_staff', false], ['admin', false]] as [$role, $mentionsBooking]) {
            $user = $this->person($this->department, $role, 'Unconnected '.$role, null, false);
            $html = $this->as($user)->get('/account')->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<div class="rb-status rb-tone-warning rb-account-email"/', $html, $role);
            $this->assertStringContainsString('>Email not connected</h2>', $html);
            $this->assertStringContainsString('Open the email we sent to <strong>'.$user->email.'</strong> and click “Connect my email”.', $html);
            $this->assertSame($mentionsBooking, str_contains($html, 'You can book once it’s connected.'), $role);
            $this->assertMatchesRegularExpression('/<form method="post" action="'.preg_quote(route('email.resend'), '/').'">\s*<input type="hidden" name="_token"[^>]*>\s*<button type="submit"[^>]*>Send the link again<\/button>/', $html);
            // The shared notice is suppressed on /account so the resend action is not offered twice.
            $this->assertSame(1, substr_count($html, 'action="'.route('email.resend').'"'));
        }
    }

    public function test_global_email_notice_is_compact_announced_and_still_blocks_nothing_it_did_not_before(): void
    {
        $rep = $this->person($this->department, 'rep', 'Notice Rep', null, false);
        $html = $this->as($rep)->get('/rooms')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<div class="rb-email-notice rb-ui">\s*<div class="rb-status rb-tone-warning"\s+role="status"/', $html);
        $this->assertStringContainsString('Email not connected', $html);
        $this->assertStringContainsString('Open the email we sent to '.$rep->email.' and click “Connect my email” to start booking.', $html);
        $this->assertStringContainsString('>Send it again</button>', $html);
        $this->assertStringNotContainsString('rb-alert', $html);

        // A connect_note replaces the resend action (existing behaviour), and is escaped.
        $this->as($rep)->withSession(['connect_note' => '<b>Sent</b> & waiting'])->get('/rooms')
            ->assertSee('&lt;b&gt;Sent&lt;/b&gt; &amp; waiting', false)->assertDontSee('Send it again');

        // Booking is still gated on a connected email (unchanged).
        $this->as($rep)->get('/book')->assertRedirect();
    }
}
