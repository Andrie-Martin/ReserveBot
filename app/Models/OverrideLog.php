<?php

namespace App\Models;

use App\Support\TimeSlots;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class OverrideLog extends Model
{
    // A log entry is never edited, so it only has created_at
    public const UPDATED_AT = null;

    protected $fillable = ['reservation_id', 'changed_by', 'action', 'old_value', 'new_value', 'reason'];

    protected function casts(): array
    {
        return ['old_value' => 'array', 'new_value' => 'array'];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** What happened, in words, e.g. 'moved it from CEA300, Nov 3, 11:30 AM–1:30 PM to CEA302, ...' */
    public function describe(): string
    {
        $new = $this->new_value ?? [];

        return match ($this->action) {
            'Rescheduled' => 'moved it from '.self::place($this->old_value).' to '.self::place($new),
            'Cancelled' => 'cancelled it',
            'StatusChanged' => match ($new['status'] ?? '') {
                'InUse' => 'handed out the key and items',
                'Completed' => 'marked everything returned',
                'NoShow' => 'marked it a no-show',
                default => 'changed its status to '.($new['status'] ?? 'something else'),
            },
            'EquipmentUpdated' => 'changed the items',
            default => strtolower($this->action),
        };
    }

    /** The reason is worth showing (not just the button's standard words) */
    public function hasOwnReason(): bool
    {
        return in_array($this->action, ['Rescheduled', 'Cancelled'], true)
            || (($this->new_value['status'] ?? null) === 'Completed' && $this->reason !== 'Everything returned');
    }

    /** 'CEA300, Nov 3, 11:30 AM–1:30 PM' */
    private static function place(?array $value): string
    {
        if (! $value) {
            return 'somewhere else';
        }
        $start = Carbon::parse($value['start']);
        $end = Carbon::parse($value['end']);

        return ($value['room'] ?? 'no room').', '.$start->format('M j').', '
            .TimeSlots::span($start->hour * 60 + $start->minute, $end->hour * 60 + $end->minute);
    }

    /** The lab staff member or admin who made the change */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
