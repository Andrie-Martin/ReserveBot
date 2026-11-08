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
 * Opt-in HTTP regression suite for the server-side pages on Accounts (20 per page) and My bookings (10 past
 * bookings per page), and for the Requests card structure. Same isolation contract as ManageRulesWorkflowTest:
 * a disposable, already-migrated MariaDB schema (RB_MANAGE_HTTP_TESTS=1, RB_MANAGE_TEST_DATABASE=..._test), every
 * test inside a rolled-back transaction, nothing migrated, seeded or truncated.
 */
final class PaginationWorkflowTest extends TestCase
{
    private bool $transactionStarted = false;
    private Department $department;
    private Section $section;
    private Room $room;
    private User $admin;
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

        $this->department = Department::create(['name' => 'Pager test', 'slug' => 'pager-'.Str::lower(Str::random(12))]);
        DepartmentConfig::create(['department_id' => $this->department->id] + DepartmentConfig::DEFAULTS);
        $this->section = Section::create(['name' => '4-'.Str::random(6), 'program' => 'BSCPE']);
        $this->room = Room::create(['name' => 'Pager-'.Str::random(10), 'capacity' => 30, 'is_active' => true]);
        $this->admin = $this->person('admin', 'Zed Admin');
        $this->rep = $this->person('rep', 'Rita Rep', $this->section->id);
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

    private function person(string $role, string $name, ?int $sectionId = null): User
    {
        return User::create([
            'department_id' => $this->department->id, 'section_id' => $sectionId, 'name' => $name,
            'email' => Str::lower(Str::random(14)).'@gmail.com', 'role' => $role, 'password' => 'Starting123',
            'is_active' => true, 'must_change_password' => false, 'email_verified_at' => now(),
        ]);
    }

    private function booking(string $status, \DateTimeInterface $start, string $purpose): Reservation
    {
        return Reservation::create(['user_id' => $this->rep->id, 'room_id' => $this->room->id, 'purpose' => $purpose,
            'start_time' => $start, 'end_time' => (clone $start)->modify('+1 hour'), 'status' => $status]);
    }

    public function test_accounts_are_paged_twenty_at_a_time_with_totals_and_links(): void
    {
        foreach (range(1, 44) as $i) {
            $this->person('rep', sprintf('Pager Person %02d', $i), $this->section->id);
        }
        // 1 admin + 1 rep + 44 = 46 people.
        $first = $this->actingAs($this->admin)->get('/accounts?filter=keep');
        $first->assertOk()->assertSee('Showing 1–20 of 46 accounts')->assertSee('Page 1 of 3');
        $this->assertSame(20, substr_count($first->getContent(), 'class="acct-name"'));
        $first->assertSee('/accounts?filter=keep&amp;page=2', false);          // other query parameters survive
        $first->assertSee('<span class="count">45</span>', false);              // group totals are not page totals

        $third = $this->actingAs($this->admin)->get('/accounts?page=3');
        $third->assertOk()->assertSee('Showing 41–46 of 46 accounts')->assertSee('Page 3 of 3');
        $this->assertSame(6, substr_count($third->getContent(), 'class="acct-name"'));
        $third->assertSee('Zed Admin');                                          // admins sort last, so they are on the last page
    }

    public function test_accounts_page_past_the_end_shows_the_last_page_and_junk_shows_the_first(): void
    {
        foreach (range(1, 24) as $i) {
            $this->person('rep', 'Pager Person '.$i, $this->section->id);
        }
        $this->actingAs($this->admin)->get('/accounts?page=99')->assertOk()->assertSee('Page 2 of 2');
        $this->actingAs($this->admin)->get('/accounts?page=abc')->assertOk()->assertSee('Page 1 of 2');
        $this->actingAs($this->admin)->get('/accounts?page=-4')->assertOk()->assertSee('Page 1 of 2');
    }

    public function test_a_small_department_has_totals_but_no_page_links(): void
    {
        $this->actingAs($this->admin)->get('/accounts')->assertOk()
            ->assertSee('Showing 1–2 of 2 accounts')->assertDontSee('Page 1 of');
    }

    public function test_past_bookings_are_paged_ten_at_a_time_and_upcoming_stays_whole(): void
    {
        foreach (range(1, 23) as $i) {
            $this->booking('Cancelled', now()->subDays($i + 2), 'Old booking '.$i);
        }
        foreach (range(1, 3) as $i) {
            $this->booking('Pending', now()->addDays($i + 2), 'Soon booking '.$i);
        }
        $page = $this->actingAs($this->rep)->get('/my-bookings');
        $page->assertOk()->assertSee('Showing 1–10 of 23 bookings')->assertSee('Soon booking 1')->assertSee('Soon booking 3');
        $page->assertSee('Old booking 1')->assertDontSee('Old booking 11')->assertDontSee('Old booking 23');
        $page->assertSee('past_page=2#past', false);                             // the next link keeps the section anchor

        $third = $this->actingAs($this->rep)->get('/my-bookings?past_page=3');
        $third->assertOk()->assertSee('Showing 21–23 of 23 bookings')->assertSee('Old booking 23')->assertSee('Soon booking 2');
        $third->assertDontSee('Old booking 1<', false);

        $this->actingAs($this->rep)->get('/my-bookings?past_page=50')->assertOk()->assertSee('Showing 21–23 of 23 bookings');
    }

