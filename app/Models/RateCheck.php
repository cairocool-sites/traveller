<?php

namespace App\Models;

use App\Enums\RateCheckStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['public_uuid', 'search_session_id', 'supplier_id', 'hotel_id', 'currency_id', 'status', 'supplier_hotel_reference', 'supplier_rate_reference', 'supplier_room_reference', 'original_amount_minor', 'checked_amount_minor', 'price_changed', 'cancellation_policy_snapshot', 'room_snapshot', 'occupancy_snapshot', 'supplier_reference_snapshot', 'correlation_id', 'checked_at', 'expires_at'])]
class RateCheck extends Model
{
    public function searchSession(): BelongsTo
    {
        return $this->belongsTo(SearchSession::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    protected function casts(): array
    {
        return [
            'status' => RateCheckStatus::class,
            'supplier_hotel_reference' => 'encrypted',
            'supplier_rate_reference' => 'encrypted',
            'supplier_room_reference' => 'encrypted',
            'price_changed' => 'boolean',
            'cancellation_policy_snapshot' => 'array',
            'room_snapshot' => 'array',
            'occupancy_snapshot' => 'array',
            'supplier_reference_snapshot' => 'array',
            'checked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
