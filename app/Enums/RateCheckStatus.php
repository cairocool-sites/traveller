<?php

namespace App\Enums;

enum RateCheckStatus: string
{
    case Available = 'available';
    case PriceChanged = 'price_changed';
    case RateExpired = 'rate_expired';
    case SoldOut = 'sold_out';
    case Failed = 'failed';

    public function allowsBooking(): bool
    {
        return in_array($this, [self::Available, self::PriceChanged], true);
    }
}
