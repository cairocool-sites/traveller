<?php

namespace App\Services\Cancellation;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Carbon\CarbonImmutable;

class CancellationEligibilityService
{
    public function evaluate(Booking $booking): CancellationEligibilityResult
    {
        if ($booking->status !== BookingStatus::Confirmed) {
            return new CancellationEligibilityResult(false, false, false, 0, 0, 'Only confirmed bookings can be cancelled.');
        }

        if ($booking->cancellations()->whereIn('status', ['requested', 'under_review', 'pending_supplier', 'manual_review', 'cancelled'])->exists()) {
            return new CancellationEligibilityResult(false, false, false, 0, 0, 'An active cancellation request already exists.');
        }

        if ($booking->check_in->isPast() || $booking->check_in->isToday()) {
            return new CancellationEligibilityResult(true, true, false, 0, 0, 'Check-in date requires manual review.');
        }

        $policies = $booking->cancellation_policy_snapshot ?? [];

        if ($policies === []) {
            return new CancellationEligibilityResult(true, true, false, 0, 0, 'Cancellation policy is unknown.');
        }

        $now = now()->toImmutable();
        $active = collect($policies)->first(function (array $policy) use ($now): bool {
            $from = isset($policy['valid_from']) ? CarbonImmutable::parse($policy['valid_from']) : null;
            $until = isset($policy['valid_until']) ? CarbonImmutable::parse($policy['valid_until']) : null;

            return (! $from || $now->greaterThanOrEqualTo($from)) && (! $until || $now->lessThanOrEqualTo($until));
        }) ?? $policies[0];

        if (($active['is_non_refundable'] ?? false) === true) {
            return new CancellationEligibilityResult(true, false, true, $booking->total_amount_minor, 0, 'This booking is non-refundable.');
        }

        $penalty = (int) ($active['penalty_amount']['minor_amount'] ?? 0);
        $refundable = max(0, $booking->total_amount_minor - $penalty);

        return new CancellationEligibilityResult(true, false, false, $penalty, $refundable, $penalty === 0 ? 'Free cancellation is proven by stored policy.' : 'Cancellation penalty applies.');
    }
}
