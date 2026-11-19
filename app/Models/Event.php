<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    public const TYPES = [
        'expo' => 'Regional Expo',
        'summit' => 'Technical Summit',
        'workshop' => 'Training Workshop',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'published' => 'Published',
        'cancelled' => 'Cancelled',
    ];

    public const STATE_LABELS = [
        'open' => 'Registration open',
        'not_open' => 'Opens soon',
        'closed' => 'Registration closed',
        'full' => 'Sold out',
        'ended' => 'Event ended',
        'unavailable' => 'Not available',
    ];

    protected $fillable = [
        'chapter_id', 'parent_id', 'title', 'type', 'description', 'venue', 'city',
        'starts_at', 'ends_at', 'registration_opens_at', 'registration_closes_at',
        'capacity', 'ticket_price', 'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'capacity' => 'integer',
            'ticket_price' => 'decimal:2',
            'chapter_id' => 'integer',
            'parent_id' => 'integer',
        ];
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('starts_at');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function confirmedRegistrations(): HasMany
    {
        return $this->hasMany(Registration::class)->where('registrations.status', Registration::CONFIRMED);
    }

    /** People holding a ticket for this event, joined through the registrations pivot table. */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'registrations')
            ->withPivot('reference_code', 'status', 'checked_in_at')
            ->withTimestamps();
    }

    public function speakers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_speaker')->withPivot('topic')->withTimestamps();
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class)->latest();
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('events.status', 'published');
    }

    /** Uses the eager-loaded count when present, otherwise asks the database. */
    public function confirmedCount(): int
    {
        if (array_key_exists('confirmed_registrations_count', $this->attributes)) {
            return (int) $this->attributes['confirmed_registrations_count'];
        }

        return $this->confirmedRegistrations()->count();
    }

    public function seatsLeft(): int
    {
        return max(0, $this->capacity - $this->confirmedCount());
    }

    public function fillPercent(): int
    {
        return $this->capacity > 0 ? (int) min(100, round($this->confirmedCount() / $this->capacity * 100)) : 0;
    }

    /**
     * Capacity & attendance logic in one place.
     * Returns: open, not_open, closed, full, ended or unavailable.
     */
    public function registrationState(): string
    {
        if ($this->status !== 'published') {
            return 'unavailable';
        }
        if ($this->ends_at->isPast()) {
            return 'ended';
        }
        if (now()->lt($this->registration_opens_at)) {
            return 'not_open';
        }
        if (now()->gt($this->registration_closes_at)) {
            return 'closed';
        }
        if ($this->seatsLeft() <= 0) {
            return 'full';
        }

        return 'open';
    }

    public function stateLabel(): string
    {
        return self::STATE_LABELS[$this->registrationState()];
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function priceLabel(): string
    {
        return (float) $this->ticket_price > 0 ? '$'.number_format((float) $this->ticket_price, 2) : 'Free';
    }

    /** Address of the uploaded venue photo, or null when the event uses the drawn cover. */
    public function coverUrl(): ?string
    {
        return $this->cover_path
            ? route('events.cover', ['event' => $this, 'v' => $this->updated_at?->timestamp])
            : null;
    }

    /** Short place name for the cover: the city, or the venue when no city is set. */
    public function placeName(): string
    {
        return $this->city ?: $this->venue;
    }

    public function dateRange(): string
    {
        if ($this->starts_at->isSameDay($this->ends_at)) {
            return $this->starts_at->format('D, M j, Y').' · '.$this->starts_at->format('g:i A').' – '.$this->ends_at->format('g:i A');
        }

        return $this->starts_at->format('M j').' – '.$this->ends_at->format('M j, Y');
    }
}
