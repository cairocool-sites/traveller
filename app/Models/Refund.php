<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['public_uuid', 'booking_id', 'payment_id', 'booking_cancellation_id', 'status', 'currency_id', 'requested_amount_minor', 'approved_amount_minor', 'refunded_amount_minor', 'method', 'external_reference', 'customer_notes', 'internal_notes', 'requested_at', 'approved_at', 'completed_at', 'rejected_at', 'created_by', 'approved_by', 'completed_by'])]
class Refund extends Model
{
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function cancellation(): BelongsTo
    {
        return $this->belongsTo(BookingCancellation::class, 'booking_cancellation_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(RefundStatusHistory::class);
    }

    protected function casts(): array
    {
        return ['status' => RefundStatus::class, 'requested_at' => 'datetime', 'approved_at' => 'datetime', 'completed_at' => 'datetime', 'rejected_at' => 'datetime'];
    }
}
