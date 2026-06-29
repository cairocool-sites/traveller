<?php

namespace App\Enums;

enum SupplierStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Degraded = 'degraded';
    case Disabled = 'disabled';

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $case): array => [$case->value => __('admin.suppliers.statuses.'.$case->value)]
        )->all();
    }
}
