<?php

namespace App\Services\Supplier\Data;

use App\Services\Supplier\Data\Concerns\SerializesData;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use JsonSerializable;

final readonly class CheckRateResultData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public bool $available,
        public bool $priceChanged,
        public ?Money $previousTotal,
        public ?Money $confirmedTotal,
        public string $currency,
        public ?string $confirmedRateKey,
        public ?CarbonImmutable $rateExpiry,
        public array $cancellationPolicies,
        public array $warnings = [],
        public ?string $failureReason = null,
        public ?string $correlationId = null,
    ) {}
}
