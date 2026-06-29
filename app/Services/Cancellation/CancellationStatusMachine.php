<?php

namespace App\Services\Cancellation;

use App\Enums\CancellationStatus;
use App\Models\BookingCancellation;
use Illuminate\Support\Facades\Auth;

class CancellationStatusMachine
{
    private const TRANSITIONS = [
        'requested' => ['under_review', 'pending_supplier', 'rejected', 'manual_review', 'expired'],
        'under_review' => ['pending_supplier', 'rejected', 'manual_review'],
        'pending_supplier' => ['cancelled', 'failed', 'manual_review', 'rejected'],
        'manual_review' => ['pending_supplier', 'cancelled', 'failed', 'rejected'],
        'cancelled' => [],
        'rejected' => [],
        'failed' => [],
        'expired' => [],
    ];

    public function transition(BookingCancellation $cancellation, CancellationStatus $to, ?string $reason = null, array $metadata = []): BookingCancellation
    {
        $from = $cancellation->status;

        if (! in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true)) {
            throw new CancellationFlowException("Invalid cancellation transition from {$from->value} to {$to->value}.");
        }

        $cancellation->forceFill(['status' => $to])->save();
        $cancellation->statusHistories()->create([
            'from_status' => $from->value,
            'to_status' => $to->value,
            'reason' => $reason,
            'metadata' => $metadata === [] ? null : $metadata,
            'changed_by' => Auth::id(),
        ]);

        return $cancellation->refresh();
    }
}
