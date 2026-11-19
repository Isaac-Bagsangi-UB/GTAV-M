<?php

namespace App\Models;

use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory;

    public const CONFIRMED = 'confirmed';

    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id', 'event_id', 'reference_code', 'qr_token', 'status',
        'amount_paid', 'checked_in_at', 'cancelled_at',
    ];

    protected $hidden = ['qr_token'];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'amount_paid' => 'decimal:2',
            'user_id' => 'integer',
            'event_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** Every gate scan of this ticket, including refused repeat scans. */
    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::CONFIRMED;
    }

    /** Attendees may cancel until the event starts, as long as they have not been admitted. */
    public function canCancel(): bool
    {
        return $this->isConfirmed() && ! $this->checked_in_at && $this->event->starts_at->isFuture();
    }

    /** Certificates are issued only to people who were actually scanned in, once the event is over. */
    public function canDownloadCertificate(): bool
    {
        return $this->isConfirmed() && $this->checked_in_at && $this->event->ends_at->isPast();
    }
}
