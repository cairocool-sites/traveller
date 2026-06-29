<?php

namespace App\Enums;

enum SupplierErrorType: string
{
    case Timeout = 'timeout';
    case Connection = 'connection';
    case InvalidResponse = 'invalid_response';
    case Authentication = 'authentication';
    case RateLimit = 'rate_limit';
    case Unavailable = 'unavailable';
    case PriceChanged = 'price_changed';
    case SoldOut = 'sold_out';
    case DuplicateRequest = 'duplicate_request';
    case UncertainBooking = 'uncertain_booking';
    case Normalization = 'normalization';
    case Validation = 'validation';
}
