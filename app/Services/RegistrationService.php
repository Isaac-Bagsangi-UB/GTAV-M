<?php

namespace App\Services;

use App\Exceptions\RegistrationException;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single place where someone gets registered for an event.
 * Used by both the website and the REST API so the rules never drift apart.
 */
class RegistrationService
{
    public function register(User $user, Event $event): Registration
    {
        $registration = DB::transaction(function () use ($user, $event) {
            // Lock the event row so two people cannot take the last seat at the same moment.
            $event = Event::whereKey($event->getKey())->lockForUpdate()->firstOrFail();

            $existing = Registration::where('user_id', $user->id)->where('event_id', $event->id)->first();
            if ($existing && $existing->isConfirmed()) {
                throw new RegistrationException('You are already registered for this event.');
            }

            $message = match ($event->registrationState()) {
                'open' => null,
                'not_open' => 'Registration opens on '.$event->registration_opens_at->format('M j, Y g:i A').'.',
                'closed' => 'Registration closed on '.$event->registration_closes_at->format('M j, Y g:i A').'.',
                'full' => 'This event has reached its maximum capacity of '.number_format($event->capacity).'.',
                'ended' => 'This event has already ended.',
                default => 'This event is not open for registration.',
            };
            if ($message) {
                throw new RegistrationException($message);
            }

            $fields = [
                'status' => Registration::CONFIRMED,
                'amount_paid' => $event->ticket_price,
                'qr_token' => self::newToken(),
                'checked_in_at' => null,
                'cancelled_at' => null,
            ];

            // A person who cancelled earlier gets the same record re-activated with a fresh QR code.
            if ($existing) {
                $existing->update($fields);

                return $existing;
            }

            return Registration::create($fields + [
                'user_id' => $user->id,
                'event_id' => $event->id,
                'reference_code' => self::newReference(),
            ]);
        });

        AuditLog::record('registration.created', $user->email.' registered for "'.$event->title.'" ('.$registration->reference_code.')', $user);

        return $registration;
    }

    public function cancel(Registration $registration): void
    {
        if (! $registration->canCancel()) {
            throw new RegistrationException('This registration can no longer be cancelled.');
        }

        $registration->update(['status' => Registration::CANCELLED, 'cancelled_at' => now()]);

        AuditLog::record('registration.cancelled', 'Registration '.$registration->reference_code.' for "'.$registration->event->title.'" cancelled');
    }

    public static function newReference(): string
    {
        do {
            $code = 'EX-'.strtoupper(Str::random(8));
        } while (Registration::where('reference_code', $code)->exists());

        return $code;
    }

    public static function newToken(): string
    {
        return Str::random(48);
    }
}
