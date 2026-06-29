<?php

namespace App\Enums;

enum CancellationSupplierStatus: string
{
    case NotCancelled = 'not_cancelled';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
    case Pending = 'pending';
    case Failed = 'failed';
}
