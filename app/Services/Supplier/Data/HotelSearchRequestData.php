<?php

namespace App\Services\Supplier\Data;

use App\Services\Supplier\Data\Concerns\SerializesData;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JsonSerializable;

final readonly class HotelSearchRequestData implements JsonSerializable
{
    use SerializesData;

    public function __construct(
        public string $destinationIdentifier,
        public CarbonImmutable $checkIn,
        public CarbonImmutable $checkOut,
        public array $rooms,
        public string $currency = 'EGP',
        public string $locale = 'ar',
        public ?string $nationality = null,
        public ?string $residencyCountry = null,
        public ?int $timeoutSeconds = null,
        public ?string $correlationId = null,
        public array $metadata = [],
    ) {
        self::validateDates($checkIn, $checkOut);
        self::validateRooms($rooms);
        self::validateCurrencyAndLocale($currency, $locale);
    }

    public static function validateDates(CarbonImmutable $checkIn, CarbonImmutable $checkOut): void
    {
        if ($checkIn->isPast() && ! $checkIn->isToday()) {
            throw new InvalidArgumentException('Check-in date cannot be in the past.');
        }

        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            throw new InvalidArgumentException('Check-out must be after check-in.');
        }
    }

    public static function validateRooms(array $rooms): void
    {
        if ($rooms === [] || count($rooms) > 6) {
            throw new InvalidArgumentException('At least one and no more than six rooms are required.');
        }

        foreach ($rooms as $room) {
            if (! $room instanceof RoomOccupancyData) {
                throw new InvalidArgumentException('Rooms must be RoomOccupancyData instances.');
            }
        }
    }

    public static function validateCurrencyAndLocale(string $currency, string $locale): void
    {
        if (! in_array($currency, config('travel.currency.supported', []), true)) {
            throw new InvalidArgumentException('Unsupported currency.');
        }

        if (! in_array($locale, config('travel.locales.supported', []), true)) {
            throw new InvalidArgumentException('Unsupported locale.');
        }
    }
}
