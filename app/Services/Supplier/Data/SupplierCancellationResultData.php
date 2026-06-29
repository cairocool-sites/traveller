<?php

namespace App\Services\Supplier\Data;

use App\Enums\CancellationSupplierStatus;
use App\Services\Supplier\Data\Concerns\SerializesData;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use JsonSerializable;

final readonly class SupplierCancellationResultData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public bool $successful,
        public CancellationSupplierStatus $status,
        public ?string $cancellationReference,
        public ?Money $penaltyAmount,
        public ?Money $refundableAmount,
        public string $currency,
        public ?CarbonImmutable $cancelledAt = null,
        public array $warnings = [],
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
        public bool $requiresManualReview = false,
        public ?string $correlationId = null,
    ) {}
}
