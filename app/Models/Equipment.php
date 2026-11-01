<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Equipment extends Model
{
    // "equipment" has no plural, so say the table name outright
    protected $table = 'equipment';

    public $timestamps = false;

    protected $fillable = ['category_id', 'name', 'total_qty', 'max_per_request', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'category_id');
    }

    /** Reservations that asked for this item, with qty_requested on ->pivot */
    public function reservations(): BelongsToMany
    {
        return $this->belongsToMany(Reservation::class, 'reservation_equipment')->withPivot('qty_requested');
    }
}
