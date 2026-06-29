<?php

namespace App\Enums;

enum SupplierEnvironment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $case): array => [$case->value => __('admin.suppliers.environments.'.$case->value)]
        )->all();
    }
}
