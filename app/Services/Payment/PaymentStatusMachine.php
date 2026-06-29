<?php

namespace App\Services\Payment;

use App\Enums\ManualPaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;

class PaymentStatusMachine
{
    private const TRANSITIONS = [
        'pending' => ['submitted', 'cancelled'],
        'submitted' => ['under_review', 'rejected'],
        'under_review' => ['approved', 'rejected'],
        'approved' => ['paid'],
        'rejected' => [],
        'paid' => [],
        'cancelled' => [],
    ];

    public function transition(Payment $payment, ManualPaymentStatus $to, ?string $reason = null, array $metadata = []): Payment
    {
        $from = $payment->status;

        if (! in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true)) {
            throw new PaymentFlowException("Invalid payment transition from {$from->value} to {$to->value}.");
        }

        $payment->forceFill(['status' => $to])->save();
        $payment->statusHistories()->create([
            'from_status' => $from->value,
            'to_status' => $to->value,
            'reason' => $reason,
            'metadata' => $metadata === [] ? null : $metadata,
            'changed_by' => Auth::id(),
        ]);

        return $payment->refresh();
    }
}
