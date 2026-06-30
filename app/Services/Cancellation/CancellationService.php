<?php

namespace App\Services\Cancellation;

use App\Enums\BookingStatus;
use App\Enums\CancellationStatus;
use App\Enums\CancellationSupplierStatus;
use App\Enums\SupplierOperation;
use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Notifications\CancellationStatusNotification;
use App\Services\Supplier\Data\SupplierCancellationRequestData;
use App\Services\Supplier\Exceptions\SupplierException;
use App\Services\Supplier\SupplierManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class CancellationService
{
    public function __construct(
        private readonly CancellationEligibilityService $eligibility,
        private readonly CancellationStatusMachine $statuses,
        private readonly SupplierManager $suppliers,
    ) {}

    public function request(Booking $booking, array $payload): BookingCancellation
    {
        $idempotencyKey = (string) ($payload['idempotency_key'] ?? '');
        $hash = hash('sha256', json_encode(Arr::except($payload, ['idempotency_key']), JSON_THROW_ON_ERROR));

        if ($existing = BookingCancellation::query()->where('idempotency_key', $idempotencyKey)->first()) {
            if (! hash_equals($existing->idempotency_payload_hash, $hash)) {
                throw new InvalidArgumentException('Cancellation idempotency key was already used with different details.');
            }

            return $existing;
        }

        return Cache::lock('cancel:'.$idempotencyKey, 10)->block(5, fn (): BookingCancellation => DB::transaction(function () use ($booking, $payload, $idempotencyKey, $hash): BookingCancellation {
            $result = $this->eligibility->evaluate($booking);

            if (! $result->eligible) {
                throw new CancellationFlowException($result->reason);
            }

            if ($result->nonRefundable && ! ($payload['acknowledge_non_refundable'] ?? false)) {
                throw new CancellationFlowException('Non-refundable cancellation requires acknowledgement.');
            }

            $cancellation = BookingCancellation::query()->create([
                'public_uuid' => (string) Str::uuid(),
                'booking_id' => $booking->id,
                'status' => CancellationStatus::Requested,
                'requested_by_user_id' => auth()->id(),
                'customer_reason' => $payload['customer_reason'] ?? null,
                'requested_at' => now(),
                'penalty_amount_minor' => $result->penaltyMinor,
                'refundable_amount_minor' => $result->refundableMinor,
                'currency_id' => $booking->currency_id,
                'cancellation_policy_snapshot' => $booking->cancellation_policy_snapshot,
                'correlation_id' => (string) Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'idempotency_payload_hash' => $hash,
            ]);

            if ($result->manualReview || $result->nonRefundable) {
                $this->statuses->transition($cancellation, CancellationStatus::ManualReview, $result->reason);
                $this->notifySafely($cancellation, 'Cancellation under review');

                return $cancellation->refresh();
            }

            return $this->submitSupplier($cancellation, $payload);
        }));
    }

    public function submitSupplier(BookingCancellation $cancellation, array $payload = []): BookingCancellation
    {
        $cancellation = $this->statuses->transition($cancellation, CancellationStatus::PendingSupplier, 'Submitting cancellation to supplier.');

        try {
            $response = $this->suppliers->resolve($cancellation->booking->supplier->code, SupplierOperation::Cancel)
                ->cancel(new SupplierCancellationRequestData(
                    supplierBookingReference: $payload['supplier_booking_reference'] ?? $cancellation->booking->supplier_booking_reference,
                    idempotencyKey: $cancellation->idempotency_key,
                    cancellationReason: $cancellation->customer_reason,
                    correlationId: $cancellation->correlation_id,
                    metadata: ['scenario' => $payload['scenario'] ?? null, 'cancellation_flag' => 'CANCELLATION'],
                ));
        } catch (SupplierException) {
            return $this->statuses->transition($cancellation, CancellationStatus::ManualReview, 'Supplier cancellation outcome is uncertain.');
        }

        $cancellation->forceFill([
            'supplier_cancellation_reference' => $response->cancellationReference,
            'supplier_status' => $response->status->value,
            'penalty_amount_minor' => $response->penaltyAmount?->minorAmount ?? $cancellation->penalty_amount_minor,
            'refundable_amount_minor' => $response->refundableAmount?->minorAmount ?? $cancellation->refundable_amount_minor,
            'supplier_response_snapshot' => $response->jsonSerialize(),
            'completed_at' => $response->successful ? now() : null,
        ])->save();

        if ($response->successful && $response->status === CancellationSupplierStatus::Cancelled) {
            $this->statuses->transition($cancellation, CancellationStatus::Cancelled, 'Supplier cancellation confirmed.');
            $cancellation->booking->forceFill(['status' => BookingStatus::Cancelled])->save();
            $cancellation->booking->vouchers()->update(['revoked_at' => now(), 'status' => 'revoked']);
            $this->notifySafely($cancellation, 'Cancellation confirmed');

            return $cancellation->refresh();
        }

        $this->statuses->transition($cancellation, CancellationStatus::Rejected, $response->failureMessage ?? 'Supplier rejected cancellation.');
        $this->notifySafely($cancellation, 'Cancellation rejected');

        return $cancellation->refresh();
    }

    private function notifySafely(BookingCancellation $cancellation, string $subject): void
    {
        try {
            if (filled($cancellation->booking->contact_email)) {
                Notification::route('mail', $cancellation->booking->contact_email)->notify(new CancellationStatusNotification($cancellation, $subject));
            }
        } catch (Throwable) {
            report(new CancellationFlowException('Cancellation notification failed but state was preserved.'));
        }
    }
}
