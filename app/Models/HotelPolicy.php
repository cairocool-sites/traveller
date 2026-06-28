<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['hotel_id', 'check_in_from', 'check_in_until', 'check_out_from', 'check_out_until', 'children_policy', 'extra_bed_policy', 'pet_policy', 'smoking_policy', 'cancellation_notes', 'important_information'])]
class HotelPolicy extends Model
{
    use HasFactory;

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    protected function casts(): array
    {
        return [
            'check_in_from' => 'datetime:H:i',
            'check_in_until' => 'datetime:H:i',
            'check_out_from' => 'datetime:H:i',
            'check_out_until' => 'datetime:H:i',
        ];
    }
}
