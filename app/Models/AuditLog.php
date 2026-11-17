<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Throwable;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'description', 'ip_address'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Write one audit entry. Never throws: a logging problem must not break the user's action.
     *
     * @param  string  $action  dotted name, e.g. "auth.login", "event.updated", "report.generated"
     */
    public static function record(string $action, string $description, ?User $user = null): void
    {
        try {
            static::create([
                'user_id' => $user?->id ?? auth()->id(),
                'action' => $action,
                'description' => Str::limit($description, 480),
                'ip_address' => request()?->ip(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** First part of the action, used for the colored label: "auth", "event", "report"… */
    public function group(): string
    {
        return Str::before($this->action, '.');
    }
}
