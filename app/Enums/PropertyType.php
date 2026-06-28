<?php

namespace App\Enums;

enum PropertyType: string
{
    case Hotel = 'hotel';
    case Resort = 'resort';
    case Apartment = 'apartment';
    case Aparthotel = 'aparthotel';
    case Villa = 'villa';
    case Hostel = 'hostel';

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => __('admin.hotels.property_types.'.$case->value)])
            ->all();
    }
}
