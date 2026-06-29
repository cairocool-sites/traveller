<?php

namespace App\Enums;

enum BookingSupplierStatus: string
{
    case Confirmed = 'confirmed';
    case Pending = 'pending';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Uncertain = 'uncertain';
    case Cancelled = 'cancelled';
}
