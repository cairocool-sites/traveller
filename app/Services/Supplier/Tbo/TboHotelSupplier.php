<?php

namespace App\Services\Supplier\Tbo;

use App\Enums\SupplierHealthStatus;
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
use App\Services\Supplier\Exceptions\UnsupportedSupplierOperationException;
use Carbon\CarbonImmutable;

class TboHotelSupplier implements HotelSupplierInterface
{
    public function __construct(
        private readonly Supplier $supplier,
        private readonly TboConfiguration $config,
        private readonly CorrelationIdFactory $correlationIds,
    ) {}

    public function search(HotelSearchRequestData $request): HotelSearchResultData
    {
        throw $this->notImplemented('search');
    }

    public function getHotelDetails(HotelDetailsRequestData $request): HotelDetailsResultData
    {
        throw $this->notImplemented('hotel details');
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
