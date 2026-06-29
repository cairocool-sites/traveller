<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['voucher_number', 'booking_id', 'status', 'snapshot', 'verification_token', 'issued_at', 'revoked_at'])]
class Voucher extends Model
{
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    protected function casts(): array
    {
        return ['status' => DocumentStatus::class, 'snapshot' => 'array', 'issued_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
