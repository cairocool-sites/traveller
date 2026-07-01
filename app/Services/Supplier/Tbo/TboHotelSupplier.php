<?php

namespace App\Services\Supplier\Tbo;

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

class TboHotelSupplier implements HotelSupplierInterface
{
    public function __construct(
        private readonly Supplier $supplier,
        private readonly TboConfiguration $config,
        private readonly TboHttpClient $client,
        private readonly TboNormalizer $normalizer,
        private readonly CorrelationIdFactory $correlationIds,
    ) {}

    public function search(HotelSearchRequestData $request): HotelSearchResultData
    {
        $correlationId = $this->correlationIds->make($request->correlationId);
        $response = $this->client->request($this->supplier, SupplierOperation::Search, 'hotel_search', [
            'CheckInDate' => $request->checkIn->format('d/m/Y'),
            'NoOfNights' => $request->checkIn->diffInDays($request->checkOut),
            'CountryCode' => $request->metadata['country_code'] ?? $request->residencyCountry ?? 'EG',
            'CityId' => $request->metadata['city_id'] ?? $request->destinationIdentifier,
            'PreferredCurrency' => $request->currency,
            'GuestNationality' => $request->nationality ?? $request->residencyCountry ?? 'EG',
            'NoOfRooms' => count($request->rooms),
            'RoomGuests' => array_map(fn ($room): array => [
                'NoOfAdults' => $room->adults,
                'NoOfChild' => $room->children,
                'ChildAge' => $room->childAges,
            ], $request->rooms),
            'ResultCount' => $request->metadata['result_count'] ?? config('travel.search.results_limit', 30),
        ], $correlationId);
        $hotels = $this->normalizer->hotels($response['body'], $request->currency, $request->rooms);

        return new HotelSearchResultData(
            supplierCode: $this->supplier->code,
            searchId: (string) (data_get($response['body'], 'HotelSearchResult.TraceId') ?? data_get($response['body'], 'TraceId') ?? 'tbo-search-'.$correlationId),
            hotels: $hotels,
            warnings: $hotels === [] ? ['TBO returned no availability.'] : [],
            partial: false,
            responseTime: ['supplier_status' => $response['status']],
            correlationId: $correlationId,
        );
    }

    public function getHotelDetails(HotelDetailsRequestData $request): HotelDetailsResultData
    {
        $correlationId = $this->correlationIds->make($request->correlationId);
        $response = $this->client->request($this->supplier, SupplierOperation::HotelDetails, 'hotel_details', [
            'HotelCode' => $request->supplierHotelId,
            'Language' => strtoupper($request->locale),
        ], $correlationId);
        $hotelPayload = data_get($response['body'], 'HotelDetails') ?? data_get($response['body'], 'HotelDetail') ?? data_get($response['body'], 'Hotel') ?? null;

        if (! is_array($hotelPayload)) {
            throw new InvalidSupplierResponseException('TBO hotel details response did not contain a hotel.', $correlationId);
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
        throw $this->notImplemented('check rate');
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
                ? 'TBO supplier is configured but live health checks are not implemented yet.'
                : 'TBO supplier is installed but credentials are not configured.',
            correlationId: $correlationId,
        );
    }

    private function notImplemented(string $operation): UnsupportedSupplierOperationException
    {
        return new UnsupportedSupplierOperationException("TBO {$operation} is not implemented yet.");
    }
}
