<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentConfig extends Model
{
    // One row per department, so the department's ID is also this table's key
    protected $primaryKey = 'department_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'department_id', 'requires_approval', 'open_days', 'business_hours_start', 'business_hours_end',
        'slot_minutes', 'min_booking_minutes', 'max_booking_minutes', 'max_days_ahead',
        'min_notice_minutes', 'cancel_cutoff_minutes', 'no_show_grace_minutes',
    ];

    protected function casts(): array
    {
        return ['requires_approval' => 'boolean'];
    }

    /** The same defaults as the department_configs migration, for a department with no row yet */
    public const DEFAULTS = [
        'requires_approval' => true,
        'open_days' => 'Mon,Tue,Wed,Thu,Fri,Sat',
        'business_hours_start' => '07:30:00',
        'business_hours_end' => '21:00:00',
        'slot_minutes' => 30,
        'min_booking_minutes' => 30,
        'max_booking_minutes' => 180,
        'max_days_ahead' => 14,
        'min_notice_minutes' => 60,
        'cancel_cutoff_minutes' => 60,
        'no_show_grace_minutes' => 15,
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** The department's rules, or the defaults if it has none saved */
    public static function forDepartment(Department $department): self
    {
        return $department->config ?? new self(['department_id' => $department->id] + self::DEFAULTS);
    }

    /** ['Mon', 'Tue', ...] */
    public function openDays(): array
    {
        return explode(',', $this->open_days);
    }

    /** Opening time as minutes after midnight, e.g. 07:30 -> 450 */
    public function opensAt(): int
    {
        return self::minutes($this->business_hours_start);
    }

    public function closesAt(): int
    {
        return self::minutes($this->business_hours_end);
    }

    /** '13:30:00' or '13:30' -> 810 */
    public static function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }
}
