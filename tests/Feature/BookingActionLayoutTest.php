<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentConfig;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Opt-in HTTP regression suite for the always-visible Approve / Reject and Cancel panels on /bookings/{id}.
 * Same isolation contract as PaginationWorkflowTest: a disposable, already-migrated MariaDB schema
 * (RB_MANAGE_HTTP_TESTS=1, RB_MANAGE_TEST_DATABASE=..._test), each test inside a rolled-back transaction.
 */
final class BookingActionLayoutTest extends TestCase
{
    private bool $transactionStarted = false;

    private Department $department;

    private Department $otherDepartment;

    private Room $room;

    private User $admin;

    private User $staff;

    private User $rep;

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

        $this->department = $this->makeDepartment('Actions test');
        $this->otherDepartment = $this->makeDepartment('Other test');
        $section = Section::create(['name' => '4-'.Str::random(6), 'program' => 'BSCPE']);
        $this->room = Room::create(['name' => 'Act-'.Str::random(10), 'capacity' => 30, 'is_active' => true]);
        $this->admin = $this->person($this->department, 'admin', 'Zed Admin');
        $this->staff = $this->person($this->department, 'lab_staff', 'Lou Staff');
        $this->rep = $this->person($this->department, 'rep', 'Rita Rep', $section->id);
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
        $department = Department::create(['name' => $name, 'slug' => 'act-'.Str::lower(Str::random(12))]);
        DepartmentConfig::create(['department_id' => $department->id] + DepartmentConfig::DEFAULTS);

