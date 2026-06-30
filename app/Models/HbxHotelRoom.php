<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['hbx_hotel_id', 'room_code', 'room_name', 'characteristic_code', 'min_adults', 'max_adults', 'max_children', 'max_pax', 'is_active', 'payload'])]
class HbxHotelRoom extends Model
{
    protected function casts(): array
    {
        return [
            'min_adults' => 'integer',
            'max_adults' => 'integer',
            'max_children' => 'integer',
            'max_pax' => 'integer',
            'is_active' => 'boolean',
            'payload' => 'array',
        ];
    }
}
