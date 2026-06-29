<?php

namespace App\Services\Supplier\Mock;

use App\Enums\BoardBasis;
use App\Enums\BookingSupplierStatus;
use App\Enums\CancellationPenaltyType;
use App\Enums\CancellationSupplierStatus;
use App\Enums\RateRefundability;
use App\Enums\SupplierErrorType;
use App\Enums\SupplierHealthStatus;
use App\Enums\SupplierOperation;
use App\Models\Hotel;
use App\Models\Supplier;
use App\Services\Supplier\Contracts\HotelSupplierInterface;
use App\Services\Supplier\CorrelationIdFactory;
use App\Services\Supplier\Data\CancellationPolicyData;
use App\Services\Supplier\Data\CheckRateRequestData;
use App\Services\Supplier\Data\CheckRateResultData;
use App\Services\Supplier\Data\HotelDetailsRequestData;
use App\Services\Supplier\Data\HotelDetailsResultData;
use App\Services\Supplier\Data\HotelSearchRequestData;
use App\Services\Supplier\Data\HotelSearchResultData;
use App\Services\Supplier\Data\RateData;
use App\Services\Supplier\Data\RoomOccupancyData;
use App\Services\Supplier\Data\SupplierBookingDetailsData;
use App\Services\Supplier\Data\SupplierBookingLookupRequestData;
use App\Services\Supplier\Data\SupplierBookingRequestData;
use App\Services\Supplier\Data\SupplierBookingResultData;
use App\Services\Supplier\Data\SupplierCancellationRequestData;
use App\Services\Supplier\Data\SupplierCancellationResultData;
use App\Services\Supplier\Data\SupplierHealthResultData;
use App\Services\Supplier\Data\SupplierHotelData;
use App\Services\Supplier\Exceptions\SupplierAuthenticationException;
use App\Services\Supplier\Exceptions\SupplierRateLimitException;
use App\Services\Supplier\Exceptions\SupplierTimeoutException;
use App\Services\Supplier\Exceptions\UnavailableSupplierException;
use App\Services\Supplier\IdempotencyService;
use App\Services\Supplier\SupplierOperationLogger;
use App\Support\Money\Money;

