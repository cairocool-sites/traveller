<?php

namespace App\Services\Supplier\Data;

use App\Services\Supplier\Data\Concerns\SerializesData;
use JsonSerializable;

final readonly class HotelDetailsRequestData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public string $supplierHotelId,
        public ?string $destinationIdentifier = null,
        public string $locale = 'ar',
        public string $currency = 'EGP',
        public ?string $correlationId = null,
        public array $metadata = [],
    ) {
        HotelSearchRequestData::validateCurrencyAndLocale($currency, $locale);
    }
}
