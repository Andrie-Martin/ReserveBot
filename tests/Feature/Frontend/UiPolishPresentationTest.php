<?php

namespace Tests\Feature\Frontend;

use PHPUnit\Framework\TestCase;

/**
 * Source-level guards for the room/booking/manage UI polish (no database, no browser). They pin the markup
 * contracts the progressive-enhancement scripts and the no-JavaScript fallbacks depend on.
 */
final class UiPolishPresentationTest extends TestCase
{
    private function read(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 3).'/'.$path);
    }

    public function test_free_rooms_schedule_is_a_dialog_drawer_not_an_expanding_row(): void
    {
        $room = $this->read('resources/views/rooms/_room.blade.php');
        $this->assertStringContainsString('data-rb-drawer="schedule-', $room);
        $this->assertStringContainsString('<x-ui.drawer', $room);
        $this->assertStringNotContainsString('<details', $room);
        $this->assertStringNotContainsString('What’s on', $room);

        $drawer = $this->read('resources/views/components/ui/drawer.blade.php');
        $this->assertStringContainsString('<dialog', $drawer);
        $this->assertStringContainsString('aria-labelledby', $drawer);
        $this->assertStringContainsString('data-rb-drawer-close', $drawer);      // a real link, so Close works without script
        $this->assertStringContainsString('showModal', $this->read('public/js/ui.js'));
        $this->assertStringContainsString(':target', $this->read('public/css/foundations/components.css'));
    }

    public function test_calendar_and_agenda_uses_the_primary_button_like_find_rooms(): void
    {
        $day = $this->read('resources/views/rooms/_day.blade.php');
        $this->assertMatchesRegularExpression('/<x-ui\.link-button href="#calendar-and-agenda">Calendar and agenda<\/x-ui\.link-button>/', $day);
        $this->assertStringContainsString('<x-ui.button type="submit"', $this->read('resources/views/rooms/_search.blade.php'));
    }

    public function test_time_selects_keep_the_native_select_as_the_submitted_control(): void
    {
        $search = $this->read('resources/views/rooms/_search.blade.php');
        $this->assertSame(2, substr_count($search, 'data-rb-picker'));
        $this->assertStringContainsString('name="from"', $search);
        $this->assertStringContainsString('name="to"', $search);
        $book = $this->read('resources/views/bookings/create.blade.php');
        foreach (['id="date" name="date" data-rb-picker', 'id="from" name="from" data-rb-picker', 'id="to" name="to" data-rb-picker', 'name="session" data-rb-picker="search"'] as $needle) {
            $this->assertStringContainsString($needle, $book);
        }
        $js = $this->read('public/js/ui.js');
        $this->assertStringContainsString("select.classList.add('rb-picker__native')", $js);   // hidden, never removed or renamed
        $this->assertStringContainsString("new Event('change'", $js);
    }

    public function test_equipment_quantities_remain_named_number_inputs_limited_by_stock(): void
    {
        $book = $this->read('resources/views/bookings/create.blade.php');
        $this->assertStringContainsString('name="qty[{{ $item->id }}]"', $book);
        $this->assertStringContainsString('type="number"', $book);
        $this->assertStringContainsString('max="{{ $most }}"', $book);
        $this->assertStringContainsString('min($item->max_per_request, $item->free)', $book);
        $this->assertStringContainsString('@disabled($item->free === 0)', $book);
        $this->assertSame(1, substr_count($book, 'name="qty[{{ $item->id }}]"'), 'one qty control per item');
        $this->assertMatchesRegularExpression('/<input type="number" class="rb-stepper__input" id="qty-\{\{ \$item->id \}\}" name="qty\[\{\{ \$item->id \}\}\]"[^>]*min="0"/', $book);
        // The helper search field is never submitted.
        $this->assertMatchesRegularExpression('/<input type="search" id="eq-search"(?:(?!name=)[^>])*>/', $book);
    }

    public function test_equipment_page_offers_inline_rename_that_posts_to_the_existing_route(): void
    {
        $page = $this->read('resources/views/manage/equipment.blade.php');
        $this->assertStringContainsString("route('manage.categories.update', \$category)", $page);
        $this->assertStringContainsString("@method('PUT')", $page);
        $this->assertStringContainsString('data-rb-rename="cat-rename-', $page);       // the category name itself is the target
        $this->assertStringContainsString('data-rb-reveal-close', $page);             // Cancel
        $this->assertStringNotContainsString('<details class="rename"', $page);
        $this->assertStringNotContainsString('mcat-edit', $page, 'no separate Rename button beside each category');
        $this->assertStringContainsString('aria-describedby="cat-hint-', $page);       // the keyboard/touch instructions are announced
        // Add actions come before the category list.
        $this->assertLessThan(strpos($page, 'id="eq-categories"'), strpos($page, 'class="add-row'));
    }

    public function test_manage_rooms_adds_a_room_above_the_list(): void
    {
        $page = $this->read('resources/views/manage/rooms.blade.php');
        $this->assertLessThan(strpos($page, 'rooms-table'), strpos($page, '+ Add a room'));
    }

    public function test_dashboard_counters_use_the_shared_compact_stat(): void
    {
        foreach (['resources/views/home.blade.php', 'resources/views/home/_representative.blade.php'] as $view) {
            $source = $this->read($view);
            $this->assertStringContainsString('class="rb-stat"', $source);
            $this->assertStringContainsString('rb-stat__value', $source);
            $this->assertStringNotContainsString('<p class="stat">', $source);
        }
        $this->assertStringContainsString('font-variant-numeric: tabular-nums', $this->read('public/css/foundations/components.css'));
    }

    public function test_rep_home_uses_previews_with_view_all_instead_of_accordions(): void
    {
        $home = $this->read('resources/views/home/_representative.blade.php');
        $this->assertStringNotContainsString('x-ui.disclosure', $home);
        $this->assertStringContainsString('data-rb-drawer="meetings-all"', $home);
        $this->assertStringContainsString('data-rb-drawer="timetable-all"', $home);
        // Every meeting row (page list and drawer) renders its Face-to-face / Online form through the one shared partial, with the rep route.
        $meeting = $this->read('resources/views/home/_meeting.blade.php');
        $this->assertStringContainsString("@include('classes._answer', ['m' => \$m, 'route' => 'classes.answer'])", $meeting);
        $this->assertStringContainsString('name="answer" value="f2f"', $this->read('resources/views/classes/_answer.blade.php'));
        // The drawer lists only the meetings that are not already answered inline, so each meeting keeps exactly one form.
        $this->assertStringContainsString('@foreach ($otherMeetings as $m)', $home);
    }

    public function test_every_page_loads_the_shared_enhancement_script_with_a_cache_buster(): void
    {
        $this->assertMatchesRegularExpression('/js\/ui\.js\'\) \}\}\?v=\{\{ filemtime\(public_path\(\'js\/ui\.js\'\)\) \}\}" defer/', $this->read('resources/views/layouts/app.blade.php'));
    }

    public function test_rename_field_cannot_widen_the_page_qa_f1(): void
    {
        $css = $this->read('public/css/manage.css');
        $this->assertDoesNotMatchRegularExpression('/\.mcat-rename input[^{]*\{[^}]*flex:\s*1 1 14rem/', $css, 'a 14rem flex-basis made the card wider than a 320px screen');
        $this->assertMatchesRegularExpression('/\.mcat-rename input\[name="name"\]\s*\{[^}]*flex:\s*1 1 100%[^}]*width:\s*100%/', $css);
    }

    public function test_no_script_drawer_overlay_cannot_paint_over_the_panel_qa_f2(): void
    {
        $css = $this->read('public/css/foundations/components.css');
        $this->assertStringNotContainsString('.rb-drawer:target:not([open])::before', $css);
        $this->assertMatchesRegularExpression('/\.rb-drawer:target:not\(\[open\]\)\s*\{[^}]*0 0 0 100vmax/', $css);
    }

    public function test_drawer_header_is_compact_and_sub_line_lives_in_the_body_qa_f4(): void
    {
        $drawer = $this->read('resources/views/components/ui/drawer.blade.php');
        $head = substr($drawer, strpos($drawer, 'rb-drawer__head'), strpos($drawer, 'rb-drawer__body') - strpos($drawer, 'rb-drawer__head'));
        $this->assertStringNotContainsString('rb-drawer__sub', $head);
        $this->assertStringContainsString('data-rb-drawer-close', $head);
        $this->assertGreaterThan(strpos($drawer, 'rb-drawer__body'), strpos($drawer, 'rb-drawer__sub'));
    }

    public function test_drawers_react_to_hash_changes_and_never_stack_qa_f5(): void
    {
        $js = $this->read('public/js/ui.js');
        $this->assertStringContainsString("window.addEventListener('hashchange', fromHash)", $js);
        $this->assertStringContainsString("$$('dialog.rb-drawer[open]').forEach(other => other.close())", $js);
    }

    public function test_dead_room_busy_css_is_gone_and_nothing_references_it_qa_f6(): void
    {
        foreach (['public/css/availability.css', 'public/css/style.css', 'public/css/foundations/components.css', 'resources/views/rooms/_room.blade.php', 'public/js/ui.js', 'public/js/availability.js'] as $file) {
            $this->assertStringNotContainsString('room-busy', $this->read($file), $file);
        }
    }

    public function test_past_booking_ties_and_account_ties_use_a_deterministic_oldest_id_first_order_qa_f3(): void
    {
        $this->assertStringContainsString("->orderByDesc('start_time')->orderBy('id')", $this->read('app/Http/Controllers/BookingController.php'));
        $this->assertStringContainsString("->orderBy('id')->get()", $this->read('app/Http/Controllers/AccountsController.php'));
    }

    public function test_requests_are_grouped_by_room_with_counts_and_unchanged_forms(): void
    {
        $view = $this->read('resources/views/requests/index.blade.php');
        $this->assertStringContainsString("fn (\$r) => (bool) \$r->room_id", $view);
        $this->assertStringContainsString("fn (\$r) => ! \$r->room_id", $view);
        $this->assertStringContainsString("\$group['items']->count()", $view);
        $this->assertStringContainsString("route('bookings.approve', \$r)", $view);
        $this->assertStringContainsString("route('bookings.reject', \$r)", $view);
        $this->assertStringContainsString('Grouping does not change who can approve', $view);
    }

    public function test_search_boxes_use_the_application_clear_control_not_the_browser_x(): void
    {
        foreach (['resources/views/manage/equipment.blade.php', 'resources/views/bookings/create.blade.php'] as $view) {
            $source = $this->read($view);
            $this->assertStringContainsString('rb-search__clear', $source);
            $this->assertStringContainsString('aria-label="Clear search"', $source);
        }
        $css = $this->read('public/css/foundations/components.css');
        $this->assertStringContainsString('::-webkit-search-cancel-button', $css);
        $this->assertStringContainsString("clear.addEventListener('click'", $this->read('public/js/ui.js'));
    }

    public function test_booking_rules_and_semesters_save_inside_their_own_card_footer(): void
    {
        $rules = $this->read('resources/views/manage/rules.blade.php');
        $this->assertSame(2, substr_count($rules, 'class="mform-footer"'), 'rules form and the per-semester form each have a footer');
        $this->assertMatchesRegularExpression('/<div class="mform-footer"><button type="submit" class="btn-small">Save rules<\/button><\/div>\s*<\/form>/', $rules);
        $this->assertMatchesRegularExpression('/<div class="mform-footer"><button type="submit" class="btn-small">Save dates<\/button><\/div>\s*<\/form>/', $rules);
    }

    public function test_password_guidance_is_a_matching_white_card(): void
    {
        $css = $this->read('public/css/auth.css');
        $this->assertDoesNotMatchRegularExpression('/\.rb-account-guidance\s*\{[^}]*background/', $css);
        $this->assertStringContainsString('rb-card rb-account-guidance', $this->read('resources/views/account/password.blade.php'));
    }

    public function test_stat_number_and_label_share_size_and_line_height(): void
    {
        $css = $this->read('public/css/foundations/components.css');
        $this->assertMatchesRegularExpression('/\.rb-stat__value\s*\{[^}]*font-size:\s*inherit[^}]*line-height:\s*inherit/', $css);
        $this->assertMatchesRegularExpression('/\.rb-stat__label\s*\{[^}]*font-size:\s*inherit[^}]*line-height:\s*inherit/', $css);
    }

    public function test_free_rooms_use_the_shared_wording_helpers_and_no_spreadsheet_remarks(): void
    {
        $room = $this->read('resources/views/rooms/_room.blade.php');
        $this->assertStringContainsString('RoomCapacity::label', $room);
        $this->assertStringContainsString('Unavailable all day — ', $room);
        $this->assertStringNotContainsString('Seats not recorded', $room);
        $this->assertStringNotContainsString('Blocked all day', $room);

        $availability = $this->read('app/Services/RoomAvailability.php');
        $this->assertStringContainsString('BlockedReason::text', $availability);
        $this->assertStringNotContainsString('Blocked: ', $availability);

        // Capacity wording is the same wherever a capacity is shown.
        $this->assertStringContainsString('RoomCapacity::label', $this->read('resources/views/bookings/create.blade.php'));
        $this->assertStringNotContainsString('not recorded', strtolower($this->read('resources/views/manage/rooms.blade.php')));
    }

    public function test_calendar_and_event_details_cards_share_a_height_with_an_internal_scroll(): void
    {
        $css = $this->read('public/css/availability.css');
        $this->assertStringContainsString('.rb-availability__events-scroll', $css);
        $this->assertMatchesRegularExpression('/\.rb-availability__events-scroll\s*\{[^}]*overflow-y:\s*auto/', $css);
        $this->assertStringContainsString('align-items: stretch', $css);

        $details = $this->read('resources/views/rooms/_calendar-details.blade.php');
        $this->assertStringContainsString('rb-availability__events-scroll', $details);
    }

    public function test_booking_details_has_a_summary_and_one_at_a_time_move_and_cancel_panels(): void
    {
        $view = $this->read('resources/views/bookings/show.blade.php');
        foreach (['booking-summary', 'bfacts', 'bequip', 'class="chips"', 'booking-actions'] as $needle) {
            $this->assertStringContainsString($needle, $view);
        }
        // The old accordion is gone; two labelled toggles open one panel each.
        $this->assertStringNotContainsString('Cancel or move this booking', $view);
        $this->assertStringContainsString('data-rb-reveal="move-panel"', $view);
        $this->assertStringContainsString('data-rb-reveal="cancel-panel"', $view);
        $this->assertSame(2, substr_count($view, 'data-rb-reveal-group="change"'));
        $this->assertSame(2, substr_count($view, 'data-rb-reveal-close'));

        // Routes, method and CSRF are the existing ones, and the panels reopen with their own error bag.
        $this->assertStringContainsString("route('bookings.move', \$reservation)", $view);
        $this->assertStringContainsString("route('bookings.staff-cancel', \$reservation)", $view);
        $this->assertGreaterThanOrEqual(2, substr_count($view, '@csrf'));
        $this->assertSame(1, substr_count($view, 'method="post" action="{{ route(\'bookings.move\''));
        $this->assertSame(1, substr_count($view, 'method="post" action="{{ route(\'bookings.staff-cancel\''));
        $this->assertStringContainsString("getBag('move')", $view);
        $this->assertStringContainsString("getBag('cancel')", $view);

        // Cancelling needs an explicit confirmation that is required natively and never submitted.
        $this->assertMatchesRegularExpression('/<input type="checkbox" id="staff-cancel-confirm" required>/', $view);
        $this->assertStringNotContainsString('name="staff-cancel-confirm"', $view);
        // Move uses the compact pickers; the native selects stay the submitted controls.
        foreach (['name="date" data-rb-picker', 'name="from" data-rb-picker', 'name="to" data-rb-picker', 'name="room"'] as $needle) {
            $this->assertStringContainsString($needle, $view);
        }
    }

    public function test_panels_open_without_script_through_target_and_submit_once_with_script(): void
    {
        $css = $this->read('public/css/booking.css');
        $this->assertStringContainsString('.change-panel.rb-reveal:target', $css);
        $this->assertStringContainsString('.change-panel.rb-reveal.is-open', $css);
        $js = $this->read('public/js/ui.js');
        $this->assertStringContainsString('rbRevealGroup', $js);
        $this->assertStringContainsString('form[data-rb-once]', $js);
    }
}
