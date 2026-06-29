<?php

namespace App\Services\Supplier\Data;

use App\Services\Supplier\Data\Concerns\SerializesData;
use JsonSerializable;

final readonly class CheckRateRequestData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public string $supplierHotelId,
        public string $supplierRateKey,
        public array $selectedRooms,
        public array $occupancy,
        public string $currency = 'EGP',
        public ?string $correlationId = null,
        public array $metadata = [],
    ) {
        HotelSearchRequestData::validateRooms($occupancy);
        HotelSearchRequestData::validateCurrencyAndLocale($currency, config('app.locale'));
    }
}
