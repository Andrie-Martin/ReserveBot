<?php

namespace App\Services;

use App\Models\ClassSchedule;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * What a department still has to add before its blocks can book.
 *
 * A department the College admin just added is empty: no rooms, timetable, equipment or accounts. Its Admin's (and
 * lab staff's) home page lists the steps below and links to the page for each, until the three that are needed
 * are done. The College admin sees the same thing as one line per department.
 *
 *   Needed:   rooms, the class timetable, block accounts   (without them nothing can be booked)
 *   Optional: equipment, lab staff accounts                (a department can run without them)
 *
 * Booking rules are not a step: every department starts with the usual ones and can change them any time.
 * Read-only: it only counts what exists.
 */
final class DepartmentSetup
{
    /** The steps in the order to do them: [key => needed?] */
    private const STEPS = ['rooms' => true, 'timetable' => true, 'equipment' => false, 'staff' => false, 'blocks' => true];

    /** What each step is called in a short list ("Still to set up: rooms, the timetable") */
    public const SHORT = ['rooms' => 'rooms', 'timetable' => 'the timetable', 'equipment' => 'equipment', 'staff' => 'lab staff accounts', 'blocks' => 'block accounts'];

    /**
     * The steps for one department, worded for $viewer: an Admin gets a link for every step; lab staff get links
     * for the three they can do (rooms, timetable, equipment), because accounts are made by the Admin.
     *
     * @return array{steps: list<array<string, mixed>>, left: list<string>, complete: bool}
     */
    public static function for(Department $department, ?User $viewer = null): array
    {
        return self::fromCounts(self::counts([$department->id])[$department->id], $viewer);
    }

    /**
     * How much of each thing every listed department has: [department id => [step => count]].
     *
     * @param  list<int>  $departmentIds
     * @return array<int, array<string, int>>
     */
    public static function counts(array $departmentIds): array
    {
        $counts = array_fill_keys($departmentIds, array_fill_keys(array_keys(self::STEPS), 0));
        $fill = function (string $step, iterable $rows) use (&$counts) {
            foreach ($rows as $departmentId => $count) {
                if (isset($counts[$departmentId])) {
                    $counts[$departmentId][$step] = (int) $count;
                }
            }
        };

        $fill('rooms', DB::table('department_room')->whereIn('department_id', $departmentIds)
            ->groupBy('department_id')->selectRaw('department_id, count(*) as total')->pluck('total', 'department_id'));
        $fill('timetable', ClassSchedule::whereIn('department_id', $departmentIds)->where('entry_type', 'Class')
            ->groupBy('department_id')->selectRaw('department_id, count(*) as total')->pluck('total', 'department_id'));
        $fill('equipment', DB::table('equipment')->join('equipment_categories', 'equipment_categories.id', '=', 'equipment.category_id')
            ->whereIn('equipment_categories.department_id', $departmentIds)
            ->groupBy('equipment_categories.department_id')->selectRaw('equipment_categories.department_id as department_id, count(*) as total')->pluck('total', 'department_id'));
        foreach (['staff' => 'lab_staff', 'blocks' => 'rep'] as $step => $role) {
            $fill($step, User::whereIn('department_id', $departmentIds)->where('role', $role)
                ->groupBy('department_id')->selectRaw('department_id, count(*) as total')->pluck('total', 'department_id'));
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $counts  from counts()
     * @return array{steps: list<array<string, mixed>>, left: list<string>, complete: bool}
     */
    public static function fromCounts(array $counts, ?User $viewer = null): array
    {
        $isAdmin = $viewer?->role === 'admin';
        $words = [
            'rooms' => ['Add your rooms', 'The rooms your department uses, like CEA300. A room another department already has is shared with you, not copied.', 'Add rooms', 'manage.rooms'],
            'timetable' => ['Add the class timetable', 'This semester’s classes, by room and block. Upload an Excel file or type it on the site. A block appears in ReserveBot once it has a class.', 'Add the timetable', 'manage.timetable'],
            'equipment' => ['List your equipment', 'What can be borrowed, like projectors and cables, and how many of each you have.', 'Add equipment', 'manage.equipment'],
            'staff' => ['Make lab staff accounts', 'For the people who approve requests and hand out keys and items with you.', 'Add accounts', 'accounts.index'],
            'blocks' => ['Make the block accounts', 'One account for each block, so the block can book. Do this after the timetable: a block can be picked once it has a class.', 'Add accounts', 'accounts.index'],
        ];

        $steps = [];
        $left = [];
        foreach (self::STEPS as $key => $needed) {
            [$title, $text, $link, $route] = $words[$key];
            $done = ($counts[$key] ?? 0) > 0;
            $accountStep = in_array($key, ['staff', 'blocks'], true);
            $steps[] = [
                'key' => $key,
                'title' => $title,
                'text' => $text,
                'link' => $link,
                // Accounts are made by the Admin: lab staff see the step, without a link
                'url' => $viewer && (! $accountStep || $isAdmin) ? route($route) : null,
                'done' => $done,
                'needed' => $needed,
                'count' => (int) ($counts[$key] ?? 0),
            ];
            if ($needed && ! $done) {
                $left[] = $key;
            }
        }

        return ['steps' => $steps, 'left' => $left, 'complete' => $left === []];
    }

    /** "rooms, the timetable and block accounts": the needed steps not done yet, for one line of text */
    public static function leftInWords(array $left): string
    {
        return collect($left)->map(fn (string $key) => self::SHORT[$key])->join(', ', ' and ');
    }
}
