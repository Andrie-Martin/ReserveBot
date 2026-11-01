<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    /** While a reservation has one of these statuses, its room and equipment are taken */
    public const BLOCKING_STATUSES = ['Pending', 'Confirmed', 'InUse'];

    protected $fillable = [
        'user_id', 'room_id', 'class_schedule_id', 'purpose', 'start_time', 'end_time',
        'status', 'status_reason', 'other_items', 'reviewed_by',
    ];

    protected function casts(): array
    {
        return ['start_time' => 'datetime', 'end_time' => 'datetime'];
    }

    /** The rep who booked it */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** The class session being booked (empty for extra bookings like make-up classes) */
    public function classSchedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class);
    }

    /** The admin who approved or rejected it */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Requested items, with qty_requested on ->pivot */
    public function equipment(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'reservation_equipment')->withPivot('qty_requested');
    }

    public function overrideLogs(): HasMany
    {
        return $this->hasMany(OverrideLog::class);
    }
}
