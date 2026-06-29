<?php

namespace App\Services\Supplier\Data;

use App\Enums\CancellationPenaltyType;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JsonSerializable;

final readonly class CancellationPolicyData implements JsonSerializable
{
    public function __construct(
        public ?CarbonImmutable $validFrom,
        public ?CarbonImmutable $validUntil,
        public CancellationPenaltyType $penaltyType,
        public ?Money $penaltyAmount = null,
        public ?int $penaltyNights = null,
        public ?string $percentage = null,
        public bool $isNoShow = false,
        public bool $isNonRefundable = false,
        public ?string $description = null,
    ) {
        if ($validFrom && $validUntil && $validUntil->lessThanOrEqualTo($validFrom)) {
            throw new InvalidArgumentException('Cancellation window end must follow its start.');
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'valid_from' => $this->validFrom?->toIso8601String(),
            'valid_until' => $this->validUntil?->toIso8601String(),
            'penalty_type' => $this->penaltyType->value,
            'penalty_amount' => $this->penaltyAmount,
            'penalty_currency' => $this->penaltyAmount?->currency,
            'penalty_nights' => $this->penaltyNights,
            'percentage' => $this->percentage,
            'is_no_show' => $this->isNoShow,
            'is_non_refundable' => $this->isNonRefundable,
            'description' => $this->description,
        ];
    }
}
