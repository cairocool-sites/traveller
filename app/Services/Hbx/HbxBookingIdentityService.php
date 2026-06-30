<?php

namespace App\Services\Hbx;

use App\Enums\SupplierOperation;
use App\Models\Booking;
use App\Models\BookingCertificationEvidence;
use App\Models\Supplier;
use App\Models\SupplierOperationLog;
use App\Services\Booking\BookingReconciliationService;
use App\Services\Supplier\Hbx\HbxHttpClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class HbxBookingIdentityService
{
    public function __construct(
        private readonly BookingReconciliationService $reconciliation,
        private readonly HbxHttpClient $client,
    ) {}

    public function audit(Booking $booking, bool $includeBookingList = true): array
    {
        $booking->loadMissing(['supplier', 'currency', 'rateCheck', 'rooms', 'guests']);
        $this->guard($booking);

        $local = $this->localFingerprint($booking);
        $original = $this->originalBookingResponseEvidence($booking);
        $detail = $this->detailFingerprint($booking);
        $candidates = $includeBookingList ? $this->bookingListCandidates($booking) : [];
        $classification = $this->classify($local, $original, $detail, $candidates);
        $cancellationAudit = $this->localCancellationAudit((string) $booking->supplier_booking_reference);

        BookingCertificationEvidence::query()->create([
            'booking_id' => $booking->id,
            'operation_type' => 'booking_identity_forensic_audit',
            'local_reference' => $booking->booking_reference,
            'supplier_reference' => $booking->supplier_booking_reference,
            'supplier_status' => $detail['status'] ?? null,
            'summary_status' => $classification,
            'field_results' => $this->comparisonTable($local, $original, $detail, $candidates[0] ?? []),
            'sanitized_snapshot' => [
                'local' => $local,
                'original_booking_response' => $original,
                'booking_detail' => $detail,
                'booking_list_candidates' => $candidates,
                'local_cancellation_audit' => $cancellationAudit,
                'official_reference_rule' => 'HBX Booking Detail uses the booking.reference value returned by confirmation or booking list. Format is documented as XXX-YYYYYY.',
            ],
            'created_by' => Auth::id(),
        ]);

        return compact('local', 'original', 'detail', 'candidates', 'classification', 'cancellationAudit');
    }

    public function correctSupplierReference(Booking $booking, string $newReference, string $reason): BookingCertificationEvidence
    {
        $audit = $this->audit($booking);
        $candidate = collect($audit['candidates'])->firstWhere('reference', $newReference);

        if (! $candidate || ! $this->exactMatch($audit['local'], $candidate)) {
            throw new HbxCertificationEvidenceException('Supplier reference cannot be corrected without exact identity evidence.');
        }

        $oldReference = $booking->supplier_booking_reference;
        $booking->forceFill([
            'supplier_booking_reference' => $newReference,
            'supplier_confirmation_reference' => $newReference,
        ])->save();

        return BookingCertificationEvidence::query()->create([
            'booking_id' => $booking->id,
            'operation_type' => 'supplier_reference_resolution',
            'local_reference' => $booking->booking_reference,
            'supplier_reference' => $newReference,
            'supplier_status' => $candidate['status'] ?? null,
            'summary_status' => 'exact_match',
            'sanitized_snapshot' => [
                'old_reference' => $oldReference,
                'new_reference' => $newReference,
                'evidence_classification' => 'exact_match',
                'changed_by' => Auth::id(),
                'changed_at' => now()->toIso8601String(),
                'reason' => Str::of($reason)->squish()->limit(500, '')->toString(),
                'correlation_id' => (string) Str::uuid(),
            ],
            'created_by' => Auth::id(),
        ]);
    }

    public function clientReference(Booking $booking): string
    {
        $key = (string) $booking->idempotency_key;
        $reference = Str::of($key)->replaceMatches('/[^A-Za-z0-9_-]/', '')->limit(20, '')->toString();

        return $reference !== '' ? $reference : strtoupper(substr(hash('sha1', $key), 0, 20));
    }

    public function isIdentityUnresolved(Booking $booking): bool
    {
        return $booking->certificationEvidences()
            ->whereIn('operation_type', ['booking_identity_forensic_audit', 'booking_detail_reconciliation', 'hbx_certification_evidence'])
            ->where('summary_status', 'manual_review')
            ->exists();
    }

    private function guard(Booking $booking): void
    {
        if ($booking->supplier?->code !== 'hbx_hotels') {
            throw new HbxCertificationEvidenceException('Only hbx_hotels bookings can be audited by this HBX identity workflow.');
        }

        if (rtrim((string) config('services.hbx.base_url'), '/') !== 'https://api.test.hotelbeds.com') {
            throw new HbxCertificationEvidenceException('HBX identity audit is blocked outside the sandbox endpoint.');
        }
    }

    private function localFingerprint(Booking $booking): array
    {
        return [
            'local_booking_id' => $booking->id,
            'local_reference' => $booking->booking_reference,
            'supplier_id' => $booking->supplier_id,
            'supplier_code' => $booking->supplier?->code,
            'supplier_reference' => $booking->supplier_booking_reference,
            'client_reference' => $this->clientReference($booking),
            'idempotency_identifier' => substr(hash('sha256', (string) $booking->idempotency_key), 0, 12),
            'rate_check_id' => $booking->rate_check_id,
            'hotel_code' => $booking->rateCheck?->supplier_hotel_reference,
            'hotel_name' => data_get($booking->hotel_snapshot, 'name'),
            'destination_code' => data_get($booking->hotel_snapshot, 'destination_code') ?? $booking->searchSession?->destination_label,
            'check_in' => $booking->check_in?->toDateString(),
            'check_out' => $booking->check_out?->toDateString(),
            'room_code' => $booking->rateCheck?->supplier_room_reference,
            'room_name' => data_get($booking->room_snapshot, 'room_name'),
            'board' => $this->normalizeBoard(data_get($booking->room_snapshot, 'board_basis')),
            'occupancy_count' => $booking->adults_count + $booking->children_count,
            'room_count' => $booking->rooms_count,
            'currency' => $booking->currency?->code,
            'final_customer_amount_minor' => $booking->total_amount_minor,
            'local_supplier_status' => $booking->supplier_status,
            'payment_status' => $booking->payment_status->value,
            'booking_created_at' => $booking->created_at?->toIso8601String(),
            'supplier_response_timestamp' => data_get($booking->supplier_response_snapshot, 'supplierTimestamps.created_at') ?? data_get($booking->supplier_response_snapshot, 'created_at'),
            'correlation_id' => $booking->correlation_id,
            'environment' => 'sandbox',
        ];
    }

    private function originalBookingResponseEvidence(Booking $booking): array
    {
        $clientReference = $this->clientReference($booking);
        $log = SupplierOperationLog::query()
            ->where('operation', SupplierOperation::Book)
            ->where('request_url', '/hotel-api/1.0/bookings')
            ->where('correlation_id', $booking->correlation_id)
            ->latest('id')
            ->first()
            ?: SupplierOperationLog::query()
                ->where('operation', SupplierOperation::Book)
                ->where('request_url', '/hotel-api/1.0/bookings')
                ->where('request_payload->clientReference', $clientReference)
                ->latest('id')
                ->first();

        if (! $log) {
            return ['found' => false, 'client_reference' => $clientReference];
        }

        $bookingPayload = $log->response_payload['booking'] ?? [];
        $requestPayload = $log->request_payload ?? [];
        $hotel = $bookingPayload['hotel'] ?? [];
        $room = collect($hotel['rooms'] ?? [])->first(fn ($room): bool => is_array($room)) ?? [];
        $rate = collect($room['rates'] ?? [])->first(fn ($rate): bool => is_array($rate)) ?? [];

        return [
            'found' => true,
            'endpoint' => $log->request_method.' '.$log->request_url,
            'response_envelope' => array_keys($log->response_payload ?? []),
            'booking_reference_path' => 'booking.reference',
            'booking_reference_value' => $bookingPayload['reference'] ?? null,
            'client_reference_path' => 'request.clientReference',
            'client_reference' => $requestPayload['clientReference'] ?? $clientReference,
            'status_path' => 'booking.status',
            'status' => $bookingPayload['status'] ?? null,
            'hotel_code_path' => 'booking.hotel.code',
            'hotel_code' => $hotel['code'] ?? null,
            'hotel_name_path' => 'booking.hotel.name',
            'hotel_name' => $hotel['name'] ?? null,
            'check_in' => $hotel['checkIn'] ?? $rate['checkIn'] ?? null,
            'check_out' => $hotel['checkOut'] ?? $rate['checkOut'] ?? null,
            'board' => $this->normalizeBoard($rate['boardCode'] ?? $room['boardCode'] ?? null),
            'currency' => $bookingPayload['currency'] ?? null,
            'creation_date' => $bookingPayload['creationDate'] ?? null,
            'modification_date' => $bookingPayload['modificationDate'] ?? null,
            'cancellation_reference' => $bookingPayload['cancellationReference'] ?? null,
        ];
    }

    private function detailFingerprint(Booking $booking): array
    {
        $detail = $this->reconciliation->retrieveSupplierBooking($booking);
        $snapshot = $this->reconciliation->sanitizedBookingDetail($detail);

        return $snapshot + [
            'reference' => $detail->supplierBookingReference,
            'status' => $detail->status->value,
        ];
    }

    private function bookingListCandidates(Booking $booking): array
    {
        $supplier = Supplier::query()->where('code', 'hbx_hotels')->firstOrFail();
        $start = $booking->created_at?->copy()->subDays(2)->toDateString() ?? $booking->check_in->copy()->subDays(30)->toDateString();
        $end = $booking->created_at?->copy()->addDays(2)->toDateString() ?? $booking->check_out->copy()->addDays(30)->toDateString();
        $query = http_build_query([
            'filterType' => 'CREATION',
            'status' => 'ALL',
            'from' => 1,
            'to' => 25,
            'clientReference' => $this->clientReference($booking),
            'start' => $start,
            'end' => $end,
        ]);

        $response = $this->client->request($supplier, SupplierOperation::BookingList, 'GET', '/hotel-api/1.0/bookings?'.$query, [], $booking->correlation_id);
        $bookings = data_get($response, 'body.bookings.bookings', []);

        return collect(is_array($bookings) ? $bookings : [])
            ->filter(fn ($candidate): bool => is_array($candidate))
            ->map(fn (array $candidate): array => $this->candidateFingerprint($candidate, $booking))
            ->values()
            ->all();
    }

    private function candidateFingerprint(array $candidate, Booking $booking): array
    {
        $hotel = $candidate['hotel'] ?? [];
        $room = collect($hotel['rooms'] ?? [])->first(fn ($room): bool => is_array($room)) ?? [];
        $rate = collect($room['rates'] ?? [])->first(fn ($rate): bool => is_array($rate)) ?? [];

        return [
            'reference' => $candidate['reference'] ?? null,
            'client_reference' => $candidate['clientReference'] ?? null,
            'client_reference_match' => ($candidate['clientReference'] ?? null) === $this->clientReference($booking),
            'hotel_code' => isset($hotel['code']) ? (string) $hotel['code'] : null,
            'hotel_name' => $hotel['name'] ?? null,
            'check_in' => $hotel['checkIn'] ?? null,
            'check_out' => $hotel['checkOut'] ?? null,
            'room_count' => count($hotel['rooms'] ?? []),
            'board' => $this->normalizeBoard($rate['boardCode'] ?? null),
            'occupancy_count' => count($room['paxes'] ?? []),
            'status' => isset($candidate['status']) ? strtolower((string) $candidate['status']) : null,
            'currency' => $candidate['currency'] ?? null,
            'creation_date' => $candidate['creationDate'] ?? null,
        ];
    }

    private function classify(array $local, array $original, array $detail, array $candidates): string
    {
        $exactCandidate = collect($candidates)->first(fn (array $candidate): bool => $this->exactMatch($local, $candidate));

        if ($exactCandidate && ($exactCandidate['reference'] ?? null) !== ($local['supplier_reference'] ?? null)) {
            return 'reference_mismatch';
        }

        if ($exactCandidate) {
            return 'exact_match';
        }

        if ($this->exactMatch($local, $detail)) {
            return 'exact_match';
        }

        if (($detail['hotel_code'] ?? null) && (string) ($detail['hotel_code'] ?? '') !== (string) ($local['hotel_code'] ?? '')) {
            return 'supplier_reference_reused_or_unexpected';
        }

        if ($original['found'] ?? false) {
            return 'local_mapping_error';
        }

        return $candidates === [] ? 'insufficient_evidence' : 'manual_review';
    }

    private function exactMatch(array $local, array $candidate): bool
    {
        return (string) ($local['hotel_code'] ?? '') !== ''
            && (string) ($local['hotel_code'] ?? '') === (string) ($candidate['hotel_code'] ?? '')
            && ($local['check_in'] ?? null) === ($candidate['check_in'] ?? null)
            && ($local['check_out'] ?? null) === ($candidate['check_out'] ?? null)
            && (int) ($local['room_count'] ?? 0) === (int) ($candidate['room_count'] ?? 0)
            && (int) ($local['occupancy_count'] ?? 0) === (int) ($candidate['occupancy_count'] ?? 0)
            && ($local['currency'] ?? null) === ($candidate['currency'] ?? null)
            && ($candidate['client_reference_match'] ?? false) === true;
    }

    private function comparisonTable(array $local, array $original, array $detail, array $candidate): array
    {
        return [
            'local_record' => $this->safeComparison($local),
            'original_booking_response' => $this->safeComparison($original),
            'hbx_detail_result' => $this->safeComparison($detail),
            'booking_list_candidate' => $this->safeComparison($candidate),
        ];
    }

    private function safeComparison(array $source): array
    {
        return [
            'reference' => $source['supplier_reference'] ?? $source['booking_reference_value'] ?? $source['reference'] ?? null,
            'client_reference_match' => $source['client_reference_match'] ?? null,
            'hotel_code' => $source['hotel_code'] ?? null,
            'dates' => array_filter([$source['check_in'] ?? null, $source['check_out'] ?? null]),
            'status' => $source['local_supplier_status'] ?? $source['status'] ?? $source['supplier_status'] ?? null,
            'currency' => $source['currency'] ?? null,
            'identity_classification' => null,
        ];
    }

    private function localCancellationAudit(string $supplierReference): array
    {
        return SupplierOperationLog::query()
            ->where('operation', SupplierOperation::Cancel)
            ->where('request_method', 'DELETE')
            ->where('request_url', 'like', '%/hotel-api/1.0/bookings/'.$supplierReference.'%')
            ->latest('id')
            ->get()
            ->map(fn (SupplierOperationLog $log): array => [
                'found' => true,
                'operation_timestamp' => $log->created_at?->toIso8601String(),
                'mode' => str_contains((string) $log->request_url, 'cancellationFlag=SIMULATION') ? 'simulation' : 'cancellation',
                'correlation_id' => $log->correlation_id,
                'result_category' => $log->successful ? 'successful' : ($log->error_type?->value ?? 'failed'),
            ])
            ->values()
            ->all();
    }

    private function normalizeBoard(mixed $board): ?string
    {
        return match (strtolower((string) $board)) {
            'bb', 'bed_and_breakfast' => 'BB',
            'ro', 'room_only' => 'RO',
            'hb', 'half_board' => 'HB',
            'fb', 'full_board' => 'FB',
            'ai', 'all_inclusive' => 'AI',
            default => filled($board) ? strtoupper((string) $board) : null,
        };
    }
}
