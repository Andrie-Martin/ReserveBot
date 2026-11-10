<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentConfig;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Room;
use App\Models\User;
use App\Services\Bookings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Deletion safety, part 2: a booking and a delete happening at the same moment.
 *
 * This needs REAL concurrency, so unlike the other suites it cannot use one rolled-back transaction: it writes committed rows
 * through the app's connection and plays "the other request" on a second connection, then removes everything it made. It runs only
 * against the disposable, migrated MariaDB (RB_MANAGE_HTTP_TESTS=1, schema ending in _test; use tests/Support/run-db-tests.sh).
 *
 * What it proves, with the real InnoDB locks and the real controllers:
 *  - a booking in flight holds its room/item, so a delete waits, then (if it times out) changes nothing and says "busy";
 *  - once that booking commits, the delete is refused because of the booking, and nothing is lost;
 *  - a delete in flight holds the room/item, so a booking waits, and once the delete commits the booking is refused by the foreign key
 *    (or, through the app, by a clear message), and no orphan row can exist.
 */
final class ResourceDeletionConcurrencyTest extends TestCase
{
    private Connection $other;

    private Department $department;

    private User $staff;

    private User $rep;

    private Room $room;

    private Equipment $item;

    /** @var list<int> */
    private array $roomIds = [];

    /** @var list<int> */
    private array $itemIds = [];

    private ?int $sectionId = null;

    private bool $ready = false;

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
        $this->assertSame(0, DB::transactionLevel(), 'this suite needs committed data, not a wrapping transaction');

        // The second connection is the same database, a separate session: the "other request".
        config(['database.connections.second' => $settings]);
        DB::purge('second');
        $this->other = DB::connection('second');
        foreach ([DB::connection(), $this->other] as $connection) {
            $connection->statement('set session innodb_lock_wait_timeout = 1');   // a blocked statement gives up after a second
        }