        return $department;
    }

    private function person(Department $department, string $role, string $name, ?int $sectionId = null): User
    {
        return User::create([
            'department_id' => $department->id, 'section_id' => $sectionId, 'name' => $name,
            'email' => Str::lower(Str::random(14)).'@gmail.com', 'role' => $role, 'password' => 'Starting123',
            'is_active' => true, 'must_change_password' => false, 'email_verified_at' => now(),
        ]);
    }

    /** auth.session ties a session to one password hash, so each change of user starts a fresh session. */
    private function as(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function booking(string $status, \DateTimeInterface $start, ?User $owner = null): Reservation
    {
        return Reservation::create(['user_id' => ($owner ?? $this->rep)->id, 'room_id' => $this->room->id, 'purpose' => 'Action layout test',
            'start_time' => $start, 'end_time' => (clone $start)->modify('+1 hour'), 'status' => $status]);
    }

    private function pending(): Reservation
    {
        return $this->booking('Pending', now()->addDays(3)->setTime(9, 0));
    }

    /** @return list<string> */
    public static function reviewers(): array
    {
        return ['admin', 'staff'];
    }

    public function test_pending_booking_shows_approve_and_reject_side_by_side_without_a_dropdown(): void
    {
        $booking = $this->pending();
        foreach (self::reviewers() as $who) {
            $html = $this->as($this->$who)->get(route('bookings.show', $booking))->assertOk()->getContent();
            $this->assertStringNotContainsString('<details', $html, "$who: no disclosure on the page");
            $this->assertStringNotContainsString('<summary', $html);
            $this->assertStringNotContainsString('Reject…', $html);
            $this->assertStringContainsString('class="review-panels"', $html);
            $this->assertStringContainsString('action="'.route('bookings.approve', $booking).'"', $html);
            $this->assertStringContainsString('action="'.route('bookings.reject', $booking).'"', $html);
            // The reason is on the page straight away, required, and inside the reject form only.
            $this->assertMatchesRegularExpression('/<form[^>]*action-panel--reject.*?<textarea id="reject-reason" name="reason"[^>]*\brequired\b.*?<\/form>/s', $html);
            $this->assertSame(1, substr_count($html, 'name="reason"'), 'one reason field, not one per action');
            $this->assertSame(2, preg_match_all('/<form[^>]*class="action-panel /', $html), 'two panels');
            $this->assertSame(2, preg_match_all('/<form[^>]*class="action-panel [^"]*"[^>]*>\s*<input type="hidden" name="_token"/', $html), 'each panel form carries its CSRF token');
        }
    }

    public function test_viewing_or_loading_the_page_never_decides_the_booking(): void
    {
        $booking = $this->pending();
        $this->as($this->staff)->get(route('bookings.show', $booking))->assertOk();
        $this->as($this->staff)->get(route('bookings.show', $booking).'#reject-reason')->assertOk();
        $this->assertSame('Pending', $booking->fresh()->status);
    }

    public function test_approve_and_reject_still_work_for_admin_and_lab_staff(): void
    {
        foreach (self::reviewers() as $who) {
            $approve = $this->pending();
            $this->as($this->$who)->post(route('bookings.approve', $approve))->assertRedirect();
            $this->assertSame('Confirmed', $approve->fresh()->status, "$who approves");

            $reject = $this->pending();
            $this->as($this->$who)->post(route('bookings.reject', $reject), ['reason' => '  Lab closed that day  '])->assertRedirect();
            $this->assertSame('Rejected', $reject->fresh()->status, "$who rejects");
            $this->assertSame('Lab closed that day', $reject->fresh()->status_reason);
        }
    }

    public function test_a_missing_reason_is_refused_and_the_error_sits_beside_the_reason_field(): void
    {
        $booking = $this->pending();
        $page = route('bookings.show', $booking);
        $this->as($this->staff)->from($page)->post(route('bookings.reject', $booking), ['reason' => ''])
            ->assertRedirect($page)->assertSessionHasErrors('reason', null, 'reject'.$booking->id);
        $this->assertSame('Pending', $booking->fresh()->status, 'a refused rejection changes nothing');

        $html = $this->as($this->staff)->from($page)->followingRedirects()->post(route('bookings.reject', $booking), ['reason' => ''])->getContent();
        $this->assertStringContainsString('Give a reason, so the rep knows what to change.', $html);
        $this->assertMatchesRegularExpression('/<textarea id="reject-reason"[^>]*aria-invalid="true"[^>]*aria-describedby="reject-reason-error"/', $html);
        $this->assertMatchesRegularExpression('/<\/textarea>\s*<p class="field-error" id="reject-reason-error">/', $html);
        // The approve panel carries no error.
        $this->assertSame(1, substr_count($html, 'class="field-error"'));
    }

    public function test_an_overlong_reason_is_refused_and_kept_in_the_field(): void
    {
        $booking = $this->pending();
        $page = route('bookings.show', $booking);
        $long = str_repeat('x', 256);
        $html = $this->as($this->staff)->from($page)->followingRedirects()->post(route('bookings.reject', $booking), ['reason' => $long])->getContent();
        $this->assertSame('Pending', $booking->fresh()->status);
        $this->assertStringContainsString('Keep the reason under 255 characters.', $html);
        $this->assertStringContainsString($long, $html, 'old input is kept');
    }

    public function test_other_departments_and_reps_cannot_review(): void
    {
        $booking = $this->pending();
        $outsider = $this->person($this->otherDepartment, 'lab_staff', 'Out Sider');
        $outsiderAdmin = $this->person($this->otherDepartment, 'admin', 'Out Admin');
        foreach ([$outsider, $outsiderAdmin] as $who) {
            $this->as($who)->get(route('bookings.show', $booking))->assertNotFound();
            $this->as($who)->post(route('bookings.approve', $booking))->assertNotFound();
            $this->as($who)->post(route('bookings.reject', $booking), ['reason' => 'No'])->assertNotFound();
        }
        $this->as($this->rep)->post(route('bookings.approve', $booking))->assertForbidden();
        $this->as($this->rep)->post(route('bookings.reject', $booking), ['reason' => 'No'])->assertForbidden();
        $this->assertSame('Pending', $booking->fresh()->status);
    }

    public function test_the_owner_never_sees_the_review_panels_and_staff_never_see_the_rep_cancel_card(): void
    {
        $booking = $this->pending();
        $mine = $this->as($this->rep)->get(route('bookings.show', $booking))->assertOk()->getContent();
        foreach (['review-panels', 'action-panel--', route('bookings.approve', $booking), route('bookings.reject', $booking), 'Staff actions'] as $needle) {
            $this->assertStringNotContainsString($needle, $mine);
        }
        $theirs = $this->as($this->staff)->get(route('bookings.show', $booking))->assertOk()->getContent();
        $this->assertStringNotContainsString('class="rb-booking__panel cancel-card"', $theirs);
        $this->assertStringNotContainsString(route('bookings.cancel', $booking), $theirs);
    }

    public function test_the_rep_sees_one_permanent_cancel_card_with_the_deadline_and_a_required_reason(): void
    {
        $booking = $this->pending();
        $html = $this->as($this->rep)->get(route('bookings.show', $booking))->assertOk()->getContent();
        $this->assertStringNotContainsString('<details', $html);
        $this->assertStringNotContainsString('<summary', $html);
        $this->assertStringContainsString('id="cancel"', $html, 'the My bookings “Cancel…” link still lands on it');
        $this->assertStringContainsString('You can cancel until', $html);
        $this->assertStringContainsString($booking->start_time->copy()->subMinutes(180)->format('D, M j, g:i A'), $html);
        $this->assertMatchesRegularExpression('/<form[^>]*action="'.preg_quote(route('bookings.cancel', $booking), '/').'"[^>]*class="cancel-card__form".*?<textarea id="cancel-reason" name="reason"[^>]*\brequired\b/s', $html);
        $this->assertSame(1, substr_count($html, 'name="reason"'));
    }

    public function test_cancelling_works_and_confirmed_bookings_can_be_cancelled_too(): void
    {
        $pending = $this->pending();
        $this->as($this->rep)->post(route('bookings.cancel', $pending), ['reason' => 'Class moved online'])->assertRedirect(route('bookings.show', $pending));
        $this->assertSame('Cancelled', $pending->fresh()->status);
        $this->assertSame('Class moved online', $pending->fresh()->status_reason);

        $confirmed = $this->booking('Confirmed', now()->addDays(4)->setTime(9, 0));
        $this->as($this->rep)->post(route('bookings.cancel', $confirmed), ['reason' => 'Event off'])->assertRedirect();
        $this->assertSame('Cancelled', $confirmed->fresh()->status);

        // Once cancelled there is nothing left to cancel, and the card is gone.
        $html = $this->as($this->rep)->get(route('bookings.show', $pending))->assertOk()->getContent();
        $this->assertStringNotContainsString('cancel-card', $html);
    }

    public function test_a_missing_cancel_reason_is_refused_and_the_error_sits_beside_the_field(): void
    {
        $booking = $this->pending();
        $page = route('bookings.show', $booking);
        $this->as($this->rep)->from($page)->post(route('bookings.cancel', $booking), ['reason' => ''])->assertRedirect($page)->assertSessionHasErrors('reason');
        $this->assertSame('Pending', $booking->fresh()->status);

        $html = $this->as($this->rep)->from($page)->followingRedirects()->post(route('bookings.cancel', $booking), ['reason' => ''])->getContent();
        $this->assertStringContainsString('Please say why you’re cancelling.', $html);
        $this->assertMatchesRegularExpression('/<textarea id="cancel-reason"[^>]*aria-invalid="true"[^>]*aria-describedby="cancel-reason-error"/', $html);
        $this->assertMatchesRegularExpression('/<\/textarea>\s*<p class="field-error" id="cancel-reason-error">/', $html);
    }

    public function test_after_the_deadline_only_the_explanation_shows_and_a_forced_post_is_refused(): void
    {
        $soon = $this->booking('Pending', now()->addMinutes(30));
        $html = $this->as($this->rep)->get(route('bookings.show', $soon))->assertOk()->getContent();
        $this->assertStringContainsString('too late to cancel here', $html);
        $this->assertStringNotContainsString('cancel-card', $html);
        $this->assertStringNotContainsString('name="reason"', $html);
        $this->assertStringNotContainsString(route('bookings.cancel', $soon), $html);

        $this->as($this->rep)->post(route('bookings.cancel', $soon), ['reason' => 'Trying anyway'])->assertSessionHas('problem');
        $this->assertSame('Pending', $soon->fresh()->status, 'the deadline is still enforced on the server');
    }

    public function test_another_rep_cannot_see_or_cancel_the_booking(): void
    {
        $booking = $this->pending();
        $other = $this->person($this->department, 'rep', 'Rex Rep', Section::create(['name' => '3-'.Str::random(6), 'program' => 'BSCPE'])->id);
        $this->as($other)->get(route('bookings.show', $booking))->assertNotFound();
        $this->as($other)->post(route('bookings.cancel', $booking), ['reason' => 'Not mine'])->assertNotFound();
        $this->assertSame('Pending', $booking->fresh()->status);
    }

    public function test_the_staff_move_and_cancel_workflow_for_confirmed_bookings_is_unchanged(): void
    {
        $booking = $this->booking('Confirmed', now()->addDays(2)->setTime(10, 0));
        $html = $this->as($this->staff)->get(route('bookings.show', $booking))->assertOk()->getContent();
        foreach (['id="move-panel"', 'id="cancel-panel"', 'data-rb-reveal="move-panel"', 'action="'.route('bookings.move', $booking).'"', 'action="'.route('bookings.staff-cancel', $booking).'"', 'staff-cancel-confirm'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $this->assertStringNotContainsString('action-panel--', $html, 'no approve/reject panels on a confirmed booking');
    }
}
