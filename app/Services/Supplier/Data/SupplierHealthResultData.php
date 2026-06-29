<?php

namespace App\Services\Supplier\Data;

use App\Enums\SupplierHealthStatus;
use App\Services\Supplier\Data\Concerns\SerializesData;
use Carbon\CarbonImmutable;
use JsonSerializable;

final readonly class SupplierHealthResultData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public bool $healthy,
        public SupplierHealthStatus $status,
        public int $responseTimeMs,
        public CarbonImmutable $checkedAt,
        public ?string $message = null,
        public ?string $correlationId = null,
    ) {}
}
