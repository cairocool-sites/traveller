<?php

namespace App\Services\Supplier\Data;

use App\Enums\BookingSupplierStatus;
use App\Services\Supplier\Data\Concerns\SerializesData;
use App\Support\Money\Money;
use JsonSerializable;

final readonly class SupplierBookingResultData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public bool $successful,
        public BookingSupplierStatus $status,
        public ?string $supplierBookingReference,
        public ?string $supplierConfirmationReference,
        public string $supplierHotelId,
        public array $rooms,
        public array $guests,
        public ?Money $confirmedTotal,
        public string $currency,
        public array $cancellationPoliciesSnapshot,
        public array $warnings = [],
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
        public bool $requiresManualReview = false,
        public array $supplierRawReferenceMetadata = [],
        public ?string $correlationId = null,
    ) {}
}
