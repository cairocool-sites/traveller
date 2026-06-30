<?php

namespace App\Services\Supplier\Data;

use App\Enums\BookingSupplierStatus;
use App\Services\Supplier\Data\Concerns\SerializesData;
use JsonSerializable;

final readonly class HbxBookingChangeResultData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public bool $successful,
        public BookingSupplierStatus $status,
        public array $booking,
        public ?string $correlationId = null,
    ) {}
}
