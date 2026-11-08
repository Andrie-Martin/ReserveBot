<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentConfig;
use App\Models\Term;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Opt-in HTTP regression suite for the Booking rules page. Requires an independently provisioned, disposable
 * MariaDB schema; NEVER migrates/seeds/truncates, and every test runs inside a rolled-back transaction.
 * Enable only for a disposable database: RB_MANAGE_HTTP_TESTS=1 and
 * RB_MANAGE_TEST_DATABASE=<exact disposable database ending in _test>.
 *
 * The page has two forms (booking rules, semester dates). A failed save of one must never change what the other
 * form shows, because pressing Save on a form that was rendered wrongly silently changes the stored setting.
 */
final class ManageRulesWorkflowTest extends TestCase
{
    private bool $transactionStarted = false;
    private Department $department;
    private User $admin;
    private Term $term;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RB_MANAGE_HTTP_TESTS') !== '1') {
            $this->markTestSkipped('Requires explicit authorization and a disposable, provisioned MariaDB test database.');
        }
        $connection = config('database.default');
        $settings = config('database.connections.'.$connection);
        $expected = (string) getenv('RB_MANAGE_TEST_DATABASE');
        $this->assertTrue($this->app->environment('testing'));
        $this->assertNotEmpty($expected);
        $this->assertStringEndsWith('_test', $expected);
        $this->assertSame('mysql', $settings['driver']);
        $this->assertSame($expected, $settings['database']);
        $this->assertEmpty($settings['url'] ?? null, 'DB URL must not override isolation.');
        $this->assertSame('array', config('session.driver'));
        $this->assertSame($expected, DB::selectOne('select database() as name')->name);
        DB::beginTransaction();
        $this->transactionStarted = true;

        $this->department = Department::create(['name' => 'Rules test department', 'slug' => 'rules-'.Str::lower(Str::random(10))]);
        $this->admin = User::create([
            'department_id' => $this->department->id, 'name' => 'Rules admin', 'email' => 'rules-'.Str::lower(Str::random(12)).'@gmail.com',
            'role' => 'admin', 'password' => 'Starting123', 'is_active' => true, 'must_change_password' => false,
        ]);
        $this->term = Term::create(['name' => 'Rules test term', 'start_date' => '2026-08-01', 'end_date' => '2026-12-01']);
        $this->actingAs($this->admin);
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

    private function config(bool $requiresApproval, string $days = 'Mon,Tue,Wed,Thu,Fri,Sat'): DepartmentConfig
    {
        $config = DepartmentConfig::updateOrCreate(['department_id' => $this->department->id],
            ['requires_approval' => $requiresApproval, 'open_days' => $days] + DepartmentConfig::DEFAULTS);
        // the signed-in user keeps its department (and config) relation in memory between requests: start from a fresh one
        $this->actingAs($this->admin = $this->admin->fresh());

        return $config;
    }

    private function stored(): DepartmentConfig
    {
        return DepartmentConfig::where('department_id', $this->department->id)->firstOrFail();
    }

    /** What a browser would submit from the rendered rules form if the person pressed "Save rules" without touching it */
    private function renderedRulesForm(string $html): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);
        $fields = [];
        foreach ($xpath->query('//form[contains(@action,"/manage/rules")]//input[@name and @name!="_token" and @name!="_method"]') as $input) {
            $type = $input->getAttribute('type');
            if (in_array($type, ['checkbox', 'radio'], true) && ! $input->hasAttribute('checked')) {
                continue;     // an unticked box submits nothing
            }
            $name = $input->getAttribute('name');
            $value = $input->hasAttribute('value') ? $input->getAttribute('value') : 'on';
            if (str_ends_with($name, '[]')) {
                $fields[substr($name, 0, -2)][] = $value;
            } else {
                $fields[$name] = $value;
            }
        }
        foreach ($xpath->query('//form[contains(@action,"/manage/rules")]//select[@name]') as $select) {
            $selected = $xpath->query('.//option[@selected]', $select)->item(0) ?? $xpath->query('.//option', $select)->item(0);
            $fields[$select->getAttribute('name')] = $selected->getAttribute('value');
        }

        return $fields;
    }

    private function approvalTicked(string $html): bool
    {
        return (bool) preg_match('/<input[^>]*name="requires_approval"[^>]*\schecked/', $html);
    }

    /** Presses "Save dates" with an end before the start, as a browser would (it always sends _token), and returns the page it lands on */
    private function failDatesSave(): string
    {
        $html = $this->followingRedirects()->from('/manage/rules')
            ->put(route('manage.terms.update', $this->term), ['_token' => 'browser-token', 'start_date' => '2026-09-01', 'end_date' => '2026-08-01'])
            ->assertOk()->getContent();
        $this->assertStringContainsString('The last day must be after the first day.', $html, 'the refusal is shown');
        $this->assertSame('2026-08-01', $this->term->fresh()->start_date->toDateString(), 'a refused save must not change the dates');

        return $html;
    }

    /** Presses "Save rules" with the given fields (plus the _token a browser sends) and returns the page it lands on */
    private function saveRules(array $fields): string
    {
        return $this->followingRedirects()->from('/manage/rules')
            ->put(route('manage.rules.update'), ['_token' => 'browser-token'] + $fields)->assertOk()->getContent();
    }

    public function test_a_failed_dates_save_followed_by_save_rules_cannot_change_approval(): void
    {
        foreach ([true, false] as $required) {
            $this->config($required);
            $html = $this->failDatesSave();
            $this->assertSame($required, $this->approvalTicked($html), 'the rules form still shows the stored approval setting');
            $this->assertStringContainsString('value="2026-09-01"', $html, 'the dates the person typed are kept');

            // pressing "Save rules" with the form exactly as it was rendered
            $this->saveRules($this->renderedRulesForm($html));
            $this->assertSame($required, (bool) $this->stored()->requires_approval, 'approval is unchanged by an unrelated validation failure');
            $this->assertSame('Mon,Tue,Wed,Thu,Fri,Sat', $this->stored()->open_days);
        }
    }

    public function test_an_intentionally_changed_approval_survives_a_failed_rules_save(): void
    {
        foreach ([[true, false], [false, true]] as [$saved, $submitted]) {
            $this->config($saved);
            $form = $this->renderedRulesForm($this->get('/manage/rules')->getContent());
            unset($form['requires_approval']);
            if ($submitted) {
                $form['requires_approval'] = '1';
            }
            $form['min_booking_minutes'] = '120';
            $form['max_booking_minutes'] = '60';        // refused: the longest booking can't be shorter than the shortest

            $html = $this->saveRules($form);
            $this->assertStringContainsString('shorter than the shortest', str_replace('&#039;', '’', $html));
            $this->assertSame($saved, (bool) $this->stored()->requires_approval, 'the refused save changed nothing');
            $this->assertSame($submitted, $this->approvalTicked($html), 'the person’s own choice is kept on the error page');

            $form['min_booking_minutes'] = '30';
            $form['max_booking_minutes'] = '180';
            $this->saveRules($form);
            $this->assertSame($submitted, (bool) $this->stored()->requires_approval, 'the corrected save stores the intended setting');
        }
    }

    public function test_open_days_the_person_unticked_are_not_silently_ticked_again(): void
    {
        $this->config(true);
        $form = $this->renderedRulesForm($this->get('/manage/rules')->getContent());
        unset($form['open_days']);                       // every day unticked

        $html = $this->saveRules($form);
        $this->assertStringContainsString('Pick at least one open day.', $html);
        $this->assertSame(0, preg_match_all('/name="open_days\\[\\]"[^>]*\\schecked/', $html));
        $this->assertSame('Mon,Tue,Wed,Thu,Fri,Sat', $this->stored()->open_days, 'the refused save changed nothing');
    }
}
