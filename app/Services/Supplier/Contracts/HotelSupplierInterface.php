<?php

namespace App\Services\Supplier\Contracts;

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

interface HotelSupplierInterface
{
    public function search(HotelSearchRequestData $request): HotelSearchResultData;

    public function getHotelDetails(HotelDetailsRequestData $request): HotelDetailsResultData;

    public function checkRate(CheckRateRequestData $request): CheckRateResultData;

    public function book(SupplierBookingRequestData $request): SupplierBookingResultData;

    public function getBooking(SupplierBookingLookupRequestData $request): SupplierBookingDetailsData;

    public function cancel(SupplierCancellationRequestData $request): SupplierCancellationResultData;

    public function healthCheck(): SupplierHealthResultData;
}
