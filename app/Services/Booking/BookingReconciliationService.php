<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\BookingSupplierStatus;
use App\Enums\SupplierOperation;
use App\Models\Booking;
use App\Services\Supplier\Data\SupplierBookingLookupRequestData;
use App\Services\Supplier\SupplierManager;

class BookingReconciliationService
{
    public function __construct(
        private readonly SupplierManager $suppliers,
        private readonly BookingStateMachine $states,
    ) {}

    public function reconcile(Booking $booking): Booking
    {
        if (blank($booking->supplier_booking_reference)) {
            return $booking;
        }

        $result = $this->suppliers
            ->resolve($booking->supplier->code, SupplierOperation::GetBooking)
            ->getBooking(new SupplierBookingLookupRequestData(
                supplierBookingReference: $booking->supplier_booking_reference,
                internalReference: $booking->booking_reference,
                correlationId: $booking->correlation_id,
            ));

        if ($result->found && $result->status === BookingSupplierStatus::Confirmed) {
            $booking->forceFill(['supplier_status' => $result->status->value])->save();
            $this->states->transition($booking, BookingStatus::Confirmed, 'Supplier lookup reconciled booking.');
        }

        return $booking->refresh();
    }
}
