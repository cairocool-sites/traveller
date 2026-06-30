<?php

namespace App\Services\Booking;

use App\Enums\SupplierOperation;
use App\Models\Booking;
use App\Models\BookingCertificationEvidence;
use App\Services\Supplier\Data\SupplierBookingDetailsData;
use App\Services\Supplier\Data\SupplierBookingLookupRequestData;
use App\Services\Supplier\SupplierManager;
use Illuminate\Support\Facades\Auth;

class BookingReconciliationService
{
    public function __construct(
        private readonly SupplierManager $suppliers,
    ) {}

    public function reconcile(Booking $booking): Booking
    {
        if (blank($booking->supplier_booking_reference)) {
            return $booking;
        }

        $this->audit($booking);

        return $booking->refresh();
    }

    public function retrieveSupplierBooking(Booking $booking): SupplierBookingDetailsData
    {
        return $this->suppliers
            ->resolve($booking->supplier->code, SupplierOperation::GetBooking)
            ->getBooking(new SupplierBookingLookupRequestData(
                supplierBookingReference: (string) $booking->supplier_booking_reference,
                internalReference: $booking->booking_reference,
                correlationId: $booking->correlation_id,
            ));
    }

    public function audit(Booking $booking, ?SupplierBookingDetailsData $supplierBooking = null): BookingCertificationEvidence
    {
        $booking->loadMissing(['supplier', 'currency', 'rooms', 'guests', 'rateCheck']);
        $supplierBooking ??= $this->retrieveSupplierBooking($booking);
        $fieldResults = $this->fieldResults($booking, $supplierBooking);
        $summaryStatus = collect($fieldResults)->contains(fn (array $result): bool => $result['classification'] === 'mismatched')
            ? 'manual_review'
            : 'matched';

        return BookingCertificationEvidence::query()->create([
            'booking_id' => $booking->id,
            'operation_type' => 'booking_detail_reconciliation',
            'local_reference' => $booking->booking_reference,
            'supplier_reference' => $booking->supplier_booking_reference,
            'supplier_status' => $supplierBooking->status->value,
            'summary_status' => $summaryStatus,
            'field_results' => $fieldResults,
            'sanitized_snapshot' => $this->sanitizedBookingDetail($supplierBooking),
            'created_by' => Auth::id(),
        ]);
    }

    public function sanitizedBookingDetail(SupplierBookingDetailsData $supplierBooking): array
    {
        $room = $this->supplierRoom($supplierBooking);

        return [
            'retrieved' => $supplierBooking->found,
            'supplier_reference' => $supplierBooking->supplierBookingReference,
            'supplier_status' => $supplierBooking->status->value,
            'hotel_code' => $supplierBooking->hotel?->supplierHotelId,
            'hotel_name' => $supplierBooking->hotel?->name,
            'check_in' => $this->roomDate($room, 'checkIn'),
            'check_out' => $this->roomDate($room, 'checkOut'),
            'room_count' => $this->supplierRoomCount($supplierBooking),
            'room_type' => $this->roomName($room),
            'board' => $this->roomBoard($room),
            'passenger_count' => $this->supplierPassengerCount($supplierBooking),
            'currency' => $supplierBooking->totals['total']->currency ?? $supplierBooking->hotel?->currency,
            'customer_public_total_category' => isset($supplierBooking->totals['total']) ? 'public_total_available' : 'not_supplied',
            'cancellation_policy_present' => $this->supplierCancellationPolicyPresent($supplierBooking),
            'remarks_present' => $this->roomRemarksPresent($room),
            'reconfirmation_number_present' => filled($supplierBooking->supplierBookingReference),
        ];
    }

