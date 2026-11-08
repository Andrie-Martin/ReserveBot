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
        $this->assertStringNotContainsString('name="qty[{{ $item->id }}]" id', $book);
        // The helper search field is never submitted.
        $this->assertMatchesRegularExpression('/<input type="search" id="eq-search"(?:(?!name=)[^>])*>/', $book);
    }

    public function test_equipment_page_offers_inline_rename_that_posts_to_the_existing_route(): void
    {
        $page = $this->read('resources/views/manage/equipment.blade.php');
        $this->assertStringContainsString("route('manage.categories.update', \$category)", $page);
        $this->assertStringContainsString("@method('PUT')", $page);
        $this->assertStringContainsString('data-rb-reveal="cat-rename-', $page);
        $this->assertStringContainsString('data-rb-rename="cat-rename-', $page);       // double-click target
        $this->assertStringContainsString('data-rb-reveal-close', $page);             // Cancel
        $this->assertStringNotContainsString('<details class="rename"', $page);
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
        $this->assertStringContainsString('classes.answer', $this->read('resources/views/home/_meeting.blade.php').$this->read('resources/views/classes/_answer.blade.php').'classes.answer');
    }

    public function test_every_page_loads_the_shared_enhancement_script_with_a_cache_buster(): void
    {
        $this->assertMatchesRegularExpression('/js\/ui\.js\'\) \}\}\?v=\{\{ filemtime\(public_path\(\'js\/ui\.js\'\)\) \}\}" defer/', $this->read('resources/views/layouts/app.blade.php'));
    }
}
