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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
