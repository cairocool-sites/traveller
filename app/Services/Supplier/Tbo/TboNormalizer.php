<?php

namespace App\Services\Supplier\Tbo;

use App\Enums\BoardBasis;
use App\Enums\RateRefundability;
use App\Services\Supplier\Data\RateData;
use App\Services\Supplier\Data\RoomOccupancyData;
use App\Services\Supplier\Data\SupplierHotelData;
use App\Support\Money\Money;

class TboNormalizer
{
    public function hotels(array $payload, string $currency, array $rooms): array
    {
        $results = data_get($payload, 'HotelSearchResult.HotelResults')
            ?? data_get($payload, 'HotelSearchResult.SearchResults')
            ?? data_get($payload, 'SearchResults')
            ?? data_get($payload, 'HotelResults')
            ?? [];

        if (! is_array($results)) {
            return [];
        }

        return collect($results)
            ->filter(fn ($hotel): bool => is_array($hotel))
            ->map(fn (array $hotel): SupplierHotelData => $this->hotel($hotel, $currency, $rooms))
            ->values()
            ->all();
    }

    public function hotel(array $hotel, string $currency, array $rooms = []): SupplierHotelData
    {
        $hotelCurrency = (string) (data_get($hotel, 'Price.CurrencyCode') ?: data_get($hotel, 'CurrencyCode') ?: $currency);
        $rate = $this->rate($hotel, $hotelCurrency, $rooms[0] ?? new RoomOccupancyData(1));

        return new SupplierHotelData(
            supplierHotelId: (string) (data_get($hotel, 'HotelCode') ?? data_get($hotel, 'Code') ?? data_get($hotel, 'ResultIndex') ?? ''),
            canonicalHotelId: null,
            name: (string) (data_get($hotel, 'HotelName') ?? data_get($hotel, 'Name') ?? 'TBO Hotel'),
            starRating: $this->starRating(data_get($hotel, 'StarRating') ?? data_get($hotel, 'HotelCategory')),
            location: (string) (data_get($hotel, 'HotelAddress') ?? data_get($hotel, 'Address') ?? data_get($hotel, 'Destination') ?? ''),
            coordinates: $this->coordinates($hotel),
            images: array_values(array_filter([(string) (data_get($hotel, 'HotelPicture') ?? data_get($hotel, 'ImageUrl') ?? '')])),
            facilities: [],
            rooms: [$rate],
            minimumTotalPrice: $rate->totalAmount,
            currency: $hotelCurrency,
            taxesAndFees: [],
            metadata: [
                'supplier' => 'tbo_hotels',
                'trace_id' => data_get($hotel, 'TraceId'),
                'result_index' => data_get($hotel, 'ResultIndex'),
                'raw_category' => data_get($hotel, 'HotelCategory'),
            ],
        );
    }

    private function rate(array $hotel, string $currency, RoomOccupancyData $occupancy): RateData
    {
        $price = data_get($hotel, 'Price.OfferedPriceRoundedOff')
            ?? data_get($hotel, 'Price.OfferedPrice')
            ?? data_get($hotel, 'Price.PublishedPrice')
            ?? data_get($hotel, 'MinHotelPrice.TotalPrice')
            ?? '0';

        return new RateData(
            supplierRoomId: (string) (data_get($hotel, 'ResultIndex') ?? data_get($hotel, 'HotelCode') ?? 'tbo-room'),
            roomName: (string) (data_get($hotel, 'RoomTypeName') ?? data_get($hotel, 'RoomName') ?? 'Available room'),
            roomDescription: data_get($hotel, 'HotelDescription'),
            boardBasis: $this->boardBasis((string) (data_get($hotel, 'MealType') ?? data_get($hotel, 'BoardBasis') ?? '')),
            occupancy: $occupancy,
            rateKey: (string) (data_get($hotel, 'ResultIndex') ?? data_get($hotel, 'RateKey') ?? data_get($hotel, 'HotelCode') ?? ''),
            totalAmount: Money::fromDecimalString((string) $price, $currency),
            netAmount: null,
            taxAmount: null,
            feeAmount: null,
            refundability: ((bool) data_get($hotel, 'IsRefundable', false)) ? RateRefundability::Refundable : RateRefundability::Unknown,
            cancellationPolicies: [],
            paymentType: 'pay_later',
            metadata: ['requires_check_rate' => true],
        );
    }

    private function boardBasis(string $value): BoardBasis
    {
        $value = strtolower($value);

        return match (true) {
            str_contains($value, 'breakfast') => BoardBasis::BedAndBreakfast,
            str_contains($value, 'half') => BoardBasis::HalfBoard,
            str_contains($value, 'full') => BoardBasis::FullBoard,
            str_contains($value, 'all') => BoardBasis::AllInclusive,
            default => BoardBasis::RoomOnly,
        };
    }

    private function starRating(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        preg_match('/[1-5]/', (string) $value, $matches);

        return isset($matches[0]) ? (int) $matches[0] : null;
    }

    private function coordinates(array $hotel): ?array
    {
        $lat = data_get($hotel, 'Latitude');
        $lng = data_get($hotel, 'Longitude');

        if ($lat === null || $lng === null) {
            return null;
        }

        return ['latitude' => (string) $lat, 'longitude' => (string) $lng];
    }
}
