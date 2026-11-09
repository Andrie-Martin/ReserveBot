<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentConfig;
use App\Models\ClassSchedule;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Section;
use App\Models\Term;
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
function status_offset(string $status): int
{
    return in_array($status, ['Completed', 'Cancelled', 'Rejected', 'NoShow'], true) ? -3 : 3;
}

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

            // Two independent native forms, each owning exactly one submit button, inside one action grid.
            $dom = new \DOMDocument();
            @$dom->loadHTML($html);
            $xp = new \DOMXPath($dom);
            $forms = $xp->query('//div[contains(concat(" ", normalize-space(@class), " "), " rb-account-photo__forms ")]/form');
            $this->assertSame($user->photo_path ? 2 : 1, $forms->length, $user->role);
            [$upload, $remove] = [$forms->item(0), $forms->item(1)];
            $this->assertSame(route('account.photo'), $upload->getAttribute('action'));
            $this->assertSame('multipart/form-data', $upload->getAttribute('enctype'));
            $this->assertSame('Replace photo', trim($xp->query('.//button[@type="submit"]', $upload)->item(0)->textContent));
            $this->assertSame(route('account.photo.remove'), $remove->getAttribute('action'));
            $this->assertSame('DELETE', $xp->query('.//input[@name="_method"]', $remove)->item(0)->getAttribute('value'));
            $this->assertSame(1, $xp->query('.//input[@name="_token"]', $remove)->length, 'CSRF on the DELETE form');
            $removeButton = $xp->query('.//button[@type="submit"]', $remove);
            $this->assertSame(1, $removeButton->length);
            $this->assertSame('Remove photo', trim($removeButton->item(0)->textContent));
            $this->assertStringContainsString('rb-button--danger-outline', $removeButton->item(0)->getAttribute('class'));
            $this->assertSame(1, $xp->query('.//button[@type="submit"]', $upload)->length, 'each form owns exactly one submit button');
            $this->assertSame(0, $xp->query('//button[@form]')->length, 'no cross-form submitters: Remove cannot submit the upload form');
            $this->assertStringContainsString('accept="image/jpeg,image/png,image/webp"', $html);
        }

        // No photo yet: Upload only, no Remove control and no stray DELETE form.
        $html = $this->as($this->person($this->department, 'rep', 'No Photo'))->get('/account')->assertOk()->getContent();
        $this->assertStringContainsString('>Upload photo</button>', $html);
        $this->assertStringNotContainsString('Remove photo', $html);
        $this->assertStringNotContainsString('name="_method" value="DELETE"', $html, 'no DELETE form without a photo');
    }

    public function test_photo_validation_errors_still_show_with_the_new_action_grid(): void
    {
        $response = $this->as($this->rep)->from('/account')->post('/account/photo', []);
        $response->assertRedirect('/account')->assertSessionHasErrors('photo', null, 'photo');
        $this->get('/account')->assertOk()->assertSee('rb-account-photo__forms', false);
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

    // ---- 4: Free rooms calendar ---------------------------------------------------------------------------------------

    /** The classes of the calendar cell for a date, then its screen-reader text (never crosses into another cell). */
    private function cell(string $html, string $date): string
    {
        $label = preg_quote(\Carbon\Carbon::parse($date)->format('l, F j'), '/');
        $this->assertSame(1, preg_match('/<td class="([^"]*)">((?:(?!<\/td>).)*?)<span class="sr-only">'.$label.'([^<]*)<\/span>/s', $html, $m), "cell for $date");

        return $m[1].' || '.$m[3];
    }

    public function test_today_and_selected_date_are_separate_markers_and_both_show_when_equal(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-11 10:00', 'Asia/Manila'));   // a Wednesday: open
        $other = $this->as($this->rep)->get('/rooms?date=2026-11-13')->assertOk()->getContent();
        $today = $this->cell($other, '2026-11-11');
        $this->assertStringContainsString('rb-availability__today', $today);
        $this->assertStringNotContainsString('rb-availability__selected', $today);
        $this->assertStringContainsString(', today', $today);
        $picked = $this->cell($other, '2026-11-13');
        $this->assertStringContainsString('rb-availability__selected', $picked);
        $this->assertStringNotContainsString('rb-availability__today', $picked);
        $this->assertStringContainsString(', selected', $picked, 'selection is also written out for screen readers, not only drawn');
        $this->assertSame(1, substr_count($other, 'aria-current="date"'), 'exactly one selected link');

        // No date asked for: today is the selected day, and both meanings are on the same cell.
        $same = $this->cell($this->as($this->rep)->get('/rooms')->assertOk()->getContent(), '2026-11-11');
        $this->assertStringContainsString('rb-availability__today', $same);
        $this->assertStringContainsString('rb-availability__selected', $same);
        $this->assertStringContainsString(', today, selected', $same);
    }

    public function test_today_follows_the_application_timezone_not_utc(): void
    {
        // 00:30 on Nov 10 in Manila is still Nov 9 in UTC.
        $this->travelTo(\Carbon\Carbon::parse('2026-11-10 00:30', 'Asia/Manila'));
        $html = $this->as($this->rep)->get('/rooms?date=2026-11-10')->assertOk()->getContent();
        $this->assertStringContainsString('rb-availability__today', $this->cell($html, '2026-11-10'));
        $this->assertStringNotContainsString('rb-availability__today', $this->cell($html, '2026-11-09'));
    }

    public function test_a_closed_today_is_still_marked_but_cannot_be_picked(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-15 10:00', 'Asia/Manila'));   // a Sunday: the department is closed
        $html = $this->as($this->rep)->get('/rooms')->assertOk()->getContent();
        $cell = $this->cell($html, '2026-11-15');
        $this->assertStringContainsString('rb-availability__today', $cell);
        $this->assertStringContainsString(', today, closed', $cell);
        $this->assertDoesNotMatchRegularExpression('/<a class="rb-availability__cell" href="[^"]*date=2026-11-15/', $html);
    }

    public function test_month_arrows_are_explained_and_inert_when_the_adjacent_month_cannot_be_picked(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-09 10:00', 'Asia/Manila'));   // window: Nov 9 to Nov 23
        $html = $this->as($this->rep)->get('/rooms')->assertOk()->getContent();
        $this->assertSame(2, preg_match_all('/<button type="button" class="[^"]*rb-availability__cal-arrow rb-availability__off" aria-disabled="true" aria-describedby="cal-(prev|next)-why"/', $html));
        $this->assertStringContainsString('Earlier months can’t be booked. The calendar starts today.', $html);
        $this->assertStringContainsString('Nothing to pick in December 2026 yet. You can look ahead up to November 23.', $html);
        $this->assertStringContainsString('You can look from today up to November 23.', $html);
        $this->assertStringNotContainsString('aria-label="Previous month', $html, 'no live link to a month that cannot be picked');
        // The booking window itself is unchanged: a date beyond it is refused with the same message.
        $this->as($this->rep)->get('/rooms?date=2026-12-02')->assertOk()->assertSee('You can look from today up to November 23.');
    }

    public function test_month_arrows_navigate_within_the_window_keep_the_search_and_stay_in_sync(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-25 10:00', 'Asia/Manila'));   // window: Nov 25 to Dec 9
        $html = $this->as($this->rep)->get('/rooms?from=09:00&to=10:00')->assertOk()->getContent();
        $this->assertStringContainsString('November 2026', $html);
        // Next: the first open day of December, with the search kept.
        $this->assertMatchesRegularExpression('/<a href="[^"]*rooms\?date=2026-12-01&amp;from=09%3A00&amp;to=10%3A00#day"[^>]*aria-label="Next month, December 2026"/', $html);
        // Previous: October is entirely past, so it is unavailable.
        $this->assertStringContainsString('Previous month, unavailable', $html);

        $dec = $this->as($this->rep)->get('/rooms?date=2026-12-02&from=09:00&to=10:00')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<h2 id="cal-title"[^>]*>December 2026<\/h2>/', $dec, 'the visible month follows the selected date');
        $this->assertMatchesRegularExpression('/<a href="[^"]*rooms\?date=2026-11-25&amp;from=09%3A00&amp;to=10%3A00#day"[^>]*aria-label="Previous month, November 2026"/', $dec, 'previous goes back to the first pickable day, today');
        $this->assertStringContainsString('Next month, unavailable', $dec, 'January is beyond the window');
        $this->assertStringNotContainsString('rooms?date=2026-01', $dec);
        // Today returns to the current day and keeps the search.
        $this->assertMatchesRegularExpression('/<a href="[^"]*rooms\?from=09%3A00&amp;to=10%3A00#day"[^>]*aria-label="Go to today"/', $dec);
    }

    // ---- 9, 10, 11: room timetable (cards on phones), Schedule label, drawer sections ---------------------------------

    private function busyDay(): \Illuminate\Support\Carbon
    {
        $day = now()->addDays(2)->startOfDay();
        while ($day->isSunday()) {
            $day = $day->addDay();
        }

        return $day;
    }

    public function test_timetable_rows_use_one_schedule_button_with_a_descriptive_name_and_phone_state_tags(): void
    {
        $day = $this->busyDay();
        $this->booking('Confirmed', $day->copy()->setTime(9, 0));
        $full = Room::create(['name' => 'Full-'.Str::random(6), 'capacity' => null, 'is_active' => true]);
        $this->department->rooms()->attach($full);
        Reservation::create(['user_id' => $this->rep->id, 'room_id' => $full->id, 'purpose' => 'All day', 'start_time' => $day->copy()->setTime(7, 30), 'end_time' => $day->copy()->setTime(21, 0), 'status' => 'Confirmed']);

        foreach ([$this->rep, $this->staff, $this->admin] as $user) {
            $html = $this->as($user)->get('/rooms?date='.$day->toDateString())->assertOk()->getContent();
            $this->assertStringContainsString('class="rb-availability__boardwrap"', $html, $user->role);
            $this->assertMatchesRegularExpression('/<span class="sr-only">View <\/span>Schedule<span class="rb-availability__count" aria-hidden="true">1<\/span><span class="sr-only"> for '.preg_quote($this->room->name, '/').', 1 entry<\/span>/', $html, $user->role);
            $this->assertStringNotContainsString('View schedule <span', $html, 'the old visible label is gone');
            $this->assertStringContainsString('rb-availability__state--free">2 free windows</span>', $html);
            $this->assertStringContainsString('rb-availability__state--none">No free time</span>', $html);
            $this->assertStringContainsString('rb-availability-track__tick', $html, 'tick marks line the rows up with the axis labels');
            $this->assertStringContainsString('class="rb-availability__scale"', $html);
        }
        // Book stays rep-only and unchanged.
        $this->assertStringContainsString('class="rb-button rb-button--secondary rb-button--regular rb-availability__btn-book"', $this->as($this->rep)->get('/rooms?date='.$day->toDateString())->getContent());
        $this->assertStringNotContainsString('rb-availability__btn-book', $this->as($this->staff)->get('/rooms?date='.$day->toDateString())->getContent());
    }

    public function test_schedule_drawer_separates_scheduled_events_from_free_times_and_handles_an_empty_free_list(): void
    {
        $day = $this->busyDay();
        $this->booking('Confirmed', $day->copy()->setTime(9, 0));
        $html = $this->as($this->rep)->get('/rooms?date='.$day->toDateString())->assertOk()->getContent();
        $id = 'schedule-'.$this->room->id;
        $this->assertMatchesRegularExpression('/<section class="rb-availability__schedule-section" aria-labelledby="'.$id.'-events">\s*<h3 class="rb-availability__schedule-sub" id="'.$id.'-events">Scheduled /', $html);
        $this->assertMatchesRegularExpression('/<section class="rb-availability__schedule-section rb-availability__schedule-section--free" aria-labelledby="'.$id.'-free">\s*<h3 class="rb-availability__schedule-sub" id="'.$id.'-free">Free times<\/h3>/', $html);
        $this->assertGreaterThan(strpos($html, $id.'-events'), strpos($html, $id.'-free'), 'events come first, free times after the divider');

        $full = Room::create(['name' => 'Packed-'.Str::random(6), 'capacity' => null, 'is_active' => true]);
        $this->department->rooms()->attach($full);
        Reservation::create(['user_id' => $this->rep->id, 'room_id' => $full->id, 'purpose' => 'All day', 'start_time' => $day->copy()->setTime(7, 30), 'end_time' => $day->copy()->setTime(21, 0), 'status' => 'Confirmed']);
        $html = $this->as($this->rep)->get('/rooms?date='.$day->toDateString())->getContent();
        $this->assertMatchesRegularExpression('/id="schedule-'.$full->id.'-free">Free times<\/h3>\s*<p class="rb-availability__schedule-free rb-availability__none">No free time this day<\/p>/', $html, 'an empty free list says so');
        // The booking link inside the drawer still points at the same booking page.
        $this->assertStringContainsString(route('book', ['for' => 'other', 'date' => $day->toDateString(), 'room' => $this->room->id]), html_entity_decode($html));
    }

    // ---- 12: Today review control -------------------------------------------------------------------------------------

    public function test_today_page_offers_review_request_as_a_secondary_button_link_that_changes_nothing(): void
    {
        $day = $this->busyDay();
        $pending = $this->booking('Pending', $day->copy()->setTime(9, 0), null, null, 'Needs review');
        $confirmed = $this->booking('Confirmed', $day->copy()->setTime(11, 0), null, null, 'Already approved');
        $done = $this->booking('Rejected', $day->copy()->setTime(13, 0), null, null, 'Already rejected');

        foreach ([$this->staff, $this->admin] as $user) {
            $html = $this->as($user)->get('/today?date='.$day->toDateString())->assertOk()->getContent();
            $link = '/<a href="'.preg_quote(route('bookings.show', $pending), '/').'" class="rb-button rb-button--secondary rb-button--compact tcard-link">Review request<span class="sr-only"> for booking #'.$pending->id.'<\/span><\/a>/';
            $this->assertMatchesRegularExpression($link, $html, $user->role.': an anchor styled as the shared outlined button');
            $this->assertStringNotContainsString('Approve or reject', $html);
            $this->assertStringNotContainsString('req-link', $html, 'the bare underlined link style is gone from this card');
            // Navigation only: nothing on the Today card approves or rejects.
            $this->assertStringNotContainsString(route('bookings.approve', $pending), $html);
            $this->assertStringNotContainsString(route('bookings.reject', $pending), $html);
            // Other states use the same control with their existing destinations.
            $this->assertStringContainsString('href="'.route('bookings.show', $confirmed).'#change" class="rb-button rb-button--secondary rb-button--compact tcard-link">Cancel or move', $html);
            $this->assertStringContainsString('href="'.route('bookings.show', $done).'" class="rb-button rb-button--secondary rb-button--compact tcard-link">Details', $html);
        }
        // Following the link only opens the review page.
        $this->as($this->staff)->get(route('bookings.show', $pending))->assertOk()->assertSee('Approve');
        $this->assertSame('Pending', $pending->fresh()->status);
    }

    // ---- 8: My bookings on phones keeps the same data and actions ----------------------------------------------------

    public function test_my_bookings_rows_keep_every_fact_and_action_in_a_stable_order_for_the_stacked_phone_card(): void
    {
        $day = $this->busyDay();
        $open = $this->booking('Confirmed', $day->copy()->setTime(9, 0), null, null, str_repeat('Very long purpose ', 8));
        $rejected = Reservation::create(['user_id' => $this->rep->id, 'room_id' => $this->room->id, 'purpose' => 'Closed one', 'start_time' => now()->subDays(3)->setTime(9, 0), 'end_time' => now()->subDays(3)->setTime(10, 0), 'status' => 'Rejected', 'status_reason' => 'Room is reserved that day']);
        $html = $this->as($this->rep)->get(route('bookings.mine'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<li class="brow">\s*<div class="brow-when">\s*<strong>'.preg_quote($open->start_time->format('D, M j'), '/').'<\/strong>\s*<span>[^<]+<\/span>\s*<\/div>\s*<div class="brow-what">\s*<p class="brow-title"><a href="'.preg_quote(route('bookings.show', $open), '/').'">Very long purpose/', $html);
        $this->assertMatchesRegularExpression('/<div class="brow-side">\s*<span class="status-badge st-confirmed">[^<]+<\/span>\s*<a class="btn-ghost btn-ghost--danger btn-ghost--compact brow-cancel" href="'.preg_quote(route('bookings.show', $open), '/').'#cancel">Cancel booking/', $html);
        $this->assertStringContainsString('Rejected: “Room is reserved that day”', $html);
        $this->assertStringNotContainsString(route('bookings.show', $rejected).'#cancel', $html, 'closed bookings still have no cancel control');
        $this->assertStringContainsString('<h2>Coming up <span class="count">1</span></h2>', $html);
        $this->assertStringContainsString('Past and closed <span class="count">1</span>', $html);
        $this->assertStringContainsString('class="blist past"', $html, 'upcoming and past stay separate lists');

        // The empty state is unchanged.
        $empty = $this->as($this->person($this->department, 'rep', 'Empty Rep', null, true))->get(route('bookings.mine'))->assertOk()->getContent();
        $this->assertStringContainsString('Nothing coming up.', $empty);
        $this->assertStringContainsString('None yet.', $empty);
    }

    // ---- 5: safe Delete for rooms and equipment ------------------------------------------------------------------------

    private function unusedRoom(?Department $department = null, string $prefix = 'Typo-'): Room
    {
        $room = Room::create(['name' => $prefix.Str::random(8), 'capacity' => 10, 'is_active' => true]);
        ($department ?? $this->department)->rooms()->attach($room);

        return $room;
    }

    private function category(?Department $department = null): EquipmentCategory
    {
        $department ??= $this->department;

        return EquipmentCategory::create(['department_id' => $department->id, 'name' => 'Cat-'.Str::random(8), 'sort_order' => 1]);
    }

    private function item(EquipmentCategory $category, string $name = null): Equipment
    {
        return Equipment::create(['category_id' => $category->id, 'name' => $name ?? 'Item-'.Str::random(8), 'total_qty' => 5, 'max_per_request' => 2, 'sort_order' => 1, 'is_active' => true]);
    }

    private function bookWith(Equipment $item, string $status = 'Completed'): Reservation
    {
        $booking = $this->booking($status, now()->subDays(2)->setTime(9, 0), null, null, 'Used '.$item->name);
        DB::table('reservation_equipment')->insert(['reservation_id' => $booking->id, 'equipment_id' => $item->id, 'qty_requested' => 1]);

        return $booking;
    }

    public function test_an_unused_room_can_be_deleted_by_lab_staff_and_by_admins_with_confirmation(): void
    {
        foreach ([$this->staff, $this->admin] as $user) {
            $room = $this->unusedRoom();
            $keep = $this->unusedRoom();
            $response = $this->as($user)->delete(route('manage.rooms.destroy', $room), ['confirm' => '1']);
            $response->assertRedirect(route('manage.rooms'))->assertSessionHas('status', fn ($m) => str_contains($m, 'Deleted '.$room->name));
            $this->assertNull(Room::find($room->id), $user->role.': the room is gone');
            $this->assertSame(0, DB::table('department_room')->where('room_id', $room->id)->count(), 'and so is its place on the list');
            $this->assertNotNull(Room::find($keep->id), 'other rooms are untouched');
            $this->assertTrue($this->department->rooms()->whereKey($keep->id)->exists());
        }
    }

    public function test_room_deletion_needs_the_confirmation_box_and_never_happens_on_get(): void
    {
        $room = $this->unusedRoom();
        $this->as($this->staff)->delete(route('manage.rooms.destroy', $room))
            ->assertRedirect(route('manage.rooms.edit', $room))->assertSessionHasErrors(null, 'delete');
        $this->assertNotNull(Room::find($room->id), 'no confirmation, no deletion');

        // The links in the list and the edit page are GETs: they only navigate.
        $this->as($this->staff)->get(route('manage.rooms.edit', $room).'#delete')->assertOk();
        $this->as($this->staff)->get(route('manage.rooms.edit', $room))->assertOk();
        $this->assertNotNull(Room::find($room->id), 'a GET never deletes');
        $this->as($this->staff)->get('/manage/rooms/'.$room->id.'/delete')->assertNotFound();

        $html = $this->as($this->staff)->get(route('manage.rooms.edit', $room))->getContent();
        $this->assertMatchesRegularExpression('/<form method="post" action="'.preg_quote(route('manage.rooms.destroy', $room), '/').'" class="rb-delete__form"[^>]*>\s*<input type="hidden" name="_token"[^>]*>\s*<input type="hidden" name="_method" value="DELETE">\s*<label class="check"><input type="checkbox" name="confirm" value="1" required> Yes, delete '.preg_quote($room->name, '/').'<\/label>/', $html, 'real DELETE form, CSRF token, named confirmation');
        $this->assertStringContainsString('It has no bookings or classes on record, so no history is lost. This can’t be undone.', $html);
    }

    public function test_a_room_with_any_booking_history_is_kept_and_so_is_every_booking(): void
    {
        foreach (['Pending', 'Confirmed', 'InUse', 'Completed', 'Cancelled', 'Rejected', 'NoShow'] as $status) {
            $room = $this->unusedRoom();
            $booking = $this->booking($status, now()->addDays(status_offset($status))->setTime(9, 0), null, $room, 'History '.$status);
            $this->as($this->admin)->delete(route('manage.rooms.destroy', $room), ['confirm' => '1'])
                ->assertRedirect(route('manage.rooms.edit', $room))->assertSessionHasErrors(null, 'delete');
            $this->assertNotNull(Room::find($room->id), "$status: room kept");
            $this->assertSame($status, $booking->fresh()->status, "$status: booking untouched");
            $this->assertSame($room->id, $booking->fresh()->room_id);
            $page = $this->as($this->admin)->get(route('manage.rooms.edit', $room))->getContent();
            $this->assertStringContainsString('can’t be deleted', $page);
            $this->assertStringContainsString('It has 1 booking on record', $page);
            $this->assertStringContainsString('Turn it off instead', $page);
            $this->assertStringNotContainsString('class="rb-delete__form"', $page);
        }
    }

    public function test_a_room_in_the_timetable_or_shared_with_another_department_is_kept(): void
    {
        $term = Term::create(['name' => 'Term '.Str::random(6), 'start_date' => now()->subMonth()->toDateString(), 'end_date' => now()->addMonths(3)->toDateString()]);
        $classRoom = $this->unusedRoom();
        ClassSchedule::create(['term_id' => $term->id, 'department_id' => $this->department->id, 'room_id' => $classRoom->id, 'day_of_week' => 'Mon', 'start_time' => '09:00:00', 'end_time' => '10:00:00', 'entry_type' => 'Blocked', 'note' => 'Maintenance']);
        $this->as($this->admin)->delete(route('manage.rooms.destroy', $classRoom), ['confirm' => '1'])->assertSessionHasErrors(null, 'delete');
        $this->assertNotNull(Room::find($classRoom->id));
        $this->assertSame(1, ClassSchedule::where('room_id', $classRoom->id)->count(), 'the timetable row is untouched');
        $this->assertStringContainsString('used by 1 class in the timetable', $this->as($this->admin)->get(route('manage.rooms.edit', $classRoom))->getContent());

        $shared = $this->unusedRoom();
        $this->otherDepartment->rooms()->attach($shared);
        $this->as($this->admin)->delete(route('manage.rooms.destroy', $shared), ['confirm' => '1'])->assertSessionHasErrors(null, 'delete');
        $this->assertNotNull(Room::find($shared->id));
        $this->assertTrue($this->department->rooms()->whereKey($shared->id)->exists(), 'still on this department’s list');
        $this->assertTrue($this->otherDepartment->rooms()->whereKey($shared->id)->exists(), 'and on the other department’s');
        $this->assertStringContainsString('shared with '.$this->otherDepartment->name, $this->as($this->admin)->get(route('manage.rooms.edit', $shared))->getContent());
    }

    public function test_room_deletion_respects_roles_and_department_ownership(): void
    {
        $room = $this->unusedRoom();
        // Another department's admin: the room isn't on their list, so it doesn't exist for them (same 404 as editing).
        $this->as($this->otherAdmin)->delete(route('manage.rooms.destroy', $room), ['confirm' => '1'])->assertNotFound();
        $this->as($this->otherAdmin)->get(route('manage.rooms.edit', $room))->assertNotFound();
        // A rep may not manage rooms at all.
        $this->as($this->rep)->delete(route('manage.rooms.destroy', $room), ['confirm' => '1'])->assertForbidden();
        // A guest is sent to log in.
        $this->flushSession();
        auth()->logout();
        $this->delete(route('manage.rooms.destroy', $room), ['confirm' => '1'])->assertRedirect(route('login'));
        $this->assertNotNull(Room::find($room->id), 'nobody unauthorised deleted it');
        $this->assertTrue($this->department->rooms()->whereKey($room->id)->exists());

        // A room that doesn't exist (e.g. already deleted) is a 404, not an error page.
        $gone = $this->unusedRoom();
        $this->as($this->admin)->delete(route('manage.rooms.destroy', $gone), ['confirm' => '1']);
        $this->as($this->admin)->delete(route('manage.rooms.destroy', $gone), ['confirm' => '1'])->assertNotFound();
    }

    public function test_a_booking_made_after_the_page_loaded_still_blocks_the_delete(): void
    {
        $room = $this->unusedRoom();
        $page = $this->as($this->staff)->get(route('manage.rooms.edit', $room))->getContent();
        $this->assertStringContainsString('class="rb-delete__form"', $page, 'the page offered the delete');
        // Meanwhile a rep books the room.
        $booking = $this->booking('Pending', now()->addDays(3)->setTime(9, 0), null, $room, 'Booked in the meantime');
        $this->as($this->staff)->delete(route('manage.rooms.destroy', $room), ['confirm' => '1'])->assertSessionHasErrors(null, 'delete');
        $this->assertNotNull(Room::find($room->id));
        $this->assertSame('Pending', $booking->fresh()->status);
    }

    public function test_the_database_itself_refuses_to_orphan_history_there_is_no_cascade(): void
    {
        $room = $this->unusedRoom();
        $booking = $this->booking('Completed', now()->subDay()->setTime(9, 0), null, $room);
        $this->department->rooms()->detach($room);
        try {
            DB::transaction(fn () => DB::table('rooms')->where('id', $room->id)->delete());
            $this->fail('the foreign key should have refused');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('foreign key', strtolower($e->getMessage()));
        }
        $this->assertNotNull(Room::find($room->id));
        $this->assertNotNull(Reservation::find($booking->id));
    }

    public function test_the_room_list_offers_delete_only_where_it_is_possible(): void
    {
        $deletable = $this->unusedRoom();
        $used = $this->unusedRoom();
        $this->booking('Completed', now()->subDays(4)->setTime(9, 0), null, $used);
        $this->booking('Completed', now()->subDays(5)->setTime(9, 0), null, $this->room);   // the base fixture room has history too
        $shared = $this->unusedRoom();
        $this->otherDepartment->rooms()->attach($shared);

        foreach ([$this->staff, $this->admin] as $user) {
            $html = $this->as($user)->get(route('manage.rooms'))->assertOk()->getContent();
            $link = '<a class="row-delete" href="'.route('manage.rooms.edit', $deletable).'#delete">Delete<span class="sr-only"> '.$deletable->name.': review and confirm on its page</span></a>';
            $this->assertStringContainsString($link, $html, $user->role);
            $this->assertSame(1, substr_count($html, 'class="row-delete"'), 'only the room nothing uses');
            $this->assertStringContainsString('<a class="row-edit" href="'.route('manage.rooms.edit', $used).'">Edit', $html, 'Edit is always there');
            $this->assertStringNotContainsString('href="'.route('manage.rooms.edit', $used).'#delete"', $html);
            $this->assertStringNotContainsString('href="'.route('manage.rooms.edit', $shared).'#delete"', $html);
            $this->assertStringContainsString('<span class="sr-only">Actions</span>', $html);
        }
    }

    public function test_unused_equipment_can_be_deleted_and_nothing_else_changes(): void
    {
        $category = $this->category();
        $siblingA = $this->item($category);
        $siblingB = $this->item($category);
        foreach ([$this->staff, $this->admin] as $user) {
            $typo = $this->item($category, 'Typo-'.Str::random(6));
            $html = $this->as($user)->get(route('manage.equipment'))->assertOk()->getContent();
            $this->assertStringContainsString('<a class="row-delete" href="'.route('manage.equipment.edit', $typo).'#delete">Delete', $html, $user->role);
            $this->as($user)->delete(route('manage.equipment.destroy', $typo), [])->assertSessionHasErrors(null, 'delete');
            $this->assertNotNull(Equipment::find($typo->id), 'no confirmation, no deletion');
            $this->as($user)->delete(route('manage.equipment.destroy', $typo), ['confirm' => '1'])
                ->assertRedirect(route('manage.equipment'))->assertSessionHas('status', fn ($m) => str_contains($m, 'Deleted '.$typo->name));
            $this->assertNull(Equipment::find($typo->id));
        }
        $this->assertNotNull(EquipmentCategory::find($category->id), 'the category stays');
        $this->assertSame(5, $siblingA->fresh()->total_qty, 'stock counts of other items are untouched');
        $this->assertSame(5, $siblingB->fresh()->total_qty);
    }

    public function test_equipment_on_any_booking_is_kept_with_its_history(): void
    {
        $category = $this->category();
        foreach (['Pending', 'Confirmed', 'InUse', 'Completed', 'Cancelled', 'Rejected', 'NoShow'] as $status) {
            $item = $this->item($category);
            $booking = $this->bookWith($item, $status);
            $this->as($this->admin)->delete(route('manage.equipment.destroy', $item), ['confirm' => '1'])->assertSessionHasErrors(null, 'delete');
            $this->assertNotNull(Equipment::find($item->id), "$status: item kept");
            $this->assertSame(1, DB::table('reservation_equipment')->where('reservation_id', $booking->id)->where('equipment_id', $item->id)->count(), "$status: the booking still lists it");
            $page = $this->as($this->admin)->get(route('manage.equipment.edit', $item))->getContent();
            $this->assertStringContainsString('can’t be deleted', $page);
            $this->assertStringContainsString('It’s on 1 booking on record', $page);
            $this->assertStringNotContainsString('class="rb-delete__form"', $page);
        }
        $list = $this->as($this->admin)->get(route('manage.equipment'))->getContent();
        $this->assertSame(0, substr_count($list, 'class="row-delete"'), 'every item here has history: no Delete offered');
    }

    public function test_equipment_deletion_respects_roles_and_department_ownership(): void
    {
        $mine = $this->item($this->category());
        $theirs = $this->item($this->category($this->otherDepartment));
        $this->as($this->otherAdmin)->delete(route('manage.equipment.destroy', $mine), ['confirm' => '1'])->assertNotFound();
        $this->as($this->admin)->delete(route('manage.equipment.destroy', $theirs), ['confirm' => '1'])->assertNotFound();
        $this->as($this->rep)->delete(route('manage.equipment.destroy', $mine), ['confirm' => '1'])->assertForbidden();
        $this->flushSession();
        auth()->logout();
        $this->delete(route('manage.equipment.destroy', $mine), ['confirm' => '1'])->assertRedirect(route('login'));
        $this->assertNotNull(Equipment::find($mine->id));
        $this->assertNotNull(Equipment::find($theirs->id));
        $this->as($this->admin)->get('/manage/equipment/'.$mine->id.'/delete')->assertNotFound();
        $this->assertNotNull(Equipment::find($mine->id), 'a GET never deletes');
    }

    public function test_an_item_booked_after_the_page_loaded_still_blocks_the_delete(): void
    {
        $item = $this->item($this->category());
        $this->assertStringContainsString('class="rb-delete__form"', $this->as($this->staff)->get(route('manage.equipment.edit', $item))->getContent());
        $booking = $this->bookWith($item, 'Pending');
        $this->as($this->staff)->delete(route('manage.equipment.destroy', $item), ['confirm' => '1'])->assertSessionHasErrors(null, 'delete');
        $this->assertNotNull(Equipment::find($item->id));
        $this->assertSame(1, DB::table('reservation_equipment')->where('reservation_id', $booking->id)->count());
    }
}
