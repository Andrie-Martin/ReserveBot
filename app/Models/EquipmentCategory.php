<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentCategory extends Model
{
    public $timestamps = false;

    protected $fillable = ['department_id', 'name', 'sort_order'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'category_id');
    }
}
