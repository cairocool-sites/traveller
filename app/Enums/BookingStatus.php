<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Draft = 'draft';
    case PendingRateCheck = 'pending_rate_check';
    case RateConfirmed = 'rate_confirmed';
    case GuestDetailsCompleted = 'guest_details_completed';
    case PendingSupplierConfirmation = 'pending_supplier_confirmation';
    case Confirmed = 'confirmed';
    case SupplierFailed = 'supplier_failed';
    case ManualReview = 'manual_review';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('admin.bookings.statuses.'.$this->value);
    }
}
