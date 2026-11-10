<?php

namespace Tests\Feature;

use App\Support\ResourceDeletion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Deletion safety, part 1: what the database itself guarantees, and how a refusal is worded.
 *
 * The schema test needs the disposable, migrated MariaDB (same gate as the other DB suites: RB_MANAGE_HTTP_TESTS=1 and a
 * schema ending in _test; run it with tests/Support/run-db-tests.sh). It only reads information_schema. The wording tests
 * need no database.
 */
final class ResourceDeletionIntegrityTest extends TestCase
{
    /** Every column that points at a room or an item today. A new one must be added here on purpose, after deciding what deleting should do about it. */
    private const EXPECTED_REFERENCES = [
        'class_schedules.room_id -> rooms',
        'department_room.room_id -> rooms',
        'reservation_equipment.equipment_id -> equipment',
        'reservations.room_id -> rooms',
    ];

    private function requireDisposableDatabase(): void
    {
        if (getenv('RB_MANAGE_HTTP_TESTS') !== '1') {
            $this->markTestSkipped('Requires explicit authorization and a disposable, provisioned MariaDB test database.');
        }
        $expected = (string) getenv('RB_MANAGE_TEST_DATABASE');
        $this->assertStringEndsWith('_test', $expected);
        $this->assertSame($expected, DB::selectOne('select database() as name')->name);
    }

    public function test_every_foreign_key_to_rooms_and_equipment_restricts_and_none_cascades(): void
    {
        $this->requireDisposableDatabase();

        $rows = DB::select(
            'select k.TABLE_NAME as t, k.COLUMN_NAME as c, k.REFERENCED_TABLE_NAME as ref, r.DELETE_RULE as del, r.UPDATE_RULE as upd
               from information_schema.KEY_COLUMN_USAGE k
               join information_schema.REFERENTIAL_CONSTRAINTS r
                 on r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA and r.CONSTRAINT_NAME = k.CONSTRAINT_NAME and r.TABLE_NAME = k.TABLE_NAME
              where k.TABLE_SCHEMA = database() and k.REFERENCED_TABLE_NAME in ("rooms", "equipment")
              order by k.TABLE_NAME, k.COLUMN_NAME'
        );
        $found = [];
        foreach ($rows as $row) {
            $found[] = $row->t.'.'.$row->c.' -> '.$row->ref;
            $this->assertContains($row->del, ['RESTRICT', 'NO ACTION'], "{$row->t}.{$row->c}: deleting a {$row->ref} row must be refused, not cascaded or nulled (rule is {$row->del})");
        }
        sort($found);
        $this->assertSame(self::EXPECTED_REFERENCES, $found, 'the set of references to rooms/equipment changed: decide what deleting should do about the new one, then update ResourceDeletion and this list');
    }

    public function test_no_table_refers_to_a_room_or_an_item_without_a_foreign_key(): void
    {
        $this->requireDisposableDatabase();

        // A room_id / equipment_id column with no constraint would let a delete orphan rows silently.
        $columns = collect(DB::select(
            'select c.TABLE_NAME as t, c.COLUMN_NAME as c
               from information_schema.COLUMNS c
               join information_schema.TABLES t on t.TABLE_SCHEMA = c.TABLE_SCHEMA and t.TABLE_NAME = c.TABLE_NAME and t.TABLE_TYPE = "BASE TABLE"
              where c.TABLE_SCHEMA = database() and c.COLUMN_NAME in ("room_id", "equipment_id")'
        ))->map(fn ($row) => $row->t.'.'.$row->c)->sort()->values()->all();

        $constrained = collect(self::EXPECTED_REFERENCES)->map(fn ($ref) => explode(' -> ', $ref)[0])->sort()->values()->all();
        $this->assertSame($constrained, $columns, 'every room_id / equipment_id column must be a real foreign key');
    }

    public function test_the_engine_enforces_the_keys(): void
    {
        $this->requireDisposableDatabase();

        foreach (['reservations', 'class_schedules', 'department_room', 'reservation_equipment', 'rooms', 'equipment'] as $table) {
            $engine = DB::selectOne('select ENGINE as e from information_schema.TABLES where TABLE_SCHEMA = database() and TABLE_NAME = ?', [$table])->e;
            $this->assertSame('InnoDB', $engine, "$table must be InnoDB or its foreign keys are decoration");
        }
        $this->assertSame('1', (string) DB::selectOne('select @@foreign_key_checks as v')->v, 'foreign_key_checks is on');
    }

    public function test_the_only_cascade_below_a_booking_is_its_own_equipment_lines_and_no_code_deletes_bookings(): void
    {
        $this->requireDisposableDatabase();

        // reservation_equipment.reservation_id cascades from a RESERVATION (its own item lines). That is safe only while nothing deletes
        // reservations, so guard that too: no app code may delete a booking.
        $hits = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app'), \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $code = file_get_contents($file->getPathname());
            if (preg_match('/Reservation::[^;]*->delete\(|Reservation::destroy\(|Reservation::truncate|table\(\'reservations\'\)[^;]*->delete\(/', $code)) {
                $hits[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }
        $this->assertSame([], $hits, 'something deletes bookings; deleting a booking cascades its equipment lines, so review it');
    }

    // ---- how a refused delete is worded (no database needed) ---------------------------------------------------------

    private function failure(int $driverCode, string $sqlstate = '23000', string $detail = 'SQLSTATE[23000]: secret table `reservations` details'): QueryException
    {
        $pdo = new \PDOException($detail);
        $pdo->errorInfo = [$sqlstate, $driverCode, $detail];

        return new QueryException('mysql', 'delete from `rooms` where `id` = ?', [7], $pdo);
    }

    public function test_a_foreign_key_refusal_says_something_started_using_it(): void
    {
        Log::spy();
        foreach ([1451, 1452] as $code) {
            $message = ResourceDeletion::messageForFailure($this->failure($code), 'Lab 1', ['room_id' => 7]);
            $this->assertStringContainsString('Something started using Lab 1 while you were deleting it, so it was kept.', $message);
            $this->assertStringContainsString('Turn it off instead', $message);
        }
        Log::shouldHaveReceived('warning')->twice();
    }

    public function test_a_busy_database_is_not_reported_as_somebody_using_the_room(): void
    {
        Log::spy();
        foreach ([[1205, 'HY000'], [1213, '40001']] as [$code, $state]) {
            $message = ResourceDeletion::messageForFailure($this->failure($code, $state), 'Lab 1');
            $this->assertStringNotContainsString('started using', $message);
            $this->assertStringContainsString('was busy', $message);
            $this->assertStringContainsString('Nothing was changed', $message);
            $this->assertStringContainsString('try again', strtolower($message));
        }
    }

    public function test_an_unexpected_database_error_is_logged_and_shown_without_technical_detail(): void
    {
        Log::spy();
        $message = ResourceDeletion::messageForFailure($this->failure(2006, 'HY000', 'MySQL server has gone away'), 'Lab 1', ['room_id' => 7]);
        $this->assertStringNotContainsString('started using', $message, 'not misreported as a foreign-key refusal');
        $this->assertStringContainsString('could not be deleted because of a problem on our side', $message);
        $this->assertStringContainsString('Nothing was changed', $message);
        foreach (['gone away', 'SQLSTATE', 'secret', 'reservations', 'delete from', '2006'] as $leak) {
            $this->assertStringNotContainsString($leak, $message, "no technical detail ($leak) on the page");
        }
        Log::shouldHaveReceived('error')->once();   // the detail goes to the log for whoever runs the system
    }
}
