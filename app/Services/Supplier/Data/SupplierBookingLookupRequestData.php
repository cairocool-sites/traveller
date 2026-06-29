<?php

namespace App\Services\Supplier\Data;

use App\Services\Supplier\Data\Concerns\SerializesData;
use JsonSerializable;

final readonly class SupplierBookingLookupRequestData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public string $supplierBookingReference,
        public ?string $internalReference = null,
        public ?string $correlationId = null,
        public array $metadata = [],
    ) {}
}
