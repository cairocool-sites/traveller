<?php

namespace App\Enums;

enum CancellationPenaltyType: string
{
    case Amount = 'amount';
    case Nights = 'nights';
    case Percentage = 'percentage';
    case None = 'none';
}
