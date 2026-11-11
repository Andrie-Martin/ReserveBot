<?php

namespace Tests\Feature;

use App\Http\Middleware\SingleValueInputs;
use App\Models\Department;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesDisposableDatabase;
use Tests\TestCase;

/**
 * Hand-edited addresses must never end in a server error (500): a field sent as a list where one value is
 * expected is ignored, and a booking number that doesn't exist is "not found" for lab staff too.
 * Database-backed: see Tests\Concerns\UsesDisposableDatabase.
 */
class OddAddressesTest extends TestCase
{
    use UsesDisposableDatabase;

    private Department $department;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startDisposableDatabase();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 08:00', 'Asia/Manila'));
        $this->department = $this->makeDepartment();
        $this->staff = $this->makeAccount($this->department, 'lab_staff');
    }

    protected function tearDown(): void
    {
        $this->stopDisposableDatabase();
        parent::tearDown();
    }

    public static function staffAddresses(): array
    {
        return [
            'today, date as a list' => ['/today?date[]=1'],
            'equipment, search as a list' => ['/manage/equipment?q[]=x'],
            'equipment, page as a list' => ['/manage/equipment?page[]=2'],
            'rooms, page as a list' => ['/manage/rooms?page[]=2'],
            'requests, tab as a list' => ['/requests?tab[]=x'],
        ];
    }

    #[DataProvider('staffAddresses')]
    public function test_a_field_sent_as_a_list_shows_the_page_as_if_it_was_not_sent(string $address): void
    {
        $this->signedInAs($this->staff)->get($address)->assertOk();
    }

    public function test_free_rooms_ignores_a_date_sent_as_a_list(): void
    {
        $block = $this->makeBlock();
        $rep = $this->makeAccount($this->department, 'rep', ['section_id' => $block->id]);

        $this->signedInAs($rep)->get('/rooms?date[]=x')->assertOk();
    }

    public function test_a_list_sent_in_a_form_field_is_refused_like_an_empty_field(): void
    {
        $this->post('/login', ['email' => ['a@b.c'], 'password' => ['x']])->assertRedirect()->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_the_fields_that_really_are_lists_still_arrive(): void
    {
        $request = Request::create('/x', 'POST', ['qty' => [3 => 2], 'open_days' => ['Mon'], 'name' => ['a']]);
        (new SingleValueInputs)->handle($request, fn () => response('ok'));

        $this->assertSame([3 => 2], $request->input('qty'));
        $this->assertSame(['Mon'], $request->input('open_days'));
        $this->assertNull($request->input('name'));
    }

    public function test_a_booking_number_that_does_not_exist_is_not_found_for_lab_staff(): void
    {
        $this->signedInAs($this->staff)->get('/bookings/99999999')->assertNotFound();
        $this->signedInAs($this->staff)->get('/bookings/99999999?from=requests')->assertNotFound();
    }
}
