<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Equipment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesDisposableDatabase;
use Tests\TestCase;

/**
 * Sandbox issue 7. The ID number left the profiles. Instead, every booking that includes equipment names who
 * will borrow it: a name and a PUP ID number, both required; a room-only booking asks for neither. Lab staff
 * see the borrower where they approve and hand out. Database-backed: see Tests\Concerns\UsesDisposableDatabase.
 */
class BorrowerOnEquipmentBookingTest extends TestCase
{
    use UsesDisposableDatabase;

    private Department $department;

    private User $block;

    private User $staff;

    private Room $room;

    private Equipment $projector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startDisposableDatabase();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 08:00', 'Asia/Manila'));   // a Monday morning
        $this->department = $this->makeDepartment();
        $section = $this->makeBlock();
        $this->block = $this->makeAccount($this->department, 'rep', ['section_id' => $section->id, 'name' => 'Bea Prieto']);
        $this->staff = $this->makeAccount($this->department, 'lab_staff', ['name' => 'Lab Assistant']);
        $this->room = $this->makeRoom($this->department);
        $this->projector = $this->makeEquipment($this->department);
    }

    protected function tearDown(): void
    {
        $this->stopDisposableDatabase();
        parent::tearDown();
    }

    /** The booking form's fields for tomorrow, 1:00 to 3:00 PM */
    private function form(string $need, array $changes = []): array
    {
        return array_replace(array_filter([
            'for' => 'other', 'date' => '2026-11-10', 'from' => '13:00', 'to' => '15:00', 'need' => $need, 'purpose' => 'Thesis demo',
            'room' => $need === 'equipment' ? null : $this->room->id,
            'qty' => $need === 'room' ? null : [$this->projector->id => 1],
        ], fn ($value) => $value !== null), $changes);
    }

    private function book(array $form)
    {
        return $this->signedInAs($this->block)->from('/book')->post('/book', $form);
    }

    private function bookings(): int
    {
        return Reservation::where('user_id', $this->block->id)->count();
    }

    // ---- the booking form ----

    public function test_borrowing_equipment_needs_the_borrowers_name_and_id_number(): void
    {
        foreach (['equipment', 'both'] as $need) {
            $this->book($this->form($need))->assertSessionHasErrors([
                'borrower_name' => 'Type the name of the person who will borrow the equipment.',
                'borrower_id' => 'Type the borrower’s ID number, e.g. 2021-00123-MN-0.',
            ]);
            $this->book($this->form($need, ['borrower_name' => 'Juan Dela Cruz']))->assertSessionHasErrors('borrower_id')->assertSessionDoesntHaveErrors('borrower_name');
            $this->book($this->form($need, ['borrower_id' => '2021-00123-MN-0']))->assertSessionHasErrors('borrower_name')->assertSessionDoesntHaveErrors('borrower_id');
        }
        $this->assertSame(0, $this->bookings(), 'nothing is booked without a borrower');
    }

    public function test_the_id_number_must_look_like_a_pup_id(): void
    {
        foreach (['12345', 'abc', '2021-123-MN-0', '2021-00123-MNL-0', 'not an id'] as $wrong) {
            $this->book($this->form('equipment', ['borrower_name' => 'Juan Dela Cruz', 'borrower_id' => $wrong]))
                ->assertSessionHasErrors(['borrower_id' => 'An ID number looks like 2021-00123-MN-0.']);
        }
        $this->assertSame(0, $this->bookings());
    }

    public function test_an_equipment_booking_saves_the_borrower_tidied_up(): void
    {
        $this->book($this->form('equipment', ['borrower_name' => '  Juan   Dela Cruz ', 'borrower_id' => ' 202100123mn0 ']))
            ->assertSessionHasNoErrors();

        $booking = Reservation::where('user_id', $this->block->id)->sole();
        $this->assertNull($booking->room_id);
        $this->assertSame('Juan Dela Cruz', $booking->borrower_name);
        $this->assertSame('2021-00123-MN-0', $booking->borrower_id_number);
        $this->assertSame('Bea Prieto', $booking->rep_name, 'the borrower does not have to be the representative');
        $this->assertTrue($booking->hasBorrower());
    }

    public function test_room_and_equipment_and_typed_in_items_ask_for_a_borrower_too(): void
    {
        $this->book($this->form('both', ['borrower_name' => 'Ana Reyes', 'borrower_id' => '2022-00456-MN-1']))->assertSessionHasNoErrors();
        $booking = Reservation::where('user_id', $this->block->id)->sole();
        $this->assertSame($this->room->id, $booking->room_id);
        $this->assertSame(['Ana Reyes', '2022-00456-MN-1'], [$booking->borrower_name, $booking->borrower_id_number]);

        // Only an item that isn't on the list: still equipment, still a borrower
        $typedOnly = $this->form('equipment', ['date' => '2026-11-11', 'qty' => [], 'other_items' => 'Bluetooth speaker x1']);
        $this->book($typedOnly)->assertSessionHasErrors(['borrower_name', 'borrower_id']);
        $this->book($typedOnly + ['borrower_name' => 'Ana Reyes', 'borrower_id' => '2022-00456-MN-1'])->assertSessionHasNoErrors();
        $this->assertSame(2, $this->bookings());
    }

    public function test_a_room_only_booking_asks_for_no_borrower_and_keeps_none(): void
    {
        $this->book($this->form('room'))->assertSessionHasNoErrors();
        // Something left in the hidden boxes is ignored for a room-only booking
        $this->book($this->form('room', ['date' => '2026-11-11', 'borrower_name' => 'Left Over', 'borrower_id' => 'whatever']))->assertSessionHasNoErrors();

        $this->assertSame(2, $this->bookings());
        foreach (Reservation::where('user_id', $this->block->id)->get() as $booking) {
            $this->assertNull($booking->borrower_name);
            $this->assertNull($booking->borrower_id_number);
            $this->assertFalse($booking->hasBorrower());
        }
    }

    public function test_the_form_shows_the_two_borrower_boxes_with_the_equipment(): void
    {
        $this->signedInAs($this->block)->get('/book?for=other&date=2026-11-10&from=13:00&to=15:00')->assertOk()
            ->assertSee('Who will borrow the equipment?')
            ->assertSee('name="borrower_name"', false)
            ->assertSee('name="borrower_id"', false)
            ->assertSee('part part-equipment part-borrower', false);     // hides together with the equipment for "Room only"
    }

    // ---- what lab staff and the block see ----

    public function test_lab_staff_see_the_borrower_where_they_approve_and_hand_out(): void
    {
        $booking = $this->makeBooking($this->block, $this->room, '2026-11-09 13:00', 'Pending', ['borrower_name' => 'Juan Dela Cruz', 'borrower_id_number' => '2021-00123-MN-0']);
        $booking->equipment()->attach($this->projector->id, ['qty_requested' => 1]);

        $this->signedInAs($this->staff)->get('/requests')->assertOk()->assertSee('Borrower')->assertSee('Juan Dela Cruz')->assertSee('2021-00123-MN-0');
        $this->get('/bookings/'.$booking->id)->assertOk()->assertSee('Borrower')->assertSee('Juan Dela Cruz')->assertSee('2021-00123-MN-0');

        $this->post('/bookings/'.$booking->id.'/approve')->assertSessionHas('status');
        $this->get('/today')->assertOk()->assertSee('Borrower:')->assertSee('Juan Dela Cruz')->assertSee('2021-00123-MN-0');
        $this->from('/today')->post('/bookings/'.$booking->id.'/hand-out')
            ->assertSessionHas('status', fn ($message) => str_contains($message, '(borrower: Juan Dela Cruz)'));

        // The block sees who it named, on its own booking page
        $this->signedInAs($this->block)->get('/bookings/'.$booking->id)->assertOk()->assertSee('Juan Dela Cruz')->assertSee('2021-00123-MN-0');
    }

    public function test_a_room_only_booking_shows_no_borrower_and_an_older_equipment_booking_says_so(): void
    {
        $roomOnly = $this->makeBooking($this->block, $this->room, '2026-11-09 13:00');
        $older = $this->makeBooking($this->block, null, '2026-11-09 15:00');     // equipment, from before borrowers were asked for
        $older->equipment()->attach($this->projector->id, ['qty_requested' => 1]);

        $this->signedInAs($this->staff)->get('/bookings/'.$roomOnly->id)->assertOk()->assertDontSee('Borrower');
        $this->get('/bookings/'.$older->id)->assertOk()->assertSee('Borrower')->assertSee('Not recorded');
        $this->get('/today')->assertOk()->assertSee('Room key only')->assertSee('Borrower not recorded');
    }

    // ---- the ID number is gone from every profile ----

    public function test_no_profile_has_an_id_number_anymore(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'school_id'));
        $this->assertTrue(Schema::hasColumn('reservations', 'borrower_name'));
        $this->assertTrue(Schema::hasColumn('reservations', 'borrower_id_number'));

        $admin = $this->makeAccount($this->department, 'admin', ['name' => 'Office Admin']);
        foreach ([$this->block, $this->staff, $admin] as $account) {
            $this->signedInAs($account)->get('/account')->assertOk()
                ->assertDontSee('name="school_id"', false)->assertDontSee('School ID');
        }
        $this->signedInAs($admin)->get('/accounts')->assertOk()->assertDontSee('name="school_id"', false)->assertDontSee('School ID');
        $this->get('/accounts/'.$this->block->id)->assertOk()->assertDontSee('name="school_id"', false)->assertDontSee('School ID');
    }

    public function test_the_database_keeps_a_borrowers_name_and_id_number_together(): void
    {
        $booking = $this->makeBooking($this->block, $this->room, '2026-11-09 13:00');
        foreach ([['borrower_name' => 'Juan Dela Cruz'], ['borrower_id_number' => '2021-00123-MN-0']] as $half) {
            try {
                Reservation::whereKey($booking->id)->update($half);
                $this->fail('A borrower with only a name or only an ID number must be refused by the database.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('chk_reservations_borrower', $e->getMessage());
            }
        }
        Reservation::whereKey($booking->id)->update(['borrower_name' => 'Juan Dela Cruz', 'borrower_id_number' => '2021-00123-MN-0']);
        $this->assertTrue($booking->fresh()->hasBorrower());
    }
}
