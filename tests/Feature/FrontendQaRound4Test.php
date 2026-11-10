<?php

namespace Tests\Feature;

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
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Opt-in HTTP regression suite for frontend QA round 4 (calendar selection, Today classes, Reject label, pagination,
 * management columns, email notice, accounts columns, booking-detail navigation). Same isolation contract as GroupFeedbackTest:
 * a disposable, already-migrated MariaDB schema, each test inside a rolled-back transaction.
 */
final class FrontendQaRound4Test extends TestCase
{
    private bool $transactionStarted = false;

    private Department $department;

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
        $this->assertStringEndsWith('_test', $expected);
        $this->assertSame($expected, DB::selectOne('select database() as name')->name);
        DB::beginTransaction();
        $this->transactionStarted = true;

        $this->department = Department::create(['name' => 'QA4 test', 'slug' => 'qa4-'.Str::lower(Str::random(12))]);
        DepartmentConfig::create(['department_id' => $this->department->id] + DepartmentConfig::DEFAULTS);
        $section = Section::create(['name' => '4-'.Str::random(6), 'program' => 'BSCPE']);
        $this->room = Room::create(['name' => 'Q4-'.Str::random(10), 'capacity' => 30, 'is_active' => true]);
        $this->department->rooms()->attach($this->room);
        $this->admin = $this->person('admin', 'Zed Admin');
        $this->staff = $this->person('lab_staff', 'Lou Staff');
        $this->rep = $this->person('rep', 'Rita Rep', $section->id);
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
        $user = User::create([
            'department_id' => $this->department->id, 'section_id' => $sectionId, 'name' => $name,
            'email' => Str::lower(Str::random(14)).'@gmail.com', 'role' => $role, 'password' => 'Starting123',
            'is_active' => true, 'must_change_password' => false,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function as(User $user): static
    {
        $this->flushSession();

        return $this->actingAs($user);
    }

    private function booking(string $status, string $start, string $purpose = 'QA4 booking'): Reservation
    {
        $start = Carbon::parse($start, 'Asia/Manila');

        return Reservation::create(['user_id' => $this->rep->id, 'room_id' => $this->room->id, 'purpose' => $purpose,
            'start_time' => $start, 'end_time' => (clone $start)->addHour(), 'status' => $status]);
    }

    /** The <td> of one calendar day */
    private function cell(string $html, string $date): string
    {
        $label = preg_quote(Carbon::parse($date)->format('l, F j'), '/');
        $this->assertSame(1, preg_match('/<td class="[^"]*">((?:(?!<\/td>).)*?)<span class="sr-only">'.$label.'[^<]*<\/span>.*?<\/td>/s', $html, $m), "cell for $date");

        return $m[0];
    }

    // ---- 1: month arrows ------------------------------------------------------------------------------------------

    public function test_month_arrows_that_lead_nowhere_are_not_drawn_but_today_stays(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 10:00', 'Asia/Manila'));   // window ends before December
        $html = $this->as($this->rep)->get('/rooms')->assertOk()->getContent();
        $this->assertStringNotContainsString('rb-availability__cal-arrow', $html);
        $this->assertStringContainsString('aria-label="Go to today"', $html);
    }

    // ---- 9-11: one selected date ----------------------------------------------------------------------------------

    public function test_adjacent_event_days_each_have_one_link_and_one_selected_state(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 10:00', 'Asia/Manila'));
        $this->booking('Confirmed', '2026-11-16 09:00', 'Monday booking');
        $this->booking('Confirmed', '2026-11-17 09:00', 'Tuesday booking');

        foreach (['2026-11-16' => ['Monday, November 16', 'Monday booking', 'Tuesday booking'], '2026-11-17' => ['Tuesday, November 17', 'Tuesday booking', 'Monday booking']] as $date => [$heading, $shows, $hides]) {
            $html = $this->as($this->rep)->get('/rooms?date='.$date)->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, 'rb-availability__selected'), "$date: exactly one selected cell");
            $this->assertSame(1, substr_count($html, 'aria-current="date"'));
            $this->assertStringContainsString('rb-availability__selected', $this->cell($html, $date));
            $other = $date === '2026-11-16' ? '2026-11-17' : '2026-11-16';
            $this->assertStringNotContainsString('rb-availability__selected', $this->cell($html, $other), 'the previous date carries no selection');
            $this->assertStringNotContainsString('rb-availability__event-count" href', $html, 'no second link in the cell');
            foreach ([$date, $other] as $d) {
                $this->assertSame(1, substr_count($this->cell($html, $d), '<a '), "$d: one link per cell");
                $this->assertStringNotContainsString('<button', $this->cell($html, $d), 'no nested interactive controls');
            }
            $this->assertStringContainsString('<h3 id="events-selected-title">'.$heading.'</h3>', $html);
            $details = substr($html, strpos($html, 'rb-availability__calendar-details'));
            $details = substr($details, 0, strpos($details, 'rb-availability__agenda-card') ?: strlen($details));
            $this->assertStringContainsString($shows, $details);
            $this->assertStringNotContainsString($hides, $details, 'details belong to the selected date only');
        }
    }

