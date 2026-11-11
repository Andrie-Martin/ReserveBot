<?php

// Seeds the disposable test database used by qa-round4-browser.sh. Refuses to run against anything but a *_test schema.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\ClassSchedule;
use App\Models\Course;
use App\Models\Department;
use App\Models\DepartmentConfig;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;

$db = DB::selectOne('select database() as d')->d;
if (substr($db, -5) !== '_test' || getenv('DB_HOST') !== '127.0.0.1') {
    fwrite(STDERR, "refusing to seed $db\n");
    exit(1);
}

$dept = Department::create(['name' => 'Browser QA', 'slug' => 'browser-qa']);
DepartmentConfig::create(['department_id' => $dept->id] + DepartmentConfig::DEFAULTS);
$section = Section::create(['name' => '4-QA', 'program' => 'BSCPE']);
$mk = fn ($role, $name, $sec = null) => tap(User::create(['department_id' => $dept->id, 'section_id' => $sec, 'name' => $name, 'email' => "$role@qa.test", 'role' => $role,
    'password' => 'Starting123', 'is_active' => true, 'must_change_password' => false]), fn ($u) => $u->forceFill(['email_verified_at' => now()])->save());
$mk('admin', 'Qa Admin');
$mk('lab_staff', 'Qa Staff');
$rep = $mk('rep', 'Qa Rep', $section->id);

$rooms = [];
foreach (range(1, 28) as $i) {
    $r = Room::create(['name' => sprintf('QA%03d', 100 + $i), 'building' => 'Main', 'capacity' => 30, 'is_active' => true]);
    $dept->rooms()->attach($r);
    $rooms[] = $r;
}
// Adjacent event days: Mon Nov 16 and Tue Nov 17 (the shapes reported in QA), plus a day with no events.
$at = fn ($d, $h) => Carbon::parse("$d $h:00", 'Asia/Manila');
foreach ([['2026-11-16', 9, 'Monday lab'], ['2026-11-16', 13, 'Monday seminar'], ['2026-11-17', 9, 'Tuesday lab'], ['2026-11-17', 13, 'Tuesday seminar'], ['2026-11-17', 15, 'Tuesday review']] as [$d, $h, $p]) {
    Reservation::create(['user_id' => $rep->id, 'room_id' => $rooms[0]->id, 'purpose' => $p, 'start_time' => $at($d, $h), 'end_time' => $at($d, $h + 1), 'status' => 'Confirmed']);
}
$pending = Reservation::create(['user_id' => $rep->id, 'room_id' => $rooms[1]->id, 'purpose' => 'Needs review', 'start_time' => $at('2026-11-18', 9), 'end_time' => $at('2026-11-18', 10), 'status' => 'Pending']);
// Equipment, with one deletable and one used item
$audio = EquipmentCategory::create(['department_id' => $dept->id, 'name' => 'Audiovisual Equipment', 'sort_order' => 1]);
$sports = EquipmentCategory::create(['department_id' => $dept->id, 'name' => 'Sports Equipment', 'sort_order' => 2]);
foreach (range(1, 9) as $i) {
    Equipment::create(['category_id' => $audio->id, 'name' => "Speaker $i", 'total_qty' => 4, 'max_per_request' => 2, 'is_active' => true]);
}
foreach (range(1, 9) as $i) {
    Equipment::create(['category_id' => $sports->id, 'name' => "Ball $i", 'total_qty' => 6, 'max_per_request' => 3, 'is_active' => $i % 4 !== 0]);
}
// 20 classes today (Wednesday) for the Today table
$term = Term::create(['name' => 'QA term', 'start_date' => '2026-08-01', 'end_date' => '2027-03-01']);
$course = Course::create(['code' => 'QA101', 'title' => 'QA course']);
foreach (range(0, 19) as $i) {
    ClassSchedule::create(['term_id' => $term->id, 'department_id' => $dept->id, 'room_id' => $rooms[2 + $i]->id, 'section_id' => $section->id, 'course_id' => $course->id,
        'day_of_week' => Carbon::now('Asia/Manila')->format('D'), 'start_time' => sprintf('%02d:00:00', 7 + intdiv($i, 3)), 'end_time' => sprintf('%02d:00:00', 8 + intdiv($i, 3)), 'entry_type' => 'Class']);
}
echo "seeded; pending booking id {$pending->id}\n";