    private function fieldResults(Booking $booking, SupplierBookingDetailsData $supplierBooking): array
    {
        $room = $this->supplierRoom($supplierBooking);
        $supplierTotal = $supplierBooking->totals['total'] ?? null;

        return [
            'local_reference' => $this->result($booking->booking_reference, null, 'not_comparable'),
            'supplier_reference' => $this->compare($booking->supplier_booking_reference, $supplierBooking->supplierBookingReference),
            'supplier_status' => $this->compare($booking->supplier_status, $supplierBooking->status->value),
            'hotel_code' => $this->compare($booking->rateCheck?->supplier_hotel_reference, $supplierBooking->hotel?->supplierHotelId),
            'hotel_name' => $this->compare(data_get($booking->hotel_snapshot, 'name'), $supplierBooking->hotel?->name),
            'check_in' => $this->compare($booking->check_in?->toDateString(), $this->roomDate($room, 'checkIn')),
            'check_out' => $this->compare($booking->check_out?->toDateString(), $this->roomDate($room, 'checkOut')),
            'room_count' => $this->compare($booking->rooms_count, $this->supplierRoomCount($supplierBooking)),
            'room_type' => $this->compare(data_get($booking->room_snapshot, 'room_name'), $this->roomName($room)),
            'board' => $this->compare(data_get($booking->room_snapshot, 'board_basis'), $this->roomBoard($room)),
            'passenger_count' => $this->compare($booking->adults_count + $booking->children_count, $this->supplierPassengerCount($supplierBooking)),
            'currency' => $this->compare($booking->currency?->code, $supplierTotal?->currency ?? $supplierBooking->hotel?->currency),
            'total_amount' => $this->compare($booking->total_amount_minor, $supplierTotal?->minorAmount),
            'cancellation_policy_presence' => $this->compare((bool) $booking->cancellation_policy_snapshot, $this->supplierCancellationPolicyPresent($supplierBooking)),
        ];
    }

    private function compare(mixed $local, mixed $supplier): array
    {
        if ($local === null || $local === '') {
            return $this->result($local, $supplier, 'missing_local');
        }

        if ($supplier === null || $supplier === '') {
            return $this->result($local, $supplier, 'missing_supplier');
        }

        $normalizedLocal = is_string($local) ? $this->normalizeString($local) : $local;
        $normalizedSupplier = is_string($supplier) ? $this->normalizeString($supplier) : $supplier;

        return $this->result($local, $supplier, $normalizedLocal == $normalizedSupplier ? 'matched' : 'mismatched');
    }

    private function normalizeString(string $value): string
    {
        $value = trim(strtolower($value));

        return match ($value) {
            'bb' => 'bed_and_breakfast',
            'ro' => 'room_only',
            'hb' => 'half_board',
            'fb' => 'full_board',
            'ai' => 'all_inclusive',
            default => $value,
        };
    }

    private function result(mixed $local, mixed $supplier, string $classification): array
    {
        return [
            'classification' => $classification,
            'local_present' => filled($local),
            'supplier_present' => filled($supplier),
        ];
    }

    private function supplierRoom(SupplierBookingDetailsData $supplierBooking): array
    {
        return collect($supplierBooking->rooms)->first(fn ($room): bool => is_array($room)) ?? [];
    }

    private function supplierRoomCount(SupplierBookingDetailsData $supplierBooking): int
    {
        return max(1, collect($supplierBooking->rooms)->filter(fn ($room): bool => is_array($room))->count());
    }

    private function supplierPassengerCount(SupplierBookingDetailsData $supplierBooking): int
    {
        $paxes = collect($supplierBooking->rooms)
            ->flatMap(fn ($room): array => is_array($room) && is_array($room['paxes'] ?? null) ? $room['paxes'] : [])
            ->count();

        if ($paxes > 0) {
            return $paxes;
        }

        return is_array($supplierBooking->guests) && $supplierBooking->guests !== [] ? 1 : 0;
    }

    private function supplierCancellationPolicyPresent(SupplierBookingDetailsData $supplierBooking): bool
    {
        return collect($supplierBooking->rooms)->contains(fn ($room): bool => is_array($room) && filled($room['rates'][0]['cancellationPolicies'] ?? $room['cancellationPolicies'] ?? null));
    }

    private function roomName(array $room): ?string
    {
        return $room['name'] ?? $room['description'] ?? $room['code'] ?? null;
    }

    private function roomBoard(array $room): ?string
    {
        return $room['rates'][0]['boardCode'] ?? $room['boardCode'] ?? $room['boardName'] ?? null;
    }

    private function roomDate(array $room, string $key): ?string
    {
        return $room['rates'][0][$key] ?? $room[$key] ?? null;
    }

    private function roomRemarksPresent(array $room): bool
    {
        return filled($room['remarks'] ?? $room['comments'] ?? $room['rates'][0]['rateComments'] ?? null);
    }
}
