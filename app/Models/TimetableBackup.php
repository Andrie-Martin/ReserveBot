<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 11: a copy of a department's timetable, saved just before it was replaced.
 * rows: [{day, start, end, room, type, code, title, block, instructor, note}, ...] (names, not IDs).
 */
class TimetableBackup extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'department_id', 'term_name', 'term_start', 'term_end', 'rows',
        'class_count', 'blocked_count', 'reason', 'created_by',
    ];

    protected function casts(): array
    {
        return ['rows' => 'array', 'term_start' => 'date', 'term_end' => 'date'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** 'Nov 3, 2026, 9:41 PM' */
    public function label(): string
    {
        return $this->created_at->format('M j, Y, g:i A');
    }

    /** Its semester then: ['name' => ..., 'start' => 'Y-m-d', 'end' => 'Y-m-d'] */
    public function semester(): array
    {
        return [
            'name' => $this->term_name,
            'start' => $this->term_start?->toDateString(),
            'end' => $this->term_end?->toDateString(),
        ];
    }
}
