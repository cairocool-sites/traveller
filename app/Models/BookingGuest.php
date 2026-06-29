<?php

namespace App\Models;

use App\Enums\GuestType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'booking_room_id', 'type', 'title', 'first_name', 'last_name', 'date_of_birth', 'age', 'nationality_country_id', 'is_lead_guest', 'sort_order'])]
class BookingGuest extends Model
{
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(BookingRoom::class, 'booking_room_id');
    }

    protected function casts(): array
    {
        return [
            'type' => GuestType::class,
            'date_of_birth' => 'date',
            'is_lead_guest' => 'boolean',
        ];
    }
}
