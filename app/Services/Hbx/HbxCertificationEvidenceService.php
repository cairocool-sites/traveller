<?php

namespace App\Services\Hbx;

use App\Enums\BookingSupplierStatus;
use App\Enums\CancellationSupplierStatus;
use App\Enums\SupplierOperation;
use App\Models\Booking;
use App\Models\BookingCertificationEvidence;
use App\Models\SupplierOperationLog;
use App\Services\Booking\BookingReconciliationService;
use App\Services\Supplier\Data\SupplierCancellationRequestData;
use App\Services\Supplier\SupplierManager;
use Illuminate\Support\Str;

class HbxCertificationEvidenceService
{
    public function __construct(
        private readonly BookingReconciliationService $reconciliation,
        private readonly SupplierManager $suppliers,
    ) {}

    public function collect(Booking $booking, bool $includeCancellationSimulation = true): array
    {
        $booking->loadMissing(['supplier', 'currency', 'rooms', 'guests', 'rateCheck', 'certificationEvidences']);

        $this->guardHbxSandboxBooking($booking);

        $supplierBooking = $this->reconciliation->retrieveSupplierBooking($booking);
        $reconciliationEvidence = $this->reconciliation->audit($booking, $supplierBooking);
        $voucherEvidence = $this->voucherEvidence($booking);
        $cancellationSimulation = $includeCancellationSimulation
            ? $this->simulateCancellation($booking)
            : ['status' => 'not_run'];
        $supplierAfterSimulation = $includeCancellationSimulation
            ? $this->reconciliation->retrieveSupplierBooking($booking)
            : $supplierBooking;

        $evidence = BookingCertificationEvidence::query()->create([
            'booking_id' => $booking->id,
            'operation_type' => 'hbx_certification_evidence',
            'local_reference' => $booking->booking_reference,
            'supplier_reference' => $booking->supplier_booking_reference,
            'supplier_status' => $supplierAfterSimulation->status->value,
            'summary_status' => $this->summaryStatus($reconciliationEvidence, $cancellationSimulation, $supplierAfterSimulation),
            'field_results' => $reconciliationEvidence->field_results,
            'sanitized_snapshot' => $this->reconciliation->sanitizedBookingDetail($supplierBooking),
            'voucher_evidence' => $voucherEvidence,
            'cancellation_simulation' => $cancellationSimulation,
        ]);

        return [
            'booking_detail' => $evidence->sanitized_snapshot,
            'reconciliation' => [
                'summary_status' => $reconciliationEvidence->summary_status,
                'field_results' => $reconciliationEvidence->field_results,
            ],
            'voucher' => $voucherEvidence,
            'cancellation_simulation' => $cancellationSimulation,
            'supplier_booking_remains_confirmed' => $supplierAfterSimulation->status === BookingSupplierStatus::Confirmed,
            'payment_status' => $booking->payment_status->value,
            'content_api_blocker' => $this->contentApiBlocker(),
            'production_blocked' => rtrim((string) config('services.hbx.base_url'), '/') !== 'https://api.hotelbeds.com',
            'outstanding_manual_evidence' => $this->manualEvidence($voucherEvidence),
        ];
    }

    private function guardHbxSandboxBooking(Booking $booking): void
    {
        if ($booking->supplier?->code !== 'hbx_hotels') {
            throw new HbxCertificationEvidenceException('Only the hbx_hotels supplier can be used for HBX certification evidence.');
        }

        if (blank($booking->supplier_booking_reference)) {
            throw new HbxCertificationEvidenceException('The booking does not have an HBX supplier reference.');
        }

        if (rtrim((string) config('services.hbx.base_url'), '/') !== 'https://api.test.hotelbeds.com') {
            throw new HbxCertificationEvidenceException('HBX certification evidence is blocked outside the sandbox endpoint.');
        }
    }

