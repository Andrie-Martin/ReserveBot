<?php

namespace App\Http\Controllers;

use App\Models\DepartmentConfig;
use App\Models\Term;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Phase 8: lab staff and admins change their department's booking rules (the department_configs
 * row), and the semester dates (shared by every department). New bookings follow the new rules;
 * bookings already made stay as they are.
 */
class ManageRulesController extends Controller
{
    public const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    public function edit(Request $request): View
    {
        return view('manage.rules', [
            'config' => DepartmentConfig::forDepartment($request->user()->department),
            'terms' => Term::orderBy('start_date')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $department = $request->user()->department;
        $validator = Validator::make($request->all(), [
            'open_days' => ['required', 'array', 'min:1'],
            'open_days.*' => [Rule::in(self::DAYS)],
            'business_hours_start' => ['required', 'date_format:H:i'],
            'business_hours_end' => ['required', 'date_format:H:i'],
            'slot_minutes' => ['required', 'integer', Rule::in([15, 30, 60])],
            'min_booking_minutes' => ['required', 'integer', 'min:15', 'max:720'],
            'max_booking_minutes' => ['required', 'integer', 'min:15', 'max:720'],
            'max_days_ahead' => ['required', 'integer', 'min:1', 'max:90'],
            'min_notice_minutes' => ['required', 'integer', 'min:0', 'max:2880'],
            'cancel_cutoff_minutes' => ['required', 'integer', 'min:0', 'max:2880'],
            'no_show_grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
        ], [
            'open_days.*' => 'Pick at least one open day.',
            'business_hours_start.*' => 'Pick an opening time.',
            'business_hours_end.*' => 'Pick a closing time.',
            'slot_minutes.*' => 'Pick a time step from the list.',
            'min_booking_minutes.*' => 'The shortest booking must be 15 to 720 minutes.',
            'max_booking_minutes.*' => 'The longest booking must be 15 to 720 minutes.',
            'max_days_ahead.*' => 'Booking ahead must be 1 to 90 days.',
            'min_notice_minutes.*' => 'Notice must be 0 to 2880 minutes (2 days).',
            'cancel_cutoff_minutes.*' => 'The cancel deadline must be 0 to 2880 minutes (2 days).',
            'no_show_grace_minutes.*' => 'Late must be 0 to 240 minutes.',
        ]);

        // Checks that compare fields with each other
        $validator->after(function ($validator) use ($request) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $opens = DepartmentConfig::minutes($request->input('business_hours_start'));
            $closes = DepartmentConfig::minutes($request->input('business_hours_end'));
            $step = (int) $request->input('slot_minutes');
            $min = (int) $request->input('min_booking_minutes');
            $max = (int) $request->input('max_booking_minutes');

            if ($closes <= $opens) {
                $validator->errors()->add('business_hours_end', 'Closing time must be after opening time.');
            }
            if ($max < $min) {
                $validator->errors()->add('max_booking_minutes', 'The longest booking can’t be shorter than the shortest.');
            }
            if ($min % $step !== 0 || $max % $step !== 0) {
                $validator->errors()->add('min_booking_minutes', 'Booking lengths must be whole time steps ('.$step.', '.(2 * $step).', '.(3 * $step).' minutes, …).');
            }
            if ($closes > $opens && $closes - $opens < $min) {
                $validator->errors()->add('min_booking_minutes', 'The shortest booking is longer than the opening hours.');
            }
        });
        $data = $validator->validateWithBag('rules');

        // Days in week order, e.g. 'Mon,Tue,Wed,Thu,Fri,Sat'
        $days = array_values(array_filter(self::DAYS, fn ($day) => in_array($day, $data['open_days'], true)));

        DepartmentConfig::updateOrCreate(['department_id' => $department->id], [
            'requires_approval' => $request->boolean('requires_approval'),
            'open_days' => implode(',', $days),
            'business_hours_start' => $data['business_hours_start'].':00',
            'business_hours_end' => $data['business_hours_end'].':00',
            'slot_minutes' => $data['slot_minutes'],
            'min_booking_minutes' => $data['min_booking_minutes'],
            'max_booking_minutes' => $data['max_booking_minutes'],
            'max_days_ahead' => $data['max_days_ahead'],
            'min_notice_minutes' => $data['min_notice_minutes'],
            'cancel_cutoff_minutes' => $data['cancel_cutoff_minutes'],
            'no_show_grace_minutes' => $data['no_show_grace_minutes'],
        ]);

        return redirect()->route('manage.rules')->with('status', 'Saved. New bookings follow these rules from now on; bookings already made stay as they are.');
    }

    /** Semester dates are shared by every department: the timetable only applies between them */
    public function updateTerm(Request $request, Term $term): RedirectResponse
    {
        $data = $request->validateWithBag('term'.$term->id, [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
        ], [
            'start_date.*' => 'Pick the first day of the semester.',
            'end_date.required' => 'Pick the last day of the semester.',
            'end_date.date_format' => 'Pick the last day of the semester.',
            'end_date.after' => 'The last day must be after the first day.',
        ]);

        $term->update($data);

        return redirect()->route('manage.rules')->with('status', 'Saved the dates of '.$term->name.'. Classes now count from '
            .$term->start_date->format('F j, Y').' to '.$term->end_date->format('F j, Y').'.');
    }
}
