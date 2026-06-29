<?php

namespace App\Notifications;

use App\Models\BookingCancellation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CancellationStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly BookingCancellation $cancellation, private readonly string $subject) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Cairo Cool Travel - '.$this->subject)
            ->line('Booking reference: '.$this->cancellation->booking->booking_reference)
            ->line('Cancellation status: '.$this->cancellation->status->value);
    }
}
