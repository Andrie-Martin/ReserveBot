<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 11: a department's new timetable while it's being made (App\Services\Timetables).
 * rows: App\Support\TimetableRules rows, each with an id that stays the same while it's edited.
 */
class TimetableDraft extends Model
{
    protected $fillable = [
        'department_id', 'rows', 'next_id', 'source', 'source_name', 'edited',
        'term_name', 'term_start', 'term_end', 'version', 'started_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['rows' => 'array', 'edited' => 'boolean', 'term_start' => 'date', 'term_end' => 'date'];
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** The semester it's for: ['name' => ..., 'start' => 'Y-m-d', 'end' => 'Y-m-d'] (null when not set) */
    public function semester(): array
    {
        return [
            'name' => $this->term_name,
            'start' => $this->term_start?->toDateString(),
            'end' => $this->term_end?->toDateString(),
        ];
    }

    /** One row, by its id */
    public function row(int $id): ?array
    {
        foreach ($this->rows as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }

        return null;
    }

    /** How it started: 'From the file “2nd sem.xlsx”', 'A copy of the timetable in use', ... */
    public function sourceLabel(): string
    {
        $changed = $this->edited ? ', changed on the site' : '';

        return match ($this->source) {
            'upload' => 'From the file “'.$this->source_name.'”'.$changed,
            'backup' => 'The backup of '.$this->source_name.$changed,
            default => 'A copy of the timetable in use'.($this->edited ? ', changed on the site' : ''),
        };
    }

    /** For the backup of the timetable it replaces: 'Replaced by the file “2nd sem.xlsx”' */
    public function replacedBy(): string
    {
        return match ($this->source) {
            'upload' => 'Replaced by the file “'.$this->source_name.'”'.($this->edited ? ' (with changes made on the site)' : ''),
            'backup' => 'Replaced when the backup of '.$this->source_name.' was brought back',
            default => 'Replaced by changes made on the site',
        };
    }
}
