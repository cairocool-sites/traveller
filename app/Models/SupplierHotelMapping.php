<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['hotel_id', 'supplier_code', 'supplier_hotel_code', 'status', 'confidence', 'manually_confirmed', 'is_active'])]
class SupplierHotelMapping extends Model
{
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'manually_confirmed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
