<?php

namespace App\Services\Supplier\Data;

use App\Enums\BoardBasis;
use App\Enums\RateRefundability;
use App\Services\Supplier\Data\Concerns\SerializesData;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use JsonSerializable;

final readonly class RateData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public string $supplierRoomId,
        public string $roomName,
        public ?string $roomDescription,
        public BoardBasis $boardBasis,
        public RoomOccupancyData $occupancy,
        public string $rateKey,
        public Money $totalAmount,
        public ?Money $netAmount,
        public ?Money $taxAmount,
        public ?Money $feeAmount,
        public RateRefundability $refundability,
        public array $cancellationPolicies,
        public string $paymentType = 'pay_later',
        public ?CarbonImmutable $rateExpiry = null,
        public ?int $remainingRooms = null,
        public array $metadata = [],
    ) {}
}
