<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 10: is one meeting of a class (a timetable class on one date) face-to-face or online?
 * See App\Services\ClassChecks.
 */
class ClassCheck extends Model
{
    public $timestamps = false;

    protected $fillable = ['class_schedule_id', 'class_date', 'answer', 'answered_by', 'answered_via', 'answered_at', 'asked_at'];

    protected function casts(): array
    {
        return [
            'class_date' => 'date',
            'answered_at' => 'datetime',
            'asked_at' => 'datetime',
        ];
    }

    public function classSchedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class);
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }
}
