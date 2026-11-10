<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentConfig;
use App\Models\OverrideLog;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * QA F-07: hand-out and no-show are refused for a booking on a future calendar day (Asia/Manila), whatever
 * the client sends; same-day (even early) and past bookings, and every return, keep working.
 * Needs a disposable MariaDB: set RB_STAFF_HTTP_TESTS=1 (tests/Support/run-db-tests.sh does everything).
 */
class StaffBookingGuardTest extends TestCase
{
    private bool $transactionStarted = false;

    private Department $department;

    private Section $section;

    private Room $room;

    private User $rep;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RB_STAFF_HTTP_TESTS') !== '1') {
            $this->markTestSkipped('Requires a disposable, provisioned MariaDB test database (tests/Support/run-db-tests.sh).');
        }
        $settings = config('database.connections.'.config('database.default'));
        $schema = (string) getenv('RB_STAFF_TEST_DATABASE');
        $this->assertTrue($this->app->environment('testing'));
        $this->assertNotEmpty($schema);
        $this->assertStringEndsWith('_test', $schema);
        $this->assertSame('mysql', $settings['driver']);
        $this->assertSame($schema, $settings['database']);
        foreach (['url', 'unix_socket', 'read', 'write'] as $key) {
            $this->assertEmpty($settings[$key] ?? null);
        }
        foreach (['session.driver', 'cache.default', 'mail.default'] as $key) {
            $this->assertSame('array', config($key));
        }
        $this->assertSame($schema, DB::selectOne('select database() as db')->db);
        DB::beginTransaction();
        $this->transactionStarted = true;
        Notification::fake();
        // 2026-11-04 09:05 in Manila is "now" for every test.
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-04 09:05', 'Asia/Manila'));
        CarbonImmutable::setTestNow(Carbon::now());

        $this->department = Department::create(['name' => 'Guard test', 'slug' => 'guard-'.Str::lower(Str::random(12))]);
        DepartmentConfig::create(['department_id' => $this->department->id] + DepartmentConfig::DEFAULTS);
        $this->section = Section::create(['name' => '4-'.Str::random(6), 'program' => 'BSCPE']);
        $this->room = Room::create(['name' => 'Guard-'.Str::random(10), 'capacity' => 40, 'is_active' => true]);
        $this->department->rooms()->attach($this->room);
        $this->rep = $this->user('rep');
        $this->staff = $this->user('lab_staff');
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
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
            parent::tearDown();
        }
    }

    private function user(string $role, ?Department $department = null): User
    {
        $user = User::create(['department_id' => ($department ?? $this->department)->id, 'section_id' => $this->section->id,
            'name' => 'Guard '.$role, 'role' => $role, 'email' => 'guard-'.Str::lower(Str::random(12)).'@gmail.com',
            'password' => 'Example123', 'is_active' => true, 'must_change_password' => false]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function booking(string $start, string $status = 'Confirmed', ?string $end = null): Reservation
    {
        $startAt = CarbonImmutable::parse($start, 'Asia/Manila');

        return Reservation::create(['user_id' => $this->rep->id, 'room_id' => $this->room->id, 'purpose' => 'Guard test purpose',
            'start_time' => $startAt, 'end_time' => $end ? CarbonImmutable::parse($end, 'Asia/Manila') : $startAt->addHour(), 'status' => $status]);
    }

    private function statusOf(Reservation $r): string
    {
        return $r->fresh()->status;
    }

    // ---- hand-out ----

    public function test_future_day_booking_cannot_be_handed_out_even_by_a_crafted_post(): void
    {
        foreach (['2026-11-05 00:00', '2026-11-05 13:00', '2026-11-09 13:00'] as $start) {
            $r = $this->booking($start);
            $this->actingAs($this->staff)->from('/today')->post(route('bookings.hand-out', $r))
                ->assertRedirect('/today')
                ->assertSessionHas('problem', fn ($m) => str_contains($m, 'can’t be handed out yet') && str_contains($m, '#'.$r->id));
            $this->assertSame('Confirmed', $this->statusOf($r), $start);
            $this->assertSame(0, OverrideLog::where('reservation_id', $r->id)->count());
        }
    }

    public function test_same_day_booking_can_be_handed_out_even_hours_before_it_starts(): void
    {
        foreach (['2026-11-04 00:30', '2026-11-04 09:00', '2026-11-04 23:00'] as $start) {   // now = 09:05
            $r = $this->booking($start);
            $this->actingAs($this->staff)->post(route('bookings.hand-out', $r))->assertSessionHas('status');
            $this->assertSame('InUse', $this->statusOf($r), $start);
        }
    }

    public function test_past_day_booking_can_still_be_handed_out(): void
    {
        $r = $this->booking('2026-11-02 13:00');
        $this->actingAs($this->staff)->post(route('bookings.hand-out', $r))->assertSessionHas('status');
        $this->assertSame('InUse', $this->statusOf($r));
    }

    public function test_midnight_in_manila_decides_the_day(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-04 23:59', 'Asia/Manila'));
        CarbonImmutable::setTestNow(Carbon::now());
        $today = $this->booking('2026-11-04 23:30');
        $tomorrow = $this->booking('2026-11-05 00:00');
        $this->actingAs($this->staff)->post(route('bookings.hand-out', $tomorrow));
        $this->post(route('bookings.hand-out', $today));
        $this->assertSame('Confirmed', $this->statusOf($tomorrow));
        $this->assertSame('InUse', $this->statusOf($today));
    }

    // ---- no-show ----

    public function test_no_show_follows_the_same_day_rule(): void
    {
        $future = $this->booking('2026-11-06 10:00');
        $today = $this->booking('2026-11-04 15:00');
        $past = $this->booking('2026-11-01 10:00');
        $this->actingAs($this->staff)->from('/today')->post(route('bookings.no-show', $future))
            ->assertSessionHas('problem', fn ($m) => str_contains($m, 'can’t be a no-show yet'));
        $this->post(route('bookings.no-show', $today))->assertSessionHas('status');
        $this->post(route('bookings.no-show', $past))->assertSessionHas('status');
        $this->assertSame('Confirmed', $this->statusOf($future));
        $this->assertSame('NoShow', $this->statusOf($today));
        $this->assertSame('NoShow', $this->statusOf($past));
    }

    // ---- returns: no date restriction ----

    public function test_return_works_for_in_use_bookings_on_time_late_and_with_an_odd_date(): void
    {
        $onTime = $this->booking('2026-11-04 08:00', 'InUse');
        $late = $this->booking('2026-11-01 08:00', 'InUse');                 // days overdue
        $oddFuture = $this->booking('2026-11-12 08:00', 'InUse');            // already in use: a return is still allowed
        foreach ([$onTime, $late, $oddFuture] as $r) {
            $this->actingAs($this->staff)->post(route('bookings.returned', $r), ['note' => 'One cable missing'])->assertSessionHas('status');
            $this->assertSame('Completed', $this->statusOf($r));
        }
        $this->assertSame('One cable missing', OverrideLog::where('reservation_id', $onTime->id)->latest('id')->value('reason'));
    }

    public function test_hand_out_then_return_full_cycle_on_the_booking_day(): void
    {
        $r = $this->booking('2026-11-04 13:00');
        $this->actingAs($this->staff)->post(route('bookings.hand-out', $r));
        $this->assertSame('InUse', $this->statusOf($r));
        $this->post(route('bookings.returned', $r))->assertSessionHas('status');
        $this->assertSame('Completed', $this->statusOf($r));
    }

    // ---- invalid transitions ----

    public function test_invalid_transitions_are_refused_with_the_status_message(): void
    {
        foreach (['Pending', 'InUse', 'Completed', 'NoShow', 'Cancelled', 'Rejected'] as $status) {
            $r = $this->booking('2026-11-04 13:00', $status);
            $this->actingAs($this->staff)->from('/today')->post(route('bookings.hand-out', $r))
                ->assertSessionHas('problem', fn ($m) => str_contains($m, 'so it can’t be handed out.') && ! str_contains($m, 'yet'));
            $this->post(route('bookings.no-show', $r))->assertSessionHas('problem');
            $this->assertSame($status, $this->statusOf($r));
        }
        foreach (['Confirmed', 'Pending', 'Completed'] as $status) {
            $r = $this->booking('2026-11-04 13:00', $status);
            $this->actingAs($this->staff)->post(route('bookings.returned', $r))->assertSessionHas('problem');
            $this->assertSame($status, $this->statusOf($r));
        }
        $twice = $this->booking('2026-11-04 13:00');
        $this->actingAs($this->staff)->post(route('bookings.hand-out', $twice));
        $this->post(route('bookings.hand-out', $twice))->assertSessionHas('problem');
        $this->assertSame('InUse', $this->statusOf($twice));
    }

    public function test_return_note_validation_is_preserved(): void
    {
        $r = $this->booking('2026-11-04 08:00', 'InUse');
        $this->actingAs($this->staff)->from('/today')->post(route('bookings.returned', $r), ['note' => str_repeat('x', 256)])
            ->assertSessionHasErrorsIn('return'.$r->id, 'note');
        $this->assertSame('InUse', $this->statusOf($r));
    }

    // ---- roles ----

    public function test_guests_are_sent_to_login_and_reps_are_forbidden(): void
    {
        $today = $this->booking('2026-11-04 13:00');
        foreach (['bookings.hand-out', 'bookings.no-show', 'bookings.returned'] as $route) {
            $this->post(route($route, $today))->assertRedirect('/login');
        }
        $this->assertSame('Confirmed', $this->statusOf($today));
    }

    public function test_reps_are_forbidden_from_every_staff_action(): void
    {
        $today = $this->booking('2026-11-04 13:00');
        $this->actingAs($this->rep);
        foreach (['bookings.hand-out', 'bookings.no-show', 'bookings.returned'] as $route) {
            $this->post(route($route, $today))->assertForbidden();
        }
        $this->assertSame('Confirmed', $this->statusOf($today));
    }

    public function test_staff_of_another_department_get_404_and_nothing_changes(): void
    {
        $today = $this->booking('2026-11-04 13:00');
        $inUse = $this->booking('2026-11-04 08:00', 'InUse');
        $other = $this->user('lab_staff', Department::create(['name' => 'Other', 'slug' => 'other-'.Str::lower(Str::random(10))]));
        $this->actingAs($other);
        $this->post(route('bookings.hand-out', $today))->assertNotFound();
        $this->post(route('bookings.no-show', $today))->assertNotFound();
        $this->post(route('bookings.returned', $inUse))->assertNotFound();
        $this->assertSame('Confirmed', $this->statusOf($today));
        $this->assertSame('InUse', $this->statusOf($inUse));
    }

    public function test_admin_is_bound_by_the_same_day_rule(): void
    {
        $future = $this->booking('2026-11-05 10:00');
        $today = $this->booking('2026-11-04 13:00');
        $this->actingAs($this->user('admin'));
        $this->post(route('bookings.hand-out', $future))->assertSessionHas('problem');
        $this->post(route('bookings.hand-out', $today))->assertSessionHas('status');
        $this->assertSame('Confirmed', $this->statusOf($future));
        $this->assertSame('InUse', $this->statusOf($today));
    }

    // ---- UI ----

    public function test_future_day_shows_a_note_instead_of_the_buttons_and_today_keeps_them(): void
    {
        $future = $this->booking('2026-11-05 10:00');
        $today = $this->booking('2026-11-04 13:00');

        $page = $this->actingAs($this->staff)->get('/today?date=2026-11-05')->assertOk();
        $page->assertSee('Hand out and no-show open on Thursday, November 5.')
            ->assertDontSee(route('bookings.hand-out', $future), false)
            ->assertDontSee(route('bookings.no-show', $future), false);

        $this->get('/today')->assertOk()
            ->assertSee(route('bookings.hand-out', $today), false)
            ->assertSee(route('bookings.no-show', $today), false)
            ->assertDontSee('open on Thursday');

        $this->get(route('bookings.show', $future))->assertOk()
            ->assertSee('Hand out and no-show open on Thursday, November 5.')
            ->assertDontSee(route('bookings.hand-out', $future), false);
        $this->get(route('bookings.show', $today))->assertOk()->assertSee(route('bookings.hand-out', $today), false);
    }
}
