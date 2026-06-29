<?php

namespace App\Services\Supplier\Data;

use App\Services\Supplier\Data\Concerns\SerializesData;
use App\Support\Money\Money;
use JsonSerializable;

final readonly class SupplierHotelData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public string $supplierHotelId,
        public ?int $canonicalHotelId,
        public string $name,
        public ?int $starRating,
        public string $location,
        public ?array $coordinates,
        public array $images,
        public array $facilities,
        public array $rooms,
        public ?Money $minimumTotalPrice,
        public string $currency,
        public array $taxesAndFees = [],
        public array $metadata = [],
    ) {}
}
