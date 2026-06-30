<?php

namespace App\Services\Supplier\Data;

use App\Services\Supplier\Data\Concerns\SerializesData;
use JsonSerializable;

final readonly class HbxBookingReconfirmationResultData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public array $reconfirmations,
        public array $auditData = [],
        public ?string $correlationId = null,
    ) {}
}
