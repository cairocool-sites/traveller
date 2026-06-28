<?php

namespace App\Enums;

enum HotelStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => __('admin.hotels.statuses.'.$case->value)])
            ->all();
    }
}