    public function test_a_selected_day_without_events_says_so_and_the_window_is_unchanged(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 10:00', 'Asia/Manila'));
        $html = $this->as($this->rep)->get('/rooms?date=2026-11-12')->assertOk()->getContent();
        $this->assertStringContainsString('No events on this day.', $html);
        $this->as($this->rep)->get('/rooms?date=2027-03-01')->assertOk()->assertSee('You can look from today up to');
        $this->assertStringNotContainsString('js/availability.js', $html, 'selection needs no script');
    }

    // ---- 2 / 4: Today classes -------------------------------------------------------------------------------------

    public function test_today_classes_are_shown_directly_and_paged_on_the_server(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 08:00', 'Asia/Manila'));   // a Wednesday
        $term = Term::create(['name' => 'T '.Str::random(6), 'start_date' => '2026-08-01', 'end_date' => '2027-03-01']);
        $section = Section::create(['name' => '3-'.Str::random(6), 'program' => 'BSCPE']);
        $course = Course::create(['code' => 'T'.Str::random(8), 'title' => 'Test course']);
        for ($i = 0; $i < 20; $i++) {
            $room = Room::create(['name' => 'Cl-'.Str::random(9), 'capacity' => 20, 'is_active' => true]);
            $this->department->rooms()->attach($room);
            ClassSchedule::create(['term_id' => $term->id, 'department_id' => $this->department->id, 'room_id' => $room->id, 'section_id' => $section->id, 'course_id' => $course->id,
                'day_of_week' => 'Wed', 'start_time' => sprintf('%02d:00:00', 8 + intdiv($i, 4)), 'end_time' => sprintf('%02d:00:00', 9 + intdiv($i, 4)), 'entry_type' => 'Class']);
        }
        $html = $this->as($this->staff)->get('/today')->assertOk()->getContent();
        $this->assertStringNotContainsString('class="done-list class-list"', $html, 'no accordion');
        $this->assertStringNotContainsString('<summary>Classes', $html);
        $this->assertMatchesRegularExpression('/<h2>Classes <span class="count">20<\/span>/', $html);
        $this->assertSame(15, substr_count($html, 'class="mtable class-table"') ? preg_match_all('/<tr[^>]*>\s*<td class="nowrap">/', $html) : 0, '15 rows on page one');
        $this->assertStringContainsString('Showing 1–15 of 20 classes', $html);
        $this->assertStringContainsString('classes_page=2', $html);

        $two = $this->as($this->staff)->get('/today?classes_page=2')->assertOk()->getContent();
        $this->assertSame(5, preg_match_all('/<tr[^>]*>\s*<td class="nowrap">/', $two));
        $this->assertStringContainsString('Showing 16–20 of 20 classes', $two);
        $this->as($this->staff)->get('/today?classes_page=99')->assertOk()->assertSee('Showing 16–20 of 20 classes');
        $this->assertStringContainsString('Face-to-face?', $two);
    }

    // ---- 3: Reject ------------------------------------------------------------------------------------------------

    public function test_requests_offer_reject_without_an_ellipsis_and_keep_the_reason_form(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 10:00', 'Asia/Manila'));
        $b = $this->booking('Pending', '2026-11-12 09:00');
        $html = $this->as($this->staff)->get('/requests')->assertOk()->getContent();
        $this->assertStringContainsString('data-rb-reveal="reject-'.$b->id.'">Reject<span class="sr-only">', $html);
        $this->assertStringNotContainsString('Reject…', $html);
        $this->assertMatchesRegularExpression('/<form[^>]*id="reject-'.$b->id.'".*?<textarea[^>]*name="reason"[^>]*\brequired\b/s', $html);
        $this->as($this->staff)->post(route('bookings.reject', $b), ['reason' => ''])->assertSessionHasErrors();
        $this->assertSame('Pending', $b->fresh()->status);
    }

    // ---- 4 / 6: rooms and equipment paging, Edit and Delete columns ------------------------------------------------

