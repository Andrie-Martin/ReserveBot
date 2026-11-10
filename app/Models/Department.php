<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Department extends Model
{
    protected $fillable = ['name', 'slug', 'contact_email'];

    /**
     * A short code for a department made on the site, from its name: "Electronics Engineering Department" ->
     * "electronics-engineering-depart" (the column holds 30 characters). A number is added if that code is taken.
     * The code is only an internal label (seeders look departments up by it); nobody types or sees it.
     */
    public static function uniqueSlug(string $name): string
    {
        $base = rtrim(Str::limit(Str::slug($name) ?: 'department', 30, ''), '-');
        $slug = $base;
        for ($n = 2; self::where('slug', $slug)->exists(); $n++) {
            $slug = rtrim(Str::limit($base, 30 - strlen('-'.$n), ''), '-').'-'.$n;
        }

        return $slug;
    }

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
