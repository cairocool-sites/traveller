<?php

namespace App\Services\Supplier\Data;

use App\Services\Supplier\Data\Concerns\SerializesData;
use JsonSerializable;

final readonly class HotelDetailsResultData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public string $supplierCode,
        public SupplierHotelData $hotel,
        public array $warnings = [],
        public ?string $correlationId = null,
    ) {}
}
