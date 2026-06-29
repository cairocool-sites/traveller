<?php

namespace App\Enums;

enum RateRefundability: string
{
    case Refundable = 'refundable';
    case NonRefundable = 'non_refundable';
    case PartiallyRefundable = 'partially_refundable';
    case Unknown = 'unknown';
}
