<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ClassSchedule extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'term_id', 'department_id', 'room_id', 'day_of_week', 'start_time', 'end_time',
        'entry_type', 'course_id', 'section_id', 'instructor', 'note',
    ];

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** $schedule->time_range, e.g. "11:30 AM – 1:30 PM" */
    protected function timeRange(): Attribute
    {
        return Attribute::get(fn () => self::clock($this->start_time).' – '.self::clock($this->end_time));
    }

    /** '13:30:00' -> '1:30 PM' */
    public static function clock(string $time): string
    {
        return Carbon::parse($time)->format('g:i A');
    }
}
