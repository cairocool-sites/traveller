<?php

namespace App\Services\Supplier\Data;

use App\Services\Supplier\Data\Concerns\SerializesData;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JsonSerializable;

final readonly class HbxBookingReconfirmationRequestData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public int $from,
        public int $to,
        public ?CarbonImmutable $start = null,
        public ?CarbonImmutable $end = null,
        public ?string $filterType = null,
        public array $clientReferences = [],
        public array $bookingIds = [],
        public ?string $correlationId = null,
    ) {
        if ($from < 1 || $to < $from) {
            throw new InvalidArgumentException('HBX reconfirmation pagination must use a valid from/to range.');
        }

        if (($start || $end) && (! $start || ! $end || ! $filterType)) {
            throw new InvalidArgumentException('HBX reconfirmation date filters require start, end, and filterType.');
        }

        if ($start && $end && $end->lessThan($start)) {
            throw new InvalidArgumentException('HBX reconfirmation end date must not be before start date.');
        }
    }

    public function toQuery(): array
    {
        return array_filter([
            'from' => $this->from,
            'to' => $this->to,
            'start' => $this->start?->toDateString(),
            'end' => $this->end?->toDateString(),
            'filterType' => $this->filterType,
            'clientReferences' => $this->clientReferences === [] ? null : implode(',', $this->clientReferences),
            'references' => $this->bookingIds === [] ? null : implode(',', $this->bookingIds),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
