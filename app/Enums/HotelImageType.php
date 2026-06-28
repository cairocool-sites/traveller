<?php

namespace App\Enums;

enum HotelImageType: string
{
    case Exterior = 'exterior';
    case Lobby = 'lobby';
    case Room = 'room';
    case Restaurant = 'restaurant';
    case Pool = 'pool';
    case Facility = 'facility';
    case Other = 'other';

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => __('admin.hotels.image_types.'.$case->value)])
            ->all();
    }
}
