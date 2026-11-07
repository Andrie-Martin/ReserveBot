<?php

namespace Tests\Feature;

use App\Models\{ClassCheck, ClassSchedule, Course, Department, DepartmentConfig, Reservation, Room, Section, Term, User};
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Notification};
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Opt-in only, after independent disposable MariaDB provisioning and isolation review.
 * No migrations, seeders, truncation, live storage or mail. HTTP tests, not browser E2E.
 * RB_AVAILABILITY_HTTP_TESTS=1; RB_AVAILABILITY_TEST_DATABASE=<schema ending _test>;
 * RB_AVAILABILITY_TEST_HOST=<verified disposable hostname>. These guards alone do not
 * authorize a run or prove Docker/network isolation. Use array drivers and DML-only grants.
 */
final class AvailabilityWorkflowTest extends TestCase
{
    private bool $transactionStarted=false;
    private Department $department;
    private Section $section;
    private Room $room;
    private ClassSchedule $class;
    private User $rep;

    protected function setUp(): void
    {
        parent::setUp();
        if(getenv('RB_AVAILABILITY_HTTP_TESTS')!=='1')$this->markTestSkipped('Requires separately authorized, disposable MariaDB provisioning and isolation verification.');
        $settings=config('database.connections.'.config('database.default'));
        $schema=(string)getenv('RB_AVAILABILITY_TEST_DATABASE');$host=(string)getenv('RB_AVAILABILITY_TEST_HOST');
        $this->assertTrue($this->app->environment('testing'));
        $this->assertNotEmpty($schema);$this->assertStringEndsWith('_test',$schema);$this->assertNotEmpty($host);
        $this->assertSame('mysql',$settings['driver']);$this->assertSame($schema,$settings['database']);$this->assertSame($host,$settings['host']);
        foreach(['url','unix_socket','read','write']as$key)$this->assertEmpty($settings[$key]??null);
        foreach(['session.driver','cache.default','mail.default']as$key)$this->assertSame('array',config($key));
        $this->assertSame('sync',config('queue.default'));
        $identity=DB::selectOne('SELECT DATABASE() AS db, @@hostname AS host, VERSION() AS version');
        $this->assertSame($schema,$identity->db);$this->assertSame($host,$identity->host);$this->assertStringContainsString('MariaDB',$identity->version);
        DB::beginTransaction();$this->transactionStarted=true;Notification::fake();
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-04 09:05','Asia/Manila'));CarbonImmutable::setTestNow(Carbon::now());
        $this->department=Department::create(['name'=>'Availability test','slug'=>'availability-'.Str::lower(Str::random(12))]);
        DepartmentConfig::create(['department_id'=>$this->department->id]+DepartmentConfig::DEFAULTS);
        $this->section=Section::create(['name'=>'4-'.Str::random(6),'program'=>'BSCPE']);
        $this->room=Room::create(['name'=>'Test-'.Str::random(10),'capacity'=>40,'is_active'=>true]);
        $this->department->rooms()->attach($this->room);
        $term=Term::create(['name'=>'Test-'.Str::random(10),'start_date'=>'2026-11-01','end_date'=>'2026-11-30']);
        $course=Course::create(['code'=>'T'.Str::random(8),'title'=>'Test course']);
        $this->class=ClassSchedule::create(['term_id'=>$term->id,'department_id'=>$this->department->id,'room_id'=>$this->room->id,'section_id'=>$this->section->id,'course_id'=>$course->id,'day_of_week'=>'Wed','start_time'=>'11:00:00','end_time'=>'12:00:00','entry_type'=>'Class','instructor'=>'Test instructor']);
        $this->rep=$this->user();
    }

    protected function tearDown(): void
    {
        try {if($this->transactionStarted)while(DB::transactionLevel()>0)DB::rollBack();}
        finally {Carbon::setTestNow();CarbonImmutable::setTestNow();parent::tearDown();}
    }

    private function user(string $role='rep',array $attributes=[]): User
    {
        $user=User::create(array_replace(['department_id'=>$this->department->id,'section_id'=>$this->section->id,'name'=>'Test viewer','role'=>$role,'email'=>'availability-'.Str::lower(Str::random(12)).'@gmail.com','password'=>'Example123','is_active'=>true,'must_change_password'=>false],$attributes));
        $user->forceFill(['email_verified_at'=>now()])->save();return $user;
    }

    private function signIn(User $user): void
    {
        if(auth()->check())$this->post('/logout')->assertRedirect('/login');
        $this->actingAs($user);
    }

