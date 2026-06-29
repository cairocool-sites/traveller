<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Paid = 'paid';

    public function label(): string
    {
        return __('admin.bookings.payment_statuses.'.$this->value);
    }
}