    public function test_manage_rooms_pages_on_the_server_with_separate_edit_and_delete_cells(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $room = Room::create(['name' => sprintf('QR-%03d', $i), 'capacity' => 20, 'is_active' => true]);
            $this->department->rooms()->attach($room);
        }
        $total = $this->department->rooms()->count();
        $html = $this->as($this->admin)->get('/manage/rooms')->assertOk()->getContent();
        $this->assertSame(12, preg_match_all('/<tr[^>]*>\s*<td><strong>/', $html), 'twelve rooms on a page, not the whole list');
        $this->assertStringContainsString('Showing 1–12 of '.$total.' rooms', $html);
        $this->assertStringContainsString('page=2', $html);
        $this->assertStringContainsString('<th class="col-action">Edit</th><th class="col-action">Delete</th>', $html);
        // Each row has an Edit cell and a Delete cell; an undeletable room leaves its Delete cell empty.
        $this->assertSame(12, preg_match_all('/<td class="col-action room-edit"><a class="row-edit"/', $html));
        $this->assertSame(12, preg_match_all('/<td class="col-action room-delete">/', $html));
        $this->assertStringNotContainsString('class="row-actions"', $html);
        $last = $this->as($this->admin)->get('/manage/rooms?page=3')->assertOk()->getContent();
        $this->assertSame($total - 24, preg_match_all('/<tr[^>]*>\s*<td><strong>/', $last));
        $this->as($this->admin)->get('/manage/rooms?page=999')->assertOk()->assertSee('Showing '.(24 + 1).'–'.$total.' of '.$total.' rooms');
        // Stable order: page one is the first twelve names, page two continues from there.
        $this->assertStringContainsString('QR-001', $html);
        $this->assertStringNotContainsString('QR-013', $html);
        $this->assertStringContainsString('QR-013', $this->as($this->admin)->get('/manage/rooms?page=2')->getContent());
        // Lab staff keep their access.
        $this->as($this->staff)->get('/manage/rooms')->assertOk();
    }

    public function test_manage_equipment_pages_groups_and_searches_on_the_server(): void
    {
        $audio = EquipmentCategory::create(['department_id' => $this->department->id, 'name' => 'Audio', 'sort_order' => 1]);
        $video = EquipmentCategory::create(['department_id' => $this->department->id, 'name' => 'Video', 'sort_order' => 2]);
        $none = EquipmentCategory::create(['department_id' => $this->department->id, 'name' => 'Nothing yet', 'sort_order' => 3]);
        foreach (range(1, 10) as $i) {
            Equipment::create(['category_id' => $audio->id, 'name' => sprintf('Speaker %02d', $i), 'total_qty' => 3, 'max_per_request' => 2, 'is_active' => true]);
        }
        foreach (range(1, 8) as $i) {
            Equipment::create(['category_id' => $video->id, 'name' => sprintf('Projector %02d', $i), 'total_qty' => 3, 'max_per_request' => 2, 'is_active' => true]);
        }
        $one = $this->as($this->admin)->get('/manage/equipment')->assertOk()->getContent();
        $this->assertSame(12, substr_count($one, 'data-eq-row'), 'twelve items on page one');
        $this->assertStringContainsString('Showing 1–12 of 18 items', $one);
        $this->assertStringContainsString('Speaker 10', $one);
        $this->assertStringContainsString('Projector 02', $one);
        $this->assertStringNotContainsString('Projector 03', $one);
        $this->assertStringNotContainsString('id="cat-title-'.$none->id.'"', $one, 'empty categories come after the last page of items');
        $this->assertStringContainsString('<th class="col-action">Delete</th>', $one);
        $two = $this->as($this->admin)->get('/manage/equipment?page=2')->assertOk()->getContent();
        $this->assertSame(6, substr_count($two, 'data-eq-row'));
        $this->assertStringContainsString('Projector 03', $two);
        $this->assertStringContainsString('id="cat-title-'.$none->id.'"', $two);
        $this->assertStringContainsString('Video', $two, 'the category heading repeats where its items continue');
        $this->assertStringContainsString('<span class="count">8</span>', $two, 'the badge is the category total, not the page');
        // Search keeps results server-side, in the pager links too.
        $found = $this->as($this->admin)->get('/manage/equipment?q=projector')->assertOk()->getContent();
        $this->assertSame(8, substr_count($found, 'data-eq-row'));
        $this->assertStringContainsString('8 items match', $found);
        $this->assertStringNotContainsString('Speaker 01', $found);
        $paged = $this->as($this->admin)->get('/manage/equipment?q=e')->assertOk()->getContent();
        $this->assertStringContainsString('q=e', substr($paged, strpos($paged, 'rb-pager')), 'the search survives paging');
        $this->as($this->admin)->get('/manage/equipment?q=zzzz')->assertOk()->assertSee('No equipment or category matches that search.');
        // Wildcards in the search are plain characters.
        $this->assertSame(0, substr_count($this->as($this->admin)->get('/manage/equipment?q=%25')->getContent(), 'data-eq-row'));
    }

    // ---- 5: rename controls ---------------------------------------------------------------------------------------

    public function test_category_rename_controls_share_one_height_rule(): void
    {
        $css = file_get_contents(base_path('public/css/manage.css'));
        $this->assertMatchesRegularExpression('/\.mcat-rename :is\(input\[name="name"\], button\.btn-small, \.btn-ghost\)\s*\{(?=[^}]*box-sizing: border-box)(?=[^}]*height: var\(--rb-control-height\))/', $css);
    }

    // ---- 7: email notice ------------------------------------------------------------------------------------------

    public function test_the_mail_notice_says_messages_are_only_logged_and_goes_away_with_a_real_mailer(): void
    {
        config(['mail.default' => 'log']);
        $html = $this->as($this->admin)->get('/accounts')->assertOk()->getContent();
        $this->assertStringContainsString('This site isn’t delivering email.', $html);
        $this->assertStringContainsString('only written to a log file', $html);
        $this->assertStringNotContainsString('Emails aren’t sent yet', $html);

        config(['mail.default' => 'smtp']);
        $html = $this->as($this->admin)->get('/accounts')->assertOk()->getContent();
        $this->assertStringNotContainsString('isn’t delivering email', $html);
    }

    // ---- 8: accounts columns --------------------------------------------------------------------------------------

    public function test_every_account_group_uses_the_same_four_columns(): void
    {
        $html = $this->as($this->admin)->get('/accounts')->assertOk()->getContent();
        $this->assertSame(3, substr_count($html, '<colgroup><col class="c-name"><col class="c-context"><col class="c-status"><col class="c-edit"></colgroup>'));
        $this->assertSame(3, preg_match_all('/<table class="mtable acct-table">.*?<\/thead>/s', $html, $tables));
        foreach ($tables[0] as $table) {
            $this->assertSame(4, preg_match_all('/<th[ >]/', $table), 'four header cells in every group, Admins included');
        }
        $this->assertMatchesRegularExpression('/<td class="acct-block" aria-hidden="true"><\/td>/', $html, 'Admins: an empty context cell keeps the grid');
    }

    // ---- 12 / 13: booking detail navigation -----------------------------------------------------------------------

    private function activeLinks(string $html): array
    {
        preg_match_all('/<a href="([^"]*)"\s+aria-current="page"[^>]*class="[^"]*rb-nav-item[^"]*"/', $html, $m);

        return array_values(array_unique($m[1]));
    }

    public function test_booking_details_keep_the_menu_entry_it_was_opened_from(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 10:00', 'Asia/Manila'));
        $pending = $this->booking('Pending', '2026-11-12 09:00');
        $approved = $this->booking('Confirmed', '2026-11-12 11:00');

        foreach (['staff', 'admin'] as $who) {
            $as = fn () => $this->as($this->$who);
            $this->assertSame([route('requests')], $this->activeLinks($as()->get(route('bookings.show', [$pending, 'from' => 'requests']))->assertOk()->getContent()), "$who from requests");
            $this->assertSame([route('requests')], $this->activeLinks($as()->get(route('bookings.show', $pending))->assertOk()->getContent()), "$who pending, no context");
            $this->assertSame([route('today')], $this->activeLinks($as()->get(route('bookings.show', [$approved, 'from' => 'today']))->assertOk()->getContent()), "$who from today");
            $this->assertSame([route('today')], $this->activeLinks($as()->get(route('bookings.show', $approved))->assertOk()->getContent()), "$who approved, no context");
            $this->assertSame([route('today')], $this->activeLinks($as()->get(route('bookings.show', [$approved, 'from' => 'https://evil.test']))->assertOk()->getContent()), 'unknown context is ignored');
        }
        $this->assertSame([route('bookings.mine')], $this->activeLinks($this->as($this->rep)->get(route('bookings.show', $approved))->assertOk()->getContent()));
    }

    public function test_requests_and_today_links_carry_their_context_and_no_page_links_to_a_bare_bookings_index(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 10:00', 'Asia/Manila'));
        $b = $this->booking('Pending', '2026-11-11 15:00');
        $requests = $this->as($this->staff)->get('/requests')->assertOk()->getContent();
        $this->assertStringContainsString(route('bookings.show', $b).'?from=requests', $requests);
        $today = $this->as($this->staff)->get('/today')->assertOk()->getContent();
        $this->assertStringContainsString(route('bookings.show', $b).'?from=today', $today);

        $this->as($this->staff)->get('/bookings')->assertNotFound();   // there is no general list: reps use /my-bookings
        foreach ([[$this->rep, '/'], [$this->staff, '/'], [$this->admin, '/'], [$this->rep, '/my-bookings'], [$this->staff, '/requests'], [$this->admin, '/accounts']] as [$user, $url]) {
            $html = $this->as($user)->get($url === '/' ? $user->homeUrl() : $url)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('#href="[^"]*/bookings(?:[?\#"])#', $html, "$url has no link to /bookings");
        }
    }
}
