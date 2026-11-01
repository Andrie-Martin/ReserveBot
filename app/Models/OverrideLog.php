<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /** The lab staff member or admin who made the change */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
