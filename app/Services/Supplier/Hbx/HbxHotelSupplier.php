<?php

namespace App\Services\Supplier\Hbx;

use App\Enums\BookingSupplierStatus;
use App\Enums\CancellationSupplierStatus;
use App\Enums\SupplierHealthStatus;
use App\Enums\SupplierOperation;
use App\Models\Supplier;
use App\Services\Supplier\Contracts\HotelSupplierInterface;
use App\Services\Supplier\CorrelationIdFactory;
use App\Services\Supplier\Data\CheckRateRequestData;
use App\Services\Supplier\Data\CheckRateResultData;
use App\Services\Supplier\Data\HotelDetailsRequestData;
use App\Services\Supplier\Data\HotelDetailsResultData;
use App\Services\Supplier\Data\HotelSearchRequestData;
use App\Services\Supplier\Data\HotelSearchResultData;
use App\Services\Supplier\Data\SupplierBookingDetailsData;
use App\Services\Supplier\Data\SupplierBookingLookupRequestData;
use App\Services\Supplier\Data\SupplierBookingRequestData;
use App\Services\Supplier\Data\SupplierBookingResultData;
use App\Services\Supplier\Data\SupplierCancellationRequestData;
use App\Services\Supplier\Data\SupplierCancellationResultData;
use App\Services\Supplier\Data\SupplierHealthResultData;
use App\Services\Supplier\Exceptions\InvalidSupplierResponseException;
use App\Services\Supplier\Exceptions\SupplierTimeoutException;
use App\Services\Supplier\IdempotencyService;
use App\Support\Money\Money;
use Illuminate\Support\Str;

class HbxHotelSupplier implements HotelSupplierInterface
{
    private ?int $lastHealthHttpStatus = null;

