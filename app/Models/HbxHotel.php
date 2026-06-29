<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['supplier_code', 'hotel_code', 'destination_code', 'hotel_name', 'category_code', 'star_rating', 'latitude', 'longitude', 'address', 'is_active', 'synced_at'])]
class HbxHotel extends Model
{
    protected function casts(): array
    {
        return [
            'star_rating' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }
}
