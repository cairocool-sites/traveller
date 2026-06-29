<?php

namespace App\Notifications;

use App\Models\Refund;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Refund $refund, private readonly string $subject) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Cairo Cool Travel - '.$this->subject)
            ->line('Booking reference: '.$this->refund->booking->booking_reference)
            ->line('Refund status: '.$this->refund->status->value);
    }
}
