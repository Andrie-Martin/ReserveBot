<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    /** While a reservation has one of these statuses, its room and equipment are taken */
    public const BLOCKING_STATUSES = ['Pending', 'Confirmed', 'InUse'];

    /** A rep can cancel a booking with one of these statuses (until the department's cancel deadline) */
    public const CANCELLABLE_STATUSES = ['Pending', 'Confirmed'];

    /** What each status is called on the pages */
    public const STATUS_LABELS = [
        'Pending' => 'Waiting for approval',
        'Confirmed' => 'Approved',
        'Rejected' => 'Rejected',
        'Cancelled' => 'Cancelled',
        'InUse' => 'In use now',
        'Completed' => 'Completed',
        'NoShow' => 'No-show',
    ];

    protected $fillable = [
        'user_id', 'room_id', 'class_schedule_id', 'purpose', 'start_time', 'end_time',
        'status', 'status_reason', 'other_items', 'reviewed_by',
    ];

    protected function casts(): array
    {
        return ['start_time' => 'datetime', 'end_time' => 'datetime'];
    }

    /** The person who booked it (a rep, lab staff or an admin) */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The booked room (empty for an equipment-only booking) */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** The class session being booked (empty for extra bookings like make-up classes) */
    public function classSchedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class);
    }

    /** The lab staff or admin who approved or rejected it */
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

    /** Reservation::blocking() = the ones still holding their room and equipment */
    public function scopeBlocking(Builder $query): void
    {
        $query->whereIn('status', self::BLOCKING_STATUSES);
    }

    /** Reservation::overlapping($start, $end) = starts before $end and ends after $start */
    public function scopeOverlapping(Builder $query, $start, $end): void
    {
        $query->where('start_time', '<', $end)->where('end_time', '>', $start);
    }

    public function hasEquipment(): bool
    {
        return $this->equipment->isNotEmpty() || filled($this->other_items);
    }

    /** 'Room and equipment', 'Room only' or 'Equipment only' */
    public function needLabel(): string
    {
        if (! $this->room_id) {
            return 'Equipment only';
        }

        return $this->hasEquipment() ? 'Room and equipment' : 'Room only';
    }

    /** 'Projector ×1, HDMI Cable ×2, Bluetooth speaker x1' (listed items, then the typed-in ones) */
    public function itemsText(): string
    {
        $items = $this->equipment->map(fn ($item) => $item->name.' ×'.$item->pivot->qty_requested);
        if (filled($this->other_items)) {
            $items->push($this->other_items);
        }

        return $items->join(', ');
    }

    /** How many requests from $user's department wait for approval (for the top bar) */
    public static function waitingCountFor(User $user): int
    {
        return self::where('status', 'Pending')->whereRelation('user', 'department_id', $user->department_id)->count();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
