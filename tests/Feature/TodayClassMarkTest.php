<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Tests\Concerns\UsesDisposableDatabase;
use Tests\TestCase;

/**
 * Marking a class on the Today page brings lab staff back to the class list (same page of it), and the result is
 * shown there, not at the top of the page. Database-backed: see Tests\Concerns\UsesDisposableDatabase.
 */
class TodayClassMarkTest extends TestCase
{
    use UsesDisposableDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startDisposableDatabase();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 06:00', 'Asia/Manila'));   // a Monday, before the 7:30 class
    }

    protected function tearDown(): void
    {
        $this->stopDisposableDatabase();
        parent::tearDown();
    }

    public function test_marking_a_class_returns_to_the_class_list_and_shows_the_result_there(): void
    {
        $department = $this->makeDepartment();
        $staff = $this->makeAccount($department, 'lab_staff');
        $class = $this->giveBlockAClass($department, $this->makeBlock());

        $response = $this->signedInAs($staff)->from('/today?classes_page=2')
            ->post('/classes/'.$class->id.'/2026-11-09/mark', ['answer' => 'online']);

        $response->assertRedirect('/today?classes_page=2#classes')->assertSessionHas('class_marked', true)->assertSessionHas('status');

        $page = $this->get('/today')->assertOk()->getContent();
        $this->assertSame(1, substr_count($page, 'class="success"'), 'the result is shown once');
        $this->assertGreaterThan(strpos($page, 'id="classes"'), strpos($page, 'class="success"'), 'and inside the class list');
    }
}
