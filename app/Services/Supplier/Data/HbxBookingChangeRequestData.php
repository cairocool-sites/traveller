<?php

namespace App\Services\Supplier\Data;

use App\Services\Supplier\Data\Concerns\SerializesData;
use InvalidArgumentException;
use JsonSerializable;

final readonly class HbxBookingChangeRequestData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public string $supplierBookingReference,
        public string $mode,
        public array $booking,
        public ?string $correlationId = null,
    ) {
        if (trim($supplierBookingReference) === '') {
            throw new InvalidArgumentException('HBX booking reference is required for booking change.');
        }

        if (! in_array($mode, ['SIMULATION', 'UPDATE'], true)) {
            throw new InvalidArgumentException('HBX booking change mode must be SIMULATION or UPDATE.');
        }

        if ($booking === []) {
            throw new InvalidArgumentException('HBX booking change payload cannot be empty.');
        }
    }

    public function toPayload(): array
    {
        return [
            'mode' => $this->mode,
            'booking' => $this->booking,
        ];
    }
}
