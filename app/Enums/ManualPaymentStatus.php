<?php

namespace App\Enums;

enum ManualPaymentStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('admin.payments.statuses.'.$this->value);
    }
}
