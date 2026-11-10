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
 * Opt-in HTTP regression suite for the final consistency pass: hidden missing capacities, the always-visible unavailable
 * rooms on /book, button-styled cancel links on My bookings, and the removed sidebar information card.
 * Same isolation contract as PaginationWorkflowTest: a disposable, already-migrated MariaDB schema
 * (RB_MANAGE_HTTP_TESTS=1, RB_MANAGE_TEST_DATABASE=..._test), each test inside a rolled-back transaction.
 */
final class FrontendConsistencyTest extends TestCase
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

        $this->department = $this->makeDepartment('Consistency test');
        $this->otherDepartment = $this->makeDepartment('Other consistency test');
        $section = Section::create(['name' => '4-'.Str::random(6), 'program' => 'BSCPE']);
        $this->room = Room::create(['name' => 'Con-'.Str::random(10), 'capacity' => 30, 'is_active' => true]);
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
        $department = Department::create(['name' => $name, 'slug' => 'con-'.Str::lower(Str::random(12))]);
        DepartmentConfig::create(['department_id' => $department->id] + DepartmentConfig::DEFAULTS);

        return $department;
    }

    private function person(Department $department, string $role, string $name, ?int $sectionId = null): User
    {
        $user = User::create([
            'department_id' => $department->id, 'section_id' => $sectionId, 'name' => $name,
            'email' => Str::lower(Str::random(14)).'@gmail.com', 'role' => $role, 'password' => 'Starting123',
            'is_active' => true, 'must_change_password' => false,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();   // a rep can only book once the email is connected

        return $user;
    }

    /** auth.session ties a session to one password hash, so each change of user starts a fresh session. */
    private function as(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function pending(): Reservation
    {
        return $this->booking('Pending', now()->addDays(3)->setTime(9, 0));
    }

    private function workingDay(): \Illuminate\Support\Carbon
    {
        $day = now()->addDays(2)->startOfDay();
        while ($day->isSunday()) {
            $day = $day->addDay();
        }

        return $day;
    }

    private function booking(string $status, \DateTimeInterface $start, ?User $owner = null, ?Room $room = null): Reservation
    {
        return Reservation::create(['user_id' => ($owner ?? $this->rep)->id, 'room_id' => ($room ?? $this->room)->id, 'purpose' => 'Consistency test',
            'start_time' => $start, 'end_time' => (clone $start)->modify('+1 hour'), 'status' => $status]);
    }

    public function test_the_sidebar_information_card_is_gone_for_every_role(): void
    {
        foreach ([[$this->rep, '/my-bookings'], [$this->staff, '/requests'], [$this->admin, '/requests'], [$this->admin, '/rooms']] as [$user, $url]) {
            $html = $this->as($user)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('class="rb-sidebar', $html, $user->role.': the sidebar itself stays');
            $this->assertStringContainsString('rb-navigation', $html);
            $this->assertStringNotContainsString('PUP CEA / NDC Campus', $html, $user->role);
            $this->assertStringNotContainsString('Reservations follow your department', $html);
            $this->assertStringNotContainsString('rb-workspace-note', $html);
            // The "Signed in as" role card is gone too; the role is still shown in the header account chip.
            $this->assertStringNotContainsString('rb-role-card', $html, $user->role);
            $this->assertStringNotContainsString('Signed in as', $html, $user->role);
            $this->assertStringContainsString('<span class="rb-account__role">'.$user->roleLabel().'</span>', $html, $user->role.': role still identified');
        }
    }

    public function test_my_bookings_offers_cancellation_as_a_button_styled_link_to_the_booking_page(): void
    {
        $open = $this->booking('Confirmed', now()->addDays(4)->setTime(9, 0));
        $pending = $this->booking('Pending', now()->addDays(5)->setTime(9, 0));
        $late = $this->booking('Pending', now()->addMinutes(30));
        $done = $this->booking('Completed', now()->subDays(3)->setTime(9, 0));
        $html = $this->as($this->rep)->get(route('bookings.mine'))->assertOk()->getContent();
        foreach ([$open, $pending] as $b) {
            $this->assertMatchesRegularExpression('/<a class="btn-ghost btn-ghost--danger btn-ghost--compact brow-cancel" href="'.preg_quote(route('bookings.show', $b), '/').'#cancel">Cancel booking<span class="sr-only">: Consistency test<\/span><\/a>/', $html);
        }
        $this->assertSame(2, substr_count($html, 'brow-cancel'), 'only the bookings that can still be cancelled get the control');
        $this->assertStringNotContainsString(route('bookings.show', $late).'#cancel', $html, 'inside the cancel cut-off: no control');
        $this->assertStringNotContainsString(route('bookings.show', $done).'#cancel', $html, 'finished bookings: no control');
        // Navigation only: nothing on the list cancels anything.
        $this->assertStringNotContainsString('/cancel"', $html);
        $this->assertStringNotContainsString('Cancel…', $html);
        $this->assertSame('Confirmed', $open->fresh()->status);
        // And the link still lands on the working cancel card.
        $this->as($this->rep)->get(route('bookings.show', $open))->assertOk()->assertSee('id="cancel"', false);
    }

    public function test_missing_capacities_leave_no_text_and_recorded_ones_stay(): void
    {
        $this->department->rooms()->attach($this->room);
        $blank = Room::create(['name' => 'Blank-'.Str::random(8), 'capacity' => null, 'is_active' => true]);
        $this->department->rooms()->attach($blank);
        $this->room->update(['capacity' => 40]);

        $free = $this->as($this->rep)->get('/rooms?date='.$this->workingDay()->toDateString())->assertOk()->getContent();
        $manage = $this->as($this->admin)->get('/manage/rooms')->assertOk()->getContent();
        foreach (['free rooms' => $free, 'manage rooms' => $manage] as $page => $html) {
            foreach (['Capacity not specified', 'Not specified', 'Capacity unknown', 'Unknown', 'N/A'] as $placeholder) {
                $this->assertStringNotContainsString($placeholder, $html, "$page: $placeholder");
            }
            $this->assertStringContainsString($this->room->name, $html);
            $this->assertStringContainsString($blank->name, $html, "$page still lists the room");
        }
        $this->assertStringContainsString('40 seats', $free);
        $this->assertMatchesRegularExpression('/<td>40<\/td>/', $manage);
        $this->assertMatchesRegularExpression('/<td><strong>'.preg_quote($blank->name, '/').'<\/strong>.*?<td class="hide-sm">[^<]*<\/td>\s*<td><\/td>/s', $manage, 'an empty seats cell, not a placeholder');
        // Editing is untouched: the admin can still type a capacity.
        $this->as($this->admin)->get(route('manage.rooms.edit', $blank))->assertOk()->assertSee('name="capacity"', false);
    }

    public function test_the_booking_page_lists_unavailable_rooms_in_the_page_and_never_as_choices(): void
    {
        $this->department->rooms()->attach($this->room);
        $free = Room::create(['name' => 'Free-'.Str::random(8), 'capacity' => null, 'is_active' => true]);
        $this->department->rooms()->attach($free);
        $day = $this->workingDay();
        $other = $this->person($this->department, 'rep', 'Other Rep', Section::create(['name' => '2-'.Str::random(6), 'program' => 'BSCPE'])->id);
        $this->booking('Confirmed', $day->copy()->setTime(9, 0), $other, $this->room);

        $html = $this->as($this->rep)->get(route('book', ['for' => 'other', 'date' => $day->toDateString(), 'from' => '09:00', 'to' => '10:00']))->assertOk()->getContent();
        $this->assertStringNotContainsString('<details class="busy-rooms"', $html);
        $this->assertStringNotContainsString('busy then', $html);
        $this->assertMatchesRegularExpression('/<section class="unavail" aria-labelledby="unavail-title">\s*<h3[^>]*>Unavailable rooms <span class="count">1<\/span>/', $html);
        $this->assertMatchesRegularExpression('/<li class="unavail__row">\s*<strong class="unavail__room">'.preg_quote($this->room->name, '/').'<\/strong>\s*<span class="unavail__reason">[^<]+<\/span>\s*<span class="unavail__time">[^<]+<\/span>/', $html);
        $this->assertStringContainsString('Rooms are shared, so other departments’ bookings count too.', $html);
        $this->assertStringNotContainsString('name="room" value="'.$this->room->id.'"', $html, 'a busy room cannot be picked');
        $this->assertStringContainsString('name="room" value="'.$free->id.'"', $html, 'a free room still can');
        $this->assertStringNotContainsString('Capacity not specified', $html);
        $this->assertMatchesRegularExpression('/<strong>'.preg_quote($free->name, '/').'<\/strong><\/span>/', $html, 'an unknown capacity adds nothing after the room name');
    }

    public function test_no_unavailable_section_when_every_room_is_free(): void
    {
        $this->department->rooms()->attach($this->room);
        $html = $this->as($this->rep)->get(route('book', ['for' => 'other', 'date' => $this->workingDay()->toDateString(), 'from' => '09:00', 'to' => '10:00']))->assertOk()->getContent();
        $this->assertStringNotContainsString('class="unavail"', $html);
        $this->assertStringNotContainsString('Unavailable rooms', $html);
    }

    public function test_requests_action_row_markup_keeps_reject_cancel_and_the_required_reason(): void
    {
        $booking = $this->booking('Pending', now()->addDays(3)->setTime(9, 0));
        $html = $this->as($this->staff)->get('/requests')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<div class="req-reject__actions">\s*<button type="submit" class="btn-reject">Reject booking<\/button>\s*<a class="btn-ghost" href="#req-'.$booking->id.'" data-rb-reveal-close>Cancel<\/a>/', $html);
        $this->assertMatchesRegularExpression('/id="reason-'.$booking->id.'" name="reason"[^>]*\brequired\b/', $html);
        // The Cancel control only dismisses: it is an in-page link, not a submit.
        $this->as($this->staff)->get('/requests#req-'.$booking->id)->assertOk();
        $this->assertSame('Pending', $booking->fresh()->status);
        // A refused reason keeps working and keeps the form open.
        $this->as($this->staff)->from('/requests')->post(route('bookings.reject', $booking), ['reason' => ''])->assertSessionHasErrors('reason', null, 'reject'.$booking->id);
        $this->assertSame('Pending', $booking->fresh()->status);
    }

    public function test_the_booking_detail_action_panels_are_unchanged(): void
    {
        $booking = $this->booking('Pending', now()->addDays(3)->setTime(9, 0));
        $staff = $this->as($this->staff)->get(route('bookings.show', $booking))->assertOk()->getContent();
        $this->assertStringContainsString('class="review-panels"', $staff);
        $this->assertStringNotContainsString('<details', $staff);
        $this->assertStringContainsString('id="reject-reason" name="reason"', $staff);
        $rep = $this->as($this->rep)->get(route('bookings.show', $booking))->assertOk()->getContent();
        $this->assertStringContainsString('class="rb-booking__panel cancel-card"', $rep);
        $this->assertStringNotContainsString('<details', $rep);
        $this->as($this->rep)->post(route('bookings.approve', $booking))->assertForbidden();
        $this->as($this->rep)->get('/requests')->assertForbidden();
        $this->as($this->rep)->get('/manage/rooms')->assertForbidden();
        $this->as($this->staff)->get('/accounts')->assertForbidden();
        $this->as($this->admin)->get('/accounts')->assertOk();
    }
}
