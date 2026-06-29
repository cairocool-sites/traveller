<?php

namespace App\Enums;

enum CancellationStatus: string
{
    case Requested = 'requested';
    case UnderReview = 'under_review';
    case PendingSupplier = 'pending_supplier';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case ManualReview = 'manual_review';
    case Expired = 'expired';
}
