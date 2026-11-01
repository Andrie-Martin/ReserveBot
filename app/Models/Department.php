<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Department extends Model
{
    protected $fillable = ['name', 'slug', 'contact_email'];

    /** Booking rules (one row in department_configs) */
    public function config(): HasOne
    {
        return $this->hasOne(DepartmentConfig::class);
    }

    /** Rooms this department can book (through department_room) */
    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function classSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class);
    }

    public function equipmentCategories(): HasMany
    {
        return $this->hasMany(EquipmentCategory::class);
    }
}
