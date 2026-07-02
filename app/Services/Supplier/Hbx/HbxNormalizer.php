<?php

namespace App\Services\Supplier\Hbx;

use App\Enums\BoardBasis;
use App\Enums\BookingSupplierStatus;
use App\Enums\CancellationPenaltyType;
use App\Enums\CancellationSupplierStatus;
use App\Enums\RateRefundability;
use App\Services\Supplier\Data\CancellationPolicyData;
use App\Services\Supplier\Data\RateData;
use App\Services\Supplier\Data\RoomOccupancyData;
use App\Services\Supplier\Data\SupplierHotelData;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class HbxNormalizer
{
    /**
     * @return array<int, SupplierHotelData>
     */
    public function hotels(array $payload, string $currency, array $occupancy): array
    {
        $hotels = Arr::get($payload, 'hotels.hotels', Arr::get($payload, 'hotels', []));

        if (! is_array($hotels)) {
            return [];
        }

        return collect($hotels)
            ->filter(fn ($hotel): bool => is_array($hotel))
            ->map(fn (array $hotel): SupplierHotelData => $this->hotel($hotel, $currency, $occupancy))
            ->values()
            ->all();
    }

    public function hotel(array $hotel, string $currency, array $occupancy = []): SupplierHotelData
    {
        $rooms = collect($hotel['rooms'] ?? [])
            ->flatMap(fn (array $room): array => $this->rates($room, $currency, $occupancy))
            ->values()
            ->all();
        $minimum = collect($rooms)->min(fn (RateData $rate): int => $rate->totalAmount->minorAmount);

        return new SupplierHotelData(
            supplierHotelId: (string) ($hotel['code'] ?? $hotel['hotelCode'] ?? 'hbx-unknown'),
            canonicalHotelId: null,
            name: (string) ($hotel['name']['content'] ?? $hotel['name'] ?? 'HBX Sandbox Hotel'),
            starRating: $this->stars($hotel['categoryCode'] ?? $hotel['categoryName'] ?? null),
            location: (string) ($hotel['destinationName'] ?? $hotel['destinationCode'] ?? $hotel['zoneName'] ?? 'HBX destination'),
            coordinates: $this->coordinates($hotel),
            images: [],
            facilities: array_values(array_filter([$hotel['categoryCode'] ?? null, $hotel['accommodationTypeCode'] ?? null])),
            rooms: $rooms,
            minimumTotalPrice: is_int($minimum) ? new Money($minimum, $currency) : null,
            currency: $currency,
            taxesAndFees: [],
            metadata: ['supplier' => 'hbx_hotels'],
        );
    }

    public function firstRate(array $payload, string $currency, array $occupancy = []): ?RateData
    {
        $hotel = Arr::get($payload, 'hotel', Arr::get($payload, 'hotels.hotels.0'));

        if (! is_array($hotel)) {
            return null;
        }

        return collect($hotel['rooms'] ?? [])
            ->flatMap(fn (array $room): array => $this->rates($room, $currency, $occupancy))
            ->first();
    }

    /**
     * @return array<int, RateData>
     */
    private function rates(array $room, string $currency, array $occupancy): array
    {
        return collect($room['rates'] ?? [])
            ->filter(fn ($rate): bool => is_array($rate))
            ->map(fn (array $rate): RateData => $this->rate($room, $rate, $currency, $occupancy))
            ->values()
            ->all();
    }

    public function rate(array $room, array $rate, string $currency, array $occupancy): RateData
    {
        $total = $this->money($rate['sellingRate'] ?? $rate['net'] ?? $rate['amount'] ?? '0', $rate['currency'] ?? $currency);
        $tax = $this->taxAmount($rate, $total->currency);
        $nonRefundable = $this->isNonRefundable($rate);
        $rateType = strtoupper((string) ($rate['rateType'] ?? 'BOOKABLE'));
        $rateComments = $this->rateComments($rate);

        return new RateData(
            supplierRoomId: (string) ($room['code'] ?? $room['roomCode'] ?? 'hbx-room'),
            roomName: (string) ($room['name'] ?? $room['description'] ?? 'HBX Room'),
            roomDescription: $room['description'] ?? null,
            boardBasis: $this->board($rate['boardCode'] ?? $rate['boardName'] ?? null),
            occupancy: $occupancy[0] ?? new RoomOccupancyData(2),
            rateKey: (string) ($rate['rateKey'] ?? ''),
            totalAmount: $total,
            netAmount: isset($rate['net']) ? $this->money($rate['net'], $total->currency) : null,
            taxAmount: $tax,
            feeAmount: null,
            refundability: $nonRefundable ? RateRefundability::NonRefundable : RateRefundability::Refundable,
            cancellationPolicies: $this->policies($rate, $total->currency, $nonRefundable),
            paymentType: (string) ($rate['paymentType'] ?? 'pay_later'),
            rateExpiry: $rateType === 'RECHECK' ? now()->toImmutable()->addMinutes(15) : now()->toImmutable()->addMinutes(30),
            remainingRooms: isset($rate['allotment']) ? (int) $rate['allotment'] : null,
            metadata: [
                'requires_check_rate' => $rateType === 'RECHECK',
                'rate_type' => $rateType,
                'hbx_rate_class' => $rate['rateClass'] ?? null,
                'rate_comments' => $rateComments,
                'rate_comments_id' => $rate['rateCommentsId'] ?? $rate['rateCommentsID'] ?? null,
            ],
        );
    }

    public function bookingStatus(?string $status): BookingSupplierStatus
    {
        return match (strtoupper((string) $status)) {
            'CONFIRMED' => BookingSupplierStatus::Confirmed,
            'CANCELLED' => BookingSupplierStatus::Cancelled,
            'REJECTED' => BookingSupplierStatus::Rejected,
            default => BookingSupplierStatus::Pending,
        };
    }

    public function cancellationStatus(?string $status): CancellationSupplierStatus
    {
        return match (strtoupper((string) $status)) {
            'CANCELLED' => CancellationSupplierStatus::Cancelled,
            'REJECTED' => CancellationSupplierStatus::Rejected,
            default => CancellationSupplierStatus::Pending,
        };
    }

    public function money(mixed $amount, string $currency): Money
    {
        $value = is_numeric($amount) ? (string) $amount : '0.00';

        if (! str_contains($value, '.')) {
            $value .= '.00';
        }

        return Money::fromDecimalString($value, strtoupper($currency));
    }

    /**
     * @return array<int, CancellationPolicyData>
     */
    public function policies(array $rate, string $currency, bool $nonRefundable = false): array
    {
        if ($nonRefundable) {
            return [new CancellationPolicyData(null, null, CancellationPenaltyType::Amount, $this->money($rate['net'] ?? $rate['sellingRate'] ?? '0', $currency), isNonRefundable: true, description: 'HBX non-refundable rate.')];
        }

        $policies = collect($rate['cancellationPolicies'] ?? [])->map(function (array $policy) use ($currency): CancellationPolicyData {
            return new CancellationPolicyData(
                validFrom: isset($policy['from']) ? CarbonImmutable::parse($policy['from']) : null,
                validUntil: null,
                penaltyType: CancellationPenaltyType::Amount,
                penaltyAmount: $this->money($policy['amount'] ?? '0', $policy['currency'] ?? $currency),
                description: 'HBX cancellation penalty window.',
            );
        })->values()->all();

        return $policies ?: [new CancellationPolicyData(null, null, CancellationPenaltyType::None, $this->money('0', $currency), description: 'HBX returned no cancellation penalties.')];
    }

    private function board(mixed $board): BoardBasis
    {
        return match (strtoupper((string) $board)) {
            'BB', 'BED AND BREAKFAST' => BoardBasis::BedAndBreakfast,
            'HB', 'HALF BOARD' => BoardBasis::HalfBoard,
            'FB', 'FULL BOARD' => BoardBasis::FullBoard,
            'AI', 'ALL INCLUSIVE' => BoardBasis::AllInclusive,
            default => BoardBasis::RoomOnly,
        };
    }

    private function isNonRefundable(array $rate): bool
    {
        return str_contains(strtoupper((string) ($rate['rateClass'] ?? $rate['rateType'] ?? '')), 'NRF')
            || str_contains(strtoupper((string) ($rate['rateClass'] ?? '')), 'NON');
    }

    private function taxAmount(array $rate, string $currency): ?Money
    {
        $taxes = Arr::get($rate, 'taxes.taxes', []);

        if (! is_array($taxes) || $taxes === []) {
            return null;
        }

        $minorAmount = collect($taxes)->sum(function (array $tax) use ($currency): int {
            $taxCurrency = strtoupper((string) ($tax['currency'] ?? $currency));

            if ($taxCurrency !== strtoupper($currency)) {
                return 0;
            }

            return $this->money($tax['amount'] ?? '0', $currency)->minorAmount;
        });

        return $minorAmount > 0 ? new Money($minorAmount, strtoupper($currency)) : null;
    }

    private function rateComments(array $rate): ?string
    {
        $value = $rate['rateComments']
            ?? $rate['rateComment']
            ?? $rate['rateCommentsForBooking']
            ?? $rate['rateCommentsForBookings']
            ?? null;

        if (is_array($value)) {
            $value = Arr::get($value, 'content') ?? Arr::get($value, 'description.content');
        }

        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : mb_substr($value, 0, 2000);
    }

    private function rateComments(array $rate): ?string
    {
        $value = $rate['rateComments']
            ?? $rate['rateComment']
            ?? $rate['rateCommentsForBooking']
            ?? $rate['rateCommentsForBookings']
            ?? null;

        if (is_array($value)) {
            $value = Arr::get($value, 'content') ?? Arr::get($value, 'description.content');
        }

        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : mb_substr($value, 0, 2000);
    }

    private function stars(mixed $category): ?int
    {
        preg_match('/([1-5])/', (string) $category, $matches);

        return isset($matches[1]) ? (int) $matches[1] : null;
    }

    private function coordinates(array $hotel): ?array
    {
        if (! isset($hotel['latitude'], $hotel['longitude'])) {
            return null;
        }

        return ['lat' => (string) $hotel['latitude'], 'lng' => (string) $hotel['longitude']];
    }
}