    private function simulateCancellation(Booking $booking): array
    {
        $result = $this->suppliers
            ->resolve('hbx_hotels', SupplierOperation::Cancel)
            ->cancel(new SupplierCancellationRequestData(
                supplierBookingReference: (string) $booking->supplier_booking_reference,
                idempotencyKey: 'cert-simulation-'.$booking->booking_reference,
                cancellationReason: 'HBX sandbox certification simulation only.',
                correlationId: (string) Str::uuid(),
                metadata: ['cancellation_flag' => 'SIMULATION'],
            ));

        $snapshot = [
            'operation_type' => 'cancellation_simulation',
            'local_reference' => $booking->booking_reference,
            'supplier_reference' => $booking->supplier_booking_reference,
            'simulation_status' => $result->status->value,
            'cancellation_amount' => $result->penaltyAmount?->minorAmount,
            'currency' => $result->currency,
            'refundable_classification' => $this->refundableClassification($result->status, $result->penaltyAmount?->minorAmount),
            'deadline_or_policy_supplied' => false,
            'result_category' => $result->requiresManualReview ? 'manual_review' : 'simulation_completed',
            'created_at' => now()->toIso8601String(),
        ];

        BookingCertificationEvidence::query()->create([
            'booking_id' => $booking->id,
            'operation_type' => 'cancellation_simulation',
            'local_reference' => $booking->booking_reference,
            'supplier_reference' => $booking->supplier_booking_reference,
            'supplier_status' => $booking->supplier_status,
            'summary_status' => $snapshot['result_category'],
            'cancellation_simulation' => $snapshot,
        ]);

        return $snapshot;
    }

    private function refundableClassification(CancellationSupplierStatus $status, ?int $penaltyMinor): string
    {
        if ($status === CancellationSupplierStatus::Rejected) {
            return 'not_refundable_or_rejected';
        }

        return ($penaltyMinor ?? 0) > 0 ? 'penalty_applies' : 'free_or_zero_penalty';
    }

    private function voucherEvidence(Booking $booking): array
    {
        return [
            'branding' => 'present',
            'sandbox_notice' => 'present',
            'local_reference' => filled($booking->booking_reference) ? 'present' : 'manual_review',
            'supplier_reference' => filled($booking->supplier_booking_reference) ? 'present' : 'manual_review',
            'hotel_name' => filled(data_get($booking->hotel_snapshot, 'name')) ? 'present' : 'manual_review',
            'hotel_address' => filled(data_get($booking->hotel_snapshot, 'address')) ? 'present' : 'blocked_by_content_api',
            'destination' => filled(data_get($booking->hotel_snapshot, 'location')) ? 'present' : 'blocked_by_content_api',
            'category' => filled(data_get($booking->hotel_snapshot, 'star_rating')) ? 'present' : 'unavailable_from_supplier',
            'check_in' => filled($booking->check_in) ? 'present' : 'manual_review',
            'check_out' => filled($booking->check_out) ? 'present' : 'manual_review',
            'room_type' => filled(data_get($booking->room_snapshot, 'room_name')) ? 'present' : 'manual_review',
            'board' => filled(data_get($booking->room_snapshot, 'board_basis')) ? 'present' : 'manual_review',
            'occupancy' => ($booking->adults_count + $booking->children_count) > 0 ? 'present' : 'manual_review',
            'passenger_information' => $booking->guests->isNotEmpty() ? 'present' : 'manual_review',
            'booking_remarks' => filled($booking->special_requests) || filled(data_get($booking->room_snapshot, 'remarks')) ? 'present' : 'unavailable_from_supplier',
            'cancellation_summary' => filled($booking->cancellation_policy_snapshot) ? 'present' : 'manual_review',
            'customer_support' => 'present',
        ];
    }

    private function contentApiBlocker(): string
    {
        $log = SupplierOperationLog::query()
            ->where('request_method', 'GET')
            ->where('request_url', '/hotel-content-api/1.0/hotels')
            ->latest('id')
            ->first();

        if (! $log) {
            return 'no_content_hotels_request_recorded';
        }

        return $log->successful ? 'not_blocked' : 'content_hotels_latest_status_'.$log->response_status;
    }

    private function manualEvidence(array $voucherEvidence): array
    {
        return collect($voucherEvidence)
            ->filter(fn (string $status): bool => $status !== 'present')
            ->keys()
            ->values()
            ->all();
    }

    private function summaryStatus(BookingCertificationEvidence $reconciliation, array $simulation, mixed $supplierAfterSimulation): string
    {
        if ($supplierAfterSimulation->status !== BookingSupplierStatus::Confirmed) {
            return 'manual_review';
        }

        if ($reconciliation->summary_status !== 'matched') {
            return 'manual_review';
        }

        return ($simulation['result_category'] ?? null) === 'manual_review' ? 'manual_review' : 'ready';
    }
}