        $this->department = Department::create(['name' => 'Concurrency test', 'slug' => 'cc-'.Str::lower(Str::random(12))]);
        DepartmentConfig::create(['department_id' => $this->department->id] + DepartmentConfig::DEFAULTS);
        $this->sectionId = DB::table('sections')->insertGetId(['name' => '9-'.Str::random(6), 'program' => 'BSCPE']);
        $this->staff = $this->person('lab_staff');
        $this->rep = $this->person('rep', $this->sectionId);
        $this->room = $this->newRoom();
        $category = EquipmentCategory::create(['department_id' => $this->department->id, 'name' => 'Cat-'.Str::random(8), 'sort_order' => 1]);
        $this->item = Equipment::create(['category_id' => $category->id, 'name' => 'Item-'.Str::random(8), 'total_qty' => 5, 'max_per_request' => 2, 'sort_order' => 1, 'is_active' => true]);
        $this->itemIds[] = $this->item->id;
        $this->ready = true;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->ready) {
                foreach ([DB::connection(), $this->other] as $connection) {
                    while ($connection->transactionLevel() > 0) {
                        $connection->rollBack();
                    }
                }
                $this->cleanUp();
            }
        } finally {
            parent::tearDown();
        }
    }

    /** Removes only what this test made, in dependency order (the schema is disposable, but leave it as found). */
    private function cleanUp(): void
    {
        $users = DB::table('users')->where('department_id', $this->department->id)->pluck('id');
        $reservations = DB::table('reservations')->whereIn('user_id', $users)->pluck('id');
        DB::table('reservation_equipment')->whereIn('reservation_id', $reservations)->delete();
        DB::table('reservations')->whereIn('id', $reservations)->delete();
        DB::table('department_room')->where('department_id', $this->department->id)->delete();
        DB::table('rooms')->whereIn('id', $this->roomIds)->delete();
        DB::table('equipment')->whereIn('id', $this->itemIds)->delete();
        DB::table('equipment_categories')->where('department_id', $this->department->id)->delete();
        DB::table('users')->where('department_id', $this->department->id)->delete();
        DB::table('sections')->where('id', $this->sectionId)->delete();
        DB::table('department_configs')->where('department_id', $this->department->id)->delete();
        DB::table('departments')->where('id', $this->department->id)->delete();
    }

    private function person(string $role, ?int $sectionId = null): User
    {
        return User::create([
            'department_id' => $this->department->id, 'section_id' => $sectionId, 'name' => ucfirst($role).' CC',
            'email' => Str::lower(Str::random(14)).'@gmail.com', 'role' => $role, 'password' => 'Starting123',
            'is_active' => true, 'must_change_password' => false, 'email_verified_at' => now(),
        ]);
    }

    private function newRoom(): Room
    {
        $room = Room::create(['name' => 'CC-'.Str::random(10), 'capacity' => 10, 'is_active' => true]);
        $this->department->rooms()->attach($room);
        $this->roomIds[] = $room->id;

        return $room;
    }

    private function as(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    /** The other request's INSERT of a booking: a raw insert on the second connection, inside its own open transaction. */
    private function insertBooking(Connection $on, ?int $roomId): int
    {
        return $on->table('reservations')->insertGetId([
            'user_id' => $this->rep->id, 'room_id' => $roomId, 'purpose' => 'Concurrent booking',
            'start_time' => now()->addDays(2)->setTime(9, 0), 'end_time' => now()->addDays(2)->setTime(10, 0),
            'status' => 'Pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function assertNoOrphans(): void
    {
        $this->assertSame(0, DB::selectOne('select count(*) as n from reservations r left join rooms x on x.id = r.room_id where r.room_id is not null and x.id is null')->n, 'a booking points at a missing room');
        $this->assertSame(0, DB::selectOne('select count(*) as n from class_schedules r left join rooms x on x.id = r.room_id where x.id is null')->n, 'a class points at a missing room');
        $this->assertSame(0, DB::selectOne('select count(*) as n from department_room r left join rooms x on x.id = r.room_id where x.id is null')->n, 'a list entry points at a missing room');
        $this->assertSame(0, DB::selectOne('select count(*) as n from reservation_equipment r left join equipment x on x.id = r.equipment_id where x.id is null')->n, 'a booking line points at a missing item');
    }

    // ---- a booking is in flight when the delete arrives -----------------------------------------------------------

    public function test_a_booking_in_flight_holds_the_room_so_the_delete_waits_then_is_refused_and_nothing_is_lost(): void
    {
        $this->other->beginTransaction();
        $bookingId = $this->insertBooking($this->other, $this->room->id);   // uncommitted: holds a shared lock on the room row

        // The delete can't take its lock; after the wait it gives up cleanly, with the honest "busy" wording.
        $this->as($this->staff)->delete(route('manage.rooms.destroy', $this->room), ['confirm' => '1'])
            ->assertRedirect(route('manage.rooms.edit', $this->room))->assertSessionHasErrors(null, 'delete');
        $message = implode(' ', session('errors')->getBag('delete')->all());
        $this->assertStringContainsString('was busy', $message);
        $this->assertStringNotContainsString('started using', $message, 'a lock timeout is not a foreign-key refusal');
        $this->assertNotNull(Room::find($this->room->id), 'the room survived');
        $this->assertTrue($this->department->rooms()->whereKey($this->room->id)->exists(), 'and kept its place on the list');

        // The booking commits; trying the delete again now sees it and refuses for the right reason.
        $this->other->commit();
        $this->as($this->staff)->delete(route('manage.rooms.destroy', $this->room), ['confirm' => '1'])->assertSessionHasErrors(null, 'delete');
        $this->assertStringContainsString('1 booking on record', implode(' ', session('errors')->getBag('delete')->all()));
        $this->assertNotNull(Room::find($this->room->id));
        $this->assertSame($this->room->id, (int) DB::table('reservations')->where('id', $bookingId)->value('room_id'), 'the booking still has its room');
        $this->assertNoOrphans();
    }

    public function test_an_item_on_a_booking_in_flight_cannot_be_deleted_either(): void
    {
        $this->other->beginTransaction();
        $bookingId = $this->insertBooking($this->other, null);   // an equipment-only booking
        $this->other->table('reservation_equipment')->insert(['reservation_id' => $bookingId, 'equipment_id' => $this->item->id, 'qty_requested' => 1]);

        $this->as($this->staff)->delete(route('manage.equipment.destroy', $this->item), ['confirm' => '1'])
            ->assertRedirect(route('manage.equipment.edit', $this->item))->assertSessionHasErrors(null, 'delete');
        $this->assertStringContainsString('was busy', implode(' ', session('errors')->getBag('delete')->all()));
        $this->assertNotNull(Equipment::find($this->item->id));

        $this->other->commit();
        $this->as($this->staff)->delete(route('manage.equipment.destroy', $this->item), ['confirm' => '1'])->assertSessionHasErrors(null, 'delete');
        $this->assertStringContainsString('1 booking on record', implode(' ', session('errors')->getBag('delete')->all()));
        $this->assertNotNull(Equipment::find($this->item->id));
        $this->assertSame(1, DB::table('reservation_equipment')->where('equipment_id', $this->item->id)->count());
        $this->assertNoOrphans();
    }

    // ---- the delete is in flight when a booking arrives -----------------------------------------------------------

    public function test_a_delete_in_flight_holds_the_room_so_a_booking_waits_and_afterwards_cannot_orphan(): void
    {
        // The delete's own first step (see ManageRoomsController::destroy): lock the room row inside a transaction.
        DB::beginTransaction();
        $this->assertNotNull(Room::whereKey($this->room->id)->lockForUpdate()->first());

        $this->other->beginTransaction();
        try {
            $this->insertBooking($this->other, $this->room->id);
            $this->fail('the booking must wait for the delete, not slip in beside it');
        } catch (QueryException $e) {
            $this->assertSame(1205, (int) $e->errorInfo[1], 'it waited for the lock and timed out');
        }
        $this->other->rollBack();

        // The delete finishes (nothing refers to the room).
        $this->room->departments()->detach();
        $this->room->delete();
        DB::commit();

        // The booking that was waiting now fails on the foreign key: no booking row exists for the missing room.
        $this->other->beginTransaction();
        try {
            $this->insertBooking($this->other, $this->room->id);
            $this->fail('the foreign key must refuse a booking for a deleted room');
        } catch (QueryException $e) {
            $this->assertSame(1452, (int) $e->errorInfo[1]);
        }
        $this->other->rollBack();
        $this->assertSame(0, DB::table('reservations')->where('room_id', $this->room->id)->count());
        $this->assertNoOrphans();
    }

    public function test_a_delete_in_flight_holds_the_item_so_a_booking_line_waits_and_afterwards_cannot_orphan(): void
    {
        $bookingId = $this->insertBooking(DB::connection(), null);   // an earlier, unrelated equipment-only booking, committed
        DB::beginTransaction();
        $this->assertNotNull(Equipment::whereKey($this->item->id)->lockForUpdate()->first());

        $this->other->beginTransaction();
        try {
            $this->other->table('reservation_equipment')->insert(['reservation_id' => $bookingId, 'equipment_id' => $this->item->id, 'qty_requested' => 1]);
            $this->fail('the booking line must wait for the delete');
        } catch (QueryException $e) {
            $this->assertSame(1205, (int) $e->errorInfo[1]);
        }
        $this->other->rollBack();

        $this->item->delete();
        DB::commit();

        $this->other->beginTransaction();
        try {
            $this->other->table('reservation_equipment')->insert(['reservation_id' => $bookingId, 'equipment_id' => $this->item->id, 'qty_requested' => 1]);
            $this->fail('the foreign key must refuse a line for a deleted item');
        } catch (QueryException $e) {
            $this->assertSame(1452, (int) $e->errorInfo[1]);
        }
        $this->other->rollBack();
        $this->assertSame(0, DB::table('reservation_equipment')->where('equipment_id', $this->item->id)->count());
        $this->assertNoOrphans();
    }

    // ---- a booking form that was checked before the delete, saved after it -------------------------------------------

    private function placement(array $override): array
    {
        $when = CarbonImmutable::now()->addDays(2)->setTime(9, 0);

        return $override + [
            'user' => $this->rep, 'session' => null, 'day' => $when->startOfDay(), 'start' => $when, 'end' => $when->addHour(),
            'room' => null, 'items' => [], 'other_items' => null, 'purpose' => 'Stale form', 'rules' => null,
        ];
    }

    public function test_saving_a_booking_for_a_room_deleted_a_moment_ago_gives_a_message_not_an_error(): void
    {
        $stale = Room::find($this->room->id);   // what the booking form had loaded
        $this->as($this->staff)->delete(route('manage.rooms.destroy', $this->room), ['confirm' => '1'])->assertRedirect(route('manage.rooms'));
        $this->assertNull(Room::find($this->room->id));

        try {
            app(Bookings::class)->place($this->placement(['room' => $stale]));
            $this->fail('a booking for a deleted room must be refused');
        } catch (ValidationException $e) {
            $this->assertSame(['room'], array_keys($e->errors()));
            $this->assertSame('Pick a free room from the list.', $e->errors()['room'][0], 'the same words the form uses for a room that is not on the list');
        }
        $this->assertSame(0, DB::table('reservations')->where('user_id', $this->rep->id)->count(), 'nothing was saved');
        $this->assertNoOrphans();
    }

    public function test_saving_a_booking_for_an_item_deleted_a_moment_ago_gives_a_message_not_an_error(): void
    {
        $this->as($this->staff)->delete(route('manage.equipment.destroy', $this->item), ['confirm' => '1'])->assertRedirect(route('manage.equipment'));
        $this->assertNull(Equipment::find($this->item->id));

        try {
            app(Bookings::class)->place($this->placement(['items' => [$this->item->id => 1]]));
            $this->fail('a booking for a deleted item must be refused');
        } catch (ValidationException $e) {
            $this->assertSame(['qty'], array_keys($e->errors()));
            $this->assertSame('Pick equipment from the list.', $e->errors()['qty'][0]);
        }
        $this->assertSame(0, DB::table('reservations')->where('user_id', $this->rep->id)->count());
        $this->assertNoOrphans();
    }

    public function test_the_normal_delete_still_works_with_nothing_in_flight(): void
    {
        // Guard against a lock-wait setting or a fixture quirk making every delete in this suite fail for the wrong reason.
        $this->as($this->staff)->delete(route('manage.rooms.destroy', $this->room), ['confirm' => '1'])->assertSessionHas('status');
        $this->as($this->staff)->delete(route('manage.equipment.destroy', $this->item), ['confirm' => '1'])->assertSessionHas('status');
        $this->assertNull(Room::find($this->room->id));
        $this->assertNull(Equipment::find($this->item->id));
        $this->assertNoOrphans();
    }
}
