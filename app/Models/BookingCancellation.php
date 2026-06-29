<?php

namespace App\Models;

use App\Enums\CancellationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['public_uuid', 'booking_id', 'status', 'requested_by_user_id', 'customer_reason', 'internal_reason', 'requested_at', 'reviewed_at', 'reviewed_by', 'supplier_cancellation_reference', 'supplier_status', 'penalty_amount_minor', 'refundable_amount_minor', 'currency_id', 'cancellation_policy_snapshot', 'supplier_response_snapshot', 'correlation_id', 'idempotency_key', 'idempotency_payload_hash', 'completed_at'])]
class BookingCancellation extends Model
{
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(CancellationStatusHistory::class);
    }

    protected function casts(): array
    {
        return [
            'status' => CancellationStatus::class,
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancellation_policy_snapshot' => 'array',
            'supplier_response_snapshot' => 'array',
        ];
    }
}
