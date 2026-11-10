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

    public function test_dashboard_counters_use_shared_stat_components(): void
    {
        // Representative: the compact pill. Lab staff and admin: three equal cards from the shared stat-card component.
        $rep = $this->read('resources/views/home/_representative.blade.php');
        $this->assertStringContainsString('class="rb-stat"', $rep);
        $this->assertStringContainsString('rb-stat__value', $rep);
        $home = $this->read('resources/views/home.blade.php');
        $this->assertStringContainsString('<x-ui.stat-card', $home);
        $this->assertStringNotContainsString('<p class="stat">', $home);
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
        foreach (['public/css/availability.css', 'public/css/style.css', 'public/css/foundations/components.css', 'resources/views/rooms/_room.blade.php', 'public/js/ui.js'] as $file) {
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
        $this->assertStringContainsString('fn ($r) => (bool) $r->room_id', $view);
        $this->assertStringContainsString('fn ($r) => ! $r->room_id', $view);
        $this->assertStringContainsString("\$tab['items']->count()", $view);
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

    public function test_secondary_navigation_is_one_shared_underline_nav_not_pills(): void
    {
        $manage = $this->read('resources/views/manage/_tabs.blade.php');
        $this->assertStringContainsString('class="rb-subnav"', $manage);
        $this->assertSame(4, substr_count($manage, 'aria-current="page"'));
        foreach (["route('manage.rooms')", "route('manage.equipment')", "route('manage.rules')", "route('manage.timetable')"] as $route) {
            $this->assertStringContainsString($route, $manage);
        }
        $this->assertStringContainsString('class="rb-subnav req-tabs"', $this->read('resources/views/requests/index.blade.php'));

        $css = $this->read('public/css/foundations/components.css');
        $this->assertMatchesRegularExpression('/\.rb-subnav\s*\{[^}]*border-bottom[^}]*overflow-x:\s*auto/', $css);
        $this->assertMatchesRegularExpression('/\.rb-subnav a\[aria-current\]::after[^{]*\{[^}]*background:\s*var\(--rb-color-maroon\)/', $css);
        $this->assertMatchesRegularExpression('/\.rb-subnav a:focus-visible\s*\{[^}]*outline/', $css);
        $this->assertStringNotContainsString('radius-pill); background: var(--rb-color-maroon)', $this->read('public/css/manage.css'));
        $this->assertStringNotContainsString('manage-tabs', $this->read('public/css/manage.css'));
    }

    public function test_requests_tabs_are_query_parameter_links_that_work_without_a_script(): void
    {
        $page = $this->read('resources/views/requests/index.blade.php');
        $this->assertStringContainsString("request()->query('tab')", $page);
        $this->assertStringContainsString("route('requests'", $page);
        $this->assertStringContainsString('aria-current="page"', $page);
        $this->assertStringContainsString('Recently decided', $page);
        $this->assertStringNotContainsString('data-rb-tab', $page);
        $this->assertStringNotContainsString('role="tablist"', $page);
    }

    public function test_free_rooms_use_yellow_only_for_an_all_day_block_and_explain_the_colours(): void
    {
        $results = $this->read('resources/views/rooms/_results.blade.php');
        foreach (['Reported free', 'Not reported as free', 'Unavailable all day'] as $word) {
            $this->assertStringContainsString($word, $results);
        }
        $room = $this->read('resources/views/rooms/_room.blade.php');
        $this->assertStringContainsString(':blocked="(bool) $row[\'allDay\']"', $room);
        $this->assertStringContainsString('rb-availability__blocked', $room);
        $track = $this->read('resources/views/components/ui/availability-track.blade.php');
        $this->assertStringContainsString("'rb-availability-track--blocked' => \$blocked", $track);
        $css = $this->read('public/css/availability.css');
        $this->assertMatchesRegularExpression('/\.rb-availability__blocked\s*\{[^}]*var\(--rb-warning-bg\)[^}]*var\(--rb-warning-text\)/', $css);
    }

    public function test_free_rooms_overview_hides_a_missing_capacity_but_keeps_a_real_one(): void
    {
        $room = $this->read('resources/views/rooms/_room.blade.php');
        $this->assertStringContainsString('@if (RoomCapacity::known($room->capacity))', $room);
        // The only place the "not specified" wording can come from is the helper, and the overview never calls it for an unknown value.
        $this->assertStringContainsString('RoomCapacity::known($room->capacity) ?', $room);
    }

    public function test_event_details_show_the_chosen_date_directly_without_a_second_disclosure(): void
    {
        $details = $this->read('resources/views/rooms/_calendar-details.blade.php');
        $this->assertStringNotContainsString('x-ui.disclosure', $details);
        $this->assertStringNotContainsString('<details', $details);
        $this->assertStringNotContainsString('<summary', $details);
        $this->assertStringContainsString('rb-availability__events-count', $details);
        $this->assertStringContainsString("\$selectedDay['date']", $details, 'the panel follows the one server-selected date');
        $cards = $this->read('resources/views/rooms/_calendar-events.blade.php');
        foreach (["\$event['text']", "\$event['room']", "\$event['tip']", "\$event['tag']", "\$event['check']"] as $field) {
            $this->assertStringContainsString($field, $cards, 'every existing event field is still shown');
        }
        $this->assertFileDoesNotExist(dirname(__DIR__, 3).'/public/js/availability.js', 'selection is server-driven; no script keeps a second selection');
        $this->assertMatchesRegularExpression('/\.rb-availability__events-scroll\s*\{[^}]*overflow-y:\s*auto/', $this->read('public/css/availability.css'));
    }

    public function test_staff_dashboard_has_three_equal_cards_that_collapse_to_two_then_one_column(): void
    {
        $css = $this->read('public/css/dashboard.css');
        $this->assertMatchesRegularExpression('/\.rb-dashboard__stats\s*\{[^}]*repeat\(3,\s*minmax\(0,\s*1fr\)\)/', $css);
        $this->assertMatchesRegularExpression('/@media \(max-width: 56rem\)\s*\{\s*\.rb-dashboard__stats\s*\{[^}]*repeat\(2/', $css);
        $this->assertMatchesRegularExpression('/@media \(max-width: 40rem\)\s*\{\s*\.rb-dashboard__stats\s*\{[^}]*minmax\(0,\s*1fr\)\s*;/', $css);
        foreach (['StaffController', 'AdminController'] as $controller) {
            $this->assertStringContainsString('DeskStats::for($user)', $this->read('app/Http/Controllers/'.$controller.'.php'));
        }
    }

    public function test_booking_detail_actions_are_always_visible_panels_not_dropdowns(): void
    {
        $view = $this->read('resources/views/bookings/show.blade.php');
        $this->assertStringNotContainsString('<details', $view);
        $this->assertStringNotContainsString('<summary', $view);
        // Same forms as before: routes, POST, CSRF and the field name.
        foreach (["route('bookings.approve', \$reservation)", "route('bookings.reject', \$reservation)", "route('bookings.cancel', \$reservation)"] as $route) {
            $this->assertStringContainsString($route, $view);
        }
        $this->assertSame(5, substr_count($view, '@csrf'), 'approve, reject, cancel, and the staff move and cancel forms each carry a token');
        $this->assertStringContainsString('id="cancel"', $view, 'the My bookings link target stays');
        $this->assertStringContainsString("getBag('reject'.\$reservation->id)", $view, 'the rejection error bag name is unchanged');
        $this->assertMatchesRegularExpression('/id="reject-reason" name="reason"[^>]*\brequired\b/', $view);
        $this->assertMatchesRegularExpression('/id="cancel-reason" name="reason"[^>]*\brequired\b/', $view);

        $css = $this->read('public/css/booking.css');
        $this->assertMatchesRegularExpression('/\.review-panels\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)\s*minmax\(0,\s*2fr\)/', $css, 'approve is one third, reject two thirds');
        $this->assertMatchesRegularExpression('/@media \(max-width: 56rem\)\s*\{\s*\.rb-booking \.review-panels[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)/', $css, 'the panels stack on narrow screens');
        $this->assertStringNotContainsString('details.reject', $css);
        $this->assertStringNotContainsString('details.cancel-box', $css);
    }

    public function test_the_sidebar_information_card_is_removed_everywhere(): void
    {
        $this->assertFileDoesNotExist(dirname(__DIR__, 3).'/resources/views/layouts/partials/workspace-note.blade.php');
        $this->assertStringNotContainsString('workspace-note', $this->read('resources/views/layouts/partials/navigation.blade.php'));
        $this->assertStringNotContainsString('workspace-note', $this->read('public/css/layout.css'), 'no CSS left over for the removed card');
        foreach (['resources/views', 'public/css'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 3).'/'.$dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                $text = file_get_contents($file->getPathname());
                $this->assertStringNotContainsString('PUP CEA / NDC Campus', $text, $file->getPathname());
                $this->assertStringNotContainsString('rb-workspace-note', $text, $file->getPathname());
            }
        }
    }

    public function test_the_signed_in_as_role_card_is_gone_and_the_sidebar_does_not_depend_on_viewport_height(): void
    {
        $root = dirname(__DIR__, 3);
        $nav = $this->read('resources/views/layouts/partials/navigation.blade.php');
        $this->assertStringNotContainsString('Signed in as', $nav);
        $this->assertStringContainsString('class="rb-navigation"', $nav, 'the Workspace navigation stays');
        foreach (['resources/views', 'public/css', 'public/js'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                $text = file_get_contents($file->getPathname());
                $this->assertStringNotContainsString('rb-role-card', $text, 'no markup, CSS or script left over for the role card: '.$file->getPathname());
                $this->assertStringNotContainsString('Signed in as', $text, $file->getPathname());
            }
        }
        // Root cause of the zoom-dependent card: a height-only media query on desktop. Nothing in the sidebar may change with
        // viewport height again, or the Workspace navigation would sit in a different place at different browser zoom levels.
        $css = $this->read('public/css/layout.css');
        preg_match_all('/@media[^{]*max-height:\s*48rem[^{]*\{((?:[^{}]|\{[^{}]*\})*)\}/', $css, $short);
        foreach ($short[1] as $body) {
            $this->assertStringNotContainsString('.rb-sidebar', $body, 'the desktop sidebar must not change with viewport height');
        }
        $this->assertStringNotContainsString('max-height: 48rem', $css);
    }

    public function test_requests_reject_and_cancel_buttons_are_one_aligned_action_group(): void
    {
        $css = $this->read('public/css/operations.css');
        $this->assertMatchesRegularExpression('/\.req-reject__actions\s*\{[^}]*align-items:\s*center/', $css);
        $this->assertMatchesRegularExpression('/\.req-reject__actions > :is\(\.btn-reject, \.btn-ghost\)\s*\{[^}]*min-height:\s*var\(--rb-control-height\)[^}]*margin:\s*0[^}]*font-size:\s*\.8125rem/', $css, 'same height, margin, type for both');
        $this->assertMatchesRegularExpression('/\.req-reject__actions > \.btn-ghost\s*\{[^}]*color:\s*var\(--rb-text-primary\)/', $css, 'Cancel is the neutral outline');
        $view = $this->read('resources/views/requests/index.blade.php');
        $this->assertStringContainsString('<button type="submit" class="btn-reject">Reject booking</button>', $view);
        $this->assertStringContainsString('data-rb-reveal-close>Cancel</a>', $view, 'Cancel still only dismisses the form');
        $this->assertSame(1, substr_count($view, 'route(\'bookings.reject\', $r)'));
    }

    public function test_my_bookings_cancel_is_a_button_styled_anchor_not_a_form_or_post(): void
    {
        $row = $this->read('resources/views/bookings/_row.blade.php');
        $this->assertMatchesRegularExpression('/<a class="btn-ghost btn-ghost--danger btn-ghost--compact brow-cancel" href="\{\{ route\(\'bookings\.show\', \$b\) \}\}#cancel">Cancel booking/', $row);
        $this->assertStringNotContainsString('<form', $row);
        $this->assertStringNotContainsString('<button', $row);
        $this->assertStringNotContainsString('Cancel…', $row);
        $this->assertStringContainsString('$canCancel', $row, 'the eligibility rule decides whether the control shows');
        $css = $this->read('public/css/booking.css');
        $this->assertMatchesRegularExpression('/\.btn-ghost--compact\s*\{[^}]*min-height:\s*2rem/', $css);
        $this->assertMatchesRegularExpression('/\(pointer: coarse\)\s*\{\s*\.rb-booking \.btn-ghost--compact\s*\{[^}]*min-height:\s*var\(--rb-control-height\)/', $css, 'touch-sized on phones and coarse pointers');
    }

    public function test_a_missing_capacity_has_no_placeholder_text_anywhere(): void
    {
        foreach (['resources/views', 'public/js', 'app'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 3).'/'.$dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (! preg_match('/\.(php|js)$/', $file->getFilename())) {
                    continue;
                }
                $text = file_get_contents($file->getPathname());
                $this->assertStringNotContainsString('Capacity not specified', $text, $file->getPathname());
                $this->assertStringNotContainsString("capacity ?? 'Not specified'", $text, $file->getPathname());
                $this->assertDoesNotMatchRegularExpression('/capacity[^\n]{0,40}\?\?\s*[\'"]/i', $text, $file->getPathname().' must not default a missing capacity to text');
            }
        }
        $book = $this->read('resources/views/bookings/create.blade.php');
        $this->assertStringContainsString('@if (\\App\\Support\\RoomCapacity::known($room->capacity))', $book, 'conditional rendering, not CSS hiding');
        $this->assertDoesNotMatchRegularExpression('/room-opt-cap[^}]*display:\s*none/', $this->read('public/css/booking.css').$this->read('public/css/style.css'), 'never hidden with CSS');
        $this->assertStringContainsString('Reason not specified', $this->read('app/Support/BlockedReason.php'), 'the unrelated availability wording stays');
    }

    public function test_unavailable_rooms_on_the_booking_page_are_listed_directly(): void
    {
        $book = $this->read('resources/views/bookings/create.blade.php');
        $this->assertStringNotContainsString('busy-rooms', $book);
        $this->assertStringNotContainsString('busy then', $book);
        $start = strpos($book, '<section class="unavail"');
        $end = strpos($book, '</section>', $start);
        $this->assertNotFalse($start);
        $panel = substr($book, $start, $end - $start);
        foreach (['<input', '<button', '<select', '<a ', '<details', '<summary'] as $control) {
            $this->assertStringNotContainsString($control, $panel, 'informational only: '.$control);
        }
        foreach (["\$row['room']->name", "\$row['clash']['label']", "TimeSlots::span(\$row['clash']['start'], \$row['clash']['end'])"] as $field) {
            $this->assertStringContainsString($field, $panel, 'the existing data is shown unchanged');
        }
        $this->assertStringContainsString('@if ($busyRooms->isNotEmpty())', $book, 'omitted when nothing is unavailable');
        $this->assertStringNotContainsString('busy-rooms', $this->read('public/css/booking.css').$this->read('public/css/style.css'));
    }

    public function test_page_titles_share_one_size_token_across_pages(): void
    {
        $this->assertStringContainsString('--rb-text-title: 1.8125rem', $this->read('public/css/foundations/tokens.css'));
        foreach (['booking', 'dashboard', 'availability', 'operations', 'auth', 'manage', 'timetable', 'layout'] as $sheet) {
            $this->assertStringNotContainsString('1.8125rem', $this->read("public/css/$sheet.css"), "$sheet.css uses the token, not a literal");
        }
        $this->assertMatchesRegularExpression('/\.rb-booking__intro h1, \.rb-booking \.mine-head h1\s*\{\s*font-size:\s*var\(--rb-text-title\)/', $this->read('public/css/booking.css'), 'My bookings follows the other pages');
        $this->assertMatchesRegularExpression('/\.rb-account-hero \.rb-heading--h1\s*\{\s*font-size:\s*var\(--rb-text-title\)/', $this->read('public/css/auth.css'));
        $this->assertMatchesRegularExpression('/\.rb-dashboard h1\s*\{\s*font-size:\s*var\(--rb-text-title\)/', $this->read('public/css/dashboard.css'));
        $this->assertMatchesRegularExpression('/\.rb-availability h1\s*\{\s*font-size:\s*var\(--rb-text-title\)/', $this->read('public/css/availability.css'));
        $this->assertMatchesRegularExpression('/\.rb-ops \.today-head h1\s*\{\s*font-size:\s*var\(--rb-text-title\)/', $this->read('public/css/operations.css'));
        // Labels and action buttons use one size each.
        $this->assertMatchesRegularExpression('/\.rb-booking \.field-label\s*\{[^}]*font-size:\s*var\(--rb-text-small\)[^}]*font-weight:\s*var\(--rb-weight-semibold\)/', $this->read('public/css/booking.css'));
        $this->assertMatchesRegularExpression('/\.rb-button[^{]*\{[^}]*font-size:\s*\.8125rem;\s*font-weight:\s*var\(--rb-weight-bold\)/', $this->read('public/css/foundations/components.css'));
        $this->assertStringContainsString('rb-subnav rb-account-tabs', $this->read('resources/views/account/_tabs.blade.php'), 'account tabs use the shared underline navigation');
    }
}
