<?php

namespace App\Services\Supplier\Data;

use App\Services\Supplier\Data\Concerns\SerializesData;
use App\Support\Money\Money;
use InvalidArgumentException;
use JsonSerializable;

final readonly class SupplierBookingRequestData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public string $idempotencyKey,
        public string $supplierRateKey,
        public string $supplierHotelId,
        public array $rooms,
        public GuestData $leadGuest,
        public array $guests,
        public array $customerContactData,
        public Money $expectedTotal,
        public ?string $specialRequests = null,
        public ?string $correlationId = null,
        public array $metadata = [],
    ) {
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('Booking idempotency key is required.');
        }

        if (! $leadGuest->isLead) {
            throw new InvalidArgumentException('Lead guest must be marked as lead.');
        }
    }
}
