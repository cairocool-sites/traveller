<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['hbx_hotel_id', 'facility_code', 'facility_group_code', 'description', 'is_active', 'payload'])]
class HbxHotelFacility extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'payload' => 'array',
        ];
    }
}
