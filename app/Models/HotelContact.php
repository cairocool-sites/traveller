<?php

namespace App\Models;

use App\Enums\HotelContactType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['hotel_id', 'contact_type', 'department', 'contact_name', 'phone', 'mobile', 'email', 'notes', 'is_primary', 'is_active'])]
class HotelContact extends Model
{
    use HasFactory;

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    protected function casts(): array
    {
        return [
            'contact_type' => HotelContactType::class,
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