    public function __construct(
        private readonly Supplier $supplier,
        private readonly HbxHttpClient $client,
        private readonly HbxNormalizer $normalizer,
        private readonly CorrelationIdFactory $correlationIds,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function search(HotelSearchRequestData $request): HotelSearchResultData
    {
        $correlationId = $this->correlationIds->make($request->correlationId);
        $payload = [
            'stay' => ['checkIn' => $request->checkIn->toDateString(), 'checkOut' => $request->checkOut->toDateString()],
            'occupancies' => array_map(fn ($room): array => [
                'rooms' => 1,
                'adults' => $room->adults,
                'children' => $room->children,
                'paxes' => array_map(fn (int $age): array => ['type' => 'CH', 'age' => $age], $room->childAges),
            ], $request->rooms),
            'destination' => ['code' => $this->destinationCode($request->destinationIdentifier)],
            'nationality' => $request->nationality,
            'currency' => $request->currency,
            'language' => $request->locale,
        ];

        if (isset($request->metadata['hotel_codes']) && is_array($request->metadata['hotel_codes'])) {
            $payload['hotels'] = ['hotel' => array_values($request->metadata['hotel_codes'])];
        }

        $response = $this->client->request($this->supplier, SupplierOperation::Search, 'POST', '/hotel-api/1.0/hotels', array_filter($payload), $correlationId);
        $hotels = $this->normalizer->hotels($response['body'], $request->currency, $request->rooms);

        return new HotelSearchResultData(
            supplierCode: $this->supplier->code,
            searchId: (string) ($response['body']['auditData']['processTime'] ?? 'hbx-search-'.$correlationId),
            hotels: $hotels,
            warnings: $hotels === [] ? ['HBX returned no availability.'] : [],
            partial: false,
            responseTime: ['supplier_status' => $response['status']],
            correlationId: $correlationId,
        );
    }

    public function getHotelDetails(HotelDetailsRequestData $request): HotelDetailsResultData
    {
        $correlationId = $this->correlationIds->make($request->correlationId);
        $payload = ['hotels' => ['hotel' => [$request->supplierHotelId]], 'language' => $request->locale];
        $response = $this->client->request($this->supplier, SupplierOperation::HotelDetails, 'POST', '/hotel-api/1.0/hotels', $payload, $correlationId);
        $hotel = $this->normalizer->hotels($response['body'], $request->currency, [])[0] ?? null;

        if (! $hotel) {
            throw new InvalidSupplierResponseException('HBX hotel details response did not contain a hotel.', $correlationId);
        }

        return new HotelDetailsResultData($this->supplier->code, $hotel, [], $correlationId);
    }

    public function checkRate(CheckRateRequestData $request): CheckRateResultData
    {
        $correlationId = $this->correlationIds->make($request->correlationId);
        $previousTotalSnapshot = $request->selectedRooms[0]['supplier_total'] ?? $request->selectedRooms[0]['total'] ?? null;
        $previousTotal = isset($previousTotalSnapshot['minor_amount'])
            ? new Money((int) $previousTotalSnapshot['minor_amount'], $request->currency)
            : null;
        $response = $this->client->request($this->supplier, SupplierOperation::CheckRate, 'POST', '/hotel-api/1.0/checkrates', [
            'rooms' => [['rateKey' => $request->supplierRateKey]],
        ], $correlationId);
        $rate = $this->normalizer->firstRate($response['body'], $request->currency, $request->occupancy);

        if (! $rate || $rate->rateKey === '') {
            return new CheckRateResultData(false, false, $previousTotal, null, $request->currency, null, null, [], ['HBX rate is unavailable.'], 'rate_expired', $correlationId);
        }

        return new CheckRateResultData(
            available: true,
            priceChanged: $previousTotal !== null && $previousTotal->minorAmount !== $rate->totalAmount->minorAmount,
            previousTotal: $previousTotal,
            confirmedTotal: $rate->totalAmount,
            currency: $rate->totalAmount->currency,
            confirmedRateKey: $rate->rateKey,
            rateExpiry: now()->toImmutable()->addMinutes(config('travel.booking.rate_check_lifetime_minutes')),
            cancellationPolicies: $rate->cancellationPolicies,
            warnings: (bool) ($rate->metadata['requires_check_rate'] ?? false) ? ['HBX RECHECK rate confirmed.'] : [],
            correlationId: $correlationId,
        );
    }

    public function book(SupplierBookingRequestData $request): SupplierBookingResultData
    {
        $correlationId = $this->correlationIds->make($request->correlationId);
        $snapshot = $this->idempotency->findOrReserve($this->supplier, SupplierOperation::Book, $request->idempotencyKey, $request->jsonSerialize());

        if ($snapshot) {
            return $this->bookingFromSnapshot($snapshot, $correlationId);
        }

        try {
            $response = $this->client->request($this->supplier, SupplierOperation::Book, 'POST', '/hotel-api/1.0/bookings', [
                'holder' => ['name' => $request->leadGuest->firstName, 'surname' => $request->leadGuest->lastName],
                'rooms' => [['rateKey' => $request->supplierRateKey, 'paxes' => $this->paxes($request)]],
                'clientReference' => $request->idempotencyKey,
                'remark' => $request->specialRequests,
            ], $correlationId);
        } catch (SupplierTimeoutException) {
            $result = new SupplierBookingResultData(false, BookingSupplierStatus::Uncertain, $request->idempotencyKey, null, $request->supplierHotelId, $request->rooms, $request->guests, null, $request->expectedTotal->currency, [], ['HBX booking outcome is uncertain after timeout; lookup is required.'], 'hbx_booking_timeout', 'HBX booking timed out after submission.', true, ['client_reference' => $request->idempotencyKey], $correlationId);
            $this->idempotency->complete($this->supplier, SupplierOperation::Book, $request->idempotencyKey, $result->jsonSerialize());

            return $result;
        }

        $booking = $response['body']['booking'] ?? [];
        $status = $this->normalizer->bookingStatus($booking['status'] ?? null);
        $reference = $booking['reference'] ?? null;
        $successful = $status === BookingSupplierStatus::Confirmed && is_string($reference);
        $result = new SupplierBookingResultData(
            successful: $successful,
            status: $status,
            supplierBookingReference: $reference,
            supplierConfirmationReference: $booking['reference'] ?? null,
            supplierHotelId: $request->supplierHotelId,
            rooms: $request->rooms,
            guests: $request->guests,
            confirmedTotal: isset($booking['totalNet']) ? $this->normalizer->money($booking['totalNet'], $booking['currency'] ?? $request->expectedTotal->currency) : ($successful ? $request->expectedTotal : null),
            currency: $booking['currency'] ?? $request->expectedTotal->currency,
            cancellationPoliciesSnapshot: [],
            failureCode: $successful ? null : 'hbx_booking_rejected',
            failureMessage: $successful ? null : 'HBX did not confirm the sandbox booking.',
            requiresManualReview: false,
            supplierRawReferenceMetadata: ['hbx_reference_present' => is_string($reference)],
            correlationId: $correlationId,
        );

        $this->idempotency->complete($this->supplier, SupplierOperation::Book, $request->idempotencyKey, $result->jsonSerialize());

        return $result;
    }

    public function getBooking(SupplierBookingLookupRequestData $request): SupplierBookingDetailsData
    {
        $correlationId = $this->correlationIds->make($request->correlationId);
        $response = $this->client->request($this->supplier, SupplierOperation::GetBooking, 'GET', '/hotel-api/1.0/bookings/'.$request->supplierBookingReference, [], $correlationId);
        $booking = $response['body']['booking'] ?? null;

        if (! is_array($booking)) {
            return new SupplierBookingDetailsData(false, $request->supplierBookingReference, BookingSupplierStatus::Failed, null, [], [], [], CancellationSupplierStatus::Failed, warnings: ['HBX booking not found.'], correlationId: $correlationId);
        }

        $hotel = isset($booking['hotel']) && is_array($booking['hotel']) ? $this->normalizer->hotel($booking['hotel'], $booking['currency'] ?? config('travel.currency.default')) : null;

        return new SupplierBookingDetailsData(
            found: true,
            supplierBookingReference: (string) ($booking['reference'] ?? $request->supplierBookingReference),
            status: $this->normalizer->bookingStatus($booking['status'] ?? null),
            hotel: $hotel,
            rooms: $booking['hotel']['rooms'] ?? [],
            guests: $booking['holder'] ?? [],
            totals: isset($booking['totalNet']) ? ['total' => $this->normalizer->money($booking['totalNet'], $booking['currency'] ?? config('travel.currency.default'))] : [],
            cancellationStatus: $this->normalizer->cancellationStatus($booking['status'] ?? null),
            supplierTimestamps: array_filter(['created_at' => $booking['creationDate'] ?? null]),
            correlationId: $correlationId,
        );
    }

    public function cancel(SupplierCancellationRequestData $request): SupplierCancellationResultData
    {
        $correlationId = $this->correlationIds->make($request->correlationId);
        $snapshot = $this->idempotency->findOrReserve($this->supplier, SupplierOperation::Cancel, $request->idempotencyKey, $request->jsonSerialize());

        if ($snapshot) {
            return $this->cancellationFromSnapshot($snapshot, $correlationId);
        }

        try {
            $response = $this->client->request($this->supplier, SupplierOperation::Cancel, 'DELETE', '/hotel-api/1.0/bookings/'.$request->supplierBookingReference, [], $correlationId);
        } catch (SupplierTimeoutException) {
            $result = new SupplierCancellationResultData(false, CancellationSupplierStatus::Pending, null, null, null, config('travel.currency.default'), warnings: ['HBX cancellation outcome is uncertain after timeout; manual review is required.'], failureCode: 'hbx_cancellation_timeout', failureMessage: 'HBX cancellation timed out after submission.', requiresManualReview: true, correlationId: $correlationId);
            $this->idempotency->complete($this->supplier, SupplierOperation::Cancel, $request->idempotencyKey, $result->jsonSerialize());

            return $result;
        }
        $booking = $response['body']['booking'] ?? [];
        $currency = $booking['currency'] ?? config('travel.currency.default');
        $penalty = $this->normalizer->money($booking['cancellationAmount'] ?? $booking['hotel']['cancellationAmount'] ?? '0', $currency);
        $status = $this->normalizer->cancellationStatus($booking['status'] ?? null);
        $successful = $status === CancellationSupplierStatus::Cancelled;
        $result = new SupplierCancellationResultData(
            successful: $successful,
            status: $status,
            cancellationReference: $successful ? ($booking['reference'] ?? $request->supplierBookingReference) : null,
            penaltyAmount: $penalty,
            refundableAmount: null,
            currency: $currency,
            cancelledAt: $successful ? now()->toImmutable() : null,
            failureCode: $successful ? null : 'hbx_cancellation_not_confirmed',
            failureMessage: $successful ? null : 'HBX did not confirm the sandbox cancellation.',
            requiresManualReview: ! $successful,
            correlationId: $correlationId,
        );

        $this->idempotency->complete($this->supplier, SupplierOperation::Cancel, $request->idempotencyKey, $result->jsonSerialize());

        return $result;
    }

    public function healthCheck(): SupplierHealthResultData
    {
        $correlationId = $this->correlationIds->make();
        $started = microtime(true);
        $response = $this->client->request($this->supplier, SupplierOperation::HealthCheck, 'GET', '/hotel-api/1.0/status', [], $correlationId, allowRetry: false);
        $this->lastHealthHttpStatus = $response['status'];
        $ok = $response['status'] >= 200 && $response['status'] < 300;

        return new SupplierHealthResultData($ok, $ok ? SupplierHealthStatus::Healthy : SupplierHealthStatus::Unavailable, (int) round((microtime(true) - $started) * 1000), now()->toImmutable(), $ok ? 'HBX sandbox reachable.' : 'HBX sandbox unavailable.', $correlationId);
    }

    public function healthDiagnostics(): HbxRequestDiagnostics
    {
        return $this->client->diagnostics($this->supplier, 'GET', '/hotel-api/1.0/status');
    }

    public function lastHealthHttpStatus(): ?int
    {
        return $this->lastHealthHttpStatus;
    }

    private function destinationCode(string $destinationIdentifier): string
    {
        $normalized = Str::of($destinationIdentifier)->lower()->trim()->toString();

        return (string) (config("services.hbx.destination_codes.{$normalized}") ?: $destinationIdentifier);
    }

    private function paxes(SupplierBookingRequestData $request): array
    {
        return array_map(fn ($guest): array => [
            'roomId' => 1,
            'type' => $this->guestValue($guest, 'type') === 'child' ? 'CH' : 'AD',
            'name' => $this->guestValue($guest, 'first_name') ?? '',
            'surname' => $this->guestValue($guest, 'last_name') ?? '',
            'age' => $this->guestValue($guest, 'age'),
        ], $request->guests);
    }

    private function guestValue(mixed $guest, string $key): mixed
    {
        if (is_array($guest)) {
            return $guest[$key] ?? null;
        }

        return match ($key) {
            'first_name' => $guest->firstName ?? null,
            'last_name' => $guest->lastName ?? null,
            'type' => $guest->type->value ?? null,
            'age' => $guest->age ?? null,
            default => null,
        };
    }

    private function bookingFromSnapshot(array $snapshot, string $correlationId): SupplierBookingResultData
    {
        return new SupplierBookingResultData($snapshot['successful'], BookingSupplierStatus::from($snapshot['status']->value ?? $snapshot['status']), $snapshot['supplierBookingReference'] ?? null, $snapshot['supplierConfirmationReference'] ?? null, $snapshot['supplierHotelId'], $snapshot['rooms'], $snapshot['guests'], isset($snapshot['confirmedTotal']['minor_amount']) ? new Money($snapshot['confirmedTotal']['minor_amount'], $snapshot['confirmedTotal']['currency']) : null, $snapshot['currency'], $snapshot['cancellationPoliciesSnapshot'] ?? [], $snapshot['warnings'] ?? [], $snapshot['failureCode'] ?? null, $snapshot['failureMessage'] ?? null, $snapshot['requiresManualReview'] ?? false, $snapshot['supplierRawReferenceMetadata'] ?? [], $correlationId);
    }

    private function cancellationFromSnapshot(array $snapshot, string $correlationId): SupplierCancellationResultData
    {
        return new SupplierCancellationResultData($snapshot['successful'], CancellationSupplierStatus::from($snapshot['status']->value ?? $snapshot['status']), $snapshot['cancellationReference'] ?? null, isset($snapshot['penaltyAmount']['minor_amount']) ? new Money($snapshot['penaltyAmount']['minor_amount'], $snapshot['penaltyAmount']['currency']) : null, isset($snapshot['refundableAmount']['minor_amount']) ? new Money($snapshot['refundableAmount']['minor_amount'], $snapshot['refundableAmount']['currency']) : null, $snapshot['currency'], null, $snapshot['warnings'] ?? [], $snapshot['failureCode'] ?? null, $snapshot['failureMessage'] ?? null, $snapshot['requiresManualReview'] ?? false, $correlationId);
    }
}
