<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;

class BookingStateMachine
{
    public function transition(Booking $booking, BookingStatus $to, ?string $reason = null, array $metadata = []): Booking
    {
        $from = $booking->status;
        $booking->forceFill(['status' => $to])->save();

        $booking->statusHistories()->create([
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'reason' => $reason,
            'metadata' => $metadata,
            'changed_by' => Auth::id(),
        ]);

        return $booking->refresh();
    }
}
