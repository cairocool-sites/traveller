<?php

namespace App\Services\Booking;

use App\Models\Booking;
use Illuminate\Support\Str;

class BookingReferenceGenerator
{
    public function make(): string
    {
        do {
            $reference = 'CCT-'.now()->format('Y').'-'.Str::upper(Str::random(8));
        } while (Booking::query()->where('booking_reference', $reference)->exists());

        return $reference;
    }
}
