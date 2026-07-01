<?php

namespace App\Services\Supplier\RateHawk;

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
use App\Services\Supplier\Exceptions\UnsupportedSupplierOperationException;
use Carbon\CarbonImmutable;

class RateHawkHotelSupplier implements HotelSupplierInterface
{
    public function __construct(
        private readonly Supplier $supplier,
        private readonly RateHawkConfiguration $config,
        private readonly RateHawkHttpClient $client,
        private readonly RateHawkNormalizer $normalizer,
        private readonly CorrelationIdFactory $correlationIds,
    ) {}

    public function search(HotelSearchRequestData $request): HotelSearchResultData
    {
        $correlationId = $this->correlationIds->make($request->correlationId);
        $endpoint = isset($request->metadata['hotel_ids']) ? 'search_hotels' : 'search_region';
        $payload = [
            'checkin' => $request->checkIn->toDateString(),
            'checkout' => $request->checkOut->toDateString(),
            'language' => $request->locale === 'ar' ? 'ar' : 'en',
            'currency' => $request->currency,
            'residency' => $request->residencyCountry ?? 'EG',
            'guests' => array_map(fn ($room): array => [
                'adults' => $room->adults,
                'children' => $room->childAges,
            ], $request->rooms),
        ];

        if ($endpoint === 'search_hotels') {
            $payload['ids'] = array_values((array) $request->metadata['hotel_ids']);
        } else {
            $payload['region_id'] = (int) ($request->metadata['region_id'] ?? $request->destinationIdentifier);
        }

        $response = $this->client->post($this->supplier, SupplierOperation::Search, $endpoint, $payload, $correlationId);
        $hotels = $this->normalizer->hotels($response['body'], $request->currency, $request->rooms);

        return new HotelSearchResultData(
            supplierCode: $this->supplier->code,
            searchId: (string) (data_get($response['body'], 'debug.request_id') ?? 'ratehawk-search-'.$correlationId),
            hotels: $hotels,
            warnings: $hotels === [] ? ['RateHawk returned no availability.'] : [],
            partial: false,
            responseTime: ['supplier_status' => $response['status']],
            correlationId: $correlationId,
        );
    }

    public function getHotelDetails(HotelDetailsRequestData $request): HotelDetailsResultData
    {
        $correlationId = $this->correlationIds->make($request->correlationId);
        $response = $this->client->post($this->supplier, SupplierOperation::HotelDetails, 'hotelpage', [
            'id' => $request->supplierHotelId,
            'language' => $request->locale === 'ar' ? 'ar' : 'en',
        ], $correlationId);
        $hotelPayload = data_get($response['body'], 'data.hotel') ?? data_get($response['body'], 'data') ?? null;

        if (! is_array($hotelPayload)) {
            throw new InvalidSupplierResponseException('RateHawk hotel details response did not contain a hotel.', $correlationId);
        }

        return new HotelDetailsResultData(
            supplierCode: $this->supplier->code,
            hotel: $this->normalizer->hotel($hotelPayload, $request->currency),
            warnings: [],
            correlationId: $correlationId,
        );
    }

    public function checkRate(CheckRateRequestData $request): CheckRateResultData
    {
        $correlationId = $this->correlationIds->make($request->correlationId);
        $response = $this->client->post($this->supplier, SupplierOperation::CheckRate, 'prebook', [
            'hash' => $request->supplierRateKey,
        ], $correlationId, allowRetry: false);
        $ratePayload = data_get($response['body'], 'data.hotels.0.rates.0') ?? data_get($response['body'], 'data.rates.0') ?? data_get($response['body'], 'data');

        if (! is_array($ratePayload)) {
            throw new InvalidSupplierResponseException('RateHawk prebook response did not contain a rate.', $correlationId);
        }

        $rate = $this->normalizer->rate($ratePayload, $request->currency, $request->occupancy[0]);

        return new CheckRateResultData(
            available: true,
            priceChanged: false,
            previousTotal: null,
            confirmedTotal: $rate->totalAmount,
            currency: $request->currency,
            confirmedRateKey: $rate->rateKey ?: $request->supplierRateKey,
            rateExpiry: null,
            cancellationPolicies: $rate->cancellationPolicies,
            warnings: [],
            correlationId: $correlationId,
            metadata: ['supplier_status' => $response['status']],
        );
    }

    public function book(SupplierBookingRequestData $request): SupplierBookingResultData
    {
        throw $this->notImplemented('booking');
    }

    public function getBooking(SupplierBookingLookupRequestData $request): SupplierBookingDetailsData
    {
        throw $this->notImplemented('booking lookup');
    }

    public function cancel(SupplierCancellationRequestData $request): SupplierCancellationResultData
    {
        throw $this->notImplemented('cancellation');
    }

    public function healthCheck(): SupplierHealthResultData
    {
        $correlationId = $this->correlationIds->make();
        $credentials = $this->config->credentials($this->supplier);

        return new SupplierHealthResultData(
            healthy: false,
            status: SupplierHealthStatus::Unknown,
            responseTimeMs: 0,
            checkedAt: CarbonImmutable::now(),
            message: $credentials->configured()
                ? 'RateHawk supplier is configured but live health checks are limited to manual diagnostics.'
                : 'RateHawk supplier is installed but credentials are not configured.',
            correlationId: $correlationId,
        );
    }

    private function notImplemented(string $operation): UnsupportedSupplierOperationException
    {
        return new UnsupportedSupplierOperationException("RateHawk {$operation} is not implemented yet.");
    }
}
