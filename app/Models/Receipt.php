<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['receipt_number', 'payment_id', 'amount_minor', 'currency_id', 'payment_method_snapshot', 'approved_at', 'issued_at', 'status', 'snapshot', 'verification_token', 'revoked_at'])]
class Receipt extends Model
{
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    protected function casts(): array
    {
        return ['status' => DocumentStatus::class, 'payment_method_snapshot' => 'array', 'snapshot' => 'array', 'approved_at' => 'datetime', 'issued_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
