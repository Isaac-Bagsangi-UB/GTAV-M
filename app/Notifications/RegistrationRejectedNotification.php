<?php

namespace App\Notifications;

use App\Models\Registration;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class RegistrationRejectedNotification extends Notification
{
    public function __construct(public Registration $registration) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'title' => 'Registration not approved',
            'message' => "Your registration for {$this->registration->event->name} was not approved.",
            'registration_id' => $this->registration->id,
            'event_id' => $this->registration->event_id,
            'rejection_reason' => $this->registration->rejection_reason,
        ]);
    }
}
