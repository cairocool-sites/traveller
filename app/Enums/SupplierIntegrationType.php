<?php

namespace App\Enums;

enum SupplierIntegrationType: string
{
    case Mock = 'mock';
    case Rest = 'rest';
    case Json = 'json';
    case Xml = 'xml';
    case Soap = 'soap';
    case OtaXml = 'ota_xml';

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $case): array => [$case->value => __('admin.suppliers.integration_types.'.$case->value)]
        )->all();
    }
}
