<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['public_uuid', 'booking_reference', 'user_id', 'search_session_id', 'rate_check_id', 'supplier_id', 'hotel_id', 'currency_id', 'status', 'payment_status', 'locale', 'check_in', 'check_out', 'rooms_count', 'adults_count', 'children_count', 'supplier_booking_reference', 'supplier_confirmation_reference', 'supplier_status', 'total_amount_minor', 'net_amount_minor', 'taxes_amount_minor', 'fees_amount_minor', 'cancellation_policy_snapshot', 'hotel_snapshot', 'room_snapshot', 'occupancy_snapshot', 'supplier_response_snapshot', 'correlation_id', 'idempotency_key', 'idempotency_payload_hash', 'contact_email', 'contact_phone', 'special_requests', 'confirmed_at', 'failed_at', 'expires_at'])]
class Booking extends Model
{
    public function searchSession(): BelongsTo
    {
        return $this->belongsTo(SearchSession::class);
    }

    public function rateCheck(): BelongsTo
    {
        return $this->belongsTo(RateCheck::class);
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

    public function rooms(): HasMany
    {
        return $this->hasMany(BookingRoom::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(BookingGuest::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class);
    }

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
            'check_in' => 'date',
            'check_out' => 'date',
            'cancellation_policy_snapshot' => 'array',
            'hotel_snapshot' => 'array',
            'room_snapshot' => 'array',
            'occupancy_snapshot' => 'array',
            'supplier_response_snapshot' => 'array',
            'confirmed_at' => 'datetime',
            'failed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
