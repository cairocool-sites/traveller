<?php

namespace App\Services\Booking;

use App\Enums\RateCheckStatus;
use App\Enums\SupplierOperation;
use App\Models\Currency;
use App\Models\RateCheck;
use App\Models\SearchSession;
use App\Models\Supplier;
use App\Services\PublicSearch\OfferPricingService;
use App\Services\Supplier\CorrelationIdFactory;
use App\Services\Supplier\Data\CheckRateRequestData;
use App\Services\Supplier\Data\RoomOccupancyData;
use App\Services\Supplier\SupplierManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RateCheckService
{
    public function __construct(
        private readonly SupplierManager $suppliers,
        private readonly CorrelationIdFactory $correlationIds,
        private readonly OfferPricingService $pricing,
    ) {}

    public function check(SearchSession $session, string $hotelToken, string $rateToken, array $metadata = []): RateCheck
    {
        if ($session->isExpired()) {
            throw BookingFlowException::invalidRate('The search session has expired.');
        }

        $hotel = collect($session->results_snapshot)->firstWhere('public_token', $hotelToken);
        $rate = collect($hotel['rates'] ?? [])->firstWhere('public_rate_token', $rateToken);

        if (! $hotel || ! $rate) {
            throw BookingFlowException::invalidRate();
        }

        $supplierCode = $hotel['supplier_code'] ?? config('travel.public_search.suppliers.0');
        $supplier = Supplier::query()->where('code', $supplierCode)->firstOrFail();
        $currency = Currency::query()->where('code', $session->currency)->firstOrFail();
        $correlationId = $this->correlationIds->make();
        $adapter = $this->suppliers->resolve($supplier->code, SupplierOperation::CheckRate);

        $result = $adapter->checkRate(new CheckRateRequestData(
            supplierHotelId: $hotel['supplier_hotel_id'],
            supplierRateKey: $rate['supplier_rate_key'],
            selectedRooms: [$rate],
            occupancy: array_map(
                fn (array $room): RoomOccupancyData => new RoomOccupancyData($room['adults'], $room['children'] ?? 0, $room['child_ages'] ?? []),
                $session->occupancy,
            ),
            currency: $session->currency,
            correlationId: $correlationId,
            metadata: $metadata,
        ));

        $status = match (true) {
            $result->available && $result->priceChanged => RateCheckStatus::PriceChanged,
            $result->available => RateCheckStatus::Available,
            $result->failureReason === 'rate_expired' => RateCheckStatus::RateExpired,
            $result->failureReason === 'sold_out' => RateCheckStatus::SoldOut,
            default => RateCheckStatus::Failed,
        };

        $checkedTotal = $result->confirmedTotal ? $this->pricing->sellingPrice($result->confirmedTotal) : null;

        return DB::transaction(fn (): RateCheck => RateCheck::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'search_session_id' => $session->id,
            'supplier_id' => $supplier->id,
            'hotel_id' => $hotel['canonical_hotel_id'] ?? null,
            'currency_id' => $currency->id,
            'status' => $status,
            'supplier_hotel_reference' => $hotel['supplier_hotel_id'],
            'supplier_rate_reference' => $result->confirmedRateKey ?? $rate['supplier_rate_key'],
            'supplier_room_reference' => $rate['supplier_room_id'] ?? null,
            'original_amount_minor' => Arr::get($rate, 'total.minor_amount'),
            'checked_amount_minor' => $checkedTotal?->minorAmount,
            'price_changed' => $result->priceChanged,
            'cancellation_policy_snapshot' => array_map(fn ($policy): array => $policy->jsonSerialize(), $result->cancellationPolicies),
            'room_snapshot' => $rate,
            'occupancy_snapshot' => $session->occupancy,
            'supplier_reference_snapshot' => [
                'confirmed_rate_key' => $result->confirmedRateKey,
                'warnings' => $result->warnings,
                'failure_reason' => $result->failureReason,
            ],
            'correlation_id' => $correlationId,
            'checked_at' => now(),
            'expires_at' => $result->rateExpiry ?? now()->addMinutes(config('travel.booking.rate_check_lifetime_minutes')),
        ]));
    }
}
