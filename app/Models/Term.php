<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Term extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function classSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class);
    }

    /** Term::current() = the semester that today falls in */
    public function scopeCurrent(Builder $query): void
    {
        $today = today()->toDateString();

        $query->where('start_date', '<=', $today)->where('end_date', '>=', $today);
    }
}