    private function booking(User $user,string $status='Confirmed',array $attributes=[]): Reservation
    {
        return Reservation::create(array_replace(['user_id'=>$user->id,'room_id'=>$this->room->id,'purpose'=>'Private test purpose','start_time'=>'2026-11-04 13:00:00','end_time'=>'2026-11-04 14:00:00','status'=>$status],$attributes));
    }

    public function test_guest_role_and_account_gates(): void
    {
        $this->get('/rooms')->assertRedirect('/login');$this->get('/rep')->assertRedirect('/login');
        foreach(['lab_staff','admin']as$role){$this->signIn($this->user($role));$this->get('/rep')->assertForbidden();$this->get('/rooms')->assertOk();}
        $this->signIn($this->user('rep',['must_change_password'=>true]));$this->get('/rooms')->assertRedirect('/welcome');
        $this->signIn($this->user('rep',['is_active'=>false]));$this->get('/rooms')->assertRedirect('/login');
    }

    public function test_dashboard_data_shortcuts_and_answer_controls(): void
    {
        $this->signIn($this->rep);
        $this->get('/rep')->assertOk()->assertSee($this->class->course->code)->assertSee('Test instructor')->assertSee(route('rooms'),false)->assertSee(route('book'),false)->assertSee(route('bookings.mine'),false)->assertSee('name="answer"',false);
        $this->rep->update(['section_id'=>null]);
        $this->get('/rep')->assertOk()->assertDontSee('name="answer"',false);
        foreach(['lab_staff','admin']as$role){$this->signIn($this->user($role));$this->get($role==='admin'?'/admin':'/staff')->assertOk()->assertDontSee('css/dashboard.css',false);}
    }

    public function test_date_fallback_closed_and_outside_term_states(): void
    {
        $this->signIn($this->rep);
        foreach(['invalid','2026-02-30','2026-11-03','2026-11-19']as$date)$this->get('/rooms?date='.$date)->assertOk()->assertViewHas('dateError',fn($v)=>is_string($v))->assertViewHas('date',fn($v)=>$v->toDateString()==='2026-11-04');
        $this->get('/rooms?date=2026-11-08')->assertOk()->assertViewHas('isOpen',false)->assertDontSee('id="from"',false);
        $this->class->term->update(['end_date'=>'2026-11-03']);
        $this->get('/rooms')->assertOk()->assertViewHas('inTerm',false)->assertSee('outside the semester');
    }

    public function test_search_validation_filter_navigation_and_room_booking_parameters(): void
    {
        $this->signIn($this->rep);
        foreach(['from=bad&to=10:00','from=10:00','from=10:00&to=09:00']as$query)$this->get('/rooms?'.$query)->assertOk()->assertViewHas('searchError',fn($v)=>is_string($v));
        $response=$this->get('/rooms?date=2026-11-04&from=10:00&to=11:00')->assertOk()->assertViewHas('searchError',null);
        $this->assertSame(route('rooms',['from'=>'10:00','to'=>'11:00']).'#day',$response->viewData('todayUrl'));
        $this->assertStringContainsString('from=10%3A00',$response->viewData('nextUrl'));
        $row=$response->viewData('roomList')->first();
        $this->assertSame(route('book',['for'=>'other','date'=>'2026-11-04','from'=>'10:00','to'=>'11:00','room'=>$this->room->id]),$row['bookUrl']);
        $this->get('/rooms?from=08:00&to=09:00')->assertViewHas('search',fn($s)=>$s['started']===true);
        $this->get('/rooms?from=11:00&to=12:00')->assertViewHas('search',fn($s)=>count($s['free'])===0);
        $this->get('/rooms?date=2026-11-18')->assertViewHas('nextUrl',null);
    }

    public function test_role_specific_agendas_privacy_and_email_gate(): void
    {
        $other=$this->user('rep',['name'=>'Other private name']);$booking=$this->booking($other);
        $this->signIn($this->rep);
        $this->get('/rooms')->assertOk()->assertDontSee('Other private name')->assertDontSee('Private test purpose');
        $this->get(route('bookings.show',$booking))->assertNotFound();
        $this->rep->forceFill(['email_verified_at'=>null])->save();$this->get('/rooms')->assertOk();$this->get('/book')->assertRedirect('/account');
        foreach(['lab_staff','admin']as$role){$this->signIn($this->user($role));$res=$this->get('/rooms')->assertOk()->assertSee('Other private name')->assertSee('Private test purpose');foreach($res->viewData('roomList')as$row)$this->assertNull($row['bookUrl']);$this->get('/book')->assertForbidden();}
    }

