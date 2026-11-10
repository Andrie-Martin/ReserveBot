<?php

use App\Support\TimeSlots;

require __DIR__.'/availability-runtime.php';
foundationsApplication();
$directory = $argv[1] ?? throw new RuntimeException('Output directory required');
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$expected = [];
$states = in_array('--baseline', $argv, true) ? ['coherent', 'adjacent', 'started'] : ['coherent', 'adjacent', 'started', 'empty-slots', 'invalid-axis', 'invalid-bounds', 'malformed'];
foreach (['rep', 'lab_staff', 'admin'] as $role) {
    foreach ($states as $state) {
        $name = 'timeline-'.$role.'-'.$state;
        $html = availabilityRender(['rooms', $role, 'normal'], function ($data) use ($role, $state, &$expected, $name) {
            $opens = 450;
            $closes = 1270;
            $step = 30;
            $now = 545;
            $data['slots'] = TimeSlots::slots($opens, $closes, $step);
            $data['step'] = $step;
            $data['now'] = $now;
            $rows = [];
            for ($i = 0; $i < 12; $i++) {
                $room = (object) ['id' => 100 + $i, 'name' => 'Example room '.($i + 1), 'capacity' => 40];
                $busy = [];
                if ($i % 4 === 1) {
                    foreach ([[500, 610, null], [780, 840, 'Pending'], [900, 960, 'Confirmed'], [1020, 1080, 'InUse']] as [$a,$b,$status]) {
                        $busy[] = ['kind' => $status ? 'booking' : 'class', 'start' => $a, 'end' => $b, 'status' => $status, 'label' => 'Example scheduled restriction', 'detail' => ['Example authorized detail'], 'mine' => false];
                    }
                }
                if ($i % 4 === 2) {
                    $busy[] = ['kind' => 'blocked', 'start' => 0, 'end' => 1440, 'status' => null, 'label' => 'Example maintenance', 'detail' => ['Example maintenance'], 'mine' => false];
                }
                if ($i % 4 === 3) {
                    $busy[] = ['kind' => 'class', 'start' => 450, 'end' => 1260, 'status' => null, 'label' => 'Example all-day class', 'detail' => ['Example timetable detail'], 'mine' => false];
                }
                $free = TimeSlots::freeRanges($busy, $opens, $closes, $step, $now);
                // Adjacent supplied ranges must remain separate geometrically, not be merged by the view.
                if ($state === 'adjacent' && $i === 4) {
                    $free = [[570, 600], [600, 630], [900, 960]];
                }
                $rows[] = ['room' => $room, 'free' => $free, 'busy' => $busy, 'allDay' => $i % 4 === 2 ? 'Example maintenance' : null, 'match' => null, 'clash' => null, 'bookUrl' => $role === 'rep' && $free ? route('book', ['for' => 'other', 'date' => $data['date']->toDateString(), 'room' => $room->id]) : null];
            }
            if ($state === 'empty-slots') {
                $data['slots'] = [];
            }
            if ($state === 'invalid-axis') {
                $data['step'] = 0;
            }
            if ($state === 'invalid-bounds') {
                $data['slots'] = [600, 570];
            }
            if ($state === 'malformed') {
                $rows[0]['free'] = [[600, 570]];
                $rows[1]['free'] = [['bad', 660]];
                $rows[4]['free'] = [[440, 600]];
                $rows[5]['free'] = [[570, 660], [630, 700]];
                $rows[8]['free'] = 'invalid';
            }
            if ($state === 'started') {
                $matching = [];
                $clashes = [];
                foreach ($rows as &$row) {
                    $row['clash'] = TimeSlots::firstClash($row['busy'], 450, 480);
                    $row['match'] = $row['clash'] === null;
                    if ($row['match']) {
                        $matching[] = $row['room'];
                    } else {
                        $clashes[$row['room']->id] = $row['clash'];
                    }
                    $row['bookUrl'] = $role === 'rep' && $row['free'] && $row['match'] ? route('book', ['for' => 'other', 'date' => $data['date']->toDateString(), 'from' => '07:30', 'to' => '08:00', 'room' => $row['room']->id]) : null;
                }unset($row);
                $data['search'] = ['from' => 450, 'to' => 480, 'free' => $matching, 'clashes' => $clashes, 'started' => true];
            }
            if ($state === 'coherent') {
                foundationsCheck($rows[0]['free'] === [[570, 1260]], 'Elapsed slot suppression');
                foundationsCheck($rows[1]['free'] === [[630, 780], [840, 900], [960, 1020], [1080, 1260]], 'Off-grid and booking-status restrictions');
                foundationsCheck($rows[2]['free'] === [] && $rows[3]['free'] === [], 'Blocked and all-day class');
            }
            $data['roomList'] = collect($rows);
            $expected[$name] = ['axisStart' => 450, 'axisEnd' => 1260, 'rows' => array_map(fn ($r) => ['name' => $r['room']->name, 'free' => $r['free'], 'bookUrl' => $r['bookUrl'], 'text' => is_array($r['free']) ? array_map(fn ($range) => is_array($range) && count($range) === 2 && is_int($range[0]) && is_int($range[1]) ? TimeSlots::span(...$range) : 'Availability time unavailable.', $r['free']) : ['Availability time unavailable.']], $rows)];

            return $data;
        });
        file_put_contents($directory.'/'.$name.'.html', $html);
    }
}
file_put_contents($directory.'/expected.json', json_encode($expected,JSON_PRETTY_PRINT));
echo 'Exported '.count($expected)." isolated timeline fixtures.\n";
