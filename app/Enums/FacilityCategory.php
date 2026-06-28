<?php

namespace App\Enums;

enum FacilityCategory: string
{
    case General = 'general';
    case Room = 'room';
    case Food = 'food';
    case Wellness = 'wellness';
    case Business = 'business';
    case Accessibility = 'accessibility';
    case Transport = 'transport';
    case Family = 'family';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => __('admin.facilities.categories.'.$case->value)])
            ->all();
    }
}
