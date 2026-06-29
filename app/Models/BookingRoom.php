<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['booking_id', 'room_index', 'room_name', 'board_basis', 'adults', 'children', 'child_ages', 'amount_minor', 'cancellation_policy_snapshot', 'supplier_room_reference'])]
class BookingRoom extends Model
{
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(BookingGuest::class);
    }

    protected function casts(): array
    {
        return [
            'child_ages' => 'array',
            'cancellation_policy_snapshot' => 'array',
            'supplier_room_reference' => 'encrypted',
        ];
    }
}
