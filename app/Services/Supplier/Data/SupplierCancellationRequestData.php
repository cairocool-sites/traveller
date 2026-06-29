<?php

namespace App\Services\Supplier\Data;

use App\Services\Supplier\Data\Concerns\SerializesData;
use InvalidArgumentException;
use JsonSerializable;

final readonly class SupplierCancellationRequestData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public string $supplierBookingReference,
        public string $idempotencyKey,
        public ?string $cancellationReason = null,
        public ?string $correlationId = null,
        public array $metadata = [],
    ) {
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('Cancellation idempotency key is required.');
        }
    }
}
