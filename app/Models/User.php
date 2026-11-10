<?php

namespace App\Models;

use App\Notifications\ConnectEmail;
use App\Notifications\ResetPasswordLink;
use App\Support\AccountRules;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Throwable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * What each role is called on the pages. 'rep' is a block's account: it belongs to the block (one account per
     * block), not to one person. The block's current representative is named on it (the `name` column) and can
     * be changed by the department's admin or by the block itself. The key stays 'rep' in the database and the code.
     *
     * 'college_admin' is college-wide and belongs to no department: it makes and looks after each department's
     * Admin accounts, and nothing else (it can't approve bookings or change rooms).
     */
    public const ROLE_LABELS = [
        'rep' => 'Block account',
        'lab_staff' => 'Lab staff',
        'admin' => 'Admin',
        'college_admin' => 'College admin',
    ];

    /** The roles that belong to a department and use its pages (Free rooms, bookings) */
    public const DEPARTMENT_ROLES = ['rep', 'lab_staff', 'admin'];

    /** The accounts an admin makes and manages (admins manage their own account themselves) */
    public const MANAGED_ROLES = ['rep', 'lab_staff'];

    /** How long the emailed "connect your email" link works */
    public const EMAIL_LINK_DAYS = 3;

    protected $fillable = [
        'department_id', 'section_id', 'name', 'email', 'password', 'role', 'is_active',
        'photo_path', 'must_change_password',
    ];

    // Never sent to the browser or included in JSON
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',     // a plain password is hashed (bcrypt) automatically when saved
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** A block account: the block it belongs to. Lab staff: their own class section (optional). */
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

    /** The college-wide account that looks after the departments' Admin accounts (it has no department) */
    public function isCollegeAdmin(): bool
    {
        return $this->role === 'college_admin';
    }

    /** Is this a block's account? */
    public function isBlock(): bool
    {
        return $this->role === 'rep';
    }

    /**
     * What the account is called on the pages. A block account goes by its block ("BSCPE 4-5"), because it belongs
     * to the block; lab staff and admins go by their own name.
     */
    public function displayName(): string
    {
        return $this->isBlock() && $this->section ? $this->section->name : (string) $this->name;
    }

    /** A block account's current representative (kept in `name`); null for lab staff and admins */
    public function representativeName(): ?string
    {
        return $this->isBlock() ? (string) $this->name : null;
    }

    /** Where this user lands after logging in */
    public function homeUrl(): string
    {
        return match ($this->role) {
            'rep' => route('rep.home'),
            'lab_staff' => route('staff.home'),
            'admin' => route('admin.home'),
            'college_admin' => route('college.home'),
        };
    }

    /** e.g. 'BSCPE', from their block or section */
    public function program(): ?string
    {
        return $this->section?->program;
    }

    /** Has this account clicked the emailed link that connects its email? */
    public function hasConnectedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /** Shown in a circle when there's no photo */
    public function initials(): string
    {
        return AccountRules::initials($this->name);
    }

    /** The photo's address (only the account itself and its department's staff can open it) */
    public function photoUrl(): ?string
    {
        return $this->photo_path ? route('photos.show', ['user' => $this->id, 'v' => substr(md5($this->photo_path), 0, 8)]) : null;
    }

    /**
     * Has this account made bookings, approved or rejected one, or changed one? Then deleting it
     * would lose that history, so it's turned off instead.
     */
    public function hasHistory(): bool
    {
        return $this->reservations()->exists()
            || Reservation::where('reviewed_by', $this->id)->exists()
            || DB::table('override_logs')->where('changed_by', $this->id)->exists();
    }

    /**
     * The link in the "connect your email" email. It's signed, so it works without logging in
     * (on a phone, say), and it stops working when it expires or the account's email changes.
     */
    public function connectEmailUrl(): string
    {
        return URL::temporarySignedRoute('email.connect', now()->addDays(self::EMAIL_LINK_DAYS), [
            'user' => $this->id,
            'hash' => sha1($this->email),
        ]);
    }

    /** Emails the connect link (with a welcome for new accounts). False if the email couldn't be sent. */
    public function sendConnectEmail(bool $welcome = false): bool
    {
        try {
            $this->notify(new ConnectEmail($welcome));

            return true;
        } catch (Throwable $e) {
            report($e);     // the reason goes to the log (storage/logs/laravel.log)

            return false;
        }
    }

    /** Used by "Forgot password?": our own wording instead of Laravel's */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordLink($token));
    }
}