class MockHotelSupplier implements HotelSupplierInterface
{
    public function __construct(
        private readonly Supplier $supplier,
        private readonly SupplierOperationLogger $logger,
        private readonly CorrelationIdFactory $correlationIds,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function search(HotelSearchRequestData $request): HotelSearchResultData
    {
        $correlationId = $this->correlationIds->make($request->correlationId);
        $scenario = $request->metadata['scenario'] ?? $request->destinationIdentifier;
        $started = microtime(true);

        $this->maybeThrowScenario($scenario, $correlationId);

        $hotels = str_contains((string) $scenario, 'no_availability') ? [] : array_map(
            fn (array $hotel): SupplierHotelData => $this->hotelData($hotel, $request->currency, $request->rooms),
            array_slice($this->catalogue(), 0, 4),
        );

        $result = new HotelSearchResultData(
            supplierCode: $this->supplier->code,
            searchId: 'mock-search-'.substr(hash('sha1', $request->destinationIdentifier.$request->checkIn->toDateString()), 0, 12),
            hotels: $hotels,
            warnings: $hotels === [] ? ['No mock availability for requested scenario.'] : [],
            partial: false,
            responseTime: ['duration_ms' => $this->duration($started)],
            correlationId: $correlationId,
        );

        $this->log(SupplierOperation::Search, $correlationId, $request->jsonSerialize(), $result->jsonSerialize(), true, $this->duration($started));

        return $result;
    }

    public function getHotelDetails(HotelDetailsRequestData $request): HotelDetailsResultData
    {
        $correlationId = $this->correlationIds->make($request->correlationId);
        $hotel = $this->findHotel($request->supplierHotelId);
        $result = new HotelDetailsResultData($this->supplier->code, $this->hotelData($hotel, $request->currency), [], $correlationId);

        $this->log(SupplierOperation::HotelDetails, $correlationId, $request->jsonSerialize(), $result->jsonSerialize(), true);

        return $result;
    }

    public function checkRate(CheckRateRequestData $request): CheckRateResultData
    {
        $correlationId = $this->correlationIds->make($request->correlationId);
        $scenario = $request->metadata['scenario'] ?? $request->supplierRateKey;
        $policies = $this->cancellationPolicies($request->currency, nonRefundable: str_contains($request->supplierRateKey, 'nr'));
        $total = Money::fromDecimalString(str_contains($scenario, 'price_changed') ? '2750.00' : '2500.00', $request->currency);

        $result = match (true) {
            str_contains($scenario, 'rate_expired') => new CheckRateResultData(false, false, null, null, $request->currency, null, null, [], ['Rate expired.'], 'rate_expired', $correlationId),
            str_contains($scenario, 'sold_out') => new CheckRateResultData(false, false, null, null, $request->currency, null, null, [], ['Sold out.'], 'sold_out', $correlationId),
            default => new CheckRateResultData(
                available: true,
                priceChanged: str_contains($scenario, 'price_changed'),
                previousTotal: str_contains($scenario, 'price_changed') ? Money::fromDecimalString('2500.00', $request->currency) : null,
                confirmedTotal: $total,
                currency: $request->currency,
                confirmedRateKey: $request->supplierRateKey.'|checked',
                rateExpiry: now()->toImmutable()->addMinutes(20),
                cancellationPolicies: $policies,
                warnings: str_contains($scenario, 'price_changed') ? ['Mock price changed during check rate.'] : [],
                correlationId: $correlationId,
            ),
        };

        $this->log(SupplierOperation::CheckRate, $correlationId, $request->jsonSerialize(), $result->jsonSerialize(), $result->available);

        return $result;
    }

    public function book(SupplierBookingRequestData $request): SupplierBookingResultData
    {
        $correlationId = $this->correlationIds->make($request->correlationId);
        $snapshot = $this->idempotency->findOrReserve($this->supplier, SupplierOperation::Book, $request->idempotencyKey, $request->jsonSerialize());

        if ($snapshot) {
            return $this->bookingResultFromSnapshot($snapshot, $correlationId);
        }

        $scenario = $request->metadata['scenario'] ?? $request->supplierRateKey;
        $reference = 'MHB-'.strtoupper(substr(hash('sha1', $request->idempotencyKey.$request->supplierRateKey), 0, 10));
        $status = str_contains($scenario, 'booking_rejected') ? BookingSupplierStatus::Rejected : BookingSupplierStatus::Confirmed;
        $uncertain = str_contains($scenario, 'uncertain') || str_contains($scenario, 'response_timeout');

        $result = new SupplierBookingResultData(
            successful: $status === BookingSupplierStatus::Confirmed && ! str_contains($scenario, 'booking_rejected'),
            status: $uncertain ? BookingSupplierStatus::Uncertain : $status,
            supplierBookingReference: $status === BookingSupplierStatus::Rejected ? null : $reference,
            supplierConfirmationReference: $status === BookingSupplierStatus::Confirmed ? 'MCF-'.substr($reference, 4) : null,
            supplierHotelId: $request->supplierHotelId,
            rooms: $request->rooms,
            guests: $request->guests,
            confirmedTotal: $status === BookingSupplierStatus::Confirmed ? $request->expectedTotal : null,
            currency: $request->expectedTotal->currency,
            cancellationPoliciesSnapshot: $this->cancellationPolicies($request->expectedTotal->currency),
            warnings: $uncertain ? ['Mock booking succeeded but response timed out; lookup is required.'] : [],
            failureCode: $status === BookingSupplierStatus::Rejected ? 'mock_booking_rejected' : null,
            failureMessage: $status === BookingSupplierStatus::Rejected ? 'Mock supplier rejected the booking.' : null,
            requiresManualReview: $uncertain,
            supplierRawReferenceMetadata: ['scenario' => $scenario, 'redacted' => true],
            correlationId: $correlationId,
        );

        $this->idempotency->complete($this->supplier, SupplierOperation::Book, $request->idempotencyKey, $result->jsonSerialize());
        $this->log(SupplierOperation::Book, $correlationId, $request->jsonSerialize(), $result->jsonSerialize(), $result->successful, bookingReference: $reference);

        return $result;
    }

    public function getBooking(SupplierBookingLookupRequestData $request): SupplierBookingDetailsData
    {
        $correlationId = $this->correlationIds->make($request->correlationId);
        $found = ! str_contains($request->supplierBookingReference, 'NOTFOUND');
        $hotel = $found ? $this->hotelData($this->catalogue()[0], 'EGP') : null;
        $result = new SupplierBookingDetailsData(
            found: $found,
            supplierBookingReference: $request->supplierBookingReference,
            status: $found ? BookingSupplierStatus::Confirmed : BookingSupplierStatus::Failed,
            hotel: $hotel,
            rooms: $found ? [['name' => 'Mock Deluxe Room']] : [],
            guests: [],
            totals: $found ? ['total' => Money::fromDecimalString('2500.00', 'EGP')] : [],
            cancellationStatus: CancellationSupplierStatus::NotCancelled,
            supplierTimestamps: ['created_at' => now()->toIso8601String()],
            warnings: $found ? [] : ['Mock booking not found.'],
            correlationId: $correlationId,
        );

        $this->log(SupplierOperation::GetBooking, $correlationId, $request->jsonSerialize(), $result->jsonSerialize(), $found, bookingReference: $request->supplierBookingReference);

        return $result;
    }

    public function cancel(SupplierCancellationRequestData $request): SupplierCancellationResultData
    {
        $correlationId = $this->correlationIds->make($request->correlationId);
        $snapshot = $this->idempotency->findOrReserve($this->supplier, SupplierOperation::Cancel, $request->idempotencyKey, $request->jsonSerialize());

        if ($snapshot) {
            return $this->cancellationResultFromSnapshot($snapshot, $correlationId);
        }

        $nonRefundable = str_contains($request->supplierBookingReference, 'NR');
        $penalty = str_contains($request->supplierBookingReference, 'PENALTY');

        $result = new SupplierCancellationResultData(
            successful: ! $nonRefundable,
            status: $nonRefundable ? CancellationSupplierStatus::Rejected : CancellationSupplierStatus::Cancelled,
            cancellationReference: $nonRefundable ? null : 'MCX-'.strtoupper(substr(hash('sha1', $request->idempotencyKey), 0, 10)),
            penaltyAmount: $penalty ? Money::fromDecimalString('500.00', 'EGP') : Money::fromDecimalString('0.00', 'EGP'),
            refundableAmount: $nonRefundable ? Money::fromDecimalString('0.00', 'EGP') : Money::fromDecimalString($penalty ? '2000.00' : '2500.00', 'EGP'),
            currency: 'EGP',
            cancelledAt: $nonRefundable ? null : now()->toImmutable(),
            failureCode: $nonRefundable ? 'non_refundable' : null,
            failureMessage: $nonRefundable ? 'Mock booking is non-refundable.' : null,
            correlationId: $correlationId,
        );

        $this->idempotency->complete($this->supplier, SupplierOperation::Cancel, $request->idempotencyKey, $result->jsonSerialize());
        $this->log(SupplierOperation::Cancel, $correlationId, $request->jsonSerialize(), $result->jsonSerialize(), $result->successful, bookingReference: $request->supplierBookingReference);

        return $result;
    }

    public function healthCheck(): SupplierHealthResultData
    {
        $scenario = request()->query('scenario', 'healthy');
        $status = match ($scenario) {
            'degraded' => SupplierHealthStatus::Degraded,
            'unavailable' => SupplierHealthStatus::Unavailable,
            default => SupplierHealthStatus::Healthy,
        };

        $result = new SupplierHealthResultData($status === SupplierHealthStatus::Healthy, $status, 12, now()->toImmutable(), 'Mock health check '.$status->value, $this->correlationIds->make());
        $this->log(SupplierOperation::HealthCheck, $result->correlationId, [], $result->jsonSerialize(), $result->healthy);

        return $result;
    }

    private function maybeThrowScenario(string $scenario, string $correlationId): void
    {
        match ($scenario) {
            'timeout', 'delayed_response' => throw new SupplierTimeoutException('Mock supplier timeout.', $correlationId),
            'supplier_unavailable' => throw new UnavailableSupplierException('Mock supplier unavailable.', $correlationId),
            'authentication_failure' => throw new SupplierAuthenticationException('Mock authentication failed.', $correlationId),
            'rate_limited' => throw new SupplierRateLimitException('Mock rate limit exceeded.', $correlationId),
            default => null,
        };
    }

    private function hotelData(array $hotel, string $currency, array $rooms = []): SupplierHotelData
    {
        $occupancy = $rooms[0] ?? new RoomOccupancyData(2);
        $rates = [
            new RateData($hotel['id'].'-room-deluxe', 'Mock Deluxe Room', 'Fictional supplier room.', BoardBasis::BedAndBreakfast, $occupancy, $hotel['id'].'|bb|ref', Money::fromDecimalString('2500.00', $currency), Money::fromDecimalString('2200.00', $currency), Money::fromDecimalString('250.00', $currency), Money::fromDecimalString('50.00', $currency), RateRefundability::Refundable, $this->cancellationPolicies($currency), 'pay_later', now()->toImmutable()->addMinutes(30), 3),
            new RateData($hotel['id'].'-room-family', 'Mock Family Room', null, BoardBasis::HalfBoard, $occupancy, $hotel['id'].'|hb|nr', Money::fromDecimalString('3100.00', $currency), null, Money::fromDecimalString('300.00', $currency), Money::fromDecimalString('80.00', $currency), RateRefundability::NonRefundable, $this->cancellationPolicies($currency, true), 'pay_now', now()->toImmutable()->addMinutes(20), 2, ['promotion' => 'mock-family']),
        ];

        return new SupplierHotelData(
            supplierHotelId: $hotel['id'],
            canonicalHotelId: Hotel::query()->where('internal_code', $hotel['canonical_code'] ?? '')->value('id'),
            name: $hotel['name'],
            starRating: $hotel['stars'],
            location: $hotel['location'],
            coordinates: $hotel['coordinates'],
            images: [['url' => 'mock://'.$hotel['id'].'/primary.webp', 'type' => 'primary']],
            facilities: ['wifi', 'breakfast', 'family_rooms'],
            rooms: $rates,
            minimumTotalPrice: Money::fromDecimalString('2500.00', $currency),
            currency: $currency,
            taxesAndFees: ['tax' => Money::fromDecimalString('250.00', $currency), 'fee' => Money::fromDecimalString('50.00', $currency)],
            metadata: ['mock' => true],
        );
    }

    private function cancellationPolicies(string $currency, bool $nonRefundable = false): array
    {
        if ($nonRefundable) {
            return [new CancellationPolicyData(null, null, CancellationPenaltyType::Amount, Money::fromDecimalString('2500.00', $currency), null, null, false, true, 'Non-refundable mock rate.')];
        }

        return [
            new CancellationPolicyData(null, now()->toImmutable()->addDays(3), CancellationPenaltyType::None, Money::fromDecimalString('0.00', $currency), description: 'Free cancellation mock window.'),
            new CancellationPolicyData(now()->toImmutable()->addDays(3), null, CancellationPenaltyType::Amount, Money::fromDecimalString('500.00', $currency), description: 'Mock late cancellation penalty.'),
        ];
    }

    private function catalogue(): array
    {
        return [
            ['id' => 'MCK-CAI-001', 'name' => 'Mock Cairo Nile Hotel', 'stars' => 5, 'location' => 'Cairo', 'coordinates' => ['lat' => '30.0444', 'lng' => '31.2357'], 'canonical_code' => 'HTL-TEST-001'],
            ['id' => 'MCK-GIZ-002', 'name' => 'Mock Giza Pyramids Resort', 'stars' => 4, 'location' => 'Giza', 'coordinates' => ['lat' => '29.9870', 'lng' => '31.2118']],
            ['id' => 'MCK-ALY-003', 'name' => 'Mock Alexandria Corniche Hotel', 'stars' => 4, 'location' => 'Alexandria', 'coordinates' => ['lat' => '31.2001', 'lng' => '29.9187']],
            ['id' => 'MCK-HRG-004', 'name' => 'Mock Hurghada Bay Resort', 'stars' => 5, 'location' => 'Hurghada', 'coordinates' => ['lat' => '27.2579', 'lng' => '33.8116']],
            ['id' => 'MCK-SSH-005', 'name' => 'Mock Sharm Reef Hotel', 'stars' => 5, 'location' => 'Sharm El Sheikh', 'coordinates' => ['lat' => '27.9158', 'lng' => '34.3299']],
            ['id' => 'MCK-DXB-006', 'name' => 'Mock Dubai Creek Hotel', 'stars' => 5, 'location' => 'Dubai', 'coordinates' => ['lat' => '25.2048', 'lng' => '55.2708']],
            ['id' => 'MCK-MAK-007', 'name' => 'Mock Makkah Central Hotel', 'stars' => 4, 'location' => 'Makkah', 'coordinates' => ['lat' => '21.3891', 'lng' => '39.8579']],
            ['id' => 'MCK-IST-008', 'name' => 'Mock Istanbul Old City Hotel', 'stars' => 4, 'location' => 'Istanbul', 'coordinates' => ['lat' => '41.0082', 'lng' => '28.9784']],
        ];
    }

    private function findHotel(string $supplierHotelId): array
    {
        return collect($this->catalogue())->firstWhere('id', $supplierHotelId) ?? $this->catalogue()[0];
    }

    private function bookingResultFromSnapshot(array $snapshot, string $correlationId): SupplierBookingResultData
    {
        return new SupplierBookingResultData($snapshot['successful'], BookingSupplierStatus::from($snapshot['status']->value ?? $snapshot['status']), $snapshot['supplierBookingReference'] ?? $snapshot['supplier_booking_reference'] ?? null, $snapshot['supplierConfirmationReference'] ?? null, $snapshot['supplierHotelId'], $snapshot['rooms'], $snapshot['guests'], isset($snapshot['confirmedTotal']['minor_amount']) ? new Money($snapshot['confirmedTotal']['minor_amount'], $snapshot['confirmedTotal']['currency']) : null, $snapshot['currency'], $snapshot['cancellationPoliciesSnapshot'] ?? [], $snapshot['warnings'] ?? [], $snapshot['failureCode'] ?? null, $snapshot['failureMessage'] ?? null, $snapshot['requiresManualReview'] ?? false, $snapshot['supplierRawReferenceMetadata'] ?? [], $correlationId);
    }

    private function cancellationResultFromSnapshot(array $snapshot, string $correlationId): SupplierCancellationResultData
    {
        return new SupplierCancellationResultData($snapshot['successful'], CancellationSupplierStatus::from($snapshot['status']->value ?? $snapshot['status']), $snapshot['cancellationReference'] ?? null, isset($snapshot['penaltyAmount']['minor_amount']) ? new Money($snapshot['penaltyAmount']['minor_amount'], $snapshot['penaltyAmount']['currency']) : null, isset($snapshot['refundableAmount']['minor_amount']) ? new Money($snapshot['refundableAmount']['minor_amount'], $snapshot['refundableAmount']['currency']) : null, $snapshot['currency'], null, $snapshot['warnings'] ?? [], $snapshot['failureCode'] ?? null, $snapshot['failureMessage'] ?? null, $snapshot['requiresManualReview'] ?? false, $correlationId);
    }

    private function log(SupplierOperation $operation, string $correlationId, array $request, array $response, bool $successful, int $durationMs = 1, ?string $bookingReference = null): void
    {
        $this->logger->log($this->supplier, $operation, [
            'correlation_id' => $correlationId,
            'request_payload' => $request,
            'response_payload' => $response,
            'duration_ms' => $durationMs,
            'successful' => $successful,
            'error_type' => $successful ? null : SupplierErrorType::InvalidResponse,
            'booking_reference' => $bookingReference,
        ]);
    }

    private function duration(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
