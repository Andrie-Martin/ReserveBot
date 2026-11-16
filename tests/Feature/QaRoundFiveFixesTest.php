<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Services\Photos;
use App\Support\AccountFields;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\UsesDisposableDatabase;
use Tests\TestCase;

/**
 * The four tests that failed in the QA round of November 14 (ACC-09, ACC-13, FREE-05, BOOK-05).
 * Database-backed: see Tests\Concerns\UsesDisposableDatabase.
 */
class QaRoundFiveFixesTest extends TestCase
{
    use UsesDisposableDatabase;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startDisposableDatabase();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 06:00', 'Asia/Manila'));   // a Monday
        $this->department = $this->makeDepartment();
    }

    protected function tearDown(): void
    {
        $this->stopDisposableDatabase();
        parent::tearDown();
    }

    // ---- ACC-09: a block account can go back to its test address (not on the real site) ----

    public function test_a_block_account_can_put_a_test_address_back_outside_production(): void
    {
        $block = $this->makeBlock();
        $rep = $this->makeAccount($this->department, 'rep', ['section_id' => $block->id, 'email' => 'bea.qa@gmail.com']);

        $this->signedInAs($rep)->put('/account', [
            'name' => $rep->name, 'email' => 'rep1-sbx@reservebot.test', 'current_password' => 'Starting123',
        ])->assertSessionHasNoErrors();

        $this->assertSame('rep1-sbx@reservebot.test', $rep->fresh()->email);
        $this->assertNull($rep->fresh()->email_verified_at, 'it still has to be connected again');
    }

    public function test_other_addresses_are_still_refused_for_a_block_account(): void
    {
        $block = $this->makeBlock();
        $rep = $this->makeAccount($this->department, 'rep', ['section_id' => $block->id]);

        $this->signedInAs($rep)->put('/account', [
            'name' => $rep->name, 'email' => 'bea@yahoo.com', 'current_password' => 'Starting123',
        ])->assertSessionHasErrorsIn('info', 'email');
    }

    public function test_on_the_real_site_a_test_address_is_refused(): void
    {
        $rules = fn () => ['email' => AccountFields::email('rep')];
        $this->assertTrue(Validator::make(['email' => 'rep9-sbx@reservebot.test'], $rules())->passes(), 'allowed here (testing)');

        $this->app->detectEnvironment(fn () => 'production');
        $this->assertTrue(Validator::make(['email' => 'rep9-sbx@reservebot.test'], $rules())->fails(), 'refused in production');
        $this->assertTrue(Validator::make(['email' => 'rep9-sbx@gmail.com'], $rules())->passes(), 'Gmail is still fine');
    }

    // ---- ACC-13: the browser doesn't keep a copy of a photo ----

    public function test_a_photo_is_never_kept_by_the_browser(): void
    {
        Storage::fake(Photos::DISK);
        Storage::disk(Photos::DISK)->put('photos/sbx.jpg', 'not really a jpeg');
        $block = $this->makeBlock();
        $rep = $this->makeAccount($this->department, 'rep', ['section_id' => $block->id]);
        $rep->forceFill(['photo_path' => 'photos/sbx.jpg'])->save();

        $response = $this->signedInAs($rep)->get('/photos/'.$rep->id)->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('max-age', (string) $response->headers->get('Cache-Control'));

        $other = $this->makeAccount($this->department, 'rep', ['section_id' => $this->makeBlock()->id]);
        $this->signedInAs($other)->get('/photos/'.$rep->id)->assertNotFound();
    }

    // ---- FREE-05: while searching, only the rooms free for that whole time are listed ----

    public function test_a_search_lists_only_the_rooms_free_for_that_whole_time(): void
    {
        $busy = $this->makeRoom($this->department);
        $free = $this->makeRoom($this->department);
        $block = $this->makeBlock();
        $this->giveBlockAClass($this->department, $block, $busy);          // Monday 7:30–10:30 AM
        $rep = $this->makeAccount($this->department, 'rep', ['section_id' => $block->id]);

        $page = $this->signedInAs($rep)->get('/rooms?date=2026-11-09&from=08:00&to=09:00')->assertOk()->getContent();
        $this->assertStringContainsString('id="room-'.$free->id.'"', $page);
        $this->assertStringNotContainsString('id="room-'.$busy->id.'"', $page);
        $this->assertStringContainsString('1 other room isn’t free for that whole time.', $page);

        $all = $this->get('/rooms?date=2026-11-09')->assertOk()->getContent();
        $this->assertStringContainsString('id="room-'.$busy->id.'"', $all, 'Clear shows every room again');
    }

    // ---- BOOK-05: the booking address keeps only what counts ----

    public function test_a_class_booking_address_drops_a_date_or_times(): void
    {
        $block = $this->makeBlock();
        $rep = $this->makeAccount($this->department, 'rep', ['section_id' => $block->id]);

        $this->signedInAs($rep)->get('/book?for=class&session=1_2026-11-09&date=2026-11-28&from=&to=')
            ->assertRedirect('/book?for=class&session=1_2026-11-09');
        $this->get('/book?for=other&session=1_2026-11-09&date=2026-11-10&from=09:00&to=10:00')
            ->assertRedirect(route('book', ['for' => 'other', 'date' => '2026-11-10', 'from' => '09:00', 'to' => '10:00']));
    }

    public function test_something_else_still_checks_the_address(): void
    {
        $block = $this->makeBlock();
        $rep = $this->makeAccount($this->department, 'rep', ['section_id' => $block->id]);

        $this->signedInAs($rep)->get('/book?for=other&date=2026-11-15&from=09:00&to=10:00')
            ->assertOk()->assertSee('Rooms and equipment aren’t booked on Sundays.');
        $this->get('/book?for=other&date=2026-11-10&from=08:15&to=10:00')
            ->assertOk()->assertSee('Pick a start and an end time from the lists.');
    }
}
