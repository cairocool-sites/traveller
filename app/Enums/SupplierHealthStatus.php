<?php

namespace App\Enums;

enum SupplierHealthStatus: string
{
    case Unknown = 'unknown';
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Unavailable = 'unavailable';

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $case): array => [$case->value => __('admin.suppliers.health_statuses.'.$case->value)]
        )->all();
    }
}
