<?php

namespace App\Models;

use Database\Factories\CheckInFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    /** @use HasFactory<CheckInFactory> */
    use HasFactory;

    protected $fillable = ['registration_id', 'event_id', 'scanned_by', 'result', 'code_scanned', 'device'];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
