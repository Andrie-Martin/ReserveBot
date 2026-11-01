<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'program'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function classSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class);
    }
}
