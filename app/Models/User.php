<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** What each role is called on the pages */
    public const ROLE_LABELS = [
        'rep' => 'Block rep',
        'lab_staff' => 'Lab staff',
        'admin' => 'Admin',
    ];

    protected $fillable = ['department_id', 'section_id', 'name', 'email', 'password', 'role', 'is_active'];

    // Never sent to the browser or included in JSON
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',     // a plain password is hashed (bcrypt) automatically when saved
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** The block a rep represents (empty for lab staff and admins) */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->role;
    }

    /** Where this user lands after logging in */
    public function homeUrl(): string
    {
        return match ($this->role) {
            'rep' => route('rep.home'),
            'lab_staff' => route('staff.home'),
            'admin' => route('admin.home'),
        };
    }
}
