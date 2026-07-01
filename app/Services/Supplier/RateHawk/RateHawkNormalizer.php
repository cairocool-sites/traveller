<?php

namespace App\Services\Supplier\RateHawk;

use App\Enums\BoardBasis;
use App\Enums\RateRefundability;
use App\Services\Supplier\Data\RateData;
use App\Services\Supplier\Data\RoomOccupancyData;
use App\Services\Supplier\Data\SupplierHotelData;
use App\Support\Money\Money;

class RateHawkNormalizer
{
    public function hotels(array $payload, string $currency, array $rooms): array
    {
        $hotels = data_get($payload, 'data.hotels')
            ?? data_get($payload, 'data')
            ?? data_get($payload, 'hotels')
            ?? [];

        if (! is_array($hotels)) {
            return [];
        }

        return collect($hotels)
            ->filter(fn ($hotel): bool => is_array($hotel))
            ->map(fn (array $hotel): SupplierHotelData => $this->hotel($hotel, $currency, $rooms))
            ->values()
            ->all();
    }

    public function hotel(array $hotel, string $currency, array $rooms = []): SupplierHotelData
    {
        $rates = data_get($hotel, 'rates') ?? data_get($hotel, 'room_groups.0.rates') ?? [];
        $ratePayload = is_array($rates) && isset($rates[0]) && is_array($rates[0]) ? $rates[0] : $hotel;
        $hotelCurrency = strtoupper((string) (data_get($ratePayload, 'payment_options.payment_types.0.currency_code') ?: data_get($ratePayload, 'daily_prices.currency') ?: data_get($ratePayload, 'currency_code') ?: $currency));
        $rate = $this->rate($ratePayload, $hotelCurrency, $rooms[0] ?? new RoomOccupancyData(1));

        return new SupplierHotelData(
            supplierHotelId: (string) (data_get($hotel, 'id') ?? data_get($hotel, 'hid') ?? data_get($hotel, 'hotel_id') ?? ''),
            canonicalHotelId: null,
            name: (string) (data_get($hotel, 'name') ?? data_get($hotel, 'hotel_name') ?? data_get($hotel, 'hotel.name') ?? 'RateHawk Hotel'),
            starRating: $this->starRating(data_get($hotel, 'star_rating') ?? data_get($hotel, 'stars')),
            location: (string) (data_get($hotel, 'address') ?? data_get($hotel, 'region.name') ?? data_get($hotel, 'destination') ?? ''),
            coordinates: $this->coordinates($hotel),
            images: $this->images($hotel),
            facilities: [],
            rooms: [$rate],
            minimumTotalPrice: $rate->totalAmount,
            currency: $hotelCurrency,
            taxesAndFees: [],
            metadata: [
                'supplier' => 'ratehawk_hotels',
                'ratehawk_region_id' => data_get($hotel, 'region.id'),
                'requires_check_rate' => true,
            ],
        );
    }

    public function rate(array $rate, string $currency, RoomOccupancyData $occupancy): RateData
    {
        $amount = data_get($rate, 'payment_options.payment_types.0.amount')
            ?? data_get($rate, 'payment_options.payment_types.0.show_amount')
            ?? data_get($rate, 'daily_prices.0')
            ?? data_get($rate, 'price')
            ?? '0';
        $amount = is_numeric($amount) ? (string) $amount : '0';

        return new RateData(
            supplierRoomId: (string) (data_get($rate, 'room_data_trans.main_room_type') ?? data_get($rate, 'room_name') ?? data_get($rate, 'book_hash') ?? 'ratehawk-room'),
            roomName: (string) (data_get($rate, 'room_name') ?? data_get($rate, 'room_data_trans.main_room_type') ?? 'Available room'),
            roomDescription: data_get($rate, 'room_data_trans.bedding_type'),
            boardBasis: $this->boardBasis((string) (data_get($rate, 'meal') ?? data_get($rate, 'meal_data.value') ?? '')),
            occupancy: $occupancy,
            rateKey: (string) (data_get($rate, 'book_hash') ?? data_get($rate, 'hash') ?? ''),
            totalAmount: Money::fromDecimalString($this->decimal($amount), $currency),
            netAmount: null,
            taxAmount: null,
            feeAmount: null,
            refundability: $this->refundability($rate),
            cancellationPolicies: [],
            paymentType: (string) (data_get($rate, 'payment_options.payment_types.0.type') ?? 'supplier'),
            metadata: [
                'requires_check_rate' => true,
                'deposit_required' => data_get($rate, 'deposit'),
            ],
        );
    }

    private function boardBasis(string $value): BoardBasis
    {
        $value = strtolower($value);

        return match (true) {
            str_contains($value, 'breakfast') || $value === 'breakfast' => BoardBasis::BedAndBreakfast,
            str_contains($value, 'half') => BoardBasis::HalfBoard,
            str_contains($value, 'full') => BoardBasis::FullBoard,
            str_contains($value, 'all') => BoardBasis::AllInclusive,
            default => BoardBasis::RoomOnly,
        };
    }

    private function refundability(array $rate): RateRefundability
    {
        $policies = data_get($rate, 'payment_options.payment_types.0.cancellation_penalties.policies');

        if ($policies === [] || $policies === null) {
            return RateRefundability::Unknown;
        }

        $freeCancellationBefore = data_get($rate, 'payment_options.payment_types.0.cancellation_penalties.free_cancellation_before');

        return $freeCancellationBefore ? RateRefundability::Refundable : RateRefundability::PartiallyRefundable;
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
        $lat = data_get($hotel, 'latitude') ?? data_get($hotel, 'location.latitude');
        $lng = data_get($hotel, 'longitude') ?? data_get($hotel, 'location.longitude');

        if ($lat === null || $lng === null) {
            return null;
        }

        return ['latitude' => (string) $lat, 'longitude' => (string) $lng];
    }

    private function images(array $hotel): array
    {
        $images = data_get($hotel, 'images') ?? data_get($hotel, 'images_ext') ?? [];

        if (! is_array($images)) {
            return [];
        }

        return collect($images)
            ->map(fn ($image): string => is_array($image) ? (string) ($image['url'] ?? $image['path'] ?? '') : (string) $image)
            ->filter()
            ->values()
            ->all();
    }

    private function decimal(string $value): string
    {
        if (! preg_match('/^\d+(\.\d+)?$/', $value)) {
            return '0.00';
        }

        return $value;
    }
}
