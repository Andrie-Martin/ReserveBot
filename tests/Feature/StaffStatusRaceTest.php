<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentConfig;
use App\Models\OverrideLog;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Section;
use App\Models\User;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Services\Bookings;
use App\Services\StaffDesk;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * QA F-B: a hand-out or no-show re-checks the booking's status and calendar day on the latest, locked row, so a
 * concurrent move to another day can never slip a future-day booking through. The interleaving is controlled: a
 * listener on the transaction that StaffDesk::setStatus opens runs the competing request at the exact moment
 * between the controller's first check and the locked re-read (what two real requests produce, deterministically).
 * Real concurrent HTTP runs: docs/frontend/BOOKING_FB_STATUS_MOVE_RACE.md.
 * Needs a disposable MariaDB: set RB_STAFF_HTTP_TESTS=1 (tests/Support/run-db-tests.sh does everything).
 */
class StaffStatusRaceTest extends TestCase
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

    private function logs(Reservation $r, string $action = 'StatusChanged'): int
    {
        return OverrideLog::where('reservation_id', $r->id)->where('action', $action)->count();
    }

    /** Runs $between once, at the start of the first transaction opened after this call (inside StaffDesk::setStatus). */
    private function interleave(\Closure $between): void
    {
        $done = false;
        Event::listen(TransactionBeginning::class, function () use (&$done, $between) {
            if ($done) {
                return;
            }
            $done = true;
            $between();
        });
    }

    /** Another staff member's move, using the real service, to a day/time that is free */
    private function moveTo(Reservation $r, string $date, int $fromMin, int $toMin): void
    {
        app(Bookings::class)->move($r, $this->staff, CarbonImmutable::createFromFormat('!Y-m-d', $date), $fromMin, $toMin, $this->room, 'Race test move');
    }

    private function act(string $route, Reservation $r, ?User $as = null)
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        return $this->actingAs($as ?? $this->staff)->from('/today')->post(route($route, $r));
    }

    private function withEquipment(Reservation $r): Equipment
    {
        $cat = EquipmentCategory::create(['department_id' => $this->department->id, 'name' => 'Race '.Str::random(5), 'sort_order' => 1]);
        $item = Equipment::create(['category_id' => $cat->id, 'name' => 'Projector '.Str::random(5), 'total_qty' => 2, 'max_per_request' => 2, 'sort_order' => 1, 'is_active' => true]);
        $r->equipment()->attach($item->id, ['qty_requested' => 1]);

        return $item;
    }

    /** @return array{0: int, 1: int} [pivot rows, total_qty] */
    private function stock(Reservation $r, Equipment $item): array
    {
        return [DB::table('reservation_equipment')->where('reservation_id', $r->id)->sum('qty_requested'), (int) $item->fresh()->total_qty];
    }

    // ---- hand-out and no-show versus a concurrent move ----

    public function test_hand_out_loses_to_a_move_to_a_future_day_committed_between_check_and_update(): void
    {
        $r = $this->booking('2026-11-04 14:00');
        $item = $this->withEquipment($r);
        $stock = $this->stock($r, $item);
        $this->interleave(fn () => $this->moveTo($r, '2026-11-05', 14 * 60, 15 * 60));

        $this->act('bookings.hand-out', $r)->assertSessionHas('problem');

        $fresh = $r->fresh();
        $this->assertSame('Confirmed', $fresh->status, 'a future-day booking must not become InUse');
        $this->assertSame('2026-11-05', $fresh->start_time->toDateString());
        $this->assertSame(0, $this->logs($r), 'a refused hand-out writes no status log');
        $this->assertSame(1, $this->logs($r, 'Rescheduled'));
        $this->assertSame($stock, $this->stock($r, $item));
        $this->assertStringContainsString('can’t be handed out yet', session('problem'));
        $this->assertStringContainsString('November 5', session('problem'), 'the message names the new day, from the latest row');
    }

    public function test_no_show_loses_to_a_move_to_a_future_day_committed_between_check_and_update(): void
    {
        $r = $this->booking('2026-11-04 14:00');
        $item = $this->withEquipment($r);
        $stock = $this->stock($r, $item);
        $this->interleave(fn () => $this->moveTo($r, '2026-11-05', 14 * 60, 15 * 60));

        $this->act('bookings.no-show', $r)->assertSessionHas('problem');

        $this->assertSame('Confirmed', $this->statusOf($r), 'a future-day booking must not become NoShow');
        $this->assertSame(0, $this->logs($r));
        $this->assertSame($stock, $this->stock($r, $item));
        $this->assertStringContainsString('can’t be a no-show yet', session('problem'));
    }

    public function test_hand_out_and_no_show_win_when_a_concurrent_move_stays_on_the_same_day(): void
    {
        $a = $this->booking('2026-11-04 14:00');
        $b = $this->booking('2026-11-04 17:00');
        $this->interleave(fn () => $this->moveTo($a, '2026-11-04', 16 * 60, 17 * 60));
        $this->act('bookings.hand-out', $a)->assertSessionHas('status');
        $this->assertSame('InUse', $this->statusOf($a));
        $this->assertSame('16:00', $a->fresh()->start_time->format('H:i'), 'the move that came first is kept');
        $this->assertSame(1, $this->logs($a));

        $this->interleave(fn () => $this->moveTo($b, '2026-11-04', 18 * 60, 19 * 60));
        $this->act('bookings.no-show', $b)->assertSessionHas('status');
        $this->assertSame('NoShow', $this->statusOf($b));
        $this->assertSame(1, $this->logs($b));
    }

    public function test_a_move_that_comes_after_a_hand_out_is_refused_and_keeps_the_booking_today(): void
    {
        $r = $this->booking('2026-11-04 14:00');
        $this->act('bookings.hand-out', $r)->assertSessionHas('status');

        try {
            $this->moveTo($r, '2026-11-05', 14 * 60, 15 * 60);
            $this->fail('moving an in-use booking must be refused');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('move', $e->errors());
        }
        $fresh = $r->fresh();
        $this->assertSame('InUse', $fresh->status);
        $this->assertSame('2026-11-04', $fresh->start_time->toDateString());
        $this->assertSame(0, $this->logs($r, 'Rescheduled'));
    }

    public function test_hand_out_loses_to_a_cancel_or_hand_out_committed_between_check_and_update(): void
    {
        $r = $this->booking('2026-11-04 14:00');
        $this->interleave(fn () => $r->newQuery()->whereKey($r->id)->update(['status' => 'Cancelled']));
        $this->act('bookings.hand-out', $r)->assertSessionHas('problem');
        $this->assertSame('Cancelled', $this->statusOf($r));
        $this->assertSame(0, $this->logs($r));

        $x = $this->booking('2026-11-04 15:00');
        $this->interleave(fn () => $x->newQuery()->whereKey($x->id)->update(['status' => 'InUse']));
        $this->act('bookings.no-show', $x)->assertSessionHas('problem');
        $this->assertSame('InUse', $this->statusOf($x), 'a booking someone just handed out is not turned into a no-show');
        $this->assertSame(0, $this->logs($x));
    }

    // ---- unchanged behaviour ----

    public function test_same_day_and_past_day_bookings_are_still_eligible(): void
    {
        foreach (['2026-11-04 08:00', '2026-11-04 20:00', '2026-11-03 10:00', '2026-10-01 10:00'] as $when) {
            $h = $this->booking($when);
            $this->act('bookings.hand-out', $h)->assertSessionHas('status');
            $this->assertSame('InUse', $this->statusOf($h), "hand-out $when");
            $n = $this->booking($when);
            $this->act('bookings.no-show', $n)->assertSessionHas('status');
            $this->assertSame('NoShow', $this->statusOf($n), "no-show $when");
            $this->assertSame(1, $this->logs($h));
            $this->assertSame(1, $this->logs($n));
        }
    }

    public function test_future_day_bookings_are_refused_without_any_change(): void
    {
        foreach (['2026-11-05 00:00', '2026-11-05 14:00', '2026-11-18 09:00'] as $when) {
            $r = $this->booking($when);
            $item = $this->withEquipment($r);
            $stock = $this->stock($r, $item);
            $before = $r->fresh()->updated_at;
            $this->act('bookings.hand-out', $r)->assertSessionHas('problem');
            $this->act('bookings.no-show', $r)->assertSessionHas('problem');
            $fresh = $r->fresh();
            $this->assertSame('Confirmed', $fresh->status, $when);
            $this->assertEquals($before, $fresh->updated_at, $when);
            $this->assertSame(0, OverrideLog::where('reservation_id', $r->id)->count(), $when);
            $this->assertSame($stock, $this->stock($r, $item), $when);
        }
    }

    public function test_wrong_status_is_refused_without_any_change(): void
    {
        foreach (['Pending', 'InUse', 'Completed', 'NoShow', 'Cancelled', 'Rejected'] as $status) {
            $r = $this->booking('2026-11-04 14:00', $status);
            $this->act('bookings.hand-out', $r)->assertSessionHas('problem');
            $this->act('bookings.no-show', $r)->assertSessionHas('problem');
            $this->assertSame($status, $this->statusOf($r));
            $this->assertSame(0, OverrideLog::where('reservation_id', $r->id)->count(), $status);
        }
    }

    public function test_authorization_and_department_boundaries_hold(): void
    {
        $r = $this->booking('2026-11-04 14:00');
        $otherDept = Department::create(['name' => 'Other', 'slug' => 'other-'.Str::lower(Str::random(10))]);
        DepartmentConfig::create(['department_id' => $otherDept->id] + DepartmentConfig::DEFAULTS);
        $otherStaff = $this->user('lab_staff', $otherDept);

        foreach (['bookings.hand-out', 'bookings.no-show'] as $route) {
            $this->assertSame(403, $this->act($route, $r, $this->rep)->getStatusCode());
            $this->assertSame(404, $this->act($route, $r, $otherStaff)->getStatusCode());
        }
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->from('/today')->post(route('bookings.hand-out', $r))->assertRedirect();      // a guest is sent to the login page
        $this->assertSame('Confirmed', $this->statusOf($r));
        $this->assertSame(0, OverrideLog::where('reservation_id', $r->id)->count());
    }

    // ---- the service itself ----

    public function test_set_status_rereads_the_row_under_a_lock_and_refuses_without_writing(): void
    {
        $r = $this->booking('2026-11-05 14:00');
        DB::enableQueryLog();
        $changed = app(StaffDesk::class)->setStatus($r, 'Confirmed', 'InUse', $this->staff, 'x', null, fn (Reservation $fresh) => false);
        $queries = collect(DB::getQueryLog())->pluck('query')->map(fn ($q) => strtolower($q));
        DB::disableQueryLog();

        $this->assertFalse($changed);
        $this->assertTrue($queries->contains(fn ($q) => str_contains($q, 'from `reservations`') && str_contains($q, 'for update')), 'the booking row is locked');
        $this->assertFalse($queries->contains(fn ($q) => str_starts_with($q, 'update') || str_starts_with($q, 'insert')), 'a refusal writes nothing');
        $this->assertSame('Confirmed', $this->statusOf($r));

        $this->assertTrue(app(StaffDesk::class)->setStatus($r, 'Confirmed', 'Cancelled', $this->staff, 'why', 'why'), 'no guard: behaves as before');
        $this->assertFalse(app(StaffDesk::class)->setStatus($r, 'Confirmed', 'Cancelled', $this->staff, 'again', 'again'), 'second change is refused');
        $this->assertSame(1, $this->logs($r, 'Cancelled'));
    }
}
