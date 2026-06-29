<?php

namespace App\Models;

use App\Enums\ManualPaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['public_uuid', 'booking_id', 'manual_payment_method_id', 'status', 'currency_id', 'amount_minor', 'submitted_reference', 'customer_notes', 'internal_notes', 'submitted_at', 'reviewed_at', 'approved_at', 'rejected_at', 'reviewed_by', 'submitted_by', 'correlation_id'])]
class Payment extends Model
{
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(ManualPaymentMethod::class, 'manual_payment_method_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(PaymentEvidence::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(PaymentStatusHistory::class);
    }

    protected function casts(): array
    {
        return [
            'status' => ManualPaymentStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }
}
