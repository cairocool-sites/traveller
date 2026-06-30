<?php

namespace App\Services\Supplier\Data;

use App\Services\Supplier\Data\Concerns\SerializesData;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JsonSerializable;

final readonly class HbxBookingListRequestData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public int $from,
        public int $to,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public string $filterType = 'CREATION',
        public string $status = 'ALL',
        public ?string $clientReference = null,
        public ?string $creationUser = null,
        public array $countries = [],
        public array $destinations = [],
        public array $hotels = [],
        public ?string $correlationId = null,
    ) {
        if ($from < 1 || $to < $from) {
            throw new InvalidArgumentException('HBX booking list pagination must use a valid from/to range.');
        }

        if ($end->lessThan($start)) {
            throw new InvalidArgumentException('HBX booking list end date must not be before start date.');
        }

        if (! in_array($filterType, ['CHECKIN', 'CREATION'], true)) {
            throw new InvalidArgumentException('Unsupported HBX booking list filter type.');
        }

        if (! in_array($status, ['ALL', 'CONFIRMED', 'CANCELLED'], true)) {
            throw new InvalidArgumentException('Unsupported HBX booking list status.');
        }
    }

    public function toQuery(): array
    {
        return array_filter([
            'from' => $this->from,
            'to' => $this->to,
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
            'filterType' => $this->filterType,
            'status' => $this->status,
            'clientReference' => $this->clientReference,
            'creationUser' => $this->creationUser,
            'country' => $this->countries === [] ? null : implode(',', $this->countries),
            'destination' => $this->destinations === [] ? null : implode(',', $this->destinations),
            'hotel' => $this->hotels === [] ? null : implode(',', array_map('intval', $this->hotels)),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
