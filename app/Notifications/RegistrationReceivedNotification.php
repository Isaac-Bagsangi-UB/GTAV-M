<?php

namespace App\Notifications;

use App\Models\Registration;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class RegistrationReceivedNotification extends Notification
{
    public function __construct(public Registration $registration) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'title' => 'New registration',
            'message' => "{$this->registration->user->name} registered for {$this->registration->event->name}.",
            'registration_id' => $this->registration->id,
            'event_id' => $this->registration->event_id,
        ]);
    }
}
