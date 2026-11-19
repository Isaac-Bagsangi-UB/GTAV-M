<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MANAGER = 'chapter_manager';

    public const ROLE_ATTENDEE = 'attendee';

    public const ROLE_SPEAKER = 'speaker';

    public const ROLE_GATE = 'gate_staff';

    public const ROLES = [
        self::ROLE_ADMIN => 'Global Event Administrator',
        self::ROLE_MANAGER => 'Regional Chapter Manager',
        self::ROLE_ATTENDEE => 'Corporate Attendee',
        self::ROLE_SPEAKER => 'Guest Speaker',
        self::ROLE_GATE => 'Venue Gate Staff',
    ];

    /** Roles that must belong to a chapter. */
    public const CHAPTER_ROLES = [self::ROLE_MANAGER, self::ROLE_GATE];

    protected $fillable = [
        'name', 'email', 'password', 'role', 'company', 'job_title', 'phone', 'chapter_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'chapter_id' => 'integer',
        ];
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Event participation matrix: every event this user holds a ticket for,
     * joined through the registrations pivot table.
     */
    public function registeredEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'registrations')
            ->withPivot('reference_code', 'status', 'checked_in_at')
            ->withTimestamps();
    }

    /** Events this user speaks at. */
    public function sessions(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_speaker')->withPivot('topic')->withTimestamps();
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst($this->role);
    }

    /** Name of the route each role lands on after logging in. */
    public function homeRoute(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN, self::ROLE_MANAGER => 'manage.dashboard',
            self::ROLE_SPEAKER => 'speaker.sessions',
            self::ROLE_GATE => 'gate.scanner',
            default => 'registrations.index',
        };
    }
}