    public function test_shared_room_conflicts_and_blocking_statuses(): void
    {
        $otherDepartment=Department::create(['name'=>'Other department','slug'=>'other-'.Str::random(10)]);
        $other=$this->user('rep',['department_id'=>$otherDepartment->id,'section_id'=>null]);$booking=$this->booking($other);
        $this->signIn($this->rep);
        foreach(['Pending','Confirmed','InUse','Cancelled']as$status){$booking->update(['status'=>$status]);$this->get('/rooms?from=13:00&to=14:00')->assertViewHas('search',fn($s)=>count($s['free'])===($status==='Cancelled'?1:0));}
        $this->room->update(['is_active'=>false]);$this->get('/rooms')->assertViewHas('roomList',fn($rows)=>$rows->isEmpty());
    }

    public function test_class_session_links_and_moved_booking_suppression(): void
    {
        $this->signIn($this->rep);
        $response=$this->get('/rooms')->assertOk();$item=collect($response->viewData('dayItems'))->firstWhere('kind','class');
        $this->assertSame(route('book',['for'=>'class','session'=>$this->class->id.'_2026-11-04']),$item['bookUrl']);
        $booking=$this->booking($this->rep,'Confirmed',['class_schedule_id'=>$this->class->id,'start_time'=>'2026-11-04 11:00:00','end_time'=>'2026-11-04 12:00:00']);
        $response=$this->get('/rooms');$item=collect($response->viewData('dayItems'))->firstWhere('kind','class');$this->assertArrayNotHasKey('bookUrl',$item);$this->assertSame(route('bookings.show',$booking),$item['url']);
        $booking->update(['start_time'=>'2026-11-04 13:00:00','end_time'=>'2026-11-04 14:00:00']);
        $item=collect($this->get('/rooms')->viewData('dayItems'))->firstWhere('kind','class');$this->assertTrue($item['booked_elsewhere']);$this->assertArrayNotHasKey('bookUrl',$item);
    }

    public function test_class_answers_preserve_state_and_release_room(): void
    {
        $this->signIn($this->rep);$url=route('classes.answer',[$this->class,'2026-11-04']);
        $this->from('/rep')->post($url,['answer'=>'online'])->assertRedirect('/rep')->assertSessionHas('status');
        $this->assertSame('online',ClassCheck::where('class_schedule_id',$this->class->id)->value('answer'));
        $this->get('/rooms?from=11:00&to=12:00')->assertViewHas('search',fn($s)=>count($s['free'])===1);
        $this->from('/rep')->post($url,['answer'=>'f2f'])->assertRedirect('/rep')->assertSessionHas('status');
        $this->get('/rooms?from=11:00&to=12:00')->assertViewHas('search',fn($s)=>count($s['free'])===0);
        $this->from('/rep')->post($url,['answer'=>'invalid'])->assertNotFound();
        $this->post(route('classes.answer',[$this->class,'2026-02-30']),['answer'=>'online'])->assertNotFound();
        $this->signIn($this->user('rep',['section_id'=>null]));$this->post($url,['answer'=>'online'])->assertNotFound();
    }

    public function test_freed_room_reclaim_booked_and_ended_refusals(): void
    {
        ClassCheck::create(['class_schedule_id'=>$this->class->id,'class_date'=>'2026-11-04','asked_at'=>'2026-11-03 12:00:00']);
        $this->signIn($this->rep);$url=route('classes.answer',[$this->class,'2026-11-04']);
        $this->get('/rooms?from=11:00&to=12:00')->assertViewHas('search',fn($s)=>count($s['free'])===1);
        $booking=$this->booking($this->rep,'Confirmed',['start_time'=>'2026-11-04 11:00:00','end_time'=>'2026-11-04 12:00:00']);
        $this->from('/rep')->post($url,['answer'=>'f2f'])->assertSessionHas('problem',fn($message)=>str_contains($message,'can’t have it back'));
        $booking->update(['class_schedule_id'=>$this->class->id]);$this->post($url,['answer'=>'online'])->assertSessionHas('problem',fn($message)=>str_contains($message,'booked as a class session'));
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-04 12:01','Asia/Manila'));CarbonImmutable::setTestNow(Carbon::now());
        $this->post($url,['answer'=>'f2f'])->assertSessionHas('problem','This class has already ended.');
    }

    public function test_class_answer_rejects_expired_csrf(): void
    {
        $this->signIn($this->rep);$this->get('/rep')->assertOk();
        $this->app->instance('env','csrf-verification');config(['app.debug'=>false]);
        try{$this->post(route('classes.answer',[$this->class,'2026-11-04']),['_token'=>'expired','answer'=>'online'])->assertStatus(419);}
        finally{$this->app->instance('env','testing');}
    }
}
