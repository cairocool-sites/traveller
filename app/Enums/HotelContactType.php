<?php

namespace App\Enums;

enum HotelContactType: string
{
    case General = 'general';
    case Reservation = 'reservation';
    case Sales = 'sales';
    case Finance = 'finance';
    case Operations = 'operations';
    case Emergency = 'emergency';

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => __('admin.hotels.contact_types.'.$case->value)])
            ->all();
    }
}
