<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = ['chapter_id', 'created_by', 'name', 'type', 'description', 'venue', 'starts_at', 'ends_at', 'registration_cutoff', 'capacity', 'ticket_price'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'registration_cutoff' => 'datetime', 'ticket_price' => 'decimal:2'];
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_staff')->withPivot('assigned_by')->withTimestamps();
    }

    public function seatsRemaining(): int
    {
        return max(0, $this->capacity - $this->registrations()->whereIn('payment_status', ['pending', 'paid'])->count());
    }
}
