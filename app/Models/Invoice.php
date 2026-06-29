<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['invoice_number', 'booking_id', 'customer_name', 'customer_email', 'billing_address', 'currency_id', 'subtotal_minor', 'tax_minor', 'fees_minor', 'discount_minor', 'total_minor', 'issued_at', 'status', 'snapshot', 'verification_token', 'revoked_at'])]
class Invoice extends Model
{
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    protected function casts(): array
    {
        return ['status' => DocumentStatus::class, 'snapshot' => 'array', 'issued_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
