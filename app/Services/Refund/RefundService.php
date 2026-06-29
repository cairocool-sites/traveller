<?php

namespace App\Services\Refund;

use App\Enums\ManualPaymentStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\BookingCancellation;
use App\Models\Payment;
use App\Models\Refund;
use App\Notifications\RefundStatusNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

class RefundService
{
    public function __construct(private readonly RefundStatusMachine $statuses) {}

    public function create(BookingCancellation $cancellation, Payment $payment, int $amountMinor, array $data = []): Refund
    {
        $this->guardAmount($cancellation, $payment, $amountMinor);

        return Refund::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'booking_id' => $cancellation->booking_id,
            'payment_id' => $payment->id,
            'booking_cancellation_id' => $cancellation->id,
            'status' => RefundStatus::Pending,
            'currency_id' => $cancellation->currency_id,
            'requested_amount_minor' => $amountMinor,
            'method' => $data['method'] ?? 'manual',
            'customer_notes' => $data['customer_notes'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
            'requested_at' => now(),
            'created_by' => Auth::id(),
        ]);
    }

    public function approve(Refund $refund, string $reason = '', bool $superAdminOverride = false): Refund
    {
        $this->guardMaker($refund, 'approve', $reason, $superAdminOverride);
        $refund = $refund->status === RefundStatus::Pending ? $this->statuses->transition($refund, RefundStatus::UnderReview, 'Refund under review.') : $refund;
        $refund = $this->statuses->transition($refund, RefundStatus::Approved, $reason ?: 'Refund approved.');
        $refund->forceFill(['approved_amount_minor' => $refund->requested_amount_minor, 'approved_at' => now(), 'approved_by' => Auth::id()])->save();

        return $refund->refresh();
    }

    public function complete(Refund $refund, string $reference, string $reason = '', bool $superAdminOverride = false): Refund
    {
        $this->guardMaker($refund, 'complete', $reason, $superAdminOverride);

        return DB::transaction(function () use ($refund, $reference): Refund {
            $refund = $refund->status === RefundStatus::Approved ? $this->statuses->transition($refund, RefundStatus::Processing, 'Manual refund processing.') : $refund;
            $refund = $this->statuses->transition($refund, RefundStatus::Completed, 'Manual refund completed.');
            $refund->forceFill(['refunded_amount_minor' => $refund->approved_amount_minor ?? $refund->requested_amount_minor, 'external_reference' => $reference, 'completed_at' => now(), 'completed_by' => Auth::id()])->save();

            $payment = $refund->payment;
            $refunded = (int) $payment->refunds()->where('status', RefundStatus::Completed)->sum('refunded_amount_minor');
            $payment->forceFill(['status' => $refunded >= $payment->amount_minor ? ManualPaymentStatus::Refunded : ManualPaymentStatus::PartiallyRefunded])->save();
            $payment->booking->forceFill(['payment_status' => $refunded >= $payment->amount_minor ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded])->save();
            $this->notifySafely($refund, 'Refund completed');

            return $refund->refresh();
        });
    }

    public function reject(Refund $refund, string $reason): Refund
    {
        if (blank($reason)) {
            throw new RefundFlowException('Refund rejection reason is required.');
        }

        $refund = $refund->status === RefundStatus::Pending ? $this->statuses->transition($refund, RefundStatus::UnderReview, 'Refund under review.') : $refund;
        $refund = $this->statuses->transition($refund, RefundStatus::Rejected, $reason);
        $refund->forceFill(['rejected_at' => now(), 'internal_notes' => $reason])->save();

        return $refund->refresh();
    }

    private function guardAmount(BookingCancellation $cancellation, Payment $payment, int $amountMinor): void
    {
        $completed = (int) $payment->refunds()->where('status', RefundStatus::Completed)->sum('refunded_amount_minor');

        if ($amountMinor < 1 || $amountMinor > $cancellation->refundable_amount_minor || $completed + $amountMinor > $payment->amount_minor) {
            throw new RefundFlowException('Refund amount exceeds eligible refundable or paid amount.');
        }
    }

    private function guardMaker(Refund $refund, string $action, string $reason, bool $superAdminOverride): void
    {
        $user = Auth::user();

        if (! $user) {
            throw new RefundFlowException('Refund operator is required.');
        }

        if ($refund->created_by && $refund->created_by === $user->id && (! $user->hasRole('super_admin') || ! $superAdminOverride || blank($reason))) {
            throw new RefundFlowException("Maker-checker prevents {$action} on your own refund.");
        }
    }

    private function notifySafely(Refund $refund, string $subject): void
    {
        try {
            if (filled($refund->booking->contact_email)) {
                Notification::route('mail', $refund->booking->contact_email)->notify(new RefundStatusNotification($refund, $subject));
            }
        } catch (Throwable) {
            report(new RefundFlowException('Refund notification failed but state was preserved.'));
        }
    }
}