    public function test_a_booking_that_ended_but_was_never_decided_counts_as_past_not_upcoming(): void
    {
        $this->booking('Pending', now()->subDays(3), 'Never decided');
        $page = $this->actingAs($this->rep)->get('/my-bookings');
        $page->assertOk()->assertSee('Showing 1–1 of 1 bookings');
        $this->assertStringContainsString('Never decided', substr($page->getContent(), (int) strpos($page->getContent(), 'id="past"')));
    }

    public function test_requests_cards_keep_approve_reject_and_the_required_reason_wired_to_the_same_routes(): void
    {
        $booking = $this->booking('Pending', now()->addDays(4), 'Card structure check');
        $staff = $this->person('lab_staff', 'Lab Person');
        $html = $this->actingAs($staff)->get('/requests')->assertOk()->getContent();

        $this->assertStringContainsString('action="'.route('bookings.approve', $booking).'"', $html);
        $this->assertStringContainsString('action="'.route('bookings.reject', $booking).'"', $html);
        // The reason field sits in its own full-width form, outside the narrow action row, and is still required.
        $this->assertMatchesRegularExpression('/<form[^>]*id="reject-'.$booking->id.'"[^>]*class="[^"]*req-reject[^"]*"[^>]*>.*?<textarea[^>]*name="reason"[^>]*required/s', $html);
        $this->assertDoesNotMatchRegularExpression('/class="req-actions">(?:(?!<\/div>).)*req-reject/s', $html);

        $this->actingAs($staff)->post(route('bookings.reject', $booking), ['reason' => ''])->assertSessionHasErrors('reason', null, 'reject'.$booking->id);
        $this->assertSame('Pending', $booking->fresh()->status);
    }

    public function test_past_bookings_with_identical_start_times_keep_oldest_id_first_across_page_boundaries(): void
    {
        $start = now()->subDays(5)->setTime(9, 0);
        $ids = [];
        foreach (range(1, 14) as $i) {
            $ids[] = $this->booking('Cancelled', $start, 'Tie '.$i)->id;       // all share one start time
        }
        $seen = [];
        foreach ([1, 2] as $page) {
            $html = $this->actingAs($this->rep)->get('/my-bookings?past_page='.$page)->assertOk()->getContent();
            preg_match_all('/Tie (\d+)/', $html, $m);
            $seen = array_merge($seen, array_map('intval', array_unique($m[1])));
        }
        $this->assertSame(range(1, 14), $seen, 'oldest id first, no gaps or repeats between pages 1 and 2');
    }

    public function test_requests_split_into_room_and_equipment_only_groups_keeping_soonest_first_order(): void
    {
        $staff = $this->person('lab_staff', 'Lab Grouper');
        $room1 = $this->booking('Pending', now()->addDays(6)->setTime(9, 0), 'Room later');
        $equip = Reservation::create(['user_id' => $this->rep->id, 'room_id' => null, 'purpose' => 'Borrow only', 'start_time' => now()->addDays(3)->setTime(9, 0), 'end_time' => now()->addDays(3)->setTime(10, 0), 'status' => 'Pending', 'other_items' => 'Extension cord']);
        $room2 = $this->booking('Pending', now()->addDays(4)->setTime(9, 0), 'Room sooner');
        $html = $this->actingAs($staff)->get('/requests')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/Room requests <span class="count">2<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Equipment-only requests <span class="count">1<\/span>/', $html);
        $roomPart = substr($html, strpos($html, 'req-group--room'), strpos($html, 'req-group--equipment') - strpos($html, 'req-group--room'));
        $equipPart = substr($html, strpos($html, 'req-group--equipment'));
        $this->assertLessThan(strpos($roomPart, 'id="req-'.$room1->id.'"'), strpos($roomPart, 'id="req-'.$room2->id.'"'), 'soonest first inside the room group');
        $this->assertStringNotContainsString('id="req-'.$equip->id.'"', $roomPart);
        $this->assertStringContainsString('id="req-'.$equip->id.'"', $equipPart);
        $this->assertStringContainsString('action="'.route('bookings.approve', $equip).'"', $equipPart);
    }
}
