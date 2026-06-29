<?php

namespace App\Services\Supplier\Data;

use App\Services\Supplier\Data\Concerns\SerializesData;
use JsonSerializable;

final readonly class HotelSearchResultData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public string $supplierCode,
        public string $searchId,
        public array $hotels,
        public array $warnings = [],
        public bool $partial = false,
        public array $responseTime = [],
        public ?string $correlationId = null,
    ) {}
}
