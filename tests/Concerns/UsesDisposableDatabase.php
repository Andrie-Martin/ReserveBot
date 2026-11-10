<?php

namespace Tests\Concerns;

use App\Models\ClassSchedule;
use App\Models\Course;
use App\Models\Department;
use App\Models\DepartmentConfig;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * For the sandbox tests that need real tables. Same safety rules as the other database suites: they only run
 * with RB_AUTH_HTTP_TESTS=1 against the disposable MariaDB named in RB_AUTH_TEST_DATABASE (it must end in
 * _test; tests/Support/run-db-tests.sh sets everything up), never migrate or seed, and roll back all they wrote.
 * Call startDisposableDatabase() in setUp() and stopDisposableDatabase() in tearDown().
 */
trait UsesDisposableDatabase
{
    private bool $disposableTransaction = false;

    protected function startDisposableDatabase(): void
    {
        if (getenv('RB_AUTH_HTTP_TESTS') !== '1') {
            $this->markTestSkipped('Requires a disposable, provisioned MariaDB test database (tests/Support/run-db-tests.sh).');
        }
        $settings = config('database.connections.'.config('database.default'));
        $expected = (string) getenv('RB_AUTH_TEST_DATABASE');
        $this->assertTrue($this->app->environment('testing'));
        $this->assertNotEmpty($expected);
        $this->assertStringEndsWith('_test', $expected);
        $this->assertSame('mysql', $settings['driver']);
        $this->assertSame($expected, $settings['database']);
        $this->assertEmpty($settings['url'] ?? null, 'DB URL must not override isolation.');
        foreach (['session.driver', 'cache.default', 'mail.default'] as $key) {
            $this->assertSame('array', config($key));
        }
        $this->assertSame($expected, DB::selectOne('select database() as name')->name);
        DB::beginTransaction();
        $this->disposableTransaction = true;
        Notification::fake();
        Storage::fake('local');
    }

    protected function stopDisposableDatabase(): void
    {
        if ($this->disposableTransaction) {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->disposableTransaction = false;
        }
    }

    /**
     * Acts as $account from a clean session, the way another browser would. Needed whenever one test switches
     * accounts: the 'auth.session' middleware logs a session out when the account behind it changes.
     */
    protected function signedInAs(User $account): static
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        return $this->actingAs($account);
    }

    /** A department with its booking rules (the defaults) */
    protected function makeDepartment(string $name = 'Sandbox test department'): Department
    {
        $department = Department::create(['name' => $name, 'slug' => 'sbx-'.Str::lower(Str::random(12))]);
        DepartmentConfig::create(['department_id' => $department->id] + DepartmentConfig::DEFAULTS);

        return $department;
    }

    /** A block (class section) with a unique name, e.g. "BSCPE 4-ab12cd" */
    protected function makeBlock(string $prefix = 'BSCPE 4-'): Section
    {
        return Section::create(['name' => $prefix.Str::lower(Str::random(6)), 'program' => 'BSCPE']);
    }

    /** Puts the block in the department's timetable, which is what makes it one of the department's blocks */
    protected function giveBlockAClass(Department $department, Section $block, ?Room $room = null): ClassSchedule
    {
        $term = Term::firstOrCreate(['name' => 'Sandbox term '.$department->id], [
            'start_date' => CarbonImmutable::today()->subMonth()->toDateString(),
            'end_date' => CarbonImmutable::today()->addMonths(3)->toDateString(),
        ]);
        $room ??= $this->makeRoom($department);

        return ClassSchedule::create([
            'term_id' => $term->id, 'department_id' => $department->id, 'room_id' => $room->id, 'section_id' => $block->id,
            'course_id' => Course::firstOrCreate(['code' => 'SBX '.random_int(100, 999), 'title' => 'Sandbox course'])->id,
            'entry_type' => 'Class', 'day_of_week' => 'Mon', 'start_time' => '07:30', 'end_time' => '10:30',
        ]);
    }

    protected function makeRoom(Department $department): Room
    {
        $room = Room::create(['name' => 'SBX-'.Str::random(8), 'capacity' => 40, 'is_active' => true]);
        $department->rooms()->attach($room);

        return $room;
    }

    protected function makeEquipment(Department $department, string $name = 'Projector', int $stock = 5, int $perBooking = 2): Equipment
    {
        $category = EquipmentCategory::create(['department_id' => $department->id, 'name' => 'Sandbox '.Str::random(6), 'sort_order' => 1]);

        return Equipment::create(['category_id' => $category->id, 'name' => $name.' '.Str::random(5), 'total_qty' => $stock,
            'max_per_request' => $perBooking, 'sort_order' => 1, 'is_active' => true]);
    }

    /** An account that can log in right away (password picked, email connected) */
    protected function makeAccount(Department $department, string $role, array $attributes = []): User
    {
        $user = User::create(array_replace([
            'department_id' => $department->id, 'name' => 'Sandbox '.$role,
            'email' => 'sbx-'.Str::lower(Str::random(14)).'@gmail.com',
            'role' => $role, 'password' => 'Starting123', 'is_active' => true, 'must_change_password' => false,
        ], $attributes));
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    /** A booking written straight to the table (the booking form's own checks are tested elsewhere) */
    protected function makeBooking(User $account, ?Room $room, string $start, string $status = 'Confirmed', array $attributes = []): Reservation
    {
        $startAt = CarbonImmutable::parse($start);

        return Reservation::create(array_replace([
            'user_id' => $account->id, 'room_id' => $room?->id, 'purpose' => 'Sandbox test booking',
            'start_time' => $startAt, 'end_time' => $startAt->addHours(2), 'status' => $status,
            'rep_name' => $account->representativeName(),
        ], $attributes));
    }
}
