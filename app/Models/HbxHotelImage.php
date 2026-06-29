<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['hbx_hotel_id', 'image_type_code', 'path', 'room_code', 'sort_order', 'width', 'height', 'alt_text', 'is_primary', 'is_active', 'payload'])]
class HbxHotelImage extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'payload' => 'array',
        ];
    }
}
