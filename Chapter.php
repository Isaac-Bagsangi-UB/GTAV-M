<?php

namespace App\Models;

use Database\Factories\ChapterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Chapter extends Model
{
    /** @use HasFactory<ChapterFactory> */
    use HasFactory;

    protected $fillable = ['name', 'code', 'region'];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Chapter managers: they organize every event the chapter hosts. */
    public function managers(): HasMany
    {
        return $this->hasMany(User::class)->where('role', User::ROLE_MANAGER)->orderBy('name');
    }

    /** Venue gate staff who work the chapter's events. */
    public function gateStaff(): HasMany
    {
        return $this->hasMany(User::class)->where('role', User::ROLE_GATE)->orderBy('name');
    }

    /** Every registration for every event (and sub-event) in this chapter. */
    public function registrations(): HasManyThrough
    {
        return $this->hasManyThrough(Registration::class, Event::class);
    }
}
