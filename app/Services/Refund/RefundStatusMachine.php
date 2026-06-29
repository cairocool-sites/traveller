<?php

namespace App\Services\Refund;

use App\Enums\RefundStatus;
use App\Models\Refund;
use Illuminate\Support\Facades\Auth;

class RefundStatusMachine
{
    private const TRANSITIONS = [
        'pending' => ['under_review', 'rejected'],
        'under_review' => ['approved', 'rejected'],
        'approved' => ['processing', 'completed'],
        'processing' => ['completed', 'failed'],
        'rejected' => [],
        'completed' => [],
        'failed' => [],
    ];

    public function transition(Refund $refund, RefundStatus $to, ?string $reason = null): Refund
    {
        $from = $refund->status;

        if (! in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true)) {
            throw new RefundFlowException("Invalid refund transition from {$from->value} to {$to->value}.");
        }

        $refund->forceFill(['status' => $to])->save();
        $refund->statusHistories()->create(['from_status' => $from->value, 'to_status' => $to->value, 'reason' => $reason, 'changed_by' => Auth::id()]);

        return $refund->refresh();
    }
}
