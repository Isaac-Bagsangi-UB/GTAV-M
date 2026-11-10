<?php

namespace App\Notifications;

use App\Models\Registration;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class PaymentVerifiedNotification extends Notification
{
    public function __construct(public Registration $registration) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'title' => 'Payment verified',
            'message' => "Your payment for {$this->registration->event->name} has been verified.",
            'registration_id' => $this->registration->id,
        ]);
    }
}
