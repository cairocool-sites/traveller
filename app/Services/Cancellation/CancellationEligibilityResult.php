<?php

namespace App\Services\Cancellation;

final readonly class CancellationEligibilityResult
{
    public function __construct(
        public bool $eligible,
        public bool $manualReview,
        public bool $nonRefundable,
        public int $penaltyMinor,
        public int $refundableMinor,
        public string $reason,
    ) {}
}
