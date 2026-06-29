<?php

namespace App\Services\Supplier\Data;

use App\Enums\BookingSupplierStatus;
use App\Enums\CancellationSupplierStatus;
use App\Services\Supplier\Data\Concerns\SerializesData;
use JsonSerializable;

final readonly class SupplierBookingDetailsData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public bool $found,
        public string $supplierBookingReference,
        public BookingSupplierStatus $status,
        public ?SupplierHotelData $hotel,
        public array $rooms,
        public array $guests,
        public array $totals,
        public CancellationSupplierStatus $cancellationStatus,
        public array $supplierTimestamps = [],
        public array $warnings = [],
        public ?string $correlationId = null,
    ) {}
}
